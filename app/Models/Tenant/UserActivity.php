<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserActivity extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tenant_user_activities';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'activity_type',
        'description',
        'subject_type',
        'subject_id',
        'properties',
        'ip_address',
        'user_agent',
        'location',
        'is_read',
        'read_at'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'properties' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime'
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

    /**
     * Get the user that owns the activity.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the subject of the activity (polymorphic).
     */
    public function subject()
    {
        return $this->morphTo();
    }

    /**
     * Mark the activity as read.
     */
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now()
        ]);
    }

    /**
     * Mark the activity as unread.
     */
    public function markAsUnread()
    {
        $this->update([
            'is_read' => false,
            'read_at' => null
        ]);
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
