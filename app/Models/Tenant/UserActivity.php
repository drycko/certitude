<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserActivity extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tenant_user_activities';
    
    // Model properties and relationships can be defined here
    protected $fillable = [
        'user_id',
        'activity_type',
        'ip_address',
        'user_agent',
        'table_name',
        'record_id',
        'description',
        'is_read',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'record_id' => 'integer',
        'is_read' => 'boolean',
    ];

    /**
     * Define activity types
     */
    const ACTIVITY_TYPES = [
        'LOGIN' => 'login',
        'LOGOUT' => 'logout',
        'CREATE' => 'create',
        'UPDATE' => 'update',
        'DELETE' => 'delete',
        'BOOKING' => 'booking',
        'ROOM_CHANGE' => 'room_change',
        'PAYMENT' => 'payment',
        'SETTINGS' => 'settings'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function admin()
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

    /**
     * Scope a query to only include unread activities.
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope a query to only include read activities.
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * Scope a query to only include activities of a specific type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('activity_type', $type);
    }

    /**
     * Create a new activity log entry.
     */
    public static function log($userId, $type, $description, $subject = null, $properties = [])
    {
        $activity = new static;
        $activity->tenant_user_id = $userId;
        $activity->activity_type = $type;
        $activity->description = $description;
        
        if ($subject) {
            $activity->subject_type = get_class($subject);
            $activity->subject_id = $subject->id;
        }

        $activity->properties = $properties;
        $activity->ip_address = request()->ip();
        $activity->user_agent = request()->userAgent();
        // You might want to use a geolocation service here
        $activity->location = null;
        
        $activity->save();

        return $activity;
    }
}
