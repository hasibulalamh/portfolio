<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Skill extends Model
{
    protected $fillable = [
        'skill_category_id',
        'name',
        'icon',
        'icon_slug',
        'logo_type',
        'logo_url',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'skill_category_id' => 'integer',
            'order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SkillCategory::class, 'skill_category_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order')->orderBy('id');
    }
}
