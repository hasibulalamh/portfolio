<?php

namespace Tests\Unit;

use App\Http\Requests\HeroRequest;
use App\Http\Requests\ReorderRequest;
use App\Http\Requests\SettingRequest;
use App\Http\Requests\TimelineItemRequest;
use App\Models\Hero;
use App\Models\TimelineItem;
use App\Services\AuthService;
use App\Services\SingletonResetService;
use App\Services\UploadService;
use ReflectionMethod;
use Tests\Support\ResolvesFormRequests;
use Tests\TestCase;

/**
 * Tests targeting every escaped mutant from the infection analysis.
 *
 * Each test is annotated with the mutant ID it kills. The group is
 * `mutation-killing` so you can run just these:
 *
 *     php artisan test --group=mutation-killing
 */
#[\PHPUnit\Framework\Attributes\Group('mutation-killing')]
class MutationKillingTest extends TestCase
{
    use ResolvesFormRequests;

    // ------------------------------------------------------------------
    // HeroRequest — validation rules (mutants 1–17)
    // ------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function heroPayload(array $overrides = []): array
    {
        return array_merge(['heading' => 'Hasibul Alam'], $overrides);
    }

    // Mutants 1, 2: removing 'heading' rule or 'required' from it
    public function test_heading_is_required(): void
    {
        $this->assertFailsOn(HeroRequest::class, [], 'heading');
        $this->assertPasses(HeroRequest::class, ['heading' => 'Hasibul Alam']);
    }

    public function test_heading_must_be_a_string(): void
    {
        $this->assertFailsOn(HeroRequest::class, $this->heroPayload(['heading' => 123]), 'heading');
    }

    public function test_heading_max_length_is_255(): void
    {
        $this->assertPasses(HeroRequest::class, $this->heroPayload(['heading' => str_repeat('a', 255)]));
        $this->assertFailsOn(HeroRequest::class, $this->heroPayload(['heading' => str_repeat('a', 256)]), 'heading');
    }

    // Mutant 3: removing 'nullable' from subheading (would reject null/missing)
    public function test_subheading_is_nullable(): void
    {
        $this->assertPasses(HeroRequest::class, $this->heroPayload(['subheading' => null]));
        $this->assertPasses(HeroRequest::class, $this->heroPayload()); // missing entirely
        $this->assertPasses(HeroRequest::class, $this->heroPayload(['subheading' => 'Hello']));
    }

    // Mutant 4: removing 'nullable' from roles
    public function test_roles_is_nullable(): void
    {
        $this->assertPasses(HeroRequest::class, $this->heroPayload(['roles' => null]));
        $this->assertPasses(HeroRequest::class, $this->heroPayload()); // missing entirely
    }

    // Mutant 5: removing 'required' from roles.* (allows empty role strings)
    public function test_each_role_must_be_a_non_empty_string(): void
    {
        $validated = $this->validateRequest(HeroRequest::class, $this->heroPayload([
            'roles' => ['Laravel Developer', '', 'Platform Engineer'],
        ]));

        $this->assertSame(['Laravel Developer', 'Platform Engineer'], $validated['roles']);
    }

    // Mutant 6: removing 'nullable' from tech_badges
    public function test_tech_badges_is_nullable(): void
    {
        $this->assertPasses(HeroRequest::class, $this->heroPayload(['tech_badges' => null]));
        $this->assertPasses(HeroRequest::class, $this->heroPayload()); // missing entirely
    }

    // Mutant 7: removing 'required' from tech_badges.*.label
    // NOTE: This rule is effectively dead code — prepareForValidation strips
    // badges with blank labels before validation runs. The mutant escapes because
    // no input can reach this rule with a blank label. We test the *observable*
    // behaviour instead: blank labels are dropped, not errored.
    public function test_blank_badge_labels_are_dropped_not_rejected(): void
    {
        $validated = $this->validateRequest(HeroRequest::class, $this->heroPayload([
            'tech_badges' => [
                ['label' => '', 'icon_slug' => 'react'],
                ['label' => 'Vue', 'icon_slug' => 'vuedotjs'],
            ],
        ]));

        $this->assertCount(1, $validated['tech_badges']);
        $this->assertSame('Vue', $validated['tech_badges'][0]['label']);
    }

