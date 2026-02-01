<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NavbarItem extends Model
{
    protected $fillable = [
        'name',
        'href',
        'section_type',
        'icon',
        'is_active',
         'order'
        ];
        protected $casts = [
            'is_active' => 'boolean',
        ];
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc');
    }

    public static function createForSection(string $name, string $href, string $sectionType, string $order = "0")
    {
        return self::firstOrCreate(
            ['section_type' => $sectionType],
            ['name' => $name,
                'href' => $href,
                'is_active' => false,
                'order' => $order,
        ]);
    }
}
