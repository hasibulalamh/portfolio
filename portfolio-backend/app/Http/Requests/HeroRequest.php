<?php

namespace App\Http\Requests;

use App\Models\Hero;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HeroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'heading' => ['required', 'string', 'max:255'],
            'subheading' => ['nullable', 'string', 'max:2000'],

            // Rotating role titles for the typewriter. Blank rows are stripped
            // in prepareForValidation, so what arrives here is already clean.
            'roles' => ['nullable', 'array', 'max:12'],
            'roles.*' => ['required', 'string', 'max:255'],

            // Orbiting badges. icon_slug is nullable: a badge with no matching
            // brand mark still renders, as a text label.
            'tech_badges' => ['nullable', 'array', 'max:'.Hero::MAX_TECH_BADGES],
            'tech_badges.*.label' => ['required', 'string', 'max:60'],
            'tech_badges.*.icon_slug' => ['nullable', 'string', 'max:100'],
            'tech_badges.*.logo_type' => ['nullable', 'string', Rule::in(['library', 'custom'])],
            'tech_badges.*.logo_url' => ['nullable', 'url', 'max:2048', 'required_if:tech_badges.*.logo_type,custom'],

            'is_available' => ['boolean'],
            'availability_label' => ['nullable', 'string', 'max:120'],

            'cta_primary_text' => ['nullable', 'string', 'max:255'],
            'cta_primary_link' => ['nullable', 'string', 'max:2048'],
            'cta_secondary_text' => ['nullable', 'string', 'max:255'],
            'cta_secondary_link' => ['nullable', 'string', 'max:2048'],
            'image_path' => ['nullable', 'string', 'max:2048'],
            'image_alt' => ['nullable', 'string', 'max:255'],

            'social_links' => ['nullable', 'array', 'max:12'],
            'social_links.*.platform' => ['required', 'string', Rule::in(Hero::socialPlatforms())],
            // Not a bare `url` rule: the email platform legitimately carries a
            // mailto: link or a plain address. checkSocialUrls below applies the
            // right shape per platform.
            'social_links.*.url' => ['required', 'string', 'max:2048'],

            // The admin form submits '' for an untouched URL field, so these
            // have to accept an empty string as well as a valid URL.
            'email' => ['nullable', 'string', 'max:255', 'email'],
            'cv_path' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /**
     * Normalise blank strings to null so `nullable` applies and the URL/email
     * rules are skipped for fields the admin simply left empty, and drop the
     * placeholder rows the repeatable list inputs start out with.
     */
    protected function prepareForValidation(): void
    {
        $blankable = [
            'email', 'image_path', 'image_alt', 'cv_path',
            'cta_primary_link', 'cta_secondary_link', 'availability_label',
        ];

        $replacements = [];

        foreach ($blankable as $field) {
            if ($this->has($field) && trim((string) $this->input($field)) === '') {
                $replacements[$field] = null;
            }
        }

        if (is_array($roles = $this->input('roles'))) {
            $replacements['roles'] = array_values(array_filter(
                array_map(fn ($role) => is_string($role) ? trim($role) : $role, $roles),
                fn ($role) => is_string($role) && $role !== '',
            ));
        }

        if (is_array($badges = $this->input('tech_badges'))) {
            $replacements['tech_badges'] = array_values(array_map(
                fn (array $badge) => [
                    'label' => trim((string) ($badge['label'] ?? '')),
                    // '' would fail the frontend's `slug ? ... : fallback`
                    // check silently; null is the honest "no logo picked".
                    'icon_slug' => filled($badge['icon_slug'] ?? null)
                        ? trim((string) $badge['icon_slug'])
                        : null,
                    'logo_type' => ($badge['logo_type'] ?? 'library') === 'custom'
                        ? 'custom'
                        : 'library',
                    'logo_url' => filled($badge['logo_url'] ?? null)
                        ? trim((string) $badge['logo_url'])
                        : null,
                ],
                array_filter(
                    $badges,
                    fn ($badge) => is_array($badge) && trim((string) ($badge['label'] ?? '')) !== '',
                ),
            ));
        }

        if (is_array($links = $this->input('social_links'))) {
            $replacements['social_links'] = array_values(array_map(
                fn (array $link) => [
                    'platform' => trim((string) ($link['platform'] ?? '')),
                    'url' => trim((string) ($link['url'] ?? '')),
                ],
                array_filter(
                    $links,
                    fn ($link) => is_array($link) && trim((string) ($link['url'] ?? '')) !== '',
                ),
            ));
        }

        if ($replacements !== []) {
            $this->merge($replacements);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(fn ($validator) => $this->checkSocialUrls($validator));
    }

    /**
     * A social link's URL has to match its platform: every brand platform needs
     * a real http(s) URL, while `email` accepts either a mailto: link or a bare
     * address so the admin is not forced to remember the scheme.
     */
    private function checkSocialUrls($validator): void
    {
        $links = $this->input('social_links');

        if (! is_array($links)) {
            return;
        }

        foreach ($links as $index => $link) {
            $url = trim((string) ($link['url'] ?? ''));

            if ($url === '') {
                continue;
            }

            if (($link['platform'] ?? null) === 'email') {
                $address = preg_replace('/^mailto:/i', '', $url);

                if (! filter_var($address, FILTER_VALIDATE_EMAIL)) {
                    $validator->errors()->add(
                        "social_links.{$index}.url",
                        'Enter a valid email address for the Email platform.',
                    );
                }

                continue;
            }

            if (! preg_match('#^https?://#i', $url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
                $validator->errors()->add(
                    "social_links.{$index}.url",
                    'Enter a full URL starting with http:// or https://.',
                );
            }
        }
    }
}
