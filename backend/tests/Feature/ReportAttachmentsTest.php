<?php

namespace Tests\Feature;

use App\Models\{Invoice, Patient, Test as LabTest, User};
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReportAttachmentsTest extends TestCase
{
    use DatabaseMigrations;
    private User $lab;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        Storage::fake('local'); Storage::fake('public');
        $this->lab = User::create(['name' => 'Attachment lab', 'email' => 'attachments@example.test', 'password' => 'test-password', 'role_id' => 2]);
        $this->lab->givePermissionTo(Permission::findOrCreate('medical reports update', 'api'));
        $patient = Patient::create(['user_id' => $this->lab->id, 'creator_id' => $this->lab->id, 'code' => 'ATTACH']);
        $this->invoice = Invoice::create(['patient_id_fk' => $patient->id, 'lab_id_fk' => $this->lab->id]);
        $test = LabTest::create(['name' => 'CBC', 'lab_id_fk' => $this->lab->id, 'result_type_id_fk' => 1]);
        $this->invoice->invoiceTestRels()->create(['test_id_fk' => $test->id, 'result' => '7', 'is_done' => true]);
        $this->actingAs($this->lab);
    }

    private function upload(string $name, string $bytes, array $extra = [])
    {
        return $this->withHeader('Accept', 'application/json')->post('/api/invoices/update-result?silent=1', array_merge([
            'id' => $this->invoice->id,
            'existing_attachments' => '[]',
            'attachments' => [['name' => 'تقرير مرفق', 'upload_id' => (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent($name, $bytes)]],
        ], $extra));
    }

    public function test_pdf_upload_is_compressed_losslessly_downloadable_and_retry_does_not_duplicate(): void
    {
        $pdf = "%PDF-1.4\n".str_repeat("% Synthetic medical attachment\n", 2000)."%%EOF";
        $response = $this->upload('report.pdf', $pdf)->assertOk()->assertJsonCount(1, 'attachments');
        $saved = $response->json('attachments.0');
        $this->assertSame('تقرير مرفق', $saved['name']);
        $this->assertSame('gzip', $saved['encoding']);
        $this->assertLessThan(strlen($pdf), $saved['stored_size']);
        $this->assertSame(strlen($pdf), $saved['original_size']);
        $this->get($saved['file'])->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame($pdf, $this->get($saved['file'])->getContent());
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->upload('report.pdf', $pdf)->assertOk()->assertJsonPath('attachments.0.file', $saved['file'])->assertJsonCount(1, 'attachments');
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->assertSame('7', $this->invoice->invoiceTestRels()->first()->result);
    }

    public function test_result_only_save_preserves_files_and_explicit_clear_removes_managed_storage(): void
    {
        $saved = $this->upload('notes.txt', str_repeat('Synthetic notes. ', 400))->assertOk()->json('attachments.0');
        $this->postJson('/api/invoices/update-result', ['id' => $this->invoice->id, 'notes' => 'Changed result note'])
            ->assertOk()->assertJsonPath('attachments.0.file', $saved['file']);
        $this->postJson('/api/invoices/update-result', ['id' => $this->invoice->id, 'existing_attachments' => '[]'])
            ->assertOk()->assertJsonCount(0, 'attachments');
        Storage::disk('local')->assertMissing($saved['storage_path']);
        $this->get($saved['file'])->assertNotFound();
    }

    public function test_display_name_is_independent_of_filename_and_can_be_renamed_or_cleared(): void
    {
        $saved = $this->upload('7884.pdf', "%PDF-1.4\nSynthetic\n%%EOF", [
            'attachments' => [['name' => '', 'file' => UploadedFile::fake()->createWithContent('7884.pdf', "%PDF-1.4\nSynthetic\n%%EOF")]],
        ])->assertOk()->assertJsonPath('attachments.0.name', '')->json('attachments.0');
        $this->assertSame('7884.pdf', $saved['download_name']);
        foreach (['نتائج الفحص الخارجي', ''] as $name) {
            $this->postJson('/api/invoices/update-result', [
                'id' => $this->invoice->id,
                'existing_attachments' => [['file' => $saved['file'], 'name' => $name]],
            ])->assertOk()->assertJsonPath('attachments.0.name', $name)
                ->assertJsonPath('attachments.0.download_name', '7884.pdf');
            $this->assertSame($name, $this->invoice->fresh()->attachments[0]['name']);
        }
        $this->assertCount(1, Storage::disk('local')->allFiles(), 'renaming does not re-upload the file');
    }

    public function test_images_keep_dimensions_and_arbitrary_files_cannot_execute_from_public_storage(): void
    {
        $file = UploadedFile::fake()->image('scan.png', 1200, 1600);
        $bytes = file_get_contents($file->getRealPath());
        $saved = $this->upload('scan.png', $bytes)->assertOk()->json('attachments.0');
        $image = $this->get($saved['file'])->assertOk()->getContent();
        $this->assertSame([1200, 1600], array_slice(getimagesizefromstring($image), 0, 2));
        $this->assertLessThanOrEqual(strlen($bytes), $saved['stored_size']);
        $this->assertEmpty(Storage::disk('public')->allFiles());
        $script = '<?php echo "synthetic";';
        $other = $this->upload('../../unsafe.php', $script)->assertOk()->json('attachments.0');
        $download = $this->get($other['file'])->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('attachment;', $download->headers->get('Content-Disposition'));
        $this->assertSame($script, $download->getContent());
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_attachment_ownership_metadata_validation_and_failed_updates_cleanup(): void
    {
        $saved = $this->upload('notes.txt', 'Some notes')->assertOk()->json('attachments.0');
        $this->postJson('/api/invoices/update-result', ['id' => $this->invoice->id, 'existing_attachments' => json_encode([
            array_merge($saved, ['name' => 'Renamed', 'storage_path' => 'wrong-path', 'encoding' => 'wrong']),
        ])])->assertOk()->assertJsonPath('attachments.0.name', 'Renamed')->assertJsonPath('attachments.0.storage_path', $saved['storage_path']);
        $this->postJson('/api/invoices/update-result', ['id' => $this->invoice->id, 'existing_attachments' => '[{"file":"https://other.test/file"}]'])->assertUnprocessable();
        $foreign = User::create(['name' => 'Other', 'email' => 'other-attachment@example.test', 'password' => 'test-password', 'role_id' => 2]);
        $foreign->givePermissionTo(Permission::findOrCreate('medical reports update', 'api'));
        $this->actingAs($foreign);
        $this->upload('foreign.txt', 'Foreign')->assertForbidden();
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->actingAs($this->lab);
        $existing = array_map(fn ($i) => ['name' => 'Legacy '.$i, 'file' => 'https://example.test/storage/'.$i.'.txt'], range(1, 20));
        $this->invoice->update(['attachments' => $existing]);
        $this->upload('overflow.txt', 'New upload', ['existing_attachments' => json_encode($existing)])->assertUnprocessable();
        $this->assertCount(1, Storage::disk('local')->allFiles(), 'failed transaction removes only its new files');
        $this->assertCount(20, $this->invoice->fresh()->attachments);
        $this->get('/api/report-attachments/'.$this->invoice->id.'/'.Str::uuid())->assertNotFound();
    }
}
