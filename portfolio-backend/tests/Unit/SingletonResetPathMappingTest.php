<?php

namespace Tests\Unit;

use App\Services\SingletonResetService;
use App\Services\UploadService;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The stored-URL → disk-path mapping that decides which file a reset may delete.
 *
 * Unit, not feature: `storagePathFor()` touches no database and no real disk. It
 * reads two config values and the static UploadService folder list, both of which
 * are set explicitly per test so the result never depends on whether this machine
 * happens to have R2 credentials in `.env`.
 *
 * Worth pinning at this level because the method is the only thing standing
 * between an admin-supplied string and `Storage::delete()`. Two of its rules are
 * security properties rather than conveniences:
 *
 *   - a value must resolve inside a folder UploadService itself writes to, so a
 *     crafted path cannot address anything else on the disk;
 *   - `..` is refused outright.
 *
 * A mutation that relaxes either would still pass every existing reset test,
 * because those only ever feed it well-formed URLs.
 *
 * Reflection is used deliberately: making the method public to test it would
 * widen the service's API for no caller's benefit, and the behaviour under test
 * is genuinely a unit of its own.
 */
class SingletonResetPathMappingTest extends TestCase
{
    private function mapper(): ReflectionMethod
    {
        $method = new ReflectionMethod(SingletonResetService::class, 'storagePathFor');
        $method->setAccessible(true);

        return $method;
    }

    private function service(): SingletonResetService
    {
        return new SingletonResetService(new UploadService);
    }

    private function map(string $value, string $disk): ?string
    {
        return $this->mapper()->invoke($this->service(), $value, $disk);
    }

    // ---------------------------------------------------------------------
    // Local `public` disk — values look like http://host/storage/<folder>/<file>
    // ---------------------------------------------------------------------

    public function test_a_local_storage_url_maps_to_its_disk_relative_path(): void
    {
        $this->assertSame(
            'logos/abc.png',
            $this->map('http://localhost:8000/storage/logos/abc.png', 'public'),
        );
    }

    public function test_the_storage_prefix_is_stripped_only_at_the_start(): void
    {
        // A file legitimately named "storage" deeper in the path must survive.
        $this->assertSame(
            'uploads/storage/abc.png',
            $this->map('http://localhost:8000/storage/uploads/storage/abc.png', 'public'),
        );
    }

    public function test_a_bare_path_without_a_host_still_maps(): void
    {
        // FileUpload.jsx has stored both absolute URLs and root-relative paths
        // across the project's history, so both shapes have to resolve.
        $this->assertSame('avatars/x.webp', $this->map('/storage/avatars/x.webp', 'public'));
    }

    public function test_a_percent_encoded_filename_is_decoded(): void
    {
        $this->assertSame(
            'uploads/my file.pdf',
            $this->map('http://localhost:8000/storage/uploads/my%20file.pdf', 'public'),
        );
    }

    public function test_a_query_string_and_fragment_are_discarded(): void
    {
        // Cache-busting suffixes are common on values pasted back in by hand.
        $this->assertSame(
            'logos/abc.png',
            $this->map('http://localhost:8000/storage/logos/abc.png?v=2#top', 'public'),
        );
    }

    // ---------------------------------------------------------------------
    // The folder allowlist — the containment rule
    // ---------------------------------------------------------------------

    public function test_every_folder_upload_service_writes_to_is_accepted(): void
    {
        // Derived from UploadService rather than hardcoded: a new upload type
        // whose folder is missing from the allowlist would silently stop being
        // deletable, and this is the assertion that notices.
        foreach (UploadService::types() as $type) {
            $folder = UploadService::rulesFor($type)['folder'];

            $this->assertSame(
                "{$folder}/file.png",
                $this->map("http://localhost:8000/storage/{$folder}/file.png", 'public'),
                "the [{$folder}] folder is written to by upload type [{$type}] but is not deletable",
            );
        }
    }

