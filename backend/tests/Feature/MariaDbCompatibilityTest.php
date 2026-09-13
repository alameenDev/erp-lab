<?php

namespace Tests\Feature;

use App\Http\Controllers\SuperAdminController;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MariaDbCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_data_has_no_demo_users_and_is_repeatable(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $counts = [DB::table('nationalities')->count(), DB::table('permissions')->count()];
        $this->seed(ReferenceDataSeeder::class);
        $this->assertSame($counts, [DB::table('nationalities')->count(), DB::table('permissions')->count()]);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('patients', 0);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_search_and_monthly_financial_totals_work_on_mariadb(): void
    {
        $user = User::create(['name' => 'مختبر ALPHA', 'email' => 'admin@example.test',
            'password' => Hash::make('test-password-only'), 'role_id' => 1]);
        $this->assertSame($user->id, User::whereLike('name', '%alpha%')->firstOrFail()->id);
        $this->assertSame($user->id, User::whereLike('name', '%مختبر%')->firstOrFail()->id);
        $this->actingAs($user);
        foreach ([[10000, 7000], [5000, 5000]] as [$total, $paid]) {
            DB::table('invoices')->insert(['lab_id_fk' => $user->id, 'total' => $total,
                'paid' => $paid, 'created_at' => now(), 'updated_at' => now()]);
        }
        $response = (new SuperAdminController)->revenueTrend(new Request(['months' => 1]));
        $rows = $response->getData(true);
        $this->assertCount(1, $rows);
        $this->assertSame(15000, (int) $rows[0]['revenue']);
        $this->assertSame(12000, (int) $rows[0]['paid']);
        $this->assertSame(2, (int) $rows[0]['invoice_count']);
    }

    public function test_image_path_migration_preserves_relative_and_non_storage_urls(): void
    {
        $user = User::create(['name' => 'Lab', 'email' => 'lab@example.test', 'password' => Hash::make('test-password-only')]);
        DB::table('lab_settings')->insert(['lab_id_fk' => $user->id,
            'logo' => 'https://old.example/storage/logos/a.png',
            'report_background' => 'https://cdn.example/background.png']);
        $migration = require database_path('migrations/2026_04_13_000002_normalize_lab_settings_image_paths.php');
        $migration->up();
        $migration->up();
        $this->assertDatabaseHas('lab_settings', ['logo' => 'logos/a.png',
            'report_background' => 'https://cdn.example/background.png']);
    }
}
