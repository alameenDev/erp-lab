<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ReportAttachmentStorage
{
    public array $uploaded = [];
    public array $createdPaths = [];
    public array $removedPaths = [];

    public function update(Invoice $invoice, Request $request): void
    {
        $current = is_array($invoice->attachments) ? $invoice->attachments : [];
        $kept = $current;
        if ($request->has('existing_attachments')) {
            $input = $request->input('existing_attachments');
            $input = is_string($input) ? json_decode($input, true) : $input;
            if (! is_array($input) || ! array_is_list($input)) {
                throw ValidationException::withMessages(['existing_attachments' => 'Invalid attachment list.']);
            }
            $kept = [];
            foreach ($input as $item) {
                $known = collect($current)->first(fn ($a) => is_array($item) && ($a['file'] ?? null) === ($item['file'] ?? null));
                if (! $known) {
                    throw ValidationException::withMessages(['existing_attachments' => 'Attachment does not belong to this report.']);
                }
                // Paths, MIME types and compression metadata always come from storage.
                if (array_key_exists('name', $item)) {
                    $known['name'] = mb_substr(trim((string) $item['name']), 0, 255);
                }
                $kept[$known['file']] = $known;
            }
            $kept = array_values($kept);
        }
        foreach ($request->file('attachments', []) as $index => $upload) {
            $file = $upload['file'] ?? null;
            if (! $file instanceof UploadedFile) continue;
            $hash = hash_file('sha256', $file->getRealPath());
            $name = trim((string) $request->input("attachments.$index.name"));
            // A retry after a lost response must not upload the same bytes twice.
            $saved = collect(array_merge($current, $kept))->firstWhere('sha256', $hash);
            if (! $saved) $saved = $this->store($invoice, $file, $hash);
            $saved['name'] = mb_substr($name, 0, 255);
            $kept = array_values(array_filter($kept, fn ($a) => $a['file'] !== $saved['file']));
            $kept[] = $saved;
            if ($uploadId = $request->input("attachments.$index.upload_id")) $this->uploaded[$uploadId] = $saved['file'];
        }
        if (count($kept) > 20) throw ValidationException::withMessages(['attachments' => 'Maximum 20 attachments per report.']);
        foreach ($current as $old) {
            if (isset($old['storage_path']) && ! in_array($old['file'], array_column($kept, 'file'), true)) {
                $this->removedPaths[] = $old['storage_path'];
            }
        }
        $invoice->attachments = $kept ?: null;
    }

    private function store(Invoice $invoice, UploadedFile $file, string $hash): array
    {
        $original = file_get_contents($file->getRealPath());
        if ($original === false) throw new RuntimeException('Could not read attachment.');
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $bytes = $original;
        $extension = strtolower($file->getClientOriginalExtension());
        $downloadName = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        // Losslessly re-encode supported scan images at their original pixel dimensions.
        // Never reinterpret scientific images, animated images or oversized images.
        if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) && function_exists('imagewebp') && defined('IMG_WEBP_LOSSLESS')) {
            $size = @getimagesizefromstring($original);
            $orientation = 1;
            if ($mime === 'image/jpeg') {
                $orientation = function_exists('exif_read_data') ? (@exif_read_data($file->getRealPath())['Orientation'] ?? 1) : 0;
            }
            if ($size && $size[0] * $size[1] <= 8000000 && $orientation === 1 && ! str_contains($original, 'acTL') && ! str_contains($original, 'ANIM')) {
                $image = @imagecreatefromstring($original);
                if ($image) {
                    ob_start();
                    imagewebp($image, null, IMG_WEBP_LOSSLESS);
                    $optimized = ob_get_clean();
                    imagedestroy($image);
                    if ($optimized && strlen($optimized) < strlen($bytes)) {
                        $bytes = $optimized;
                        $mime = 'image/webp';
                        $extension = 'webp';
                        $downloadName = pathinfo($downloadName, PATHINFO_FILENAME).'.webp';
                    }
                }
            }
        }
        // Lossless storage compression also works for PDFs and arbitrary documents.
        // Downloads restore the exact optimized file bytes; no .zip is shown to users.
        $gzip = gzencode($bytes, 9);
        $compressed = $gzip !== false && strlen($gzip) < strlen($bytes);
        $stored = $compressed ? $gzip : $bytes;
        $token = (string) Str::uuid();
        $path = 'report-attachments/'.$invoice->id.'/'.$token.'.bin';
        if (! Storage::disk('local')->put($path, $stored)) throw new RuntimeException('Could not store attachment.');
        $this->createdPaths[] = $path;

        return [
            'id' => $token,
            'file' => route('report-attachments.show', ['invoice' => $invoice->id, 'token' => $token]),
            'storage_path' => $path, 'encoding' => $compressed ? 'gzip' : 'identity',
            'mime_type' => $mime, 'extension' => $extension, 'download_name' => $downloadName,
            'size' => strlen($bytes), 'original_size' => strlen($original), 'stored_size' => strlen($stored), 'sha256' => $hash,
        ];
    }

    public function rollback(): void { $this->deleteManaged($this->createdPaths); }
    public function cleanup(): void
    {
        try { $this->deleteManaged($this->removedPaths); }
        catch (\Throwable $error) { report($error); }
    }

    private function deleteManaged(array $paths): void
    {
        foreach ($paths as $path) {
            if (preg_match('~^report-attachments/\d+/[a-f0-9-]{36}\.bin$~D', $path)) Storage::disk('local')->delete($path);
        }
    }
}