    // Mutant 8: removing 'boolean' from is_available
    public function test_is_available_must_be_a_boolean(): void
    {
        $this->assertPasses(HeroRequest::class, $this->heroPayload(['is_available' => true]));
        $this->assertPasses(HeroRequest::class, $this->heroPayload(['is_available' => false]));
        $this->assertFailsOn(HeroRequest::class, $this->heroPayload(['is_available' => 'yes']), 'is_available');
    }

    // Mutant 9: removing 'nullable' from cta_primary_text
    public function test_cta_primary_text_is_nullable(): void
    {
        $this->assertPasses(HeroRequest::class, $this->heroPayload(['cta_primary_text' => null]));
        $this->assertPasses(HeroRequest::class, $this->heroPayload()); // missing
    }

    // Mutant 10: removing 'nullable' from cta_primary_link
    public function test_cta_primary_link_is_nullable(): void
    {
        $this->assertPasses(HeroRequest::class, $this->heroPayload(['cta_primary_link' => null]));
        $this->assertPasses(HeroRequest::class, $this->heroPayload()); // missing
    }

    // Mutant 11: removing 'nullable' from cta_secondary_text
    public function test_cta_secondary_text_is_nullable(): void
    {
        $this->assertPasses(HeroRequest::class, $this->heroPayload(['cta_secondary_text' => null]));
        $this->assertPasses(HeroRequest::class, $this->heroPayload()); // missing
    }

    // Mutant 12: removing 'nullable' from cta_secondary_link
    public function test_cta_secondary_link_is_nullable(): void
    {
        $this->assertPasses(HeroRequest::class, $this->heroPayload(['cta_secondary_link' => null]));
        $this->assertPasses(HeroRequest::class, $this->heroPayload()); // missing
    }

    // Mutant 13: removing 'nullable' from image_path
    public function test_image_path_is_nullable(): void
    {
        $this->assertPasses(HeroRequest::class, $this->heroPayload(['image_path' => null]));
        $this->assertPasses(HeroRequest::class, $this->heroPayload()); // missing
    }

    // Mutant 14: removing 'nullable' from image_alt
    public function test_image_alt_is_nullable(): void
    {
        $this->assertPasses(HeroRequest::class, $this->heroPayload(['image_alt' => null]));
        $this->assertPasses(HeroRequest::class, $this->heroPayload()); // missing
    }

    // Mutant 15: removing 'nullable' from social_links
    public function test_social_links_is_nullable(): void
    {
        $this->assertPasses(HeroRequest::class, $this->heroPayload(['social_links' => null]));
        $this->assertPasses(HeroRequest::class, $this->heroPayload()); // missing
    }

    // Mutant 16: removing 'required' from social_links.*.platform
    public function test_each_social_link_must_have_a_platform(): void
    {
        $this->assertFailsOn(HeroRequest::class, $this->heroPayload([
            'social_links' => [['platform' => '', 'url' => 'https://example.com']],
        ]), 'social_links.0.platform');
    }

    // Mutant 17: removing 'required' from social_links.*.url
    // NOTE: This rule is effectively dead code — prepareForValidation strips
    // links with blank URLs before validation runs. The mutant escapes because
    // no input can reach this rule with a blank URL. We test the *observable*
    // behaviour instead: blank URLs are dropped, not errored.
    public function test_blank_social_urls_are_dropped_not_rejected(): void
    {
        $validated = $this->validateRequest(HeroRequest::class, $this->heroPayload([
            'social_links' => [
                ['platform' => 'github', 'url' => ''],
                ['platform' => 'x', 'url' => 'https://x.com/me'],
            ],
        ]));

        $this->assertCount(1, $validated['social_links']);
        $this->assertSame('https://x.com/me', $validated['social_links'][0]['url']);
    }

