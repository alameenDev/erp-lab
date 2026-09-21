<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncInspectCommand extends Command
{
    protected $signature = 'lab-sync:inspect';
    protected $description = 'Read-only schema and row counts for planning an initial sync baseline; no patient data or secrets';

    public function handle(): int
    {
        $tables = ['users', 'patients', 'invoices', 'invoice_test_rels', 'invoice_paid_details',
            'inventory_items', 'inventory_kits', 'inventory_movements', 'loyalty_transactions', 'lab_settings'];
        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = Schema::hasTable($table) ? DB::table($table)->count() : null;
        }
        $migrations = Schema::hasTable('migrations')
            ? DB::table('migrations')->orderBy('migration')->pluck('migration')->all() : [];
        $owners = Schema::hasTable('users') ? DB::table('users')->whereNull('deleted_at')
            ->whereIn('role_id', [1, 2])->orderBy('id')->get(['id', 'role_id'])->all() : [];
        $this->line(json_encode([
            'report_version' => 1, 'scope' => 'whole_installation_counts_including_soft_deleted',
            'read_only' => true, 'clinical_sync_ready' => false,
            'driver' => DB::getDriverName(), 'counts' => $counts,
            'owner_ids' => $owners,
            'schema_fingerprint' => hash('sha256', implode("\n", $migrations)),
            'migration_count' => count($migrations),
            'transport_schema_present' => Schema::hasTable('lab_sync_peers'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
