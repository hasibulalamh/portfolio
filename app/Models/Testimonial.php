<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    protected $fillable = [
        'client_name',
        'client_role',
        'client_company',
        'client_photo',
        'content',
        'rating',
        'project_type',
        'date',
        'order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'date'      => 'date',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderBy('created_at', 'desc');
    }
}
