<?php
namespace Tests\Feature;

use App\Models\InvoiceTestRel;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventorySafetyTest extends TestCase
{
    use DatabaseMigrations;

    private function fixture(): array
    {
        $owner = User::create(['name'=>'Lab', 'email'=>'lab@example.test', 'password'=>'test-password-123', 'role_id'=>2]);
        $this->actingAs($owner);
        $test = DB::table('tests')->insertGetId(['name'=>'Vitamin D3', 'lab_id_fk'=>$owner->id]);
        $invoice = DB::table('invoices')->insertGetId(['lab_id_fk'=>$owner->id]);
        $rel = DB::table('invoice_test_rels')->insertGetId(['invoice_id_fk'=>$invoice, 'test_id_fk'=>$test, 'is_done'=>true, 'result'=>'0']);
        $item = DB::table('inventory_items')->insertGetId(['lab_id'=>$owner->id, 'name'=>'D3 reagent']);
        $kit = DB::table('inventory_kits')->insertGetId(['lab_id'=>$owner->id, 'item_id'=>$item, 'serial_number'=>'D3-001', 'capacity'=>30, 'remaining'=>30, 'expires_on'=>now()->addMonth()->toDateString()]);
        DB::table('inventory_bindings')->insert(['lab_id'=>$owner->id, 'item_id'=>$item, 'subject_type'=>'test', 'subject_id'=>$test, 'quantity'=>1]);
        return [$owner, $invoice, InvoiceTestRel::findOrFail($rel), $kit, $item];
    }

    public function test_zero_result_consumes_once_and_repeat_request_is_idempotent(): void
    {
        [$owner,$invoice,$rel,$kit] = $this->fixture();
        $service = app(InventoryService::class);
        $service->consumeInitial($rel);
        $service->consumeInitial($rel);
        $this->assertSame(29, (int) DB::table('inventory_kits')->where('id',$kit)->value('remaining'));
        $request = (string) Str::uuid();
        $first = $service->repeat($invoice,$rel->id,$owner,'Sample verification',$request);
        $second = $service->repeat($invoice,$rel->id,$owner,'Sample verification',$request);
        $this->assertSame($first->id,$second->id);
        $this->assertSame(28, (int) DB::table('inventory_kits')->where('id',$kit)->value('remaining'));
        $this->assertDatabaseCount('inventory_movements',2);
        $this->assertDatabaseHas('inventory_movements',['kind'=>'repeat','reason'=>'Sample verification','actor_id'=>$owner->id,'invoice_id'=>$invoice,'quantity'=>-1]);
    }

    public function test_expired_stock_cannot_be_consumed_and_failed_run_rolls_back(): void
    {
        [,,$rel,$kit] = $this->fixture();
        DB::table('inventory_kits')->where('id',$kit)->update(['expires_on'=>now()->subDay()->toDateString()]);
        try {
            app(InventoryService::class)->consumeInitial($rel);
            $this->fail('Expired reagent was accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('inventory',$e->errors());
        }
        $this->assertDatabaseCount('inventory_runs',0);
        $this->assertDatabaseCount('inventory_movements',0);
        $this->assertSame(30,(int)DB::table('inventory_kits')->where('id',$kit)->value('remaining'));
    }

    public function test_staff_invoice_uses_owner_stock(): void
    {
        [$owner,$invoice,$rel,$kit] = $this->fixture();
        $staff=User::create(['name'=>'Staff','email'=>'staff@example.test','password'=>'test-password-123','role_id'=>7,'creator_id'=>$owner->id]);
        DB::table('invoices')->where('id',$invoice)->update(['lab_id_fk'=>$staff->id]);
        $this->actingAs($staff);
        app(InventoryService::class)->consumeInitial($rel);
        $this->assertSame(29,(int)DB::table('inventory_kits')->where('id',$kit)->value('remaining'));
        $this->assertDatabaseHas('inventory_movements',['lab_id'=>$owner->id,'actor_id'=>$staff->id]);
    }

    public function test_external_analysis_does_not_consume_local_stock(): void
    {
        [$owner,,$rel,$kit] = $this->fixture();
        DB::table('invoice_test_rels')->where('id',$rel->id)->update(['to_lab_id_fk'=>$owner->id]);
        app(InventoryService::class)->consumeInitial($rel->fresh());
        $this->assertDatabaseCount('inventory_movements',0);
        $this->assertSame(30,(int)DB::table('inventory_kits')->where('id',$kit)->value('remaining'));
    }
}