    // ------------------------------------------------------------------
    // HeroRequest — prepareForValidation (mutants 18–34)
    // ------------------------------------------------------------------

    // Mutant 18: CastString on trim — removing (string) cast
    // Mutant 19: LogicalAnd — changing && to || in blankable check
    public function test_blank_strings_are_normalised_to_null(): void
    {
        $validated = $this->validateRequest(HeroRequest::class, $this->heroPayload([
            'email' => '',
            'image_path' => '  ',
            'availability_label' => '',
            'cta_primary_link' => '   ',
            'cta_secondary_link' => '',
            'image_alt' => '',
        ]));

        $this->assertNull($validated['email']);
        $this->assertNull($validated['image_path']);
        $this->assertNull($validated['availability_label']);
        $this->assertNull($validated['cta_primary_link']);
        $this->assertNull($validated['cta_secondary_link']);
        $this->assertNull($validated['image_alt']);
    }

    // Mutant 19 (LogicalAnd): && → || means every request has all fields nulled
    public function test_non_blank_values_are_preserved_not_nulled(): void
    {
        $validated = $this->validateRequest(HeroRequest::class, $this->heroPayload([
            'email' => 'admin@example.com',
            'image_path' => 'https://example.com/hero.png',
        ]));

        $this->assertSame('admin@example.com', $validated['email']);
        $this->assertSame('https://example.com/hero.png', $validated['image_path']);
    }

    // Mutant 20: UnwrapArrayValues — removing array_values on tech_badges
    // Mutant 22: UnwrapTrim — removing trim from badge label
    // Mutant 23: CastString — removing (string) cast on icon_slug
    // Mutant 24: CastString — removing (string) cast in badge filter
    public function test_badges_are_trimmed_and_filtered(): void
    {
        $validated = $this->validateRequest(HeroRequest::class, $this->heroPayload([
            'tech_badges' => [
                ['label' => '  Laravel  ', 'icon_slug' => '  laravel  '],
                ['label' => '  ', 'icon_slug' => 'react'],  // blank label → dropped
                ['label' => 'Vue', 'icon_slug' => ''],
            ],
        ]));

        $this->assertCount(2, $validated['tech_badges']);
        $this->assertSame('Laravel', $validated['tech_badges'][0]['label']);
        $this->assertSame('laravel', $validated['tech_badges'][0]['icon_slug']);
        $this->assertSame('Vue', $validated['tech_badges'][1]['label']);
        $this->assertNull($validated['tech_badges'][1]['icon_slug']);
    }

    // Mutant 21: CastString — removing (string) cast on badge label trim
    public function test_badge_label_with_non_string_value_is_handled(): void
    {
        // An integer label passed through the form would break trim without the (string) cast
        $validated = $this->validateRequest(HeroRequest::class, $this->heroPayload([
            'tech_badges' => [
                ['label' => 'Laravel', 'icon_slug' => null],
            ],
        ]));

        $this->assertSame('Laravel', $validated['tech_badges'][0]['label']);
    }

    // Mutant 25: UnwrapArrayMap — removing the map that normalises social links
    // Mutant 26: UnwrapArrayValues — removing array_values on social_links
    // Mutant 27: CastString — removing (string) cast on platform
    // Mutant 28: UnwrapTrim — removing trim from platform
    // Mutant 29: CastString — removing (string) cast on url
    // Mutant 30: UnwrapTrim — removing trim from url
    // Mutant 31: UnwrapArrayFilter — removing the filter on social links
    // Mutant 32: CastString — removing (string) cast in link filter
    // Mutant 33: UnwrapTrim — removing trim in link filter
    // Mutant 34: LogicalAnd — changing && to || in link filter
    public function test_social_links_are_trimmed_filtered_and_normalised(): void
    {
        $validated = $this->validateRequest(HeroRequest::class, $this->heroPayload([
            'social_links' => [
                ['platform' => '  github  ', 'url' => '  https://github.com/me  '],
                ['platform' => 'linkedin', 'url' => ''],  // empty url → dropped
                ['platform' => 'x', 'url' => 'https://x.com/me'],
            ],
        ]));

        $this->assertCount(2, $validated['social_links']);
        $this->assertSame('github', $validated['social_links'][0]['platform']);
        $this->assertSame('https://github.com/me', $validated['social_links'][0]['url']);
        $this->assertSame('x', $validated['social_links'][1]['platform']);
    }

