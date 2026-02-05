<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
      protected $fillable = [
        'name',
        'category',
        'icon',
        'color',
        'proficiency',
        'order',
        'is_featured',
        'is_active',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderBy('name');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    // Available categories
    public static function getCategories()
    {
        return [
            'frontend' => 'Frontend',
            'backend' => 'Backend',
            'database' => 'Database',
            'tools' => 'Tools & Others',
        ];
    }
}
