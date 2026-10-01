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
        LabSetting::create(['lab_id_fk'=>$owner->id,'lab_display_name'=>'Keep name','report_template'=>'modern','document_config'=>['thermal'=>['width'=>58]],'print_table_config'=>['body_font_size'=>20]]);
        $this->actingAs($owner)->postJson('/api/lab-settings/reset',['section'=>'print'])
            ->assertOk()->assertJsonPath('setting.lab_display_name','Keep name')
            ->assertJsonPath('setting.report_template','classic')
            ->assertJsonPath('setting.document_config',null)
            ->assertJsonPath('setting.print_table_config',null);
    }

    public function test_template_defaults_to_classic_and_owner_can_switch_both_ways(): void
    {
        $owner = $this->owner();
        $other = User::create(['name'=>'Other Lab','email'=>'other-settings@example.test','password'=>'Test-password-123','role_id'=>2]);
        LabSetting::create(['lab_id_fk'=>$other->id]);
        $this->getJson('/api/lab-settings/'.$owner->id)->assertOk()->assertJsonPath('report_template','classic');
        $this->actingAs($owner)->getJson('/api/lab-settings')->assertOk()->assertJsonPath('report_template','classic');
        $this->postJson('/api/lab-settings', ['report_template'=>'modern'])->assertOk()->assertJsonPath('setting.report_template','modern');
        $this->getJson('/api/lab-settings/'.$owner->id)->assertOk()->assertJsonPath('report_template','modern');
        $this->assertSame('classic', LabSetting::where('lab_id_fk',$other->id)->value('report_template'));
        // Partial saves and a branding reset must not discard the selection.
        $this->postJson('/api/lab-settings', ['lab_display_name'=>'Updated'])->assertOk()->assertJsonPath('setting.report_template','modern');
        $this->postJson('/api/lab-settings/reset', ['section'=>'branding'])->assertOk()->assertJsonPath('setting.report_template','modern');
        $this->postJson('/api/lab-settings', ['report_template'=>'classic'])->assertOk()->assertJsonPath('setting.report_template','classic');
    }

    public function test_template_validation_and_staff_permissions(): void
    {
        $owner = $this->owner();
        LabSetting::create(['lab_id_fk'=>$owner->id,'report_template'=>'modern']);
        $this->actingAs($owner)->postJson('/api/lab-settings', ['report_template'=>'unknown'])->assertUnprocessable();
        $this->postJson('/api/lab-settings', ['report_template'=>null])->assertUnprocessable();
        $staff = User::create(['name'=>'Template Staff','email'=>'template-staff@example.test','password'=>'Test-password-123','role_id'=>7,'creator_id'=>$owner->id]);
        $this->actingAs($staff)->getJson('/api/lab-settings')->assertOk()->assertJsonPath('report_template','modern');
        $this->postJson('/api/lab-settings', ['report_template'=>'classic'])->assertForbidden();
        $this->assertSame('modern', LabSetting::where('lab_id_fk',$owner->id)->value('report_template'));
    }

    public function test_column_widths_persist_for_the_owner_and_public_reports_without_losing_other_settings(): void
    {
        $owner = $this->owner();
        $other = User::create(['name'=>'Other','email'=>'columns-other@example.test','password'=>'Test-password-123','role_id'=>2]);
        LabSetting::create(['lab_id_fk'=>$owner->id,'print_table_config'=>['body_font_size'=>16,'border_color'=>'#123456']]);
        LabSetting::create(['lab_id_fk'=>$other->id]);
        $widths = ['test'=>30,'result'=>15,'unit'=>12,'reference'=>25,'last_result'=>10,'status'=>8];
        $this->actingAs($owner)->postJson('/api/lab-settings', ['print_table_config'=>['custom_column_widths'=>true,'column_widths'=>$widths]])
            ->assertOk()->assertJsonPath('setting.print_table_config.column_widths', $widths)
            ->assertJsonPath('setting.print_table_config.body_font_size',16);
        $this->postJson('/api/lab-settings', ['print_table_config'=>['column_widths'=>['reference'=>40]]])
            ->assertOk()->assertJsonPath('setting.print_table_config.column_widths.test',30);
        $this->getJson('/api/lab-settings/'.$owner->id)->assertOk()
            ->assertJsonPath('print_table_config.custom_column_widths',true)
            ->assertJsonPath('print_table_config.column_widths.reference',40);
        $this->postJson('/api/lab-settings', ['print_table_config'=>['custom_column_widths'=>false,'body_font_size'=>14]])
            ->assertOk()->assertJsonPath('setting.print_table_config.column_widths.reference',40);
        $this->assertNull(LabSetting::where('lab_id_fk',$other->id)->value('print_table_config'));
        $staff = User::create(['name'=>'Staff','email'=>'columns-staff@example.test','password'=>'Test-password-123','role_id'=>7,'creator_id'=>$owner->id]);
        $this->actingAs($staff)->postJson('/api/lab-settings', ['print_table_config'=>['custom_column_widths'=>true]])->assertForbidden();
    }

    public function test_column_width_validation_rejects_unknown_columns_and_unsafe_dimensions(): void
    {
        $owner = $this->owner();
        foreach ([0, -1, 86, 12.5, '40%;color:red', null] as $invalid) {
            $this->actingAs($owner)->postJson('/api/lab-settings', ['print_table_config'=>['column_widths'=>['reference'=>$invalid]]])
                ->assertUnprocessable()->assertJsonValidationErrors('print_table_config.column_widths.reference');
        }
        $this->postJson('/api/lab-settings', ['print_table_config'=>['column_widths'=>['unknown'=>20]]])->assertUnprocessable();
        $this->postJson('/api/lab-settings', ['print_table_config'=>['custom_column_widths'=>'yes']])->assertUnprocessable();
    }
}
