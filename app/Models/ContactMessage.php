<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $fillable = [
    'name',
    'email',
    'subject',
    'message',
    'status',
    'ip_address',
    'user_agent',
    'read_at',
    'project_details_sent',
    'project_details_sent_at',
];

protected $casts = [
    'read_at' => 'datetime',
    'project_details_sent' => 'boolean',
    'project_details_sent_at' => 'datetime',       
];

    // Scopes
    public function scopeUnread($query)
    {
        return $query->where('status', 'unread');
    }

    public function scopeRead($query)
    {
        return $query->where('status', 'read');
    }

    // Mark as read
    public function markAsRead()
    {
        $this->update([
            'status' => 'read',
            'read_at' => now(),
        ]);
    }
}
