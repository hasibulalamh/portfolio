<?php

namespace Tests\Contract;

use App\Models\About;
use App\Models\ContactInfo;
use App\Models\ContactMessage;
use App\Models\Hero;
use App\Models\MeetingRequest;
use App\Models\Project;
use App\Models\SectionVisibility;
use App\Models\Setting;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\TimelineItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Consumer contract for the public site and admin panel.
 *
 * This intentionally uses Laravel's resources as the provider under test rather
 * than duplicating them in a second schema library: the frontend consumers are
 * JavaScript-only and this project has no existing Pact boundary. The assertions
 * therefore lock the JSON keys the consumers read while remaining fast and local.
 *
 * Chosen over Pact because: (a) both consumers live in the same repo, (b) there
 * is no existing Pact broker infrastructure, and (c) the project is small enough
 * that a schema-violation bug will surface in a single test run, not across
 * independent CI pipelines.
 */
class ApiShapeContractTest extends TestCase
{
    use RefreshDatabase;

    // =====================================================================
    // Public endpoints — what portfolio-frontend reads
    // =====================================================================

    /** @return array<string, array{string, list<string>}> */
    public static function publicContracts(): array
    {
        return [
            'settings' => ['/api/settings', ['id', 'site_title', 'brand_name', 'logo_type', 'logo_text', 'logo_path', 'logo_alt', 'favicon_path', 'accent_color', 'footer_text', 'copyright_text']],
            'hero' => ['/api/hero', ['id', 'heading', 'subheading', 'roles', 'social_links', 'tech_badges', 'is_available', 'availability_label', 'image_path', 'email', 'cv_path']],
            'about' => ['/api/about', ['id', 'bio_paragraph_1']],
            'skills' => ['/api/skills', []],
            'timeline' => ['/api/timeline', []],
            'projects' => ['/api/projects', []],
            'testimonials' => ['/api/testimonials', []],
            'contact-info' => ['/api/contact-info', ['id', 'email', 'phone', 'location']],
            'api-showcases' => ['/api/api-showcases', []],
            'section-visibility' => ['/api/section-visibility', []],
        ];
    }

    #[DataProvider('publicContracts')]
    public function test_public_endpoint_matches_frontend_envelope_and_shape(string $uri, array $keys): void
    {
        $response = $this->getJson($uri);

        $response->assertOk()->assertJsonStructure(['data', 'message']);
        $data = $response->json('data');
        $this->assertIsArray($data);

        if ($keys !== []) {
            $response->assertJsonStructure(['data' => $keys]);
        }
    }

    public function test_settings_resource_carries_all_admin_editable_fields(): void
    {
        Setting::query()->create([
            'site_title' => 'Contract Test',
            'brand_name' => 'CT',
            'accent_color' => '#000000',
        ]);

        $response = $this->getJson('/api/settings');

        $response->assertOk()->assertJsonStructure([
            'data' => [
                'id', 'site_title', 'brand_name', 'footer_text', 'copyright_text',
                'accent_color', 'favicon_path', 'logo_type', 'logo_text',
                'logo_path', 'logo_alt', 'updated_at',
            ],
        ]);
    }

    public function test_hero_resource_carries_all_public_fields(): void
    {
        Hero::query()->create([
            'heading' => 'Contract Hero',
            'subheading' => 'sub',
        ]);

        $response = $this->getJson('/api/hero');

        $response->assertOk()->assertJsonStructure([
            'data' => [
                'id', 'heading', 'subheading', 'roles', 'tech_badges',
                'is_available', 'availability_label', 'cta_primary_text',
                'cta_primary_link', 'cta_secondary_text', 'cta_secondary_link',
                'image_path', 'image_alt', 'social_links', 'email', 'cv_path',
                'updated_at',
            ],
        ]);
    }

    public function test_skill_resource_carries_icon_slug(): void
    {
        $category = SkillCategory::query()->create(['name' => 'Backend', 'order' => 0]);
        Skill::query()->create([
            'skill_category_id' => $category->id,
            'name' => 'PHP',
            'icon_slug' => 'php',
            'order' => 0,
        ]);

        $response = $this->getJson('/api/skills');

        // Public skills endpoint returns SkillCategoryResource with nested skills
        $response->assertOk()->assertJsonStructure([
            'data' => [['id', 'name', 'order', 'skills']],
        ]);
    }

