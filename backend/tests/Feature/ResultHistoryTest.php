<?php

namespace Tests\Feature;

use App\Models\{Invoice, InvoiceTestRel, Patient, Test as LabTest, User};
use App\Services\ResultHistoryService;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class ResultHistoryTest extends TestCase
{
    use DatabaseMigrations;

    public function test_history_is_completed_same_patient_same_lab_and_chronological(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $lab = User::create(['name'=>'Lab','email'=>'history@example.test','password'=>'secret-password-123','role_id'=>2]);
        $other = User::create(['name'=>'Other','email'=>'other-history@example.test','password'=>'secret-password-123','role_id'=>2]);
        $person = User::create(['name'=>'Patient','email'=>'history-patient@example.test','password'=>'secret-password-123','role_id'=>6]);
        $patient = Patient::create(['user_id'=>$person->id,'creator_id'=>$lab->id,'code'=>'H-1']);
        $test = LabTest::create(['name'=>'CBC','unit'=>'','lab_id_fk'=>$lab->id]);
        $sameName = LabTest::create(['name'=>'CBC','unit'=>'','lab_id_fk'=>$lab->id]);
        $make = function ($date, $value, $overrides = [], $relOverrides = []) use ($lab, $patient, $test) {
            $invoice = Invoice::create(array_merge(['lab_id_fk'=>$lab->id,'patient_id_fk'=>$patient->id,'is_done'=>true,'result_date'=>$date,'created_at'=>'2026-01-01'], $overrides));
            $invoice->forceFill(['created_at'=>$overrides['created_at'] ?? '2026-01-01'])->save();
            InvoiceTestRel::create(array_merge(['invoice_id_fk'=>$invoice->id,'test_id_fk'=>$test->id,'result'=>$value,'is_done'=>true], $relOverrides));
            return $invoice;
        };
        $make('2026-01-02','0');
        $make('2026-01-03',null,[],['sub_tests'=>[['name'=>'WBC','value'=>'11.10','unit'=>'10*3/uL'],['name'=>'Empty','value'=>'']]]);
        $make('2026-01-04',null,[],['test_id_fk'=>null,'package_tests'=>[['id'=>$test->id,'name'=>'CBC','result'=>'Negative','unit'=>''],['id'=>$test->id,'name'=>'CBC','result'=>'not-done','is_done'=>false]]]);
        $make('2026-01-05','other-lab',['lab_id_fk'=>$other->id]);
        $make('2026-01-05','unreleased',['is_done'=>false]);
        $make('2026-01-05','unfinished',[],['is_done'=>false]);
        $make('2026-01-05','different-patient',['patient_id_fk'=>null]);
        $make('2026-01-05','same-name',[],['test_id_fk'=>$sameName->id]);
        $make('2026-04-01','future');
        $make('2026-01-05','later-created',['created_at'=>'2026-03-01']);
        $current = $make('2026-02-01','current',['created_at'=>'2026-02-01']);
        $history = app(ResultHistoryService::class)->forInvoice($current);
        $rows = $history['test_'.$test->id];
        $this->assertSame(['Negative','11.10','0'], array_column($rows,'value'));
        $this->assertSame('WBC',$rows[1]['field']);
        $this->assertSame('10*3/uL',$rows[1]['unit']);
        $this->assertSame('same-name',$history['test_'.$sameName->id][0]['value']);
        $this->assertSame([],app(ResultHistoryService::class)->forInvoice(new Invoice()));
    }
}
