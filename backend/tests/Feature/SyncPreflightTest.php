<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SyncPreflightTest extends TestCase
{
    use DatabaseMigrations;

    public function test_standalone_probe_works_without_sync_schema_and_exposes_counts_only(): void
    {
        $owner = User::create(['name' => 'PRIVATE_OWNER_NAME', 'email' => 'private-owner@example.test',
            'password' => 'never-print-this-password', 'role_id' => 2]);
        // Match the live release: the transport tables have not been installed.
        foreach (['lab_sync_events', 'lab_sync_heads', 'lab_sync_peers'] as $table) {
            DB::statement('DROP TABLE '.$table);
        }
        $before = DB::table('users')->get()->toJson();
        $process = new Process([PHP_BINARY, base_path('../scripts/sync-preflight.php')], base_path());
        $process->mustRun();
        $output = $process->getOutput();
        $report = json_decode($output, true, 32, JSON_THROW_ON_ERROR);
        $this->assertTrue($report['read_only']);
        $this->assertFalse($report['baseline_created']);
        $this->assertFalse($report['transport_schema_present']);
        $this->assertFalse($report['clinical_sync_ready']);
        $this->assertSame(1, $report['counts']['users']);
        $this->assertSame(0, $report['counts']['patients']);
        $this->assertSame([['id' => $owner->id, 'role_id' => 2, 'active' => true]], $report['owner_ids']);
        $this->assertFalse($report['requires_tenant_scope_review']);
        $this->assertSame($before, DB::table('users')->get()->toJson());
        foreach (['PRIVATE_OWNER_NAME', 'private-owner@example.test', 'never-print-this-password', config('app.key')] as $secret) {
            $this->assertStringNotContainsString($secret, $output);
        }
    }

    public function test_probe_flags_multiple_labs_including_soft_deleted_ones(): void
    {
        User::create(['name' => 'Lab1', 'email' => 'one@example.test', 'password' => 'test-only', 'role_id' => 2]);
        $other = User::create(['name' => 'Lab2', 'email' => 'two@example.test', 'password' => 'test-only', 'role_id' => 2]);
        $other->delete();
        $process = new Process([PHP_BINARY, base_path('../scripts/sync-preflight.php')], base_path());
        $process->mustRun();
        $report = json_decode($process->getOutput(), true, 32, JSON_THROW_ON_ERROR);
        $this->assertTrue($report['requires_tenant_scope_review']);
        $this->assertSame(2, $report['lab_owner_count']);
        $this->assertFalse($report['owner_ids'][1]['active']);
    }
}
