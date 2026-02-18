<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Experience extends Model
{
    protected $fillable = [
        'type',
        'title',
        'company',
        'company_logo',
        'location',
        'start_date',
        'end_date',
        'is_current',
        'description',
        'responsibilities',
        'technologies',
        'achievements',
        'website_url',
        'is_active',
        'order',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
        'is_active' => 'boolean',
        'responsibilities' => 'array',
        'technologies' => 'array',
        'achievements' => 'array',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order')
            ->orderByDesc('start_date');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    // Accessors
    public function getDurationAttribute()
    {
        $start = $this->start_date;
        $end = $this->is_current ? Carbon::now() : $this->end_date;

        if (!$end) {
            return 'Present';
        }

        $diff = $start->diffInMonths($end);
        $years = floor($diff / 12);
        $months = $diff % 12;

        $duration = [];
        if ($years > 0) {
            $duration[] = $years . ' ' . ($years > 1 ? 'years' : 'year');
        }
        if ($months > 0) {
            $duration[] = $months . ' ' . ($months > 1 ? 'months' : 'month');
        }

        return implode(' ', $duration) ?: '1 month';
    }

    public function getFormattedDateRangeAttribute()
    {
        $start = $this->start_date->format('M Y');
        $end = $this->is_current ? 'Present' : ($this->end_date ? $this->end_date->format('M Y') : 'Present');

        return $start . ' - ' . $end;
    }
}
