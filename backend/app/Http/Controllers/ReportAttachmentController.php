<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;

class ReportAttachmentController extends Controller
{
    public function show(int $invoice, string $token)
    {
        // Like the previous random public-storage links, possession of this
        // unguessable attachment URL permits viewing it from a shared report.
        $record = Invoice::findOrFail($invoice);
        $attachment = collect($record->attachments ?? [])->firstWhere('id', $token);
        abort_unless($attachment, 404);
        $path = 'report-attachments/'.$invoice.'/'.$token.'.bin';
        abort_unless(($attachment['storage_path'] ?? '') === $path && Storage::disk('local')->exists($path), 404);
        $stored = Storage::disk('local')->get($path);
        $bytes = ($attachment['encoding'] ?? '') === 'gzip' ? gzdecode($stored, 10 * 1024 * 1024) : $stored;
        abort_if($bytes === false, 500);
        $mime = $attachment['mime_type'] ?? 'application/octet-stream';
        $inline = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);
        $name = str_replace(["\r", "\n", '/', '\\', '%'], '_', $attachment['download_name'] ?? 'attachment');
        $disposition = HeaderUtils::makeDisposition($inline ? 'inline' : 'attachment', $name, 'attachment.'.preg_replace('/[^a-z0-9]/', '', $attachment['extension'] ?? 'bin'));

        return response($bytes, 200, [
            'Content-Type' => $inline ? $mime : 'application/octet-stream',
            'Content-Disposition' => $disposition,
            'Content-Length' => strlen($bytes),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
