<?php

namespace Tests\Feature;

use App\Models\SectionVisibility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Section visibility toggle propagation: admin changes a section's visibility
 * and the public API reflects the change.
 *
 * Integration, not unit: the contract under test is that the admin write
 * endpoint and the public read endpoint agree on the same source of truth.
 */
class SectionVisibilityIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function actAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_toggling_a_section_off_makes_it_invisible_on_the_public_api(): void
    {
        $this->actAsAdmin();

        // Read current sections
        $sections = $this->getJson('/api/admin/section-visibility')->json('data');
        $aboutSection = collect($sections)->firstWhere('section_key', 'about');

        $this->assertNotNull($aboutSection, 'about section must exist');

        // Toggle off
        $updatedSections = collect($sections)->map(function ($s) {
            if ($s['section_key'] === 'about') {
                $s['is_visible'] = false;
            }
            return $s;
        })->values()->all();

        $this->putJson('/api/admin/section-visibility', ['sections' => $updatedSections])
            ->assertOk();

        // Public API must reflect the change
        $publicSections = $this->getJson('/api/section-visibility')->json('data');
        $aboutPublic = collect($publicSections)->firstWhere('section_key', 'about');

        $this->assertFalse((bool) $aboutPublic['is_visible']);
    }

    public function test_toggling_back_on_restores_visibility(): void
    {
        $this->actAsAdmin();

        $sections = $this->getJson('/api/admin/section-visibility')->json('data');
        $aboutSection = collect($sections)->firstWhere('section_key', 'about');

        // Toggle off first
        $off = collect($sections)->map(function ($s) {
            if ($s['section_key'] === 'about') {
                $s['is_visible'] = false;
            }
            return $s;
        })->values()->all();
        $this->putJson('/api/admin/section-visibility', ['sections' => $off])->assertOk();

        // Toggle back on
        $on = collect($off)->map(function ($s) {
            if ($s['section_key'] === 'about') {
                $s['is_visible'] = true;
            }
            return $s;
        })->values()->all();
        $this->putJson('/api/admin/section-visibility', ['sections' => $on])->assertOk();

        $publicSections = $this->getJson('/api/section-visibility')->json('data');
        $aboutPublic = collect($publicSections)->firstWhere('section_key', 'about');

        $this->assertTrue((bool) $aboutPublic['is_visible']);
    }

    public function test_reorder_changes_reflect_on_the_public_api(): void
    {
        $this->actAsAdmin();

        // Read current public order before any changes
        $beforePublic = array_column($this->getJson('/api/section-visibility')->json('data'), 'section_key');
        $this->assertNotEmpty($beforePublic);

        // Read admin sections and swap the order values of the first two
        $sections = $this->getJson('/api/admin/section-visibility')->json('data');
        if (count($sections) < 2) {
            $this->markTestSkipped('Need at least 2 sections to test reorder.');
        }

        // Swap order values of first two items
        $temp = $sections[0]['order'];
        $sections[0]['order'] = $sections[1]['order'];
        $sections[1]['order'] = $temp;

        $this->putJson('/api/admin/section-visibility', ['sections' => $sections])->assertOk();

        // The public API order must have changed
        $afterPublic = array_column($this->getJson('/api/section-visibility')->json('data'), 'section_key');

        $this->assertNotSame($beforePublic, $afterPublic, 'reordering must change the public API order');
    }

    public function test_locked_sections_cannot_be_hidden(): void
    {
        $this->actAsAdmin();

        $sections = $this->getJson('/api/admin/section-visibility')->json('data');

        // Try to hide a locked section (hero is typically locked)
        $lockedSection = collect($sections)->firstWhere('is_toggleable', false);

        if ($lockedSection === null) {
            $this->markTestSkipped('No locked sections exist to test against.');
        }

        $updatedSections = collect($sections)->map(function ($s) {
            if ($s['section_key'] === $s['section_key'] && ! ($s['is_toggleable'] ?? true)) {
                $s['is_visible'] = false;
            }
            return $s;
        })->values()->all();

        $response = $this->putJson('/api/admin/section-visibility', ['sections' => $updatedSections]);

        // Should either reject or keep the locked section visible
        if ($response->status() === 422) {
            $this->assertTrue(true, 'Request was rejected as expected');
        } else {
            $publicSections = $this->getJson('/api/section-visibility')->json('data');
            $lockedPublic = collect($publicSections)->firstWhere('section_key', $lockedSection['section_key']);
            $this->assertTrue((bool) $lockedPublic['is_visible'], 'locked section must remain visible');
        }
    }
}