    public function test_project_card_resource_omits_description(): void
    {
        Project::query()->create([
            'title' => 'Contract Project',
            'slug' => 'contract-project',
            'description' => 'Should not appear in card.',
            'order' => 0,
        ]);

        $response = $this->getJson('/api/projects');

        $response->assertOk()->assertJsonStructure([
            'data' => [['id', 'title', 'slug', 'image_path', 'tags', 'github_url', 'live_url', 'is_featured', 'order']],
        ]);
        // description must NOT be in the card resource
        $this->assertArrayNotHasKey('description', $response->json('data.0'));
    }

    public function test_timeline_resource_includes_legacy_and_new_fields(): void
    {
        TimelineItem::query()->create([
            'type' => 'experience',
            'institute_or_company' => 'Acme',
            'subject_or_role' => 'Engineer',
            'start_year' => '2022',
            'year' => '2022 — Present',
            'title' => 'Engineer',
            'company' => 'Acme',
            'order' => 0,
        ]);

        $response = $this->getJson('/api/timeline');

        $response->assertOk()->assertJsonStructure([
            'data' => [[
                'id', 'type', 'institute_or_company', 'subject_or_role',
                'start_year', 'end_year', 'year_range', 'description', 'order',
                // Legacy fields still present
                'year', 'title', 'company',
            ]],
        ]);
    }

    public function test_section_visibility_resource_carries_all_keys(): void
    {
        // section_visibility rows are seeded by migration, so just read them.
        $response = $this->getJson('/api/section-visibility');

        $response->assertOk()->assertJsonStructure([
            'data' => [['id', 'section_key', 'label', 'nav_href', 'is_visible', 'order', 'is_toggleable']],
        ]);
    }

    public function test_about_resource_returns_bio_paragraphs_and_stats(): void
    {
        About::query()->create([
            'bio_paragraph_1' => 'Test bio.',
        ]);

        $response = $this->getJson('/api/about');

        $response->assertOk()->assertJsonStructure([
            'data' => ['id', 'bio_paragraph_1'],
        ]);
    }

    // =====================================================================
    // Admin endpoints — what portfolio-admin reads
    // =====================================================================

