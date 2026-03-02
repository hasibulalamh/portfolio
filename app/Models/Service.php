<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Service extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'icon',
        'short_description',
        'detailed_description',
        'scope_type',
        'scope_description',
        'pricing_note',
        'min_duration',
        'max_duration',
        'technologies',
        'is_featured',
        'is_active',
        'order',
    ];

    protected $casts = [
        'technologies' => 'array',
        'is_featured'  => 'boolean',
        'is_active'    => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($service) {
            if (empty($service->slug)) {
                $service->slug = Str::slug($service->title);
            }
        });
    }

    public function getDurationAttribute(): ?string
    {
        if ($this->min_duration && $this->max_duration) {
            return "{$this->min_duration} - {$this->max_duration}";
        }
        return $this->min_duration ?? $this->max_duration ?? null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderBy('created_at');
    }
}
