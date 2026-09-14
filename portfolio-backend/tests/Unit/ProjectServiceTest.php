<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ProjectService::uniqueSlug — the title → slug derivation and collision
 * resolution.
 *
 * Feature test (uses RefreshDatabase) because ProjectService no longer accepts
 * a constructor-injected model class — it references Project::class directly,
 * which means the slug-existence check hits the real database. The interesting
 * decisions are still the same: suffix progression, empty-title fallback,
 * explicit-slug priority, and self-slug preservation on update.
 */
class ProjectServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_clean_title_derives_a_sluggified_slug(): void
    {
        Project::query()->create([
            'title' => 'Existing',
            'slug' => 'existing-project',
            'description' => 'Old project.',
            'order' => 0,
        ]);

        $service = new ProjectService;
        $project = $service->create([
            'title' => 'My Portfolio Site',
            'description' => 'New.',
            'order' => 1,
        ]);

        $this->assertSame('my-portfolio-site', $project->getAttribute('slug'));
    }

    public function test_a_taken_slug_is_suffixed_from_two_upwards(): void
    {
        Project::query()->create([
            'title' => 'App',
            'slug' => 'portfolio-app',
            'description' => 'First.',
            'order' => 0,
        ]);
        Project::query()->create([
            'title' => 'App 2',
            'slug' => 'portfolio-app-2',
            'description' => 'Second.',
            'order' => 1,
        ]);

        $service = new ProjectService;
        $project = $service->create([
            'title' => 'Portfolio App',
            'description' => 'Third.',
            'order' => 2,
        ]);

        $this->assertSame('portfolio-app-3', $project->getAttribute('slug'));
    }

    public function test_a_title_with_no_usable_characters_falls_back_to_project(): void
    {
        Project::query()->create([
            'title' => 'First',
            'slug' => 'project',
            'description' => 'd',
            'order' => 0,
        ]);
        Project::query()->create([
            'title' => 'Second',
            'slug' => 'project-2',
            'description' => 'd',
            'order' => 1,
        ]);

        $service = new ProjectService;
        $project = $service->create([
            'title' => '***',
            'description' => 'd',
            'order' => 2,
        ]);

        $this->assertSame('project-3', $project->getAttribute('slug'));
    }

    public function test_an_explicit_slug_wins_over_the_title(): void
    {
        $service = new ProjectService;
        $project = $service->create([
            'title' => 'Should Not Win',
            'slug' => 'chosen-slug',
            'description' => 'd',
            'order' => 0,
        ]);

        $this->assertSame('chosen-slug', $project->getAttribute('slug'));
    }

    public function test_an_explicit_taken_slug_is_suffixed_like_any_other(): void
    {
        Project::query()->create([
            'title' => 'Existing',
            'slug' => 'case-study',
            'description' => 'd',
            'order' => 0,
        ]);

        $service = new ProjectService;
        $project = $service->create([
            'title' => 'Long Title',
            'slug' => 'case-study',
            'description' => 'd',
            'order' => 1,
        ]);

        $this->assertSame('case-study-2', $project->getAttribute('slug'));
    }

    public function test_update_derives_a_fresh_slug_from_a_renamed_title(): void
    {
        $project = Project::query()->create([
            'title' => 'Original Title',
            'slug' => 'original-title',
            'description' => 'd',
            'order' => 0,
        ]);

        $service = new ProjectService;
        $updated = $service->update($project, ['title' => 'Renamed Title']);

        $this->assertSame('renamed-title', $updated->getAttribute('slug'));
    }

    public function test_update_keeps_the_existing_slug_when_the_title_is_unchanged(): void
    {
        $project = Project::query()->create([
            'title' => 'Same Title',
            'slug' => 'already-good',
            'description' => 'd',
            'order' => 0,
        ]);

        $service = new ProjectService;
        $updated = $service->update($project, ['title' => 'Same Title']);

        $this->assertSame('already-good', $updated->getAttribute('slug'));
    }

    // ------------------------------------------------------------------
    // saveCaseStudy — upsert into project_details
    // ------------------------------------------------------------------

    public function test_save_case_study_creates_a_new_detail_row(): void
    {
        $project = Project::query()->create([
            'title' => 'Case Study Project',
            'slug' => 'case-study-project',
            'description' => 'd',
            'order' => 0,
        ]);

        $service = new ProjectService;
        $detail = $service->saveCaseStudy($project, [
            'challenge' => 'Legacy monolith.',
            'solution' => 'Microservices.',
        ]);

        $this->assertSame($project->id, $detail->project_id);
        $this->assertSame('Legacy monolith.', $detail->challenge);
        $this->assertSame('Microservices.', $detail->solution);
    }

    public function test_save_case_study_upserts_on_second_call(): void
    {
        $project = Project::query()->create([
            'title' => 'Upsert Project',
            'slug' => 'upsert-project',
            'description' => 'd',
            'order' => 0,
        ]);

        $service = new ProjectService;

        $service->saveCaseStudy($project, ['challenge' => 'First version.']);
        $updated = $service->saveCaseStudy($project, ['challenge' => 'Second version.']);

        $this->assertSame(1, $project->detail()->count(), 'only one detail row per project');
        $this->assertSame('Second version.', $updated->challenge);
    }

    public function test_save_case_study_passes_through_all_fields(): void
    {
        $project = Project::query()->create([
            'title' => 'Full Fields',
            'slug' => 'full-fields',
            'description' => 'd',
            'order' => 0,
        ]);

        $service = new ProjectService;
        $detail = $service->saveCaseStudy($project, [
            'client' => 'Acme Corp',
            'date_range' => 'Jan 2026 — Mar 2026',
            'challenge' => 'Slow API.',
            'solution' => 'Redis caching.',
            'results' => ['50% faster', '99.9% uptime'],
        ]);

        $this->assertSame('Acme Corp', $detail->client);
        $this->assertSame('Jan 2026 — Mar 2026', $detail->date_range);
        $this->assertSame(['50% faster', '99.9% uptime'], $detail->results);
    }
}
