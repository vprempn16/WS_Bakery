<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Bakery-safe local image upload service (no Google Drive dependency).
 */
class ImageUploadService
{
    protected const MAX_FILE_SIZE = 5242880;

    protected const ALLOWED_MIME_TYPES = [
        'image/png',
        'image/jpeg',
        'image/jpg',
        'image/gif',
        'image/webp',
    ];

    protected const DISK = 'public';
    protected const DIRECTORY = 'uploads/images';

    /**
     * @return array{path: string, url: string|null, meta: array}
     */
    public function processAndSave($imageData, ?string $recordId = null, ?string $oldImagePath = null): array
    {
        $base64String = $this->extractBase64String($imageData);

        if (empty($base64String)) {
            throw ValidationException::withMessages(['image' => ['Invalid image data provided']]);
        }

        $decoded = $this->decodeBase64Image($base64String);
        $filename = $this->generateFilename($recordId, $decoded['extension']);
        $relativePath = self::DIRECTORY . '/' . $filename;

        if ($oldImagePath) {
            $this->deleteImage($oldImagePath);
        }

        Storage::disk(self::DISK)->put($relativePath, $decoded['binary']);

        $publicUrl = $this->transformToUrl($relativePath);

        Log::info('Image uploaded to local storage', [
            'path' => $relativePath,
            'size' => $decoded['size'],
            'mime' => $decoded['mimeType'],
        ]);

        return [
            'path' => $relativePath,
            'url' => $publicUrl,
            'meta' => [
                'fileName' => $decoded['originalFileName'] ?? $filename,
                'mimeType' => $decoded['mimeType'],
                'size' => $decoded['size'],
                'extension' => $decoded['extension'],
                'uploadedAt' => now()->toIso8601String(),
            ],
        ];
    }

    protected function extractBase64String($imageData): ?string
    {
        if (is_array($imageData)) {
            $value = $imageData['value'] ?? null;
            if (is_string($value)) {
                return $value;
            }
        }

        if (is_string($imageData)) {
            return $imageData;
        }

        return null;
    }

    protected function decodeBase64Image(string $base64String): array
    {
        $matches = [];
        if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $base64String, $matches)) {
            $extension = strtolower($matches[1]);
            $data = $matches[2];
        } else {
            $data = $base64String;
            $extension = 'png';
        }

        $binary = base64_decode($data, true);

        if ($binary === false) {
            throw ValidationException::withMessages(['image' => ['Invalid base64 image data']]);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($binary);

        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw ValidationException::withMessages(['image' => [
                'Invalid image format. Allowed: png, jpg, jpeg, gif, webp',
            ]]);
        }

        $size = strlen($binary);
        if ($size > self::MAX_FILE_SIZE) {
            $maxMB = self::MAX_FILE_SIZE / 1024 / 1024;
            throw ValidationException::withMessages(['image' => [
                "Image size exceeds maximum allowed size of {$maxMB}MB",
            ]]);
        }

        $mimeToExt = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        $extension = $mimeToExt[$mimeType] ?? $extension;

        return [
            'binary' => $binary,
            'mimeType' => $mimeType,
            'extension' => $extension,
            'size' => $size,
            'originalFileName' => null,
        ];
    }

    protected function generateFilename(?string $recordId, string $extension): string
    {
        if ($recordId) {
            $cleanId = str_replace('-', '', $recordId);

            return $cleanId . '_' . time() . '.' . $extension;
        }

        return str_replace('-', '', (string) Str::uuid()) . '.' . $extension;
    }

    public function deleteImage(string $path): bool
    {
        try {
            $relative = $this->normalizePath($path);
            if ($relative && Storage::disk(self::DISK)->exists($relative)) {
                Storage::disk(self::DISK)->delete($relative);
                Log::info('Image deleted from local storage', ['path' => $relative]);

                return true;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to delete local image', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    public function isBase64ImageData($value): bool
    {
        if (is_array($value) && isset($value['type']) && $value['type'] === 'image') {
            $inner = $value['value'] ?? null;

            return is_string($inner) && str_starts_with($inner, 'data:image/');
        }

        if (is_string($value) && str_starts_with($value, 'data:image/')) {
            return true;
        }

        return false;
    }

    public function transformToUrl(?string $storedPath): ?string
    {
        if (empty($storedPath)) {
            return null;
        }

        if (str_starts_with($storedPath, 'http://') || str_starts_with($storedPath, 'https://')) {
            return $storedPath;
        }

        $relative = $this->normalizePath($storedPath);

        return $relative ? Storage::disk(self::DISK)->url($relative) : null;
    }

    protected function normalizePath(string $path): string
    {
        $path = trim($path);
        if (str_starts_with($path, '/storage/')) {
            return ltrim(substr($path, strlen('/storage/')), '/');
        }

        return ltrim($path, '/');
    }
}
