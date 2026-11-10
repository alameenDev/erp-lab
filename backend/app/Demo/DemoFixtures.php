<?php
namespace App\Demo;
use App\Models\{User, Patient, Invoice, Subscription, LabSetting, Category, Sample, Test, TestGroup, Package, PortalAccessToken, LabDevice, DeviceResult};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class DemoFixtures {
    public function create(): array {
        $tag = Str::lower(Str::random(16));
        $lab = $this->user('مختبر التدريب — بيانات تجريبية', "$tag-lab", 2);
        $lab->assignRole('Lab');
        $owner = $this->user('صاحب المختبر التجريبي', "$tag-owner", null);
        $owner->forceFill(['owner_portal_only' => true])->save();
        DB::table('owner_labs')->insert(['owner_id' => $owner->id, 'lab_id' => $lab->id]);
        $doctor = $this->user('طبيب التدريب', "$tag-doctor", 5, $lab->id);
        $doctor->forceFill(['referral_portal_only' => true])->save();
        $partner = $this->user('مختبر الإحالة التجريبي', "$tag-partner", 5, $lab->id);
        $partner->forceFill(['referral_portal_only' => true])->save();
        \App\Models\ReferralLabProfile::create(['user_id_fk' => $partner->id, 'display_name' => 'مختبر الإحالة التجريبي']);
        foreach ([$doctor, $partner] as $referrer) \App\Models\Referal::create(['referral_id_fk' => $referrer->id, 'lab_id_fk' => $lab->id, 'commission' => 10]);
        \App\Models\PaymentMethod::create(['lab_id_fk' => $lab->id, 'name' => 'نقد — تجريبي']);
        $collector = $this->user('مندوب التدريب', "$tag-collector", 6, $lab->id);
        // A demo-specific role avoids changing the shared collector role.
        $collector->givePermissionTo(['invoices view', 'invoices create', 'patients view', 'patients create']);
        Subscription::create(['lab_id_fk' => $lab->id, 'plan_name' => 'تجربة تعليمية', 'status' => 'trial', 'start_date' => today(), 'end_date' => today()->addDays(2), 'max_users' => 30, 'max_invoices_per_month' => 500, 'price' => 0]);
        LabSetting::create(['lab_id_fk' => $lab->id, 'lab_display_name' => 'مختبر التدريب', 'tagline' => 'بيانات تجريبية — غير صالحة للاستخدام الطبي', 'show_categories' => true, 'show_tests_on_barcode' => true]);
        $category = Category::create(['lab_id_fk' => $lab->id, 'name' => 'الكيمياء السريرية']);
        $sample = Sample::create(['lab_id_fk' => $lab->id, 'sample_name' => 'Serum — مصل']);
        $tests = collect();
        foreach ([['Glucose', 'GLU', 5000, 'mg/dL'], ['Urea', 'UREA', 6000, 'mg/dL'], ['Creatinine', 'CREA', 7000, 'mg/dL']] as [$name, $code, $price, $unit]) {
            $tests->push(Test::create(['lab_id_fk' => $lab->id, 'category_id_fk' => $category->id, 'sample_id_fk' => $sample->id, 'name' => $name, 'report_name' => $name, 'shortcut' => $code, 'interface_code' => $code, 'price' => $price, 'for_customer_price' => $price, 'unit' => $unit, 'result_type_id_fk' => 1, 'result_comments' => ['يرجى مراجعة الطبيب لتقييم النتيجة.']]));
        }
        $group = TestGroup::create(['lab_id_fk' => $lab->id, 'category_id_fk' => $category->id, 'sample_id_fk' => $sample->id, 'group_name' => 'وظائف الكلى — مثال', 'shortcut' => 'RFT', 'original_price' => 13000, 'for_customer_price' => 13000]);
        $group->tests()->attach([$tests[1]->id => ['order' => 0], $tests[2]->id => ['order' => 1]]);
        $package = Package::create(['lab_id_fk' => $lab->id, 'name' => 'باقة الاطمئنان — مثال', 'shortcut' => 'CHECK', 'price' => 18000, 'is_constant_price' => true, 'show_in_patient_portal' => true]);
        $package->tests()->attach([$tests[0]->id => ['position' => 0]]);
        $package->testGroups()->attach([$group->id => ['position' => 1]]);
        $invoices = []; $patients = [];
        foreach ([['أحمد علي — تجريبي', 32, 1, 5000], ['زينب حسن — تجريبي', 27, 2, 2000], ['علي محمد — تجريبي', 45, 1, 0]] as $index => [$name, $age, $gender, $paid]) {
            $person = $this->user($name, "$tag-p$index", 3, $lab->id);
            $patient = Patient::create(['user_id' => $person->id, 'creator_id' => $lab->id, 'parent_id' => $lab->id, 'code' => "$tag-$index", 'age' => $age, 'age_unit_id_fk' => 1, 'gender_id_fk' => $gender, 'title_id_fk' => $gender, 'dob' => today()->subYears($age), 'dob_is_estimated' => true, 'barcode' => "D$lab->id$index"]);
            $invoice = Invoice::create(['patient_id_fk' => $patient->id, 'lab_id_fk' => $lab->id, 'referral_id_fk' => $index === 0 ? $doctor->id : null, 'from_lab_id_fk' => $index === 2 ? $partner->id : null, 'sub_total' => 5000, 'total' => 5000, 'paid' => $paid, 'registration_date' => today(), 'result_date' => today(), 'barcode' => "D$lab->id$index", 'is_done' => $index === 0, 'notes' => 'مثال تعليمي فقط']);
            $invoice->invoiceTestRels()->create(['test_id_fk' => $tests[0]->id, 'price' => 5000, 'result' => $index === 0 ? '95' : null, 'is_done' => $index === 0]);
            if ($paid) $invoice->paidDetails()->create(['amount' => $paid]);
            $invoices[] = $invoice->id; $patients[] = $patient->id;
        }
        $portal = PortalAccessToken::create(['patient_id_fk' => $patients[0], 'token' => PortalAccessToken::generatePlainToken(), 'verified_at' => now(), 'expires_at' => now()->addDay()]);
        $device = LabDevice::create(['lab_id_fk' => $lab->id, 'name' => 'جهاز كيمياء — محاكاة', 'device_type' => 'chemistry', 'connection_type' => 'tcp', 'api_token' => LabDevice::generateApiToken(), 'status' => 'offline']);
        DeviceResult::create(['device_id_fk' => $device->id, 'invoice_id_fk' => $invoices[1], 'specimen_barcode' => "D$lab->id".'1', 'raw_message' => 'DEMO — simulated result', 'parsed_results' => [['test_code' => 'GLU', 'test_name' => 'Glucose', 'value' => '102', 'unit' => 'mg/dL']], 'status' => 'matched', 'matched_at' => now()]);
        return ['identities' => ['lab' => $lab->id, 'owner' => $owner->id, 'collector' => $collector->id, 'doctor' => $doctor->id, 'referral' => $partner->id], 'examples' => ['invoices' => $invoices, 'patients' => $patients, 'tests' => $tests->pluck('id')->all(), 'portal' => $portal->token]];
    }
    private function user(string $name, string $slug, ?int $role, ?int $creator = null): User {
        return User::create(['name' => $name, 'email' => "$slug@example.invalid", 'password' => Str::random(48), 'role_id' => $role, 'creator_id' => $creator, 'email_verified_at' => now(), 'status' => 1]);
    }
}
