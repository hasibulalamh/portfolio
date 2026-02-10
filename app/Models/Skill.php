<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    protected $fillable = [
        'name',
        'icon',
        'category',
        'proficiency',
        'color',
        'order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'proficiency' => 'integer',
        'order' => 'integer',
    ];

    // Scopes (ADD THESE IF MISSING)
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }
}
