<?php

namespace Tests\Feature;

use App\Models\{Invoice, Patient, Subscription, Test as LabTest, User};
use App\Services\{AuditTrail, InvoiceChangeArchive};
use Carbon\Carbon;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OwnerPortalTest extends TestCase
{
    use DatabaseMigrations;
    private User $lab;
    private User $owner;
    private Patient $patient;
    private LabTest $test;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-27 09:00:00', 'Asia/Baghdad')->utc());
        $this->seed(ReferenceDataSeeder::class);
        $this->lab = $this->user('lab', 2);
        $this->owner = $this->user('owner'); $this->owner->forceFill(['owner_portal_only' => true])->save();
        $this->link($this->lab);
        $person = $this->user('patient', 3);
        $this->patient = Patient::create(['user_id' => $person->id, 'creator_id' => $this->lab->id, 'code' => 'OWNER-P']);
        $this->test = LabTest::create(['name' => 'Glucose', 'price' => 1000, 'lab_id_fk' => $this->lab->id]);
        $this->actingAs($this->owner);
    }

    private function user(string $name, ?int $role = null, ?int $creator = null): User
    {
        return User::create(['name' => $name, 'email' => $name.'@owner.test', 'password' => 'owner-password-123',
            'role_id' => $role, 'creator_id' => $creator, 'status' => 1, 'email_verified_at' => now()]);
    }
    private function link(User $lab): void { DB::table('owner_labs')->insert(['owner_id' => $this->owner->id, 'lab_id' => $lab->id]); }
    private function invoice(array $attributes = []): Invoice
    {
        $invoice = Invoice::create(array_merge(['lab_id_fk' => $this->lab->id, 'patient_id_fk' => $this->patient->id, 'total' => 10000, 'sub_total' => 10000, 'paid' => 999999, 'is_done' => false], $attributes));
        $invoice->invoiceTestRels()->create(['test_id_fk' => $this->test->id, 'price' => 10000, 'result' => '90']);
        return $invoice;
    }

    public function test_only_administrator_creates_and_links_owners_and_owner_login_uses_dedicated_portal(): void
    {
        $payload = ['name' => 'New owner', 'email' => 'new@owner.test', 'password' => 'strong-owner-password', 'status' => 1, 'lab_ids' => [$this->lab->id]];
        $this->postJson('/api/super-admin/owners', $payload)->assertForbidden();
        $this->actingAs($this->lab); $this->postJson('/api/super-admin/owners', $payload)->assertForbidden();
        $admin = $this->user('admin', 1); $this->actingAs($admin);
        $this->postJson('/api/super-admin/owners', [...$payload, 'lab_ids' => [$admin->id]])->assertUnprocessable();
        $id = $this->postJson('/api/super-admin/owners', $payload)->assertCreated()->json('id');
        $created = User::findOrFail($id);
        $this->assertTrue($created->owner_portal_only); $this->assertNull($created->role_id);
        $this->assertCount(0, $created->getAllPermissions());
        // Owners can inspect subscription expiry even when every linked lab is expired.
        Subscription::create(['lab_id_fk' => $this->lab->id, 'plan_name' => 'Old', 'status' => 'active', 'start_date' => today()->subYear(), 'end_date' => today()->subDay()]);
        Auth::shouldUse('web'); Auth::forgetGuards();
        $login = $this->postJson('/api/user/login', ['email' => $payload['email'], 'password' => $payload['password']])->assertOk()->assertJsonPath('user.owner_portal_only', true);
        Auth::guard('web')->logout(); Auth::forgetGuards(); $this->withToken($login->json('token'));
        $this->getJson('/api/owner-portal/me')->assertOk()->assertJsonPath('labs.0.subscription.status', 'expired');
        $this->getJson('/api/user/access')->assertOk()->assertJsonPath('permissions', []);
        foreach (['/api/invoices', '/api/user/show', '/api/accounting-reports', '/api/super-admin/owners'] as $url) $this->getJson($url)->assertForbidden();
        $this->actingAs($admin); $this->putJson('/api/super-admin/owners/'.$id, [...$payload, 'password' => null, 'status' => 0])->assertOk();
        $this->assertSame(0, $created->tokens()->count());
    }

    public function test_multi_lab_overview_uses_ledger_and_includes_former_staff_without_crossing_tenants(): void
    {
        $second = $this->user('second', 2); $this->link($second);
        $staff = $this->user('former', 6, $this->lab->id);
        $first = $this->invoice(['is_done' => true]); $first->paidDetails()->create(['amount' => 10000]);
        $partial = $this->invoice(['lab_id_fk' => $staff->id]); $partial->paidDetails()->create(['amount' => 3000]); $staff->delete();
        $deletedPayment = $partial->paidDetails()->create(['amount' => 50000]); DB::table('invoice_paid_details')->where('id', $deletedPayment->id)->update(['deleted_at' => now()]);
        $this->invoice(['lab_id_fk' => $second->id, 'total' => 20000]);
        $gone = $this->invoice(); $gone->delete();
        $foreignLab = $this->user('foreign', 2); $foreign = $this->invoice(['lab_id_fk' => $foreignLab->id]);
        $child = $this->user('independentChild', 2, $this->lab->id); $this->invoice(['lab_id_fk' => $child->id]);
        $r = $this->getJson('/api/owner-portal/overview')->assertOk()->json();
        $this->assertSame(3, $r['summary']['invoice_count']); $this->assertSame(1, $r['summary']['patient_count']);
        $this->assertEquals(40000, $r['summary']['total']); $this->assertEquals(13000, $r['summary']['paid']); $this->assertEquals(27000, $r['summary']['balance']);
        $this->assertSame(3, $r['summary']['analysis_count']); $this->assertSame(['completed' => 1, 'pending' => 2], $r['results']);
        $this->getJson('/api/owner-portal/invoices?status=partial')->assertOk()->assertJsonPath('data.0.id', $partial->id)->assertJsonPath('pagination.total', 1);
        $this->getJson('/api/owner-portal/invoices?result_status=done')->assertOk()->assertJsonPath('pagination.total', 1);
        $this->getJson('/api/owner-portal/invoices/'.$first->id)->assertOk()->assertJsonPath('results.test_'.$this->test->id.'_1.result', '90');
        $this->getJson('/api/owner-portal/overview?lab_id='.$second->id)->assertOk()->assertJsonPath('summary.invoice_count', 1);
        $this->getJson('/api/owner-portal/overview?lab_id='.$foreignLab->id)->assertNotFound();
        $this->getJson('/api/owner-portal/invoices/'.$foreign->id)->assertNotFound();
        DB::table('owner_labs')->where('lab_id', $second->id)->delete();
        $this->getJson('/api/owner-portal/overview?lab_id='.$second->id)->assertNotFound();
        $this->getJson('/api/owner-portal/overview')->assertOk()->assertJsonPath('summary.invoice_count', 2);
        $this->actingAs($this->lab); $this->getJson('/api/owner-portal/me')->assertForbidden();
    }

    public function test_baghdad_day_boundary_and_same_elapsed_comparison_do_not_create_false_decline(): void
    {
        foreach (['2026-10-26 22:00:00', '2026-10-26 20:59:59', '2026-10-26 05:00:00', '2026-10-26 12:00:00'] as $date) {
            $i = $this->invoice(); DB::table('invoices')->where('id', $i->id)->update(['created_at' => $date]);
        }
        $r = $this->getJson('/api/owner-portal/overview')->assertOk()->json();
        $this->assertSame(1, $r['summary']['invoice_count']);
        $this->assertSame(1, $r['trend'][0]['count']);
        $this->assertSame(1, $r['comparison']['previous']); $this->assertSame(1, $r['comparison']['current']);
        $this->assertEquals(0, $r['comparison']['percent']); $this->assertTrue($r['comparison']['same_elapsed_time']);
        $this->getJson('/api/owner-portal/overview?from=2026-01-01&to=2026-10-27')->assertUnprocessable();
        $this->getJson('/api/owner-portal/overview?from=2026-10-27&to=2026-10-26')->assertUnprocessable();
    }

    private function mutate(Invoice $invoice, User $actor, callable $mutation): void
    {
        $request = Request::create('/api/invoices/update', 'PUT', ['id' => $invoice->id]);
        $request->setUserResolver(fn () => $actor);
        $audit = app(AuditTrail::class); $audit->begin($request);
        $mutation(); $audit->finish(200);
    }

    public function test_archive_keeps_deleted_invoices_results_and_money_changes_after_activity_cleanup(): void
    {
        $staff = $this->user('reviewedStaff', 6, $this->lab->id); $invoice = $this->invoice();
        $this->mutate($invoice, $staff, fn () => $invoice->update(['total' => 15000]));
        $this->mutate($invoice, $staff, function () use ($invoice) {
            $invoice->update(['total' => 13000]); $invoice->invoiceTestRels->first()->update(['result' => '100']);
            $invoice->paidDetails()->create(['amount' => 4000]);
        });
        $this->mutate($invoice, $staff, fn () => $invoice->delete());
        $this->assertSame(3, DB::table('invoice_change_archive')->count());
        $a = Activity::where('subject_type', Invoice::class)->where('event', 'deleted')->firstOrFail();
        app(InvoiceChangeArchive::class)->capture($a); // Backfill/retry is idempotent.
        $this->assertSame(3, DB::table('invoice_change_archive')->count());
        $r = $this->getJson('/api/owner-portal/changes')->assertOk()->json();
        foreach (['deleted_count' => 1, 'invoices_count' => 1, 'deleted_total' => 13000, 'increases' => 5000, 'decreases' => 2000, 'net_change' => -10000, 'payments_change' => 0] as $key => $value) $this->assertEquals($value, $r['summary'][$key], $key);
        $id = $r['data'][0]['id'];
        $this->getJson('/api/owner-portal/changes/'.$id)->assertOk()->assertJsonPath('actor_name', 'reviewedStaff')->assertJsonPath('snapshot.before.invoice.total', 13000);
        $this->getJson('/api/owner-portal/changes?change_type=results')->assertOk()->assertJsonPath('pagination.total', 2);
        DB::table('activity_log')->delete(); $invoice->forceDelete(); $staff->delete();
        $this->getJson('/api/owner-portal/changes/'.$id)->assertOk()->assertJsonPath('invoice_id', $invoice->id);
        $foreign = $this->user('unlinkedOwner'); $foreign->forceFill(['owner_portal_only' => true])->save(); $this->actingAs($foreign);
        $this->getJson('/api/owner-portal/changes/'.$id)->assertNotFound();
        $this->getJson('/api/owner-portal/changes')->assertOk()->assertJsonPath('pagination.total', 0);
    }

    public function test_owner_can_manage_only_staff_in_linked_labs_and_cannot_escalate_permissions(): void
    {
        $role = Role::create(['name' => 'Reception', 'guard_name' => 'api', 'lab_id_fk' => $this->lab->id]); $role->givePermissionTo('invoices view');
        $foreignLab = $this->user('foreignTeam', 2);
        $otherRole = Role::create(['name' => 'Foreign role', 'guard_name' => 'api', 'lab_id_fk' => $foreignLab->id]);
        $payload = ['lab_id' => $this->lab->id, 'name' => 'Employee', 'email' => 'staff@owner.test', 'password' => 'employee-password-123', 'role_id' => $role->id, 'status' => 1];
        $this->postJson('/api/owner-portal/team', [...$payload, 'lab_id' => $foreignLab->id])->assertNotFound();
        $this->postJson('/api/owner-portal/team', [...$payload, 'role_id' => 1])->assertUnprocessable();
        $this->postJson('/api/owner-portal/team', [...$payload, 'role_id' => $otherRole->id])->assertNotFound();
        $id = $this->postJson('/api/owner-portal/team', $payload)->assertCreated()->json('id');
        $staff = User::findOrFail($id); $this->assertSame($this->lab->id, $staff->creator_id); $this->assertTrue($staff->can('invoices view'));
        $this->assertFalse($staff->can('super admin dashboard'));
        $this->getJson('/api/owner-portal/team?lab_id='.$this->lab->id)->assertOk()->assertJsonPath('staff.0.id', $id);
        $this->getJson('/api/owner-portal/activity')->assertOk()->assertJsonPath('summary.total', 1);
        $staff->createToken('test');
        $this->putJson('/api/owner-portal/team/'.$id, [...$payload, 'password' => null, 'status' => 0])->assertOk();
        $this->assertSame(0, $staff->tokens()->count());
        $this->putJson('/api/owner-portal/team/'.$this->lab->id, $payload)->assertNotFound();
        $this->actingAs($staff->refresh()); $this->getJson('/api/owner-portal/me')->assertUnauthorized();
    }
}
