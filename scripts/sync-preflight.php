<?php

// Standalone read-only CLI probe. It can inspect the existing cloud release
// without installing the experimental sync branch or running any migration.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

ini_set('display_errors', '0');

try {
    // Prefer the explicitly selected working directory; never take a web parameter.
    $roots = [getcwd(), getcwd().'/backend', dirname(__DIR__).'/backend'];
    $root = null;
    foreach ($roots as $candidate) {
        if (is_file($candidate.'/artisan') && is_file($candidate.'/vendor/autoload.php')
            && is_file($candidate.'/bootstrap/app.php')) {
            $root = realpath($candidate);
            break;
        }
    }
    if ($root === null) {
        fwrite(STDERR, "Run this command from the Laravel backend directory. No data was changed.\n");
        exit(2);
    }
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $connection = \Illuminate\Support\Facades\DB::connection();
    if (!in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
        throw new \RuntimeException('unsupported_driver');
    }

    // Enforce a read-only transaction in the database, not just by convention.
    // This script never writes files, runs migrations, exports rows, or changes configuration.
    $connection->statement('SET TRANSACTION READ ONLY');
    $connection->beginTransaction();
    try {
        $schema = $connection->getSchemaBuilder();
        $tables = ['users', 'patients', 'invoices', 'invoice_test_rels', 'invoice_paid_details',
            'inventory_items', 'inventory_kits', 'inventory_movements', 'loyalty_transactions', 'lab_settings'];
        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = $schema->hasTable($table) ? $connection->table($table)->count() : null;
        }
        $migrationNames = $schema->hasTable('migrations')
            ? $connection->table('migrations')->orderBy('migration')->pluck('migration')->all() : [];
        $domainMigrations = array_values(array_filter($migrationNames,
            fn ($name) => $name !== '2026_09_21_000001_create_sync_transport_tables'));
        // Includes inactive and soft-deleted owners: their records must not disappear during planning.
        $owners = $schema->hasTable('users') ? $connection->table('users')->whereIn('role_id', [1, 2])
            ->orderBy('id')->get(['id', 'role_id', 'status', 'deleted_at'])->map(fn ($row) => [
                'id' => (int) $row->id,
                'role_id' => (int) $row->role_id,
                'active' => (int) $row->status === 1 && $row->deleted_at === null,
            ])->all() : [];
        $labCount = count(array_filter($owners, fn ($row) => $row['role_id'] === 2));
        $engines = $connection->select(
            'SELECT ENGINE AS engine, COUNT(*) AS table_count FROM information_schema.TABLES '
            .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = ? GROUP BY ENGINE ORDER BY ENGINE', ['BASE TABLE']
        );
        $report = [
            'report_version' => 2,
            'read_only' => true,
            'clinical_sync_ready' => false,
            'baseline_created' => false,
            'scope' => 'whole_installation_including_soft_deleted',
            'driver' => $connection->getDriverName(),
            'counts' => $counts,
            'owner_ids' => $owners,
            'lab_owner_count' => $labCount,
            'requires_tenant_scope_review' => $labCount !== 1,
            'migration_count' => count($migrationNames),
            'domain_migration_fingerprint' => hash('sha256', implode("\n", $domainMigrations)),
            'table_engines' => array_map(fn ($row) => ['engine' => $row->engine, 'table_count' => (int) $row->table_count], $engines),
            'transport_schema_present' => $schema->hasTable('lab_sync_peers'),
        ];
    } finally {
        $connection->rollBack();
    }
    // No names, email addresses, database credentials, APP_KEY or patient content.
    fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
} catch (\Throwable $e) {
    // Exceptions may embed SQL or connection details; do not expose them in a shareable report.
    fwrite(STDERR, "Preflight could not complete. Check the backend directory and database access. No baseline was created.\n");
    exit(1);
}
