<?php
declare(strict_types=1);

// Only media fields are eligible. Audit snapshots and clinical result text are
// deliberately outside this list and are never rewritten.
const MEDIA_COLUMNS = [
    'users' => ['image' => 'string', 'signiture' => 'string'],
    'patients' => ['image' => 'string'],
    'doctors' => ['photo' => 'string'],
    'client_map_locations' => ['logo_path' => 'string'],
    'lab_banners' => ['image_path' => 'string', 'mobile_image_path' => 'string'],
    'lab_settings' => ['logo' => 'string', 'report_background' => 'string', 'document_config' => 'json', 'patient_header_config' => 'json'],
    'referral_lab_profiles' => ['logo' => 'string'],
    'referral_print_settings' => ['logo' => 'string', 'report_background' => 'string', 'document_config' => 'json', 'patient_header_config' => 'json'],
    'invoices' => ['result_doc' => 'string', 'pdf_qr_code' => 'string', 'attachments' => 'json'],
];

function localMediaUrl(string $value, array $origins, string $localOrigin): string
{
    $parts = parse_url($value);
    if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) return $value;
    $scheme = strtolower($parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) return $value;
    $origin = $scheme.'://'.strtolower($parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '');
    $path = $parts['path'] ?? '';
    if (!in_array($origin, $origins, true) || !preg_match('~^/(?:storage/|api/report-attachments/|report-attachments/)~D', $path)) return $value;
    return $localOrigin.$path.(isset($parts['query']) ? '?'.$parts['query'] : '').(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
}

function localMediaValues(mixed $value, array $origins, string $localOrigin, int $depth = 0): mixed
{
    if ($depth > 128) throw new RuntimeException('MEDIA_JSON_TOO_DEEP');
    if (is_string($value)) return localMediaUrl($value, $origins, $localOrigin);
    if (is_array($value)) {
        foreach ($value as $key => $item) $value[$key] = localMediaValues($item, $origins, $localOrigin, $depth + 1);
    } elseif ($value instanceof stdClass) {
        foreach (get_object_vars($value) as $key => $item) $value->$key = localMediaValues($item, $origins, $localOrigin, $depth + 1);
    }
    return $value;
}

// Test the exact pure transformation without booting Laravel or reading a DB.
if (getenv('DL_MEDIA_TRANSFORM_TEST') === '1') {
    try {
        $input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
        $value = json_decode($input['json'], false, 512, JSON_THROW_ON_ERROR);
        echo json_encode(['value' => localMediaValues($value, $input['origins'], 'http://127.0.0.1:17892')], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $error) {
        echo json_encode(['ok' => false, 'code' => 'MEDIA_TRANSFORM_FAILED']);
        exit(1);
    }
    exit(0);
}

try {
    $bundle = getenv('DL_MIGRATION_BUNDLE');
    if (!$bundle || getenv('DESKTOP_MODE') !== 'true') throw new RuntimeException('NOT_DESKTOP');
    $app = require $bundle.'/desktop-php/bootstrap.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    if (Illuminate\Support\Facades\DB::connection()->getDatabaseName() !== 'digital_lab_desktop') throw new RuntimeException('NOT_STAGING_DATABASE');
    $input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    $config = $input['urlRewrite'] ?? [];
    $origins = [];
    foreach ($config['sourceOrigins'] ?? [] as $origin) {
        if (!is_string($origin) || !preg_match('~^https?://[a-z0-9.-]+(?::[0-9]{1,5})?$~D', $origin)) throw new RuntimeException('BAD_ORIGIN');
        $origins[] = strtolower($origin);
    }
    $rules = $config['columns'] ?? [];
    foreach ($rules as $rule) {
        if (!is_array($rule) || (MEDIA_COLUMNS[$rule['table'] ?? ''][$rule['column'] ?? ''] ?? null) !== ($rule['kind'] ?? null)) throw new RuntimeException('BAD_MEDIA_RULE');
    }
    $changed = 0; $changedFields = 0;
    Illuminate\Support\Facades\DB::transaction(function () use ($rules, $origins, &$changed, &$changedFields) {
        foreach ($rules as $rule) {
            $table = $rule['table']; $column = $rule['column']; $kind = $rule['kind'];
            if (!Illuminate\Support\Facades\Schema::hasColumn($table, $column)) throw new RuntimeException('MISSING_MEDIA_COLUMN');
            $fieldChanges = 0;
            Illuminate\Support\Facades\DB::table($table)->select(['id', $column])->whereNotNull($column)->where($column, '<>', '')
                ->orderBy('id')->chunkById(100, function ($rows) use ($table, $column, $kind, $origins, &$changed, &$fieldChanges) {
                    foreach ($rows as $row) {
                        $before = $row->$column;
                        if ($kind === 'json') {
                            $decoded = json_decode($before, false, 512, JSON_THROW_ON_ERROR);
                            $canonicalBefore = json_encode($decoded, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            $after = json_encode(localMediaValues($decoded, $origins, 'http://127.0.0.1:17892'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            if ($canonicalBefore === $after) continue;
                        } else {
                            $after = localMediaUrl($before, $origins, 'http://127.0.0.1:17892');
                            if ($before === $after) continue;
                        }
                        Illuminate\Support\Facades\DB::table($table)->where('id', $row->id)->update([$column => $after]);
                        $changed++; $fieldChanges++;
                    }
                }, 'id');
            if ($fieldChanges > 0) $changedFields++;
        }
    });
    echo json_encode(['ok' => true, 'changedValues' => $changed, 'changedFields' => $changedFields], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    // Database errors may include private values. Never return their text.
    echo json_encode(['ok' => false, 'code' => 'MEDIA_RELINK_FAILED']);
    exit(1);
}
