<?php

namespace App\Services;

use App\Models\About;
use App\Models\ApiShowcase;
use App\Models\Hero;
use App\Models\Project;
use App\Models\ProjectDetail;
use App\Models\Setting;
use App\Models\Skill;
use App\Models\Testimonial;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Detects uploaded files that no model row references any more, and deletes
 * them — but only when an admin explicitly picks them, one re-verified path
 * at a time. Nothing here ever deletes automatically: findOrphans() only
 * reads, and deleteFiles() re-checks every path against a freshly built
 * reference set immediately before removing it.
 *
 * The stored values are the PUBLIC URLs UploadService returns (that is what
 * the admin forms persist), while a disk listing yields bare paths such as
 * "logos/{uuid}.png". Matching therefore normalises every stored value down
 * to its disk path first — the same mapping SingletonResetService performs,
 * kept deliberately more permissive here: for orphan detection a false
 * "referenced" verdict costs nothing (a file is kept), while a false
 * "unreferenced" verdict would offer a live file for deletion.
 */
class OrphanFileService
{
    /**
     * Grace period for uploads that have not been saved into a form yet: a
     * file uploaded 30 seconds ago is invisible to findOrphans() no matter
     * what, so an in-progress admin upload can never be listed, let alone
     * deleted, by a scan that races their edit session.
     */
    private const GRACE_MINUTES = 15;

    /**
     * Every column anywhere in the app that can hold an uploaded file
     * reference. Keep this in sync when a new one is added — missing an entry
     * means a live file gets offered for deletion.
     *
     * Singleton columns of models with no file columns are omitted entirely.
     * gallery_images is a JSON array of URLs; tech_badges is a JSON array of
     * objects whose logo_url key holds the upload (over-collecting any
     * additional string it contains is always the safe direction here).
     *
     * NOTE: hero.social_links is deliberately absent — its `url` values are
     * external profile links (github.com/...), not files this app stored.
     */
    private const PATH_SOURCES = [
        Setting::class => ['favicon_path', 'logo_path'],
        Hero::class => ['image_path', 'cv_path', 'tech_badges'],
        About::class => ['image_path'],
        Project::class => ['image_path'],
        ProjectDetail::class => ['document_path', 'gallery_images'],
        Testimonial::class => ['avatar_path'],
        Skill::class => ['logo_url'],
        ApiShowcase::class => ['logo_url'],
    ];

    public function __construct(private readonly UploadService $uploads) {}

