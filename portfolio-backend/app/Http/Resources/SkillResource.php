<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SkillResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'skill_category_id' => $this->skill_category_id,
            'name' => $this->name,
            'icon' => $this->icon,
            // Simple Icons slug, e.g. "laravel". Rendered client-side from the
            // slug; `icon` remains the fallback when this is null.
            'icon_slug' => $this->icon_slug,
            'logo_type' => $this->logo_type ?: 'library',
            'logo_url' => $this->logo_url,
            'order' => $this->order,
        ];
    }
}