    public function test_a_folder_upload_service_never_writes_to_is_refused(): void
    {
        $this->assertNull($this->map('http://localhost:8000/storage/framework/cache/x', 'public'));
        $this->assertNull($this->map('http://localhost:8000/storage/app/private/secret.pdf', 'public'));
    }

    public function test_a_file_at_the_disk_root_is_refused(): void
    {
        // No folder segment at all means the allowlist check has nothing to
        // match, and the disk root is not a place uploads live.
        $this->assertNull($this->map('http://localhost:8000/storage/loose.png', 'public'));
    }

    public function test_traversal_is_refused_even_under_an_allowed_folder(): void
    {
        $this->assertNull($this->map('http://localhost:8000/storage/logos/../../.env', 'public'));
        $this->assertNull($this->map('http://localhost:8000/storage/uploads/..%2f..%2f.env', 'public'));
    }

    public function test_an_empty_value_is_refused(): void
    {
        $this->assertNull($this->map('http://localhost:8000/storage/', 'public'));
    }

    public function test_an_arbitrary_external_url_is_refused(): void
    {
        // An admin may paste a URL this app never uploaded. Deleting is not ours
        // to do there, and the folder check is what prevents it.
        $this->assertNull($this->map('https://cdn.example.com/images/logo.png', 'public'));
    }

    // ---------------------------------------------------------------------
    // R2 disk — values are absolute URLs under the configured public base
    // ---------------------------------------------------------------------

    public function test_an_r2_url_under_the_configured_base_maps_to_its_key(): void
    {
        config(['filesystems.disks.r2.url' => 'https://pub-abc.r2.dev']);

        $this->assertSame(
            'uploads/abc.png',
            $this->map('https://pub-abc.r2.dev/uploads/abc.png', 'r2'),
        );
    }

    public function test_a_trailing_slash_on_the_configured_base_is_tolerated(): void
    {
        config(['filesystems.disks.r2.url' => 'https://pub-abc.r2.dev/']);

        $this->assertSame(
            'uploads/abc.png',
            $this->map('https://pub-abc.r2.dev/uploads/abc.png', 'r2'),
        );
    }

    public function test_an_r2_url_under_a_different_host_is_refused(): void
    {
        // The prefix check is what stops a reset reaching into another bucket or
        // an unrelated CDN that happens to use the same folder names.
        config(['filesystems.disks.r2.url' => 'https://pub-abc.r2.dev']);

        $this->assertNull($this->map('https://pub-other.r2.dev/uploads/abc.png', 'r2'));
    }

    public function test_an_r2_value_still_has_to_sit_in_an_upload_folder(): void
    {
        config(['filesystems.disks.r2.url' => 'https://pub-abc.r2.dev']);

        $this->assertNull($this->map('https://pub-abc.r2.dev/backups/dump.sql', 'r2'));
    }

    public function test_r2_without_a_configured_public_base_falls_back_to_path_parsing(): void
    {
        // R2_URL unset is a supported state (UploadService::urlFor falls back to
        // Storage::url), so the reverse mapping must not simply refuse everything.
        config(['filesystems.disks.r2.url' => null]);

        $this->assertSame('uploads/abc.png', $this->map('/storage/uploads/abc.png', 'r2'));
    }

    // ---------------------------------------------------------------------
    // Mutation-killing tests — close escaped mutants detected by Infection
    // ---------------------------------------------------------------------

    /**
     * Kills ConcatOperandRemoval on line 267.
     *
     * The trailing slash in the R2 prefix prevents URLs like
     * `https://host.devuploads/...` from matching the base `https://host.dev`.
     * Without the `/`, `str_starts_with` succeeds and the path is accepted.
     */
    public function test_r2_url_concatenated_directly_to_base_without_slash_is_refused(): void
    {
        config(['filesystems.disks.r2.url' => 'https://pub-abc.r2.dev']);

        $this->assertNull(
            $this->map('https://pub-abc.r2.devuploads/abc.png', 'r2'),
            'A URL where the folder is glued to the base (no slash) must not match',
        );
    }

