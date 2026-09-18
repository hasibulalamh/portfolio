<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ApiShowcase extends Model
{
    protected $fillable = [
        'icon_name',
        'icon_slug',
        'logo_type',
        'logo_url',
        'title',
        'description',
        'endpoints',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'endpoints' => 'array',
            'order' => 'integer',
        ];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order')->orderBy('id');
    }
}
