<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiShowcaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'icon_name' => $this->icon_name,
            // Simple Icons slug, e.g. "laravel". Rendered client-side from the
            // slug; `icon_name` remains the lucide fallback when this is null.
            'icon_slug' => $this->icon_slug,
            'logo_type' => $this->logo_type ?: 'library',
            'logo_url' => $this->logo_url,
            'title' => $this->title,
            'description' => $this->description,
            'endpoints' => $this->endpoints ?? [],
            'order' => $this->order,
        ];
    }
}