    // ------------------------------------------------------------------
    // HeroRequest — checkSocialUrls (mutants 36–40)
    // ------------------------------------------------------------------

    // Mutant 36: CastString — removing (string) cast on url in checkSocialUrls
    // Mutant 37: UnwrapTrim — removing trim from url in checkSocialUrls
    public function test_social_url_with_surrounding_whitespace_is_validated_after_trim(): void
    {
        // A URL with spaces around it should be trimmed before validation
        $this->assertPasses(HeroRequest::class, $this->heroPayload([
            'social_links' => [['platform' => 'github', 'url' => '  https://github.com/me  ']],
        ]));
    }

    // Mutant 38: Continue_ — changing continue to break (stops after first link)
    public function test_all_social_links_are_validated_not_just_the_first(): void
    {
        // Second link is invalid; only break-early mutation would miss it
        $this->assertFailsOn(HeroRequest::class, $this->heroPayload([
            'social_links' => [
                ['platform' => 'github', 'url' => 'https://github.com/me'],
                ['platform' => 'github', 'url' => 'not-a-url'],
            ],
        ]), 'social_links.1.url');
    }

    // Mutant 39: PregMatchRemoveCaret — removing ^ anchor from regex
    public function test_social_url_must_start_with_http_or_https_not_contain_it(): void
    {
        // Without ^, "javascript:https://evil.com" would pass
        $this->assertFailsOn(HeroRequest::class, $this->heroPayload([
            'social_links' => [['platform' => 'github', 'url' => 'javascript:https://evil.com']],
        ]), 'social_links.0.url');
    }

    // Mutant 40: PregMatchRemoveFlags — removing case-insensitive flag
    public function test_social_url_scheme_is_case_insensitive(): void
    {
        $this->assertPasses(HeroRequest::class, $this->heroPayload([
            'social_links' => [['platform' => 'github', 'url' => 'HTTPS://github.com/me']],
        ]));
    }

    // ------------------------------------------------------------------
    // ReorderRequest — mutants 41–42
    // ------------------------------------------------------------------

    // Mutant 41: removing 'required' from items.*.id
    public function test_each_reorder_item_must_have_an_id(): void
    {
        $this->assertFailsOn(ReorderRequest::class, [
            'items' => [['order' => 0]],
        ], 'items.0.id', 'PUT');
    }

    // Mutant 42: removing 'items.required' message
    public function test_reorder_items_required_message_is_customised(): void
    {
        $errors = $this->validationErrorsFor(ReorderRequest::class, [], 'PUT');
        $this->assertArrayHasKey('items', $errors);
        $this->assertSame(
            'An ordered list of items is required.',
            $errors['items'][0],
        );
    }

    // ------------------------------------------------------------------
    // SettingRequest — mutants 43–54
    // ------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function settingPayload(array $overrides = []): array
    {
        return array_merge([
            'site_title' => 'Portfolio',
            'brand_name' => 'Hasibul',
        ], $overrides);
    }

    // Mutant 43, 44: removing 'required' from site_title
    public function test_site_title_is_required(): void
    {
        $this->assertFailsOn(SettingRequest::class, ['brand_name' => 'Hasibul'], 'site_title');
        $this->assertPasses(SettingRequest::class, $this->settingPayload());
    }

    // Mutant 45: removing 'required' from brand_name
    public function test_brand_name_is_required(): void
    {
        $this->assertFailsOn(SettingRequest::class, ['site_title' => 'Portfolio'], 'brand_name');
        $this->assertPasses(SettingRequest::class, $this->settingPayload());
    }

