<?php

namespace Tests\Unit;

use App\Services\UploadService;
use PHPUnit\Framework\TestCase;

/**
 * UploadService::rulesFor / ::types — the per-type MIME allowlist, size ceiling
 * and destination folder.
 *
 * Extends PHPUnit's TestCase directly, not the Laravel one: none of this touches
 * the container, the database or the filesystem, so booting the framework would
 * only make it slower. `store()` and `disk()` are deliberately not covered here —
 * both read config or write to a disk, which is integration territory.
 *
 * These values are a contract with two other places: UploadRequest builds its
 * `mimetypes:` and `max:` validation strings from them, and
 * SingletonResetService::uploadFolders() derives the set of folders a reset is
 * allowed to delete inside from the `folder` keys. A folder renamed here without
 * a migration of stored URLs would silently make reset stop deleting files.
 */
class UploadServiceRulesTest extends TestCase
{
    public function test_every_declared_type_has_rules(): void
    {
        foreach (UploadService::types() as $type) {
            $rules = UploadService::rulesFor($type);

            $this->assertNotEmpty($rules['mimetypes'], "{$type} has no allowed MIME types");
            $this->assertGreaterThan(0, $rules['max_kb'], "{$type} has no size ceiling");
            $this->assertNotSame('', $rules['folder'], "{$type} has no destination folder");
        }
    }

    public function test_types_are_unique_and_include_the_generic_fallback(): void
    {
        $types = UploadService::types();

        $this->assertSame($types, array_values(array_unique($types)));
        $this->assertContains(UploadService::TYPE_GENERIC, $types);
    }

    public function test_an_unknown_type_falls_back_to_the_generic_rules(): void
    {
        // FileUpload.jsx sends no type at all, so this is the common path rather
        // than an error case.
        $this->assertSame(
            UploadService::rulesFor(UploadService::TYPE_GENERIC),
            UploadService::rulesFor('not-a-real-type'),
        );
    }

    public function test_the_cv_type_accepts_pdf_only(): void
    {
        $rules = UploadService::rulesFor(UploadService::TYPE_CV);

        $this->assertSame(['application/pdf'], $rules['mimetypes']);
        $this->assertSame('cv', $rules['folder']);
    }

    public function test_the_favicon_type_accepts_ico_but_not_arbitrary_images(): void
    {
        $mimes = UploadService::rulesFor(UploadService::TYPE_FAVICON)['mimetypes'];

        $this->assertContains('image/x-icon', $mimes);
        $this->assertContains('image/vnd.microsoft.icon', $mimes);
        // A JPEG favicon is not useful and browsers vary on it, so it is excluded
        // even though every other image type accepts JPEG.
        $this->assertNotContains('image/jpeg', $mimes);
    }

    public function test_the_technology_logo_type_uses_the_shared_image_limits_and_folder(): void
    {
        $rules = UploadService::rulesFor(UploadService::TYPE_TECHNOLOGY_LOGO);

        $this->assertContains('image/svg+xml', $rules['mimetypes']);
        $this->assertContains('image/png', $rules['mimetypes']);
        $this->assertSame(5120, $rules['max_kb']);
        $this->assertSame('technology-logos', $rules['folder']);
    }

    public function test_image_types_never_accept_a_pdf(): void
    {
        foreach ([
            UploadService::TYPE_FAVICON,
            UploadService::TYPE_LOGO,
            UploadService::TYPE_HERO_IMAGE,
            UploadService::TYPE_ABOUT_IMAGE,
            UploadService::TYPE_PROJECT_IMAGE,
            UploadService::TYPE_AVATAR,
        ] as $type) {
            $this->assertNotContains(
                'application/pdf',
                UploadService::rulesFor($type)['mimetypes'],
                "{$type} must not accept a PDF",
            );
        }
    }

    public function test_the_generic_type_accepts_both_images_and_pdf(): void
    {
        $mimes = UploadService::rulesFor(UploadService::TYPE_GENERIC)['mimetypes'];

        $this->assertContains('image/png', $mimes);
        $this->assertContains('application/pdf', $mimes);
    }

    public function test_no_type_exceeds_the_generic_size_ceiling(): void
    {
        // The generic bucket is documented as "the largest of the above limits";
        // a per-type limit above it would make that comment false and would let a
        // typed upload through a check the untyped path would reject.
        $ceiling = UploadService::rulesFor(UploadService::TYPE_GENERIC)['max_kb'];

        foreach (UploadService::types() as $type) {
            $this->assertLessThanOrEqual(
                $ceiling,
                UploadService::rulesFor($type)['max_kb'],
                "{$type} allows a larger file than the generic fallback",
            );
        }
    }

    public function test_each_folder_is_a_plain_single_segment_name(): void
    {
        // SingletonResetService matches the FIRST path segment of a stored URL
        // against these values, so a folder containing a slash or a traversal
        // sequence would break deletion and widen what a reset can address.
        foreach (UploadService::types() as $type) {
            $folder = UploadService::rulesFor($type)['folder'];

            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $folder, "folder for {$type}");
        }
    }
}
