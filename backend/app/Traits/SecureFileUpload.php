<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Trait for secure file upload handling
 *
 * SECURITY: This trait provides secure file upload functionality including:
 * - Filename sanitization to prevent directory traversal
 * - Random filename generation to prevent enumeration
 * - File type validation (extension and MIME type)
 * - File size validation
 */
trait SecureFileUpload
{
    /**
     * Allowed image MIME types
     */
    protected array $allowedImageMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /**
     * Allowed image extensions
     */
    protected array $allowedImageExtensions = [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
    ];

    /**
     * Maximum file size in bytes (5MB)
     */
    protected int $maxFileSize = 5242880;

    /**
     * Securely upload an image file
     *
     * @param UploadedFile $file The uploaded file
     * @param string $directory The storage directory
     * @param string $disk The storage disk (default: public)
     * @return array{success: bool, path?: string, url?: string, error?: string}
     */
    protected function secureUploadImage(UploadedFile $file, string $directory, string $disk = 'public'): array
    {
        // Validate file size
        if ($file->getSize() > $this->maxFileSize) {
            return [
                'success' => false,
                'error' => 'File size exceeds maximum allowed size of 5MB',
            ];
        }

        // Validate MIME type
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, $this->allowedImageMimeTypes, true)) {
            return [
                'success' => false,
                'error' => 'Invalid file type. Allowed types: JPEG, PNG, GIF, WebP',
            ];
        }

        // Validate extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $this->allowedImageExtensions, true)) {
            return [
                'success' => false,
                'error' => 'Invalid file extension. Allowed: jpg, jpeg, png, gif, webp',
            ];
        }

        // Generate a secure random filename
        $secureFilename = $this->generateSecureFilename($extension);

        // Store the file
        $path = $file->storeAs($directory, $secureFilename, $disk);

        if (!$path) {
            return [
                'success' => false,
                'error' => 'Failed to upload file',
            ];
        }

        return [
            'success' => true,
            'path' => $path,
            'url' => config('app.url') . Storage::url($path),
        ];
    }

    /**
     * Generate a secure random filename
     *
     * @param string $extension The file extension
     * @return string
     */
    protected function generateSecureFilename(string $extension): string
    {
        return Str::uuid()->toString() . '_' . time() . '.' . $extension;
    }

    /**
     * Safely delete an old file.
     * Accepts either a relative storage path (e.g. "logos/abc.png") or a full URL
     * (e.g. "https://digitals-labs.com/storage/logos/abc.png").
     */
    protected function safeDeleteFile(?string $fileUrlOrPath): bool
    {
        if (empty($fileUrlOrPath)) {
            return false;
        }

        // Extract relative storage path
        if (str_contains($fileUrlOrPath, 'storage/')) {
            $parts = explode('storage/', $fileUrlOrPath, 2);
            $relative = $parts[1] ?? null;
        } else {
            // Already a relative path (e.g. "logos/abc.png")
            $relative = ltrim($fileUrlOrPath, '/');
        }

        if (! $relative) {
            return false;
        }

        if (Storage::disk('public')->exists($relative)) {
            return Storage::disk('public')->delete($relative);
        }

        return false;
    }

    /**
     * Sanitize a filename to prevent directory traversal
     * Note: This is kept for reference but generateSecureFilename should be preferred
     *
     * @param string $filename The original filename
     * @return string
     */
    protected function sanitizeFilename(string $filename): string
    {
        // Remove any directory components
        $filename = basename($filename);

        // Remove potentially dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // Prevent empty filenames
        if (empty($filename) || $filename === '.' || $filename === '..') {
            $filename = 'file_' . time();
        }

        return $filename;
    }
}