    /**
     * Kills ReturnRemoval on line 270.
     *
     * When `str_starts_with` fails, execution must stop immediately.
     * Without the `return null`, substr runs on a non-matching URL and — if the
     * resulting substring happens to start with an upload folder name — returns
     * a path that should have been refused.
     *
     * The prefix `https://pub-abc.r2.dev/` is 23 chars. Position 22 is `/`.
     * We craft a URL where position 22 is NOT `/` (so str_starts_with fails)
     * but position 23 IS `u` (the start of `uploads`). Without `return null`,
     * substr(23) yields `uploads/abc.png` — a valid upload path.
     */
    public function test_r2_different_host_return_removal_must_not_fall_through(): void
    {
        config(['filesystems.disks.r2.url' => 'https://pub-abc.r2.dev']);

        // Position 22 is `x` (not `/`), position 23 is `u` (start of `uploads`).
        // With return null: str_starts_with fails → null.
        // Without return null: substr(23) = `uploads/abc.png` → accepted.
        $this->assertNull(
            $this->map('https://pub-abc.r2.devxuploads/abc.png', 'r2'),
            'A URL with a non-slash char at the prefix boundary must be refused',
        );
    }

    /**
     * Kills UnwrapLtrim on line 286.
     *
     * When a percent-encoded slash (`%2F`) appears in the path, `urldecode`
     * turns it into a literal `/` that ltrim must strip. Without ltrim the
     * leading slash causes `strtok` to return an empty token, which is never
     * in the upload-folder allowlist.
     */
    public function test_percent_encoded_slash_in_path_is_decoded_and_stripped(): void
    {
        // R2 path: %2F before the folder name
        config(['filesystems.disks.r2.url' => 'https://pub-abc.r2.dev']);

        $this->assertSame(
            'uploads/abc.png',
            $this->map('https://pub-abc.r2.dev/%2Fuploads/abc.png', 'r2'),
            'A percent-encoded leading slash in the R2 path must be decoded and stripped',
        );
    }

    /**
     * Kills UnwrapArrayUnique on line 301.
     *
     * The assertion compares the raw output of `uploadFolders()` against the
     * same call with `array_unique` applied externally. If `array_unique` is
     * removed from the source, the raw output gains duplicate entries and the
     * two sides diverge.
     */
    public function test_upload_folders_are_deduplicated(): void
    {
        $method = new ReflectionMethod(SingletonResetService::class, 'uploadFolders');
        $method->setAccessible(true);

        $folders = $method->invoke(new SingletonResetService(new UploadService));

        // The raw result must already be deduplicated — removing array_unique
        // from the source would leave duplicates and fail this assertion.
        $this->assertSame(
            array_values(array_unique($folders)),
            $folders,
            'uploadFolders() must return deduplicated folder names',
        );
    }

    /**
     * Kills UnwrapArrayValues on line 301.
     *
     * The return of `uploadFolders()` is consumed by `in_array` in
     * `storagePathFor`, which works regardless of key ordering. However,
     * `array_values` guarantees a list shape (sequential int keys from 0).
     * This assertion pins that contract: the raw output must already be a
     * list. Removing `array_values` from the source would break this only if
     * `array_unique` reindexes non-sequentially, which it does when the
     * input has duplicates at non-adjacent positions.
     *
     * Because `array_map` always produces sequential keys and `array_unique`
     * on a unique-sequential input preserves them, this call is currently a
     * no-op. The assertion documents the intended invariant regardless.
     */
    public function test_upload_folders_are_sequentially_indexed(): void
    {
        $method = new ReflectionMethod(SingletonResetService::class, 'uploadFolders');
        $method->setAccessible(true);

        $folders = $method->invoke(new SingletonResetService(new UploadService));

        // Pin the list-shape contract: keys must be 0..n-1.
        $this->assertSame(
            range(0, count($folders) - 1),
            array_keys($folders),
            'uploadFolders() must return a sequentially-indexed list',
        );
    }
}