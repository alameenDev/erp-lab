<?php
namespace Tests\Feature;
use App\Demo\{DemoSession, DemoProvider};
use App\Models\{User, Invoice};
use Database\Seeders\{ReferenceDataSeeder, PermissionsSeeder};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{DB, Auth, Http};
use Tests\TestCase;
class LearningDemoTest extends TestCase {
    use DatabaseMigrations;
    private function enable(): void {
        config(['demo.enabled'=>true, 'demo.database'=>DB::connection()->getDatabaseName(), 'demo.live_database'=>'production_not_test', 'demo.live_username'=>'production_not_test']);
        $this->seed([ReferenceDataSeeder::class, PermissionsSeeder::class]);
    }
    private function start(): array {
        $this->flushHeaders();
        return $this->postJson('/api/learning-demo/start')->assertOk()->json();
    }
    private function session(array $session): void {
        Auth::forgetGuards(); $this->flushHeaders();
        $this->withHeaders(['X-Demo-Session'=>$session['secret'], 'Authorization'=>'Bearer '.$session['token']]);
    }
    public function test_live_installation_does_not_provision_or_expose_demo_accounts(): void {
        $this->postJson('/api/learning-demo/start')->assertNotFound();
        $this->assertDatabaseCount('learning_demo_sessions', 0);
    }
    public function test_shared_database_or_credentials_fail_closed_before_provisioning(): void {
        config(['demo.enabled'=>true]);
        $this->postJson('/api/learning-demo/start')->assertStatus(503);
        $this->enable(); config(['demo.live_database'=>DB::connection()->getDatabaseName()]);
        $this->postJson('/api/learning-demo/start')->assertStatus(503);
        $this->assertDatabaseCount('learning_demo_sessions',0);
    }
    public function test_real_workflows_and_roles_are_seeded_in_separate_workspaces(): void {
        $this->enable(); $a=$this->start(); $b=$this->start();
        $this->assertNotSame($a['user']['id'],$b['user']['id']);
        $this->assertCount(3,$a['examples']['invoices']);
        $this->assertDatabaseMissing('learning_demo_sessions',['secret_hash'=>$a['secret']]);
        $this->session($a);
        $this->getJson('/api/user/access')->assertOk();
        $this->getJson('/api/patients')->assertOk()->assertJsonCount(3);
        $this->getJson('/api/invoices')->assertOk();
        $this->getJson('/api/portal/'.$a['examples']['portal'])->assertOk();
        $this->getJson('/api/invoices/public/'.$b['examples']['invoices'][0])->assertNotFound();
        $this->getJson('/api/portal/'.$b['examples']['portal'])->assertNotFound();
        $this->withToken($b['token'])->getJson('/api/user/access')->assertForbidden();
        $this->session($a);
        $owner=$this->postJson('/api/learning-demo/resume',['identity'=>'owner'])->assertOk()->assertJsonPath('user.owner_portal_only',true)->json();
        $this->session([...$owner,'secret'=>$a['secret']]);
        $this->getJson('/api/owner-portal/overview')->assertOk()->assertJsonPath('summary.invoice_count',3);
        $this->postJson('/api/learning-demo/resume',['identity'=>'administrator'])->assertUnprocessable();
    }
    public function test_expiry_reset_and_external_action_boundaries(): void {
        $this->enable(); $a=$this->start(); $this->session($a);
        $this->postJson('/api/device/bridge/results')->assertForbidden();
        $this->postJson('/api/whatsapp/message',['phone'=>'123'])->assertStatus(409)->assertJsonPath('simulated',true);
        $this->postJson('/api/user/register')->assertForbidden();
        $this->getJson('/api/super-admin/labs')->assertForbidden();
        $new=$this->postJson('/api/learning-demo/reset')->assertOk()->json();
        $this->assertNotSame($new['user']['id'],$a['user']['id']);
        $this->session($a); $this->getJson('/api/patients')->assertUnauthorized();
        $this->session($new); $this->getJson('/api/patients')->assertOk()->assertJsonCount(3);
        $this->travel(25)->hours();
        $this->getJson('/api/patients')->assertUnauthorized();
    }
    public function test_server_side_integrations_cannot_send_from_demo(): void {
        $this->enable(); (new DemoProvider($this->app))->boot();
        $this->assertFalse(config('portal_push.enabled'));
        $this->expectException(\RuntimeException::class);
        Http::post('https://example.invalid/send');
    }
}
