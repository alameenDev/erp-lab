<?php
namespace App\Services;

use App\Models\{Lab, Plan, Subscription, User};
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\{DB, Hash, Validator};
use Spatie\Permission\Models\Role;

class DesktopInstallation
{
    public function install(array $input): array
    {
        if (!config('desktop.enabled') || DB::connection()->getDatabaseName() !== 'digital_lab_desktop') {
            throw new \RuntimeException('Desktop installation requires its dedicated local database.');
        }
        foreach (['owner_email', 'lab_email'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) $input[$field] = strtolower(trim($input[$field]));
        }
        $data = Validator::make($input, [
            'owner_name' => 'required|string|max:255', 'owner_email' => 'required|email|max:255|different:lab_email',
            'owner_password' => 'required|string|min:12|max:128', 'lab_name' => 'required|string|max:255',
            'lab_email' => 'required|email|max:255', 'lab_password' => 'required|string|min:12|max:128',
            'phone' => 'nullable|string|max:40', 'address' => 'nullable|string|max:255',
        ])->validate();
        // Retry is safe after any UI/process failure: existing users are never reset.
        if (User::withTrashed()->exists()) throw new \RuntimeException('This database already contains accounts.');
        // Keep reference IDs stable if account creation fails and is retried.
        // InnoDB rolls rows back but does not roll AUTO_INCREMENT counters back.
        \Illuminate\Database\Eloquent\Model::unguarded(fn () => (new ReferenceDataSeeder)->run());
        foreach (['Admin', 'Lab', 'Patient', 'Branch Lab', 'Doctor', 'Sample Collector', 'User'] as $i => $name) {
            if ((int) Role::where('name', $name)->where('guard_name', 'api')->value('id') !== $i + 1) {
                throw new \RuntimeException('Unexpected role IDs.');
            }
        }
        return DB::transaction(function () use ($data) {
            $owner = User::create(['name' => $data['owner_name'], 'email' => strtolower($data['owner_email']),
                'password' => Hash::make($data['owner_password']), 'role_id' => 1, 'status' => 1, 'email_verified_at' => now()]);
            $owner->assignRole('Admin');
            $lab = User::create(['name' => $data['lab_name'], 'email' => strtolower($data['lab_email']),
                'password' => Hash::make($data['lab_password']), 'role_id' => 2, 'status' => 1,
                'email_verified_at' => now(), 'creator_id' => $owner->id,
                'phone_number' => $data['phone'] ?? null, 'address' => $data['address'] ?? null]);
            $lab->assignRole('Lab');
            Lab::create(['user_id_fk' => $lab->id]);
            $plan = Plan::create(['name' => 'Desktop V1', 'name_ar' => 'نسخة سطح المكتب V1',
                'monthly_price' => 0, 'yearly_price' => 0, 'currency' => 'IQD',
                'max_users' => null, 'max_invoices_per_month' => null, 'is_active' => true,
                'features' => ['جميع وظائف المختبر', 'قاعدة بيانات محلية', 'الطباعة والنسخ الاحتياطي']]);
            Subscription::create(['lab_id_fk' => $lab->id, 'plan_id_fk' => $plan->id, 'plan_name' => $plan->name,
                'status' => 'active', 'start_date' => today(), 'end_date' => today()->addYear(),
                'max_users' => null, 'max_invoices_per_month' => null, 'price' => 0, 'currency' => 'IQD',
                'notes' => 'اشتراك محلي مستقل؛ يجدد من حساب المدير المحلي.']);
            return ['installed' => true, 'lab_email' => $lab->email];
        });
    }
}
