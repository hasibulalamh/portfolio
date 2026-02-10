<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AboutSetting extends Model
{
    protected $fillable = [
        'title',
        'subtitle',
        'bio',
        'image',
        'years_experience',
        'projects_completed',
        'happy_clients',
        'technologies_count',
        'highlights',
        'show_image',
        'show_stats',
        'is_active',
    ];

    protected $casts = [
        'highlights' => 'array',
        'show_image' => 'boolean',
        'show_stats' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Get active about settings (ADD THIS METHOD)
    public static function getActive()
    {
        return self::where('is_active', true)->first();
    }
}
