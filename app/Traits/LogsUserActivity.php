<?php

namespace App\Traits;

use App\Models\Tenant\UserActivity;
use App\Models\Tenant\UserNotification;

trait LogsUserActivity
{
  /**
  * Log user activity
  *
  * @param string $activityType create|update|delete|soft_delete|restore
  * @param string $tableName
  * @param int|null $recordId
  * @param string $description
  * @return void
  */
  protected function logUserActivity(string $activityType, string $tableName, ?int $recordId, string $description)
  {
    if (auth()->user()) {
      $userActivity = new UserActivity();
      $userActivity->user_id = auth()->user()->id;
      $userActivity->activity_type = $activityType;
      $userActivity->ip_address = request()->ip();
      $userActivity->user_agent = request()->header('User-Agent');
      $userActivity->table_name = $tableName;
      $userActivity->record_id = $recordId;
      $userActivity->description = $description;
      $userActivity->is_read = false;
      $userActivity->save();
    }
  }
  
  /**
  * Create user notification
  *
  * @param string $message
  * @return void
  */
  protected function createUserNotification(string $message)
  {
    if (auth()->user()) {
      $userNotification = new UserNotification();
      $userNotification->user_id = auth()->user()->id;
      $userNotification->notification_type = 'system';
      $userNotification->message = $message;
      $userNotification->ip_address = request()->ip();
      $userNotification->user_agent = request()->header('User-Agent');
      $userNotification->is_read = false;
      $userNotification->save();
    }
  }
  
  /**
  * Log both user activity and notification
  *
  * @param string $activityType
  * @param string $tableName
  * @param int $recordId
  * @param string $description
  * @param string $notificationMessage
  * @return void
  */
  protected function logUserActivityAndNotification(
    string $activityType,
    string $tableName,
    int $recordId,
    string $description,
    string $notificationMessage
  ) {
    $this->logUserActivity($activityType, $tableName, $recordId, $description);
    $this->createUserNotification($notificationMessage);
  }
  
  /**
  * Log user login activity
  *
  * @return void
  */
  public function logLoginActivity()
  {
    if (auth()->user()) {
      $this->logUserActivity(
        'login',
        'users',
        auth()->user()->id,
        'User logged in'
      );
    }
  }
  
  /**
  * Log user logout activity
  *
  * @return void
  */
  public function logLogoutActivity()
  {
    if (auth()->user()) {
      $this->logUserActivity(
        'logout',
        'users',
        auth()->user()->id,
        'User logged out'
      );
    }
  }

  /**
   * Log password reset activity
   *
   * @param \App\Models\User $user
   * @return void
   */
  public function logPasswordResetActivity($user)
  {
    $this->logUserActivity(
      'password_reset',
      'users',
      $user->id,
      'User password reset'
    );
  }

  /**
   * Get activity logs for a specific model
   *
   * @param string $tableName
   * @param int $recordId
   * @return \Illuminate\Database\Eloquent\Collection
   */
  public function getActivityLogs($tableName, $recordId)
  {
    return UserActivity::where('table_name', $tableName)
        ->where('record_id', $recordId)
        ->orderBy('created_at', 'desc')
        ->get();
  }
}