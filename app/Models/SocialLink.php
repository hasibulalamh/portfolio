<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialLink extends Model
{
    protected $fillable = [
        'platform',
        'url',
        'icon',
        'color',
        'order',
        'show_in_hero',
        'show_in_contact',
        'show_in_footer',
        'is_active',
    ];

    protected $casts = [
        'show_in_hero'    => 'boolean',
        'show_in_contact' => 'boolean',
        'show_in_footer'  => 'boolean',
        'is_active'       => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('order');
    }

    public function scopeHero($query)
    {
        return $query->where('show_in_hero', true);
    }

    public function scopeContact($query)
    {
        return $query->where('show_in_contact', true);
    }

    public function scopeFooter($query)
    {
        return $query->where('show_in_footer', true);
    }
}