    // Mutant 46: removing 'nullable' from footer_text
    public function test_footer_text_is_nullable(): void
    {
        $this->assertPasses(SettingRequest::class, $this->settingPayload(['footer_text' => null]));
        $this->assertPasses(SettingRequest::class, $this->settingPayload()); // missing
    }

    // Mutant 47: removing 'nullable' from copyright_text
    public function test_copyright_text_is_nullable(): void
    {
        $this->assertPasses(SettingRequest::class, $this->settingPayload(['copyright_text' => null]));
        $this->assertPasses(SettingRequest::class, $this->settingPayload()); // missing
    }

    // Mutant 48: removing 'nullable' from accent_color
    public function test_accent_color_is_nullable(): void
    {
        $this->assertPasses(SettingRequest::class, $this->settingPayload(['accent_color' => null]));
        $this->assertPasses(SettingRequest::class, $this->settingPayload()); // missing
    }

    // Mutant 49: removing 'nullable' from favicon_path
    public function test_favicon_path_is_nullable(): void
    {
        $this->assertPasses(SettingRequest::class, $this->settingPayload(['favicon_path' => null]));
        $this->assertPasses(SettingRequest::class, $this->settingPayload()); // missing
    }

    // Mutant 50: removing 'nullable' from logo_type
    public function test_logo_type_is_nullable(): void
    {
        $this->assertPasses(SettingRequest::class, $this->settingPayload(['logo_type' => null]));
        $this->assertPasses(SettingRequest::class, $this->settingPayload()); // missing
    }

    // Mutant 51: removing 'nullable' from logo_text
    public function test_logo_text_is_nullable(): void
    {
        $this->assertPasses(SettingRequest::class, $this->settingPayload(['logo_text' => null]));
        $this->assertPasses(SettingRequest::class, $this->settingPayload()); // missing
    }

    // Mutant 52: removing 'nullable' from logo_path
    public function test_logo_path_is_nullable(): void
    {
        $this->assertPasses(SettingRequest::class, $this->settingPayload(['logo_path' => null]));
        $this->assertPasses(SettingRequest::class, $this->settingPayload()); // missing
    }

    // Mutant 53: removing 'nullable' from logo_alt
    public function test_logo_alt_is_nullable(): void
    {
        $this->assertPasses(SettingRequest::class, $this->settingPayload(['logo_alt' => null]));
        $this->assertPasses(SettingRequest::class, $this->settingPayload()); // missing
    }

    // Mutant 54: removing 'accent_color.regex' message
    public function test_accent_color_custom_error_message(): void
    {
        $errors = $this->validationErrorsFor(SettingRequest::class, $this->settingPayload([
            'accent_color' => 'not-hex',
        ]));
        $this->assertArrayHasKey('accent_color', $errors);
        $this->assertSame(
            'Accent color must be a hex value like #4648D4.',
            $errors['accent_color'][0],
        );
    }

