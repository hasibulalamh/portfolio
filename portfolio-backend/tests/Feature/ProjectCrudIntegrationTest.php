<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Project CRUD lifecycle through the full HTTP stack — admin routes, FormRequest
 * validation, ProjectService, and the three-key envelope contract.
 *
 * Integration, not unit: the point is that the controller, the service, the
 * request, and the resource are wired correctly. ProjectServiceTest covers
 * slug logic in isolation; this covers the HTTP boundary the admin panel
 * actually talks to.
 */
class ProjectCrudIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function actAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    /** @return array<string, mixed> */
    private function projectPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'E2E Test Project',
            'description' => 'A project for integration testing.',
            'order' => 0,
        ], $overrides);
    }

    // =====================================================================
    // Create
    // =====================================================================

    public function test_a_valid_project_is_created_and_returns_201(): void
    {
        $this->actAsAdmin();

        $response = $this->postJson('/api/admin/projects', $this->projectPayload());

        $response->assertCreated();
        $response->assertJsonPath('message', 'Project created.');
        $response->assertJsonStructure(['data' => ['id', 'title', 'slug', 'description', 'order']]);
        $this->assertDatabaseHas('projects', ['title' => 'E2E Test Project']);
    }

    public function test_a_created_project_has_a_sluggified_slug(): void
    {
        $this->actAsAdmin();

        $response = $this->postJson('/api/admin/projects', $this->projectPayload());

        $this->assertSame('e2e-test-project', $response->json('data.slug'));
    }

    public function test_a_duplicate_title_gets_a_suffixed_slug(): void
    {
        $this->actAsAdmin();

        $this->postJson('/api/admin/projects', $this->projectPayload())->assertCreated();
        $second = $this->postJson('/api/admin/projects', $this->projectPayload())->assertCreated();

        $this->assertSame('e2e-test-project-2', $second->json('data.slug'));
    }

    public function test_a_missing_title_is_rejected(): void
    {
        $this->actAsAdmin();

        $this->postJson('/api/admin/projects', ['description' => 'No title.'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');
    }

    public function test_unauthenticated_create_is_refused(): void
    {
        $this->postJson('/api/admin/projects', $this->projectPayload())
            ->assertStatus(401);
    }

    // =====================================================================
    // Read
    // =====================================================================

    public function test_index_lists_all_projects(): void
    {
        $this->actAsAdmin();

        Project::query()->create(['title' => 'P1', 'slug' => 'p1', 'description' => 'd', 'order' => 0]);
        Project::query()->create(['title' => 'P2', 'slug' => 'p2', 'description' => 'd', 'order' => 1]);

        $response = $this->getJson('/api/admin/projects');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    // =====================================================================
    // Update
    // =====================================================================

    public function test_a_project_can_be_renamed(): void
    {
        $this->actAsAdmin();

        $project = Project::query()->create([
            'title' => 'Old Title',
            'slug' => 'old-title',
            'description' => 'd',
            'order' => 0,
        ]);

        $response = $this->putJson("/api/admin/projects/{$project->id}", [
            'title' => 'New Title',
            'description' => 'Updated.',
            'order' => 0,
        ]);

        $response->assertOk();
        $this->assertSame('New Title', $response->json('data.title'));
        $this->assertSame('new-title', $response->json('data.slug'));
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'title' => 'New Title']);
    }

    // =====================================================================
    // Case study
    // =====================================================================

    public function test_a_case_study_can_be_saved_to_a_project(): void
    {
        $this->actAsAdmin();

        $project = Project::query()->create([
            'title' => 'Case Study Project',
            'slug' => 'case-study-project',
            'description' => 'd',
            'order' => 0,
        ]);

        $response = $this->postJson("/api/admin/projects/{$project->id}/case-study", [
            'client' => 'Acme Corp',
            'challenge' => 'Legacy API.',
            'solution' => 'Rewrite in Laravel.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Case study saved.');
        $this->assertDatabaseHas('project_details', [
            'project_id' => $project->id,
            'client' => 'Acme Corp',
        ]);
    }

    public function test_a_case_study_is_upserted_on_second_call(): void
    {
        $this->actAsAdmin();

        $project = Project::query()->create([
            'title' => 'Upsert',
            'slug' => 'upsert',
            'description' => 'd',
            'order' => 0,
        ]);

        $this->postJson("/api/admin/projects/{$project->id}/case-study", [
            'challenge' => 'First.',
        ])->assertOk();

        $this->postJson("/api/admin/projects/{$project->id}/case-study", [
            'challenge' => 'Second.',
        ])->assertOk();

        $this->assertDatabaseCount('project_details', 1);
        $this->assertDatabaseHas('project_details', ['challenge' => 'Second.']);
    }

    // =====================================================================
    // Delete
    // =====================================================================

    public function test_a_project_can_be_deleted(): void
    {
        $this->actAsAdmin();

        $project = Project::query()->create([
            'title' => 'Doomed',
            'slug' => 'doomed',
            'description' => 'd',
            'order' => 0,
        ]);

        $this->deleteJson("/api/admin/projects/{$project->id}")->assertOk();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_deleting_a_missing_project_answers_404(): void
    {
        $this->actAsAdmin();

        $this->deleteJson('/api/admin/projects/999999')->assertStatus(404);
    }

    // =====================================================================
    // Reorder
    // =====================================================================

    public function test_reorder_updates_project_order(): void
    {
        $this->actAsAdmin();

        $p1 = Project::query()->create(['title' => 'P1', 'slug' => 'p1', 'description' => 'd', 'order' => 0]);
        $p2 = Project::query()->create(['title' => 'P2', 'slug' => 'p2', 'description' => 'd', 'order' => 1]);

        $this->putJson('/api/admin/projects/reorder', [
            'items' => [
                ['id' => $p1->id, 'order' => 1],
                ['id' => $p2->id, 'order' => 0],
            ],
        ])->assertOk();

        $this->assertSame(1, $p1->refresh()->order);
        $this->assertSame(0, $p2->refresh()->order);
    }

    // =====================================================================
    // Envelope contract
    // =====================================================================

    public function test_all_project_endpoints_return_the_three_key_envelope(): void
    {
        $this->actAsAdmin();

        $project = Project::query()->create([
            'title' => 'Envelope',
            'slug' => 'envelope',
            'description' => 'd',
            'order' => 0,
        ]);

        $endpoints = [
            ['GET', '/api/admin/projects'],
            ['POST', '/api/admin/projects', $this->projectPayload()],
            ['PUT', "/api/admin/projects/{$project->id}", ['title' => 'Updated', 'description' => 'u', 'order' => 0]],
            ['DELETE', "/api/admin/projects/{$project->id}"],
            ['GET', '/api/projects'],
        ];

        foreach ($endpoints as $endpoint) {
            [$verb, $uri] = [$endpoint[0], $endpoint[1]];
            $body = $endpoint[2] ?? null;
            $response = $body
                ? $this->json($verb, $uri, $body)
                : $this->json($verb, $uri);

            $keys = array_keys($response->json());
            sort($keys);
            $this->assertSame(
                ['data', 'errors', 'message'],
                $keys,
                "{$verb} {$uri} must return the three-key envelope",
            );
        }
    }
}
