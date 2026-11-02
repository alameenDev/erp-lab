<?php
declare(strict_types=1);

// This helper receives only private local stdin. It never prints row data, SQL,
// connection strings, exception messages, encryption keys or authentication data.
function reject(string $code): never
{
    echo json_encode(['ok' => false, 'code' => $code], JSON_THROW_ON_ERROR);
    exit(2);
}
function safe_identifier(mixed $value): bool
{
    return is_string($value) && preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/D', $value) === 1;
}
function decode_key(mixed $value): string
{
    if (!is_string($value) || !str_starts_with($value, 'base64:')) {
        throw new RuntimeException('INVALID_SOURCE_KEY');
    }
    $key = base64_decode(substr($value, 7), true);
    if ($key === false || strlen($key) !== 32) throw new RuntimeException('INVALID_SOURCE_KEY');
    return $key;
}

try {
    $raw = stream_get_contents(STDIN, 8 * 1024 * 1024 + 1);
    if (strlen($raw) > 8 * 1024 * 1024) reject('INVALID_PAYLOAD');
    $payload = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    unset($raw);
    if (!is_array($payload) || !in_array($payload['action'] ?? '', ['schema', 'validate', 'rekey', 'verify_crypto'], true)) reject('INVALID_PAYLOAD');
    $bundle = getenv('DL_MIGRATION_BUNDLE');
    if (!$bundle || getenv('DESKTOP_MODE') !== 'true') reject('INVALID_RUNTIME');
    $app = require $bundle . '/desktop-php/bootstrap.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $db = \Illuminate\Support\Facades\DB::connection();
    if ($db->getDatabaseName() !== 'digital_lab_desktop'
        || $db->getConfig('host') !== '127.0.0.1') reject('INVALID_RUNTIME');
    $schema = [];
    foreach ($db->select('SELECT TABLE_NAME AS table_name FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = ? ORDER BY TABLE_NAME', ['BASE TABLE']) as $row) {
        $name = $row->table_name;
        if (!safe_identifier($name)) reject('INVALID_SCHEMA');
        $schema[$name] = [];
    }
    foreach ($db->select('SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION') as $row) {
        if (isset($schema[$row->table_name])) $schema[$row->table_name][] = $row->column_name;
    }
    if ($payload['action'] === 'schema') {
        echo json_encode(['ok' => true, 'schema' => $schema], JSON_THROW_ON_ERROR);
        exit(0);
    }

    $tables = $payload['tables'] ?? null;
    $encrypted = $payload['encryptedColumns'] ?? [];
    if (!is_array($tables) || !$tables || !is_array($encrypted)) reject('INVALID_PAYLOAD');
    $actualNames = array_keys($schema);
    $expectedNames = array_keys($tables);
    sort($actualNames); sort($expectedNames);
    if ($actualNames !== $expectedNames) reject('TABLE_SET_MISMATCH');
    $total = 0;
    foreach ($tables as $name => $definition) {
        if (!safe_identifier($name) || !is_array($definition)
            || !isset($definition['rows']) || !is_int($definition['rows'])) reject('INVALID_PAYLOAD');
        if (isset($definition['columns']) && $definition['columns'] !== $schema[$name]) reject('COLUMN_SET_MISMATCH');
        $count = (int) $db->table($name)->count();
        if ($count !== $definition['rows']) reject('ROW_COUNT_MISMATCH');
        $total += $count;
    }
    $foreignKeys = [];
    foreach ($db->select('SELECT TABLE_NAME AS table_name, CONSTRAINT_NAME AS constraint_name, COLUMN_NAME AS column_name, REFERENCED_TABLE_SCHEMA AS referenced_schema, REFERENCED_TABLE_NAME AS referenced_table, REFERENCED_COLUMN_NAME AS referenced_column FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION') as $key) {
        if ($key->referenced_schema !== $db->getDatabaseName()
            || !safe_identifier($key->table_name) || !safe_identifier($key->column_name)
            || !safe_identifier($key->referenced_table) || !safe_identifier($key->referenced_column)) reject('INVALID_FOREIGN_KEY');
        $foreignKeys[$key->table_name . '.' . $key->constraint_name][] = $key;
    }
    foreach ($foreignKeys as $parts) {
        $first = $parts[0]; $joins = []; $required = [];
        foreach ($parts as $part) {
            $joins[] = 'c.`' . $part->column_name . '` = p.`' . $part->referenced_column . '`';
            $required[] = 'c.`' . $part->column_name . '` IS NOT NULL';
        }
        $query = 'SELECT 1 FROM `' . $first->table_name . '` c LEFT JOIN `' . $first->referenced_table . '` p ON '
            . implode(' AND ', $joins) . ' WHERE ' . implode(' AND ', $required)
            . ' AND p.`' . $first->referenced_column . '` IS NULL LIMIT 1';
        if ($db->select($query)) reject('ORPHAN_REFERENCE');
    }
    if (!isset($schema['users']) || !in_array('deleted_at', $schema['users'], true)) reject('INVALID_USERS_SCHEMA');
    $adminCount = (int) $db->table('users')->where('role_id', 1)->whereNull('deleted_at')->count();
    $usableAdmins = (int) $db->table('users')->where('role_id', 1)->whereNull('deleted_at')
        ->where('status', 1)->whereNotNull('email_verified_at')->count();
    if ($adminCount < 1) reject('ADMIN_MISSING');
    if ($usableAdmins < 1) reject('NO_USABLE_ADMIN');

    $declared = [];
    $encryptedValues = 0;
    foreach ($encrypted as $item) {
        $table = $item['table'] ?? null;
        $column = $item['column'] ?? null;
        if (!safe_identifier($table) || !safe_identifier($column)
            || !isset($schema[$table]) || !in_array($column, $schema[$table], true)
            || !isset($item['nonNullCount']) || !is_int($item['nonNullCount'])) reject('INVALID_ENCRYPTED_SCHEMA');
        $name = $table . '.' . $column;
        if (isset($declared[$name])) reject('INVALID_ENCRYPTED_SCHEMA');
        $count = (int) $db->table($table)->whereNotNull($column)->count();
        if ($count !== $item['nonNullCount']) reject('ENCRYPTED_COUNT_MISMATCH');
        $declared[$name] = true;
        $encryptedValues += $count;
        if ($count) {
            $keys = $db->select('SELECT COLUMN_NAME AS column_name FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? ORDER BY ORDINAL_POSITION', [$table, 'PRIMARY']);
            if (count($keys) !== 1 || $keys[0]->column_name !== 'id') reject('UNSUPPORTED_ENCRYPTED_PRIMARY_KEY');
        }
    }
    // Inherited casts and newer portal tables must not silently escape re-keying.
    foreach ([['lab_settings', 'ai_config'], ['referral_print_settings', 'ai_config'],
        ['portal_push_subscriptions', 'subscription'], ['owner_push_subscriptions', 'subscription']] as [$table, $column]) {
        if (isset($schema[$table]) && in_array($column, $schema[$table], true)
            && !isset($declared[$table . '.' . $column])
            && $db->table($table)->whereNotNull($column)->exists()) reject('ENCRYPTED_COLUMN_UNDECLARED');
    }

    $rekeyed = 0;
    if ($payload['action'] === 'rekey' && $encryptedValues > 0) {
        if (empty($payload['sourceAppKey'])) reject('SOURCE_KEY_REQUIRED');
        $sourceKey = decode_key($payload['sourceAppKey']);
        $localKey = decode_key(getenv('APP_KEY'));
        unset($payload['sourceAppKey']);
        $source = new \Illuminate\Encryption\Encrypter($sourceKey, 'AES-256-CBC');
        $target = new \Illuminate\Encryption\Encrypter($localKey, 'AES-256-CBC');
        try {
            $db->transaction(function () use ($db, $encrypted, $source, $target, &$rekeyed) {
                foreach ($encrypted as $item) {
                    if (!$item['nonNullCount']) continue;
                    $table = $item['table']; $column = $item['column'];
                    $db->table($table)->select(['id', $column])->whereNotNull($column)
                        ->chunkById(100, function ($rows) use ($db, $table, $column, $source, $target, &$rekeyed) {
                            foreach ($rows as $row) {
                                // Laravel encrypted casts use un-serialized string encryption.
                                $plain = $source->decrypt($row->{$column}, false);
                                if (!is_string($plain)) throw new RuntimeException('ENCRYPTED_DATA_INVALID');
                                json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
                                $encoded = $target->encrypt($plain, false);
                                if (!hash_equals($plain, $target->decrypt($encoded, false))) throw new RuntimeException('ENCRYPTED_DATA_INVALID');
                                $db->table($table)->where('id', $row->id)->update([$column => $encoded]);
                                $rekeyed++;
                            }
                        }, 'id');
                }
            });
        } catch (\Throwable $error) {
            reject('ENCRYPTED_DATA_INVALID');
        } finally {
            if (function_exists('sodium_memzero')) {
                sodium_memzero($sourceKey); sodium_memzero($localKey);
            }
        }
        if ($rekeyed !== $encryptedValues) reject('ENCRYPTED_COUNT_MISMATCH');
    }
    if ($payload['action'] === 'verify_crypto' && $encryptedValues > 0) {
        $localKey = decode_key(getenv('APP_KEY'));
        $target = new \Illuminate\Encryption\Encrypter($localKey, 'AES-256-CBC');
        try {
            foreach ($encrypted as $item) {
                if (!$item['nonNullCount']) continue;
                $column = $item['column'];
                $db->table($item['table'])->select(['id', $column])->whereNotNull($column)
                    ->chunkById(100, function ($rows) use ($column, $target) {
                        foreach ($rows as $row) {
                            $plain = $target->decrypt($row->{$column}, false);
                            if (!is_string($plain)) throw new RuntimeException('ENCRYPTED_DATA_INVALID');
                            json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
                        }
                    }, 'id');
            }
        } catch (\Throwable $error) {
            reject('ENCRYPTED_DATA_INVALID');
        } finally {
            if (function_exists('sodium_memzero')) sodium_memzero($localKey);
        }
    }
    echo json_encode(['ok' => true, 'tableCount' => count($tables), 'rowCount' => $total,
        'adminCount' => $adminCount, 'usableAdminCount' => $usableAdmins,
        'foreignKeyCount' => count($foreignKeys), 'encryptedValues' => $encryptedValues,
        'rekeyed' => $rekeyed, 'schema' => $schema], JSON_THROW_ON_ERROR);
} catch (\Throwable $error) {
    reject('VALIDATION_FAILED');
}
