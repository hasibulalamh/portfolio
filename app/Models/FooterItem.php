<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FooterItem extends Model
{
    protected $fillable = [
        'type',
        'name',
        'value',
        'icon',
        'category',
        'is_active',
        'order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc');
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // Helper
    public static function createForSection(array $data)
    {
        return self::firstOrCreate(
            [
                'name' => $data['name'],
                'category' => $data['category']
            ],
            [
                'type' => $data['type'],
                'value' => $data['value'],
                'icon' => $data['icon'] ?? null,
                'is_active' => $data['is_active'] ?? false,
                'order' => $data['order'] ?? 0,
            ]
        );
    }
}
