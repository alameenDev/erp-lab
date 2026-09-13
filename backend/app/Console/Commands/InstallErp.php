<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class InstallErp extends Command
{
    protected $signature = 'erp:install';
    protected $description = 'Create tables, reference data and the first administrator without demo data';

    public function handle(): int
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->error('Configure DB_CONNECTION=mysql and your Hostinger database first.');
            return self::FAILURE;
        }
        if (!config('app.key')) {
            $this->error('APP_KEY is missing. Run php artisan key:generate once on this new installation.');
            return self::FAILURE;
        }
        DB::select('SELECT 1');
        $this->info('Connected to database: '.DB::connection()->getDatabaseName());
        if (!$this->confirm('Apply pending migrations and initialize this database?', false)) {
            return self::FAILURE;
        }
        if ($this->call('migrate', ['--force' => true]) !== 0) {
            return self::FAILURE;
        }
        $roles = ['Admin', 'Lab', 'Patient', 'Branch Lab', 'Doctor', 'Sample Collector', 'User'];
        foreach (Role::where('guard_name', 'api')->get() as $role) {
            if (in_array($role->name, $roles, true) && ($roles[$role->id - 1] ?? null) !== $role->name) {
                $this->error('Existing role IDs do not match this application. Installation stopped; no roles were changed.');
                return self::FAILURE;
            }
        }
        if (User::where('role_id', 1)->exists()) {
            $this->info('An administrator already exists. Its credentials have not been changed.');
            return self::SUCCESS;
        }
        $name = $this->ask('Administrator name');
        $email = strtolower(trim((string) $this->ask('Administrator email')));
        $password = $this->secret('Password (at least 12 characters; input is hidden)');
        $confirmation = $this->secret('Confirm password');
        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation],
            ['name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users,email',
                'password' => 'required|string|min:12|confirmed']
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }
        DB::transaction(function () use ($name, $email, $password, $roles) {
            if ($this->call('db:seed', ['--class' => ReferenceDataSeeder::class, '--force' => true]) !== 0) {
                throw new \RuntimeException('Reference data initialization failed.');
            }
            foreach ($roles as $offset => $roleName) {
                $role = Role::where('name', $roleName)->where('guard_name', 'api')->firstOrFail();
                if ((int) $role->id !== $offset + 1) {
                    throw new \RuntimeException('Role IDs are incompatible; use a fresh database for installation.');
                }
            }
            $admin = User::create([
                'name' => $name, 'email' => $email, 'password' => Hash::make($password),
                'role_id' => 1, 'status' => 1, 'email_verified_at' => now(),
            ]);
            $admin->assignRole('Admin');
        });
        $this->info('Installation complete. Administrator created; no demo patients or invoices were added.');
        return self::SUCCESS;
    }
}
