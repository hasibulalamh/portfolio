<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HeroResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'heading' => $this->heading,
            'subheading' => $this->subheading,
            // The three list fields are cast to array on the model, but a row
            // predating the migration holds null. Coerce here so the frontend
            // can map over them without a per-field Array.isArray guard.
            'roles' => $this->roles ?? [],
            'tech_badges' => array_map(
                static fn (array $badge): array => [
                    ...$badge,
                    'logo_type' => ($badge['logo_type'] ?? 'library') === 'custom'
                        ? 'custom'
                        : 'library',
                    'logo_url' => $badge['logo_url'] ?? null,
                ],
                $this->tech_badges ?? [],
            ),
            'is_available' => (bool) $this->is_available,
            'availability_label' => $this->availability_label,
            'cta_primary_text' => $this->cta_primary_text,
            'cta_primary_link' => $this->cta_primary_link,
            'cta_secondary_text' => $this->cta_secondary_text,
            'cta_secondary_link' => $this->cta_secondary_link,
            'image_path' => $this->image_path,
            'image_alt' => $this->image_alt,
            'social_links' => $this->social_links ?? [],
            'email' => $this->email,
            'cv_path' => $this->cv_path,
            'updated_at' => $this->updated_at,
        ];
    }
}