    /**
     * Every file object on the active upload disk, with path, size and last
     * modification time.
     *
     * Flysystem's listContents() is generator-based and the S3 adapter pages
     * the bucket internally (1000 keys per request), so large buckets come
     * back complete rather than truncated.
     *
     * @return array<int, array{path: string, size: int, last_modified: CarbonImmutable}>
     */
    public function listR2Files(): array
    {
        $disk = Storage::disk($this->disk());

        return collect($disk->listContents('', true))
            ->filter(fn ($attributes) => $attributes->isFile())
            ->map(fn ($attributes) => [
                'path' => $attributes->path(),
                'size' => (int) ($attributes->fileSize() ?? 0),
                'last_modified' => CarbonImmutable::createFromTimestamp(
                    $attributes->lastModified() ?? 0,
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * All file paths referenced by any model row, as a flat, de-duplicated set
     * of disk paths.
     *
     * Values that do not look like something this app stored — an admin-pasted
     * external URL, an empty string — contribute nothing rather than
     * normalising to a path that could shadow a real object.
     *
     * @return array<int, string>
     */
    public function collectReferencedPaths(): array
    {
        $paths = [];

        foreach (self::PATH_SOURCES as $modelClass => $columns) {
            $rows = $modelClass::query()->get($columns);

            foreach ($rows as $row) {
                foreach ($columns as $column) {
                    foreach ($this->extractValues($row->getAttribute($column)) as $value) {
                        $path = $this->storagePathFor($value);

                        if ($path !== null) {
                            $paths[$path] = true;
                        }
                    }
                }
            }
        }

        return array_keys($paths);
    }

    /**
     * Files on the disk that nothing references, each older than the upload
     * grace period.
     *
     * @return array{total_r2_files: int, referenced_files: int, orphan_candidates: array<int, array{path: string, size: int, last_modified: string}>}
     */
    public function findOrphans(): array
    {
        $files = $this->listR2Files();
        $referenced = collect($this->collectReferencedPaths());
        $graceCutoff = now()->subMinutes(self::GRACE_MINUTES);

        $orphans = [];

        foreach ($files as $file) {
            if ($referenced->contains($file['path'])) {
                continue;
            }

            if ($file['last_modified']->gt($graceCutoff)) {
                continue;
            }

            $orphans[] = [
                'path' => $file['path'],
                'size' => $file['size'],
                'last_modified' => $file['last_modified']->toIso8601String(),
            ];
        }

        return [
            'total_r2_files' => count($files),
            'referenced_files' => $referenced->count(),
            'orphan_candidates' => $orphans,
        ];
    }

    /**
     * Delete the given paths, re-verifying each one against a freshly built
     * reference set immediately before its deletion — the scan result the
     * admin saw can be minutes stale, and a form save in between may have
     * claimed a file.
     *
     * One failing file never aborts the batch: every path gets its own
     * verdict, and the response reports exactly what happened per file.
     *
     * @param  array<int, string>  $paths
     * @return array{deleted: array<int, string>, skipped: array<int, array{path: string, reason: string}>}
     */
    public function deleteFiles(array $paths): array
    {
        $diskName = $this->disk();
        $disk = Storage::disk($diskName);
        $folders = $this->uploadFolders();

        $deleted = [];
        $skipped = [];
        $seen = [];

        foreach ($paths as $path) {
            if (! is_string($path) || trim($path) === '') {
                continue;
            }

            $path = ltrim(trim($path), '/');

            // De-duplicate: the same path twice in one batch would report a
            // phantom "not found" skip for its second occurrence.
            if (isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;

            // Only folders UploadService writes to are addressable — the same
            // containment rule SingletonResetService applies before deleting.
            // Nothing outside them can have been produced by this app.
            $folder = strtok($path, '/');

            if (! in_array($folder, $folders, true) || str_contains($path, '..')) {
                $skipped[] = ['path' => $path, 'reason' => 'outside upload folders'];
                continue;
            }

            try {
                // Re-check against a FRESH reference set, not the one the scan
                // cached — this is the race-condition guard.
                if (in_array($path, $this->collectReferencedPaths(), true)) {
                    $skipped[] = ['path' => $path, 'reason' => 'now referenced'];
                    continue;
                }

                if (! $disk->exists($path)) {
                    $skipped[] = ['path' => $path, 'reason' => 'not found on disk'];
                    continue;
                }

                $disk->delete($path);

                $deleted[] = $path;

                Log::info('Orphan file deleted.', [
                    'disk' => $diskName,
                    'path' => $path,
                ]);
            } catch (\Throwable $e) {
                $skipped[] = ['path' => $path, 'reason' => 'delete failed: '.$e->getMessage()];

                Log::warning('Orphan file delete failed.', [
                    'disk' => $diskName,
                    'path' => $path,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return ['deleted' => $deleted, 'skipped' => $skipped];
    }

    /**
     * The disk to scan: R2 when configured, otherwise the local `public`
     * fallback — the same selection UploadService writes to, so the scan and
     * the uploads always look at the same place.
     */
    public function disk(): string
    {
        return $this->uploads->disk();
    }

    /**
     * Pull every stored string out of one attribute value.
     *
     * A single-path column yields the value itself; a JSON array column yields
     * its elements, one level deep into objects so tech_badges' nested
     * logo_url values are collected too.
     *
     * @return array<int, string>
     */
    private function extractValues(mixed $value): array
    {
        if (blank($value)) {
            return [];
        }

        if (is_string($value)) {
            return [$value];
        }

        if (is_array($value)) {
            $values = [];

            foreach ($value as $item) {
                if (is_string($item)) {
                    $values[] = $item;
                } elseif (is_array($item)) {
                    // Inside nested objects only values under path-shaped keys
                    // count (tech_badges[].logo_url). Collecting every string
                    // would sweep label text in as pseudo-paths.
                    foreach ($item as $key => $inner) {
                        if (is_string($inner) && ! blank($inner) && $this->isPathKey($key)) {
                            $values[] = $inner;
                        }
                    }
                }
            }

            return $values;
        }

        return [];
    }

    /** Does this object key plausibly name an uploaded file reference? */
    private function isPathKey(int|string $key): bool
    {
        return is_string($key)
            && ($key === 'url' || str_ends_with($key, '_path') || str_ends_with($key, '_url'));
    }

    /**
     * Map a stored value back to its path on the active disk, or null when it
     * is not something this app could have uploaded.
     *
     * Recognised forms: the R2 public URL (`{R2_URL}/{path}`), the local
     * `public` disk URL (`.../storage/{path}`), and a bare relative path.
     * Anything else — an absolute URL for some other host — returns null so
     * external links can never shadow disk objects.
     */
    private function storagePathFor(string $value): ?string
    {
        $candidate = trim($value);

        if ($candidate === '') {
            return null;
        }

        $r2Base = config('filesystems.disks.r2.url');

        if (is_string($r2Base) && $r2Base !== '') {
            $prefix = rtrim($r2Base, '/').'/';

            if (str_starts_with($candidate, $prefix)) {
                return $this->normalisePath(substr($candidate, strlen($prefix)));
            }
        }

        // Local `public` disk URLs embed /storage/ before the path.
        if (str_contains($candidate, '/storage/')) {
            return $this->normalisePath(
                substr($candidate, strrpos($candidate, '/storage/') + strlen('/storage/')),
            );
        }

        // A bare relative path (no scheme, no leading slash) — accept as-is.
        if (! str_contains($candidate, '://') && ! str_starts_with($candidate, '/')) {
            return $this->normalisePath($candidate);
        }

        return null;
    }

    private function normalisePath(string $path): ?string
    {
        $path = ltrim(urldecode(trim($path)), '/');

        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        return $path;
    }

    /** @return array<int, string> */
    private function uploadFolders(): array
    {
        return array_values(array_unique(array_map(
            fn (string $type) => UploadService::rulesFor($type)['folder'],
            UploadService::types(),
        )));
    }
}