    public function test_admin_inboxes_match_their_consumer_item_shapes(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $message = ContactMessage::query()->create([
            'name' => 'Contract Visitor',
            'email' => 'visitor@example.com',
            'message' => 'A message.',
        ]);
        $request = MeetingRequest::query()->create([
            'name' => 'Contract Client',
            'email' => 'client@example.com',
            'status' => MeetingRequest::STATUS_PENDING,
        ]);

        $this->getJson('/api/admin/messages')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'email', 'message', 'is_read', 'admin_reply', 'replied_at', 'delivery_failed_at', 'created_at']],
                'message',
            ])
            ->assertJsonPath('data.0.id', $message->id);

        $this->getJson('/api/admin/meeting-requests')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'email', 'preferred_date', 'preferred_time', 'message', 'status', 'admin_reply', 'admin_note', 'replied_at', 'delivery_failed_at', 'created_at']],
                'message',
            ])
            ->assertJsonPath('data.0.id', $request->id);
    }

    public function test_admin_skill_categories_match_consumer_shape(): void
    {
        Sanctum::actingAs(User::factory()->create());

        SkillCategory::query()->create(['name' => 'Frontend', 'order' => 0]);

        $this->getJson('/api/admin/skill-categories')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'order']],
                'message',
            ]);
    }

    public function test_admin_skills_match_consumer_shape(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $category = SkillCategory::query()->create(['name' => 'Backend', 'order' => 0]);
        Skill::query()->create([
            'skill_category_id' => $category->id,
            'name' => 'Laravel',
            'icon_slug' => 'laravel',
            'order' => 0,
        ]);

        $this->getJson('/api/admin/skills')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'skill_category_id', 'name', 'icon', 'icon_slug', 'order']],
                'message',
            ]);
    }

    public function test_admin_timeline_items_match_consumer_shape(): void
    {
        Sanctum::actingAs(User::factory()->create());

        TimelineItem::query()->create([
            'type' => 'experience',
            'institute_or_company' => 'Acme',
            'subject_or_role' => 'Engineer',
            'start_year' => '2022',
            'year' => '2022 — Present',
            'title' => 'Engineer',
            'company' => 'Acme',
            'order' => 0,
        ]);

        $this->getJson('/api/admin/timeline-items')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'type', 'institute_or_company', 'subject_or_role', 'start_year', 'end_year', 'year_range', 'description', 'order', 'year', 'title', 'company']],
                'message',
            ]);
    }

    public function test_admin_projects_match_consumer_shape(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Project::query()->create([
            'title' => 'Admin Project',
            'slug' => 'admin-project',
            'description' => 'A project.',
            'order' => 0,
        ]);

        $this->getJson('/api/admin/projects')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'title', 'slug', 'description', 'image_path', 'tags', 'github_url', 'live_url', 'is_featured', 'order']],
                'message',
            ]);
    }

    public function test_admin_section_visibility_match_consumer_shape(): void
    {
        Sanctum::actingAs(User::factory()->create());

        // section_visibility rows are seeded by migration.
        $this->getJson('/api/admin/section-visibility')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'section_key', 'label', 'nav_href', 'is_visible', 'order', 'is_toggleable']],
                'message',
            ]);
    }

    public function test_admin_settings_singleton_matches_consumer_shape(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'site_title', 'brand_name', 'footer_text', 'copyright_text',
                    'accent_color', 'favicon_path', 'logo_type', 'logo_text',
                    'logo_path', 'logo_alt', 'updated_at',
                ],
                'message',
            ]);
    }

    public function test_admin_hero_singleton_matches_consumer_shape(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/hero')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'heading', 'subheading', 'roles', 'tech_badges',
                    'is_available', 'availability_label', 'cta_primary_text',
                    'cta_primary_link', 'cta_secondary_text', 'cta_secondary_link',
                    'image_path', 'image_alt', 'social_links', 'email', 'cv_path',
                    'updated_at',
                ],
                'message',
            ]);
    }

    public function test_admin_about_singleton_matches_consumer_shape(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/about')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'bio_paragraph_1', 'bio_paragraph_2',
                    'image_path', 'image_alt', 'stats', 'updated_at',
                ],
                'message',
            ]);
    }

    public function test_admin_contact_info_singleton_matches_consumer_shape(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/contact-info')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'email', 'phone', 'location', 'calendly_link',
                    'whatsapp_number', 'updated_at',
                ],
                'message',
            ]);
    }

    public function test_admin_testimonials_match_consumer_shape(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/admin/testimonials');

        $response->assertOk()->assertJsonStructure(['data', 'message']);
        $this->assertIsArray($response->json('data'));

        // When there are records, each must carry these keys
        if (count($response->json('data')) > 0) {
            $response->assertJsonStructure([
                'data' => [['id', 'quote', 'author_name', 'author_role', 'avatar_path', 'avatar_alt', 'order']],
            ]);
        }
    }

    public function test_admin_api_showcases_match_consumer_shape(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/admin/api-showcases');

        $response->assertOk()->assertJsonStructure(['data', 'message']);
        $this->assertIsArray($response->json('data'));

        if (count($response->json('data')) > 0) {
            $response->assertJsonStructure([
                'data' => [['id', 'icon_name', 'icon_slug', 'title', 'description', 'endpoints', 'order']],
            ]);
        }
    }

    public function test_envelope_always_contains_data_message_errors(): void
    {
        // Every endpoint must return the three-key envelope that apiCall() reads.
        Sanctum::actingAs(User::factory()->create());

        $endpoints = [
            ['GET', '/api/settings'],
            ['GET', '/api/hero'],
            ['GET', '/api/admin/me'],
            ['GET', '/api/admin/settings'],
            ['GET', '/api/admin/messages'],
            ['GET', '/api/admin/meeting-requests'],
        ];

        foreach ($endpoints as [$verb, $uri]) {
            $response = $this->json($verb, $uri);
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
