<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeroSetting extends Model
{
    protected $fillable = [
        'name',
        'tagline',
        'description',
        'photo',
        'resume_url',
        'primary_cta_text',
        'primary_cta_url',
        'secondary_cta_text',
        'secondary_cta_url',
        'show_photo',
        'show_social_links',
        'is_active',
    ];

    protected $casts = [
        'show_photo' => 'boolean',
        'show_social_links' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Get active hero settings
    public static function getActive()
    {
        return self::where('is_active', true)->first();
    }
}
