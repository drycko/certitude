<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

trait LogsAdminActivity
{
    /**
     * Log an admin activity.
     *
     * @param string $action The action performed (create, update, delete, etc.)
     * @param string $model The model type (tenants, users, etc.)
     * @param mixed $modelId The ID of the affected model
     * @param string $description A human-readable description of the activity
     * @return void
     */
    protected function logAdminActivity(string $action, string $model, $modelId, string $description)
    {
        $user = Auth::user();
        
        $logData = [
            'admin_id' => $user ? $user->id : null,
            'admin_email' => $user ? $user->email : 'system',
            'action' => $action,
            'model' => $model,
            'model_id' => $modelId,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toDateTimeString(),
        ];

        Log::channel('admin_activity')->info($description, $logData);
    }

    /**
     * Create an admin notification (can be expanded to database notifications).
     *
     * @param string $message The notification message
     * @return void
     */
    protected function createAdminNotification(string $message)
    {
        // For now, just log it. Later you can implement database notifications
        Log::channel('admin_notifications')->info($message, [
            'admin_id' => Auth::id(),
            'admin_email' => Auth::user()->email ?? 'system',
            'timestamp' => now()->toDateTimeString(),
        ]);

        // TODO: Implement database notifications
        // Notification::create([
        //     'type' => 'admin_action',
        //     'notifiable_type' => 'App\Models\User',
        //     'notifiable_id' => Auth::id(),
        //     'data' => ['message' => $message],
        // ]);
    }
}
