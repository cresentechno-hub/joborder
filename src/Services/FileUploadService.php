<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Handles the quotation/PO file uploads. Used by JobOrderController
 * (Step 3) for both the 'quotation_file' and 'po_file' form fields.
 */
final class FileUploadService
{
    /**
     * Fixed allow-list of real MIME signatures per extension. A Settings-configured
     * extension not listed here is rejected by default — extending the accepted
     * types requires a code change, not just a Settings edit, so nothing unsafe
     * can be turned on from the UI alone.
     */
    private const SAFE_MIME_TYPES = [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
    ];

    /**
     * @param array $file One entry from $_FILES, e.g. $_FILES['quotation_file']
     * @param ?string $desiredFileName When given, the stored filename becomes
     *   this (sanitized) + the real extension — e.g. "INV-0456" — instead of
     *   a random hex name. Caller is responsible for uniqueness (e.g. scoping
     *   $targetDir to a per-job-order subfolder), since a fixed name can
     *   overwrite a prior file with the same name in the same folder.
     * @return array{stored_name:string, original_name:string, path:string}
     */
    public static function upload(array $file, string $targetDir, ?string $desiredFileName = null): array
    {
        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            throw new \InvalidArgumentException('No file was uploaded.');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('File upload failed (error code ' . $file['error'] . ').');
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException('Invalid upload.');
        }

        $maxSizeMb = (int) setting('upload_max_size_mb', (string) UPLOAD_MAX_SIZE_MB);
        $maxBytes = $maxSizeMb * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            throw new \RuntimeException('File exceeds the maximum size of ' . $maxSizeMb . 'MB.');
        }

        $allowedTypes = array_filter(array_map('trim', explode(',', setting('allowed_upload_types', implode(',', UPLOAD_ALLOWED_TYPES)))));
        $originalName = $file['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedTypes, true)) {
            throw new \RuntimeException('File type .' . $extension . ' is not allowed.');
        }

        // Trust the file's actual content over its extension/name — a renamed
        // .php file with a .pdf name must not pass just because of its name.
        $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $allowedMimes = self::SAFE_MIME_TYPES[$extension] ?? [];
        if ($detectedMime === false || !in_array($detectedMime, $allowedMimes, true)) {
            throw new \RuntimeException('File content does not match a valid .' . $extension . ' file.');
        }

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Could not create upload directory.');
        }

        $storedName = $desiredFileName !== null
            ? self::sanitizeFileName($desiredFileName) . '.' . $extension
            : bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new \RuntimeException('Failed to move uploaded file.');
        }

        return [
            'stored_name'   => $storedName,
            'original_name' => $originalName,
            'path'          => $destination,
        ];
    }

    /** Strips anything that isn't safe in a filename (path traversal, slashes, etc.) — this can come from user input. */
    private static function sanitizeFileName(string $name): string
    {
        $clean = preg_replace('/[^A-Za-z0-9_-]/', '_', $name) ?? '';
        $clean = trim($clean, '_');
        return $clean !== '' ? substr($clean, 0, 100) : 'file';
    }
}
