<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\LabSetting;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class LabSettingsTest extends TestCase
{
    use DatabaseMigrations;

    private function owner(): User
    {
        return User::create(['name'=>'Settings Lab','email'=>'settings@example.test','password'=>'Test-password-123','role_id'=>2]);
    }

    public function test_owner_can_save_documents_without_replacing_existing_settings(): void
    {
        $owner=$this->owner();
        LabSetting::create(['lab_id_fk'=>$owner->id,'lab_display_name'=>'Existing Lab','print_margins'=>['top'=>0,'left'=>12]]);
        $this->actingAs($owner)->postJson('/api/lab-settings',[
            'document_config'=>['thermal'=>['width'=>58,'margin'=>0,'show_qr'=>false]],
            'whatsapp_invoice_message'=>'Hello {patient_name}: {link}',
        ])->assertOk()->assertJsonPath('setting.document_config.thermal.width',58);
        $this->actingAs($owner)->postJson('/api/lab-settings',[
            'document_config'=>['thermal'=>['font_size'=>14]],
        ])->assertOk()->assertJsonPath('setting.document_config.thermal.width',58);
        $setting=LabSetting::where('lab_id_fk',$owner->id)->firstOrFail();
        $this->assertSame('Existing Lab',$setting->lab_display_name);
        $this->assertSame(0,$setting->print_margins['top']);
        $this->assertFalse($setting->document_config['thermal']['show_qr']);
    }

    public function test_invalid_dimensions_and_unknown_reset_section_are_rejected(): void
    {
        $owner=$this->owner();
        $this->actingAs($owner)->postJson('/api/lab-settings',['document_config'=>['thermal'=>['width'=>200]]])->assertUnprocessable();
        $this->postJson('/api/lab-settings',['document_config'=>['invoice'=>['accent'=>'</style>']]])->assertUnprocessable();
        $this->postJson('/api/lab-settings/reset',['section'=>'misspelled'])->assertUnprocessable();
    }

    public function test_staff_cannot_update_or_delete_owner_branding(): void
    {
        $owner=$this->owner();
        $staff=User::create(['name'=>'Staff','email'=>'staff@example.test','password'=>'Test-password-123','role_id'=>7,'creator_id'=>$owner->id]);
        $this->actingAs($staff)->postJson('/api/lab-settings',['lab_display_name'=>'Changed'])->assertForbidden();
        $this->deleteJson('/api/lab-settings/logo')->assertForbidden();
        $this->deleteJson('/api/lab-settings/background')->assertForbidden();
    }

    public function test_print_reset_preserves_branding_and_clears_print_configs(): void
    {
        $owner=$this->owner();
        LabSetting::create(['lab_id_fk'=>$owner->id,'lab_display_name'=>'Keep name','document_config'=>['thermal'=>['width'=>58]],'print_table_config'=>['body_font_size'=>20]]);
        $this->actingAs($owner)->postJson('/api/lab-settings/reset',['section'=>'print'])
            ->assertOk()->assertJsonPath('setting.lab_display_name','Keep name')
            ->assertJsonPath('setting.document_config',null)
            ->assertJsonPath('setting.print_table_config',null);
    }
}
