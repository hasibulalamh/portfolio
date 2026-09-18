<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadService
{
    public const TYPE_FAVICON = 'favicon';
    public const TYPE_LOGO = 'logo';
    public const TYPE_HERO_IMAGE = 'hero-image';
    public const TYPE_ABOUT_IMAGE = 'about-image';
    public const TYPE_PROJECT_IMAGE = 'project-image';
    public const TYPE_TECHNOLOGY_LOGO = 'technology-logo';
    public const TYPE_AVATAR = 'avatar';
    public const TYPE_CV = 'cv';
    public const TYPE_GENERIC = 'generic';

    private const IMAGE_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/svg+xml',
    ];

    private const DOC_MIMES = [
        'application/pdf',
    ];

    /** @return array<int, string> */
    public static function types(): array
    {
        return [
            self::TYPE_FAVICON,
            self::TYPE_LOGO,
            self::TYPE_HERO_IMAGE,
            self::TYPE_ABOUT_IMAGE,
            self::TYPE_PROJECT_IMAGE,
            self::TYPE_TECHNOLOGY_LOGO,
            self::TYPE_AVATAR,
            self::TYPE_CV,
            self::TYPE_GENERIC,
        ];
    }

    /**
     * Allowed MIME types and size ceiling per upload type. Limits match the
     * per-field maxSize values the admin panel passes to FileUpload.
     *
     * @return array{mimetypes: array<int, string>, max_kb: int, folder: string}
     */
    public static function rulesFor(string $type): array
    {
        return match ($type) {
            self::TYPE_FAVICON => [
                'mimetypes' => ['image/png', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/svg+xml'],
                'max_kb' => 1024,
                'folder' => 'favicons',
            ],
            self::TYPE_LOGO => [
                'mimetypes' => self::IMAGE_MIMES,
                'max_kb' => 5120,
                'folder' => 'logos',
            ],
            self::TYPE_HERO_IMAGE => [
                'mimetypes' => self::IMAGE_MIMES,
                'max_kb' => 5120,
                'folder' => 'hero',
            ],
            self::TYPE_ABOUT_IMAGE => [
                'mimetypes' => self::IMAGE_MIMES,
                'max_kb' => 5120,
                'folder' => 'about',
            ],
            self::TYPE_PROJECT_IMAGE => [
                'mimetypes' => self::IMAGE_MIMES,
                'max_kb' => 5120,
                'folder' => 'projects',
            ],
            self::TYPE_TECHNOLOGY_LOGO => [
                'mimetypes' => self::IMAGE_MIMES,
                'max_kb' => 5120,
                'folder' => 'technology-logos',
            ],
            self::TYPE_AVATAR => [
                'mimetypes' => self::IMAGE_MIMES,
                'max_kb' => 2048,
                'folder' => 'avatars',
            ],
            self::TYPE_CV => [
                'mimetypes' => self::DOC_MIMES,
                'max_kb' => 10240,
                'folder' => 'cv',
            ],
            // Generic callers may omit the type, so accept any supported image
            // or PDF at the largest of the above limits.
            default => [
                'mimetypes' => [...self::IMAGE_MIMES, ...self::DOC_MIMES],
                'max_kb' => 10240,
                'folder' => 'uploads',
            ],
        };
    }

    /**
     * Store a validated upload and return its publicly reachable URL.
     *
     * @return array{url: string, path: string, disk: string}
     */
    public function store(UploadedFile $file, string $type): array
    {
        $folder = self::rulesFor($type)['folder'];
        $disk = $this->disk();

        // Never reuse the client-supplied filename: it is attacker-controlled
        // and could contain path separators or a misleading extension.
        $filename = Str::uuid()->toString().'.'.$this->extensionFor($file);

        $path = Storage::disk($disk)->putFileAs($folder, $file, $filename, 'public');

        if ($path === false) {
            throw new \RuntimeException('Failed to write the uploaded file to storage.');
        }

        $url = $this->urlFor($disk, $path);

        Log::info('Media upload stored.', [
            'type' => $type,
            'disk' => $disk,
            'path' => $path,
            'url' => $url,
            'object_exists' => Storage::disk($disk)->exists($path),
        ]);

        return [
            'url' => $url,
            'path' => $path,
            'disk' => $disk,
        ];
    }

    /**
     * R2 when it is configured, otherwise the local `public` disk so uploads
     * keep working in development before credentials are supplied.
     */
    public function disk(): string
    {
        $configured = config('filesystems.disks.r2.key')
            && config('filesystems.disks.r2.bucket')
            && config('filesystems.disks.r2.endpoint');

        return $configured ? 'r2' : 'public';
    }

    private function urlFor(string $disk, string $path): string
    {
        // R2's public hostname must be explicit. Falling back to the local
        // disk URL here would persist a localhost URL in production.
        if ($disk === 'r2') {
            $base = config('filesystems.disks.r2.url');

            if (! is_string($base) || trim($base) === '') {
                throw new \RuntimeException('R2_URL must be configured for public media URLs.');
            }

            return rtrim($base, '/').'/'.ltrim($path, '/');
        }

        return Storage::disk($disk)->url($path);
    }

    /** Extension derived from the verified MIME type, not the client filename. */
    private function extensionFor(UploadedFile $file): string
    {
        return match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            'image/x-icon', 'image/vnd.microsoft.icon' => 'ico',
            'application/pdf' => 'pdf',
            default => $file->extension() ?: 'bin',
        };
    }
}
