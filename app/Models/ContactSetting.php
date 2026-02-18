<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactSetting extends Model
{
    protected $fillable = [
        'title',
        'subtitle',
        'description',
        'email',
        'phone',
        'address',
        'availability_status',
        'availability_message',
        'show_form',
        'is_active',
    ];

    protected $casts = [
        'show_form' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Get active contact settings
    public static function getActive()
    {
        return self::where('is_active', true)->first();
    }
}
