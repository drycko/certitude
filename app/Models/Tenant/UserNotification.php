<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    // Model properties and relationships can be defined here
    protected $fillable = [
        'user_id',
        'notification_type',
        'message',
        'ip_address',
        'user_agent',
        'is_read',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'is_read' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function markAsRead()
    {
        $this->is_read = true;
        $this->save();
    }

    public function markAsUnread()
    {
        $this->is_read = false;
        $this->save();
    }
}