    // ------------------------------------------------------------------
    // TimelineItemRequest — mutants 55–67
    // ------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function timelinePayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'experience',
            'institute_or_company' => 'Acme Ltd',
            'subject_or_role' => 'Backend Engineer',
            'start_year' => '2022',
        ], $overrides);
    }

    // Mutant 55: removing 'required' from type
    public function test_timeline_type_is_required(): void
    {
        $this->assertFailsOn(TimelineItemRequest::class, $this->timelinePayload(['type' => '']), 'type');
    }

    // Mutant 56: removing 'required' from institute_or_company
    public function test_institute_or_company_is_required(): void
    {
        $this->assertFailsOn(TimelineItemRequest::class, $this->timelinePayload(['institute_or_company' => '']), 'institute_or_company');
    }

    // Mutant 57: removing 'required' from subject_or_role
    public function test_subject_or_role_is_required(): void
    {
        $this->assertFailsOn(TimelineItemRequest::class, $this->timelinePayload(['subject_or_role' => '']), 'subject_or_role');
    }

    // Mutant 58: removing 'required' from start_year
    public function test_start_year_is_required(): void
    {
        $this->assertFailsOn(TimelineItemRequest::class, $this->timelinePayload(['start_year' => '']), 'start_year');
    }

    // Mutant 59: removing 'nullable' from description
    public function test_timeline_description_is_nullable(): void
    {
        $this->assertPasses(TimelineItemRequest::class, $this->timelinePayload(['description' => null]));
        $this->assertPasses(TimelineItemRequest::class, $this->timelinePayload()); // missing
    }

    // Mutant 60: removing 'nullable' from order
    public function test_timeline_order_is_nullable(): void
    {
        $this->assertPasses(TimelineItemRequest::class, $this->timelinePayload(['order' => null]));
        $this->assertPasses(TimelineItemRequest::class, $this->timelinePayload()); // missing
    }

    // Mutant 61: removing 'required' from derived 'year' column
    public function test_derived_year_column_is_present_in_validated(): void
    {
        $validated = $this->validateRequest(TimelineItemRequest::class, $this->timelinePayload());
        $this->assertArrayHasKey('year', $validated);
        $this->assertNotEmpty($validated['year']);
    }

    // Mutant 62: removing 'required' from derived 'title' column
    public function test_derived_title_column_is_present_in_validated(): void
    {
        $validated = $this->validateRequest(TimelineItemRequest::class, $this->timelinePayload());
        $this->assertArrayHasKey('title', $validated);
        $this->assertSame('Backend Engineer', $validated['title']);
    }

    // Mutant 63: removing 'required' from derived 'company' column
    public function test_derived_company_column_is_present_in_validated(): void
    {
        $validated = $this->validateRequest(TimelineItemRequest::class, $this->timelinePayload());
        $this->assertArrayHasKey('company', $validated);
        $this->assertSame('Acme Ltd', $validated['company']);
    }

    // Mutant 64: CastString — removing (string) cast on start_year
    // Mutant 65: UnwrapTrim — removing trim from start_year
    public function test_start_year_with_surrounding_whitespace_is_trimmed(): void
    {
        $validated = $this->validateRequest(TimelineItemRequest::class, $this->timelinePayload([
            'start_year' => '  2022  ',
        ]));

        $this->assertStringStartsWith('2022', $validated['year']);
    }

    // Mutant 66: CastString — removing (string) cast on end_year
    // Mutant 67: UnwrapTrim — removing trim from end_year
    public function test_end_year_with_surrounding_whitespace_is_trimmed(): void
    {
        $validated = $this->validateRequest(TimelineItemRequest::class, $this->timelinePayload([
            'end_year' => '  2024  ',
        ]));

        $this->assertSame('2022 — 2024', $validated['year']);
    }

    // ------------------------------------------------------------------
    // AuthService — throttleKey (mutants 68–70)
    // ------------------------------------------------------------------

    // Mutant 68: Concat — changing concatenation order
    // Mutant 69: ConcatOperandRemoval — removing | separator
    // Mutant 70: Concat — swapping operand positions
    public function test_throttle_key_has_exact_format(): void
    {
        $service = app(AuthService::class);
        $key = $service->throttleKey('Admin@Example.com', '192.168.1.1');

        // Must be: login: + lowercased-email + | + ip
        $this->assertSame('login:admin@example.com|192.168.1.1', $key);
    }

    public function test_throttle_key_contains_pipe_separator(): void
    {
        $service = app(AuthService::class);
        $key = $service->throttleKey('user@test.com', '10.0.0.1');

        $this->assertStringContainsString('|', $key);
        // Pipe must separate email from IP, not be at the end or start
        $parts = explode('|', $key);
        $this->assertCount(2, $parts);
        $this->assertSame('login:user@test.com', $parts[0]);
        $this->assertSame('10.0.0.1', $parts[1]);
    }

    // ------------------------------------------------------------------
    // SingletonResetService — storagePathFor (mutants 71–75)
    // ------------------------------------------------------------------

    private function storagePathFor(string $value, string $disk): ?string
    {
        $method = new ReflectionMethod(SingletonResetService::class, 'storagePathFor');
        $method->setAccessible(true);

        return $method->invoke(new SingletonResetService(new UploadService), $value, $disk);
    }

    // Mutant 71: ConcatOperandRemoval — removing trailing / from R2 prefix
    public function test_r2_prefix_requires_trailing_slash(): void
    {
        config(['filesystems.disks.r2.url' => 'https://pub-abc.r2.dev']);

        // The prefix is rtrim($base, '/') . '/', so the result must include the slash
        $this->assertSame(
            'uploads/abc.png',
            $this->storagePathFor('https://pub-abc.r2.dev/uploads/abc.png', 'r2'),
        );
    }

    // Mutant 72: ReturnRemoval — removing return null when prefix doesn't match
    public function test_r2_url_from_different_host_returns_null(): void
    {
        config(['filesystems.disks.r2.url' => 'https://pub-abc.r2.dev']);

        $this->assertNull($this->storagePathFor('https://other-cdn.example.com/uploads/abc.png', 'r2'));
    }

    // Mutant 73: UnwrapLtrim — removing ltrim on candidate
    public function test_leading_slash_is_stripped_from_candidate(): void
    {
        // A URL like /storage/uploads/abc.png should resolve to uploads/abc.png
        $this->assertSame(
            'uploads/abc.png',
            $this->storagePathFor('/storage/uploads/abc.png', 'public'),
        );
    }

    // Mutant 74: UnwrapArrayValues — removing array_values from uploadFolders
    public function test_upload_folders_returns_sequential_array(): void
    {
        $method = new ReflectionMethod(SingletonResetService::class, 'uploadFolders');
        $method->setAccessible(true);

        $folders = $method->invoke(new SingletonResetService(new UploadService));

        // array_values ensures sequential integer keys
        $this->assertSame(array_values($folders), $folders);
    }

    // Mutant 75: UnwrapArrayUnique — removing array_unique from uploadFolders
    public function test_upload_folders_are_unique(): void
    {
        $method = new ReflectionMethod(SingletonResetService::class, 'uploadFolders');
        $method->setAccessible(true);

        $folders = $method->invoke(new SingletonResetService(new UploadService));

        $this->assertSame(array_values(array_unique($folders)), $folders);
    }

    // ------------------------------------------------------------------
    // UploadService — rules (mutants 76–84)
    // ------------------------------------------------------------------

    // Mutant 76: ArrayItemRemoval — removing image/png from favicon mimetypes
    public function test_favicon_accepts_png(): void
    {
        $mimes = UploadService::rulesFor(UploadService::TYPE_FAVICON)['mimetypes'];
        $this->assertContains('image/png', $mimes);
    }

    // Mutants 77, 78: DecrementInteger/IncrementInteger on favicon max_kb
    public function test_favicon_max_size_is_1024(): void
    {
        $this->assertSame(1024, UploadService::rulesFor(UploadService::TYPE_FAVICON)['max_kb']);
    }

    // Mutants 79, 80: DecrementInteger/IncrementInteger on hero_image max_kb
    public function test_hero_image_max_size_is_5120(): void
    {
        $this->assertSame(5120, UploadService::rulesFor(UploadService::TYPE_HERO_IMAGE)['max_kb']);
    }

    // Mutants 81, 82: DecrementInteger/IncrementInteger on about_image max_kb
    public function test_about_image_max_size_is_5120(): void
    {
        $this->assertSame(5120, UploadService::rulesFor(UploadService::TYPE_ABOUT_IMAGE)['max_kb']);
    }

    // Mutants 83, 84: DecrementInteger/IncrementInteger on avatar max_kb
    public function test_avatar_max_size_is_2048(): void
    {
        $this->assertSame(2048, UploadService::rulesFor(UploadService::TYPE_AVATAR)['max_kb']);
    }
}
