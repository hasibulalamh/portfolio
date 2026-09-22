<?php

namespace Tests\Feature;

use App\Models\About;
use App\Models\Hero;
use App\Models\Project;
use App\Models\ProjectDetail;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The orphan-file pair end to end: the scan (GET) and the explicit delete
 * (DELETE).
 *
 * Both run against Storage::fake('r2') — the local .env has real R2
 * credentials, so without the fake these tests would list and delete real
 * Cloudflare objects. The fake also makes the grace period trivial to
 * exercise by touching file mtimes directly.
 *
 * Stored reference values are built with r2Url() because the normaliser
 * matches against the configured R2 public base, whatever it is.
 */
class OrphanFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('r2');
        Storage::fake('public');
    }

    private function actAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    private function r2Url(string $path): string
    {
        return rtrim(config('filesystems.disks.r2.url'), '/').'/'.$path;
    }

    /**
     * Seed a file on the fake R2 disk, optionally back-dating its mtime —
     * Storage::fake stores the r2 disk in a local directory, so the timestamp
     * the scan will read as last_modified can be set with touch().
     */
    private function seedFile(string $path, ?Carbon $modifiedAt = null): void
    {
        Storage::disk('r2')->put($path, 'fake content');

        if ($modifiedAt !== null) {
            $real = storage_path('framework/testing/disks/r2/'.$path);

            if (is_file($real)) {
                touch($real, $modifiedAt->getTimestamp());
            }
        }
    }

    /**
     * A skill whose logo is an upload on the disk, exercising the single-path
     * column source.
     */
    private function seedSkillWithLogo(string $path): Skill
    {
        $category = SkillCategory::query()->create(['name' => 'Category']);

        return Skill::query()->create([
            'skill_category_id' => $category->getKey(),
            'name' => 'Skill',
            'logo_url' => $this->r2Url($path),
        ]);
    }

    public function test_scan_requires_authentication(): void
    {
        $this->getJson('/api/admin/storage/orphans')->assertStatus(401);
    }

    public function test_delete_requires_authentication(): void
    {
        $this->deleteJson('/api/admin/storage/orphans', ['paths' => ['logos/x.png']])
            ->assertStatus(401);
    }

    public function test_scan_returns_the_documented_shape(): void
    {
        $this->actAsAdmin();

        // Back-dated past the grace period so it appears as a candidate.
        $this->seedFile('logos/orphan.png', now()->subHour());

        $response = $this->getJson('/api/admin/storage/orphans');

        $response->assertOk();
        $response->assertJsonPath('data.total_r2_files', 1);
        $response->assertJsonPath('data.referenced_files', 0);
        $response->assertJsonCount(1, 'data.orphan_candidates');
        $response->assertJsonPath('data.orphan_candidates.0.path', 'logos/orphan.png');
        $response->assertJsonStructure([
            'data' => [
                'total_r2_files',
                'referenced_files',
                'orphan_candidates' => [
                    '*' => ['path', 'size', 'last_modified'],
                ],
            ],
        ]);
    }

    public function test_a_file_referenced_by_a_model_is_excluded_from_orphans(): void
    {
        $this->actAsAdmin();

        $this->seedSkillWithLogo('logos/live.png');
        $this->seedFile('logos/live.png', now()->subDay());

        $response = $this->getJson('/api/admin/storage/orphans');

        $response->assertOk();
        $this->assertNotContains(
            'logos/live.png',
            collect($response->json('data.orphan_candidates'))->pluck('path')->all(),
        );
        $response->assertJsonPath('data.referenced_files', 1);
    }

    public function test_a_genuinely_unreferenced_file_is_included_in_orphans(): void
    {
        $this->actAsAdmin();

        $this->seedFile('logos/orphan.png', now()->subDays(3));

        $response = $this->getJson('/api/admin/storage/orphans');

        $response->assertOk();
        $this->assertContains(
            'logos/orphan.png',
            collect($response->json('data.orphan_candidates'))->pluck('path')->all(),
        );
    }

    public function test_a_recently_uploaded_file_is_excluded_by_the_grace_period(): void
    {
        $this->actAsAdmin();

        // Written seconds ago and referenced by nothing — still excluded.
        $this->seedFile('logos/fresh.png');

        $response = $this->getJson('/api/admin/storage/orphans');

        $response->assertOk();
        $this->assertNotContains(
            'logos/fresh.png',
            collect($response->json('data.orphan_candidates'))->pluck('path')->all(),
        );
    }

    public function test_json_array_and_nested_array_columns_are_collected(): void
    {
        $this->actAsAdmin();

        $project = Project::query()->create([
            'title' => 'P',
            'slug' => 'p',
            'is_featured' => false,
            'order' => 0,
        ]);
        ProjectDetail::query()->create([
            'project_id' => $project->getKey(),
            'gallery_images' => [
                $this->r2Url('projects/a.png'),
                $this->r2Url('projects/b.png'),
            ],
        ]);
        Hero::query()->create([
            'tech_badges' => [
                ['label' => 'React', 'icon_slug' => 'react', 'logo_url' => $this->r2Url('hero-badges/react.png')],
                ['label' => 'Vue'],
            ],
        ]);

        foreach (['projects/a.png', 'projects/b.png', 'hero-badges/react.png'] as $path) {
            $this->seedFile($path, now()->subDay());
        }

        $response = $this->getJson('/api/admin/storage/orphans');

        $paths = collect($response->json('data.orphan_candidates'))->pluck('path')->all();

        $this->assertNotContains('projects/a.png', $paths);
        $this->assertNotContains('projects/b.png', $paths);
        $this->assertNotContains('hero-badges/react.png', $paths);
        $response->assertJsonPath('data.referenced_files', 3);
    }

    public function test_delete_reports_skipped_for_a_path_that_became_referenced(): void
    {
        $this->actAsAdmin();

        $this->seedFile('logos/claimed.png');

        // Simulate the race the guard exists for: the admin's scan said
        // orphan, but a form save landed before the delete call. The fresh
        // collectReferencedPaths() inside deleteFiles() must protect the file.
        $this->seedSkillWithLogo('logos/claimed.png');

        $response = $this->deleteJson('/api/admin/storage/orphans', ['paths' => ['logos/claimed.png']]);

        $response->assertOk();
        $response->assertJsonCount(0, 'data.deleted');
        $response->assertJsonPath('data.skipped.0.path', 'logos/claimed.png');
        $response->assertJsonPath('data.skipped.0.reason', 'now referenced');
        Storage::disk('r2')->assertExists('logos/claimed.png');
    }

    public function test_delete_removes_a_genuinely_orphaned_path(): void
    {
        $this->actAsAdmin();

        $this->seedFile('logos/gone.png');

        $response = $this->deleteJson('/api/admin/storage/orphans', ['paths' => ['logos/gone.png']]);

        $response->assertOk();
        $response->assertJsonPath('data.deleted.0', 'logos/gone.png');
        $response->assertJsonCount(0, 'data.skipped');
        Storage::disk('r2')->assertMissing('logos/gone.png');
    }

    public function test_delete_never_throws_on_an_individual_failure(): void
    {
        $this->actAsAdmin();

        $this->seedFile('logos/survivor.png');

        // The second path is outside every upload folder — the batch must
        // continue past it and still remove the first path.
        $response = $this->deleteJson('/api/admin/storage/orphans', [
            'paths' => ['logos/survivor.png', '../secrets/db-password.txt'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.deleted.0', 'logos/survivor.png');
        $response->assertJsonCount(1, 'data.skipped');
        Storage::disk('r2')->assertMissing('logos/survivor.png');
    }

    public function test_delete_with_an_empty_paths_array_fails_validation(): void
    {
        $this->actAsAdmin();

        $this->deleteJson('/api/admin/storage/orphans', ['paths' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('paths');
    }

    public function test_delete_with_a_non_array_body_fails_validation(): void
    {
        $this->actAsAdmin();

        $this->deleteJson('/api/admin/storage/orphans', ['paths' => 'logos/x.png'])
            ->assertStatus(422);
    }

    public function test_an_external_url_is_never_collected_as_a_reference(): void
    {
        $this->actAsAdmin();

        About::query()->create(['image_path' => 'https://some-other-host.com/not-ours.png']);
        $this->seedFile('about/not-ours.png', now()->subDay());

        $response = $this->getJson('/api/admin/storage/orphans');

        $response->assertOk();
        $this->assertContains(
            'about/not-ours.png',
            collect($response->json('data.orphan_candidates'))->pluck('path')->all(),
        );
    }
}
