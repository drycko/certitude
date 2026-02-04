<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantSetting extends Model
{
    // The table associated with the model.
    protected $table = 'tenant_settings';

    // The attributes that are mass assignable.
    protected $fillable = [
        'setting_key',
        'setting_value',
        'is_serialized',
    ];

    // Accessors & Mutators
    public function getSettingValueAttribute($value)
    {
        if ($this->is_serialized) {
            return unserialize($value);
        }
        return $value;
    }

    public function setSettingValueAttribute($value)
    {
        $this->attributes['setting_value'] = $this->is_serialized ? serialize($value) : $value;
    }

    // Get a setting by key
    public static function getSetting($key, $default = null)
    {
        $setting = self::where('setting_key', $key)->first();
        return $setting ? $setting->setting_value : $default;
    }

    // Set a setting by key
    public static function setSetting($key, $value, $isSerialized = false)
    {
        return self::updateOrCreate(
            ['setting_key' => $key],
            ['setting_value' => $isSerialized ? serialize($value) : $value, 'is_serialized' => $isSerialized]
        );
    }

    // Get multiple settings as an associative array
    public static function getSettings(array $keys): array
    {
        $settings = self::whereIn('setting_key', $keys)->get();
        return $settings->pluck('setting_value', 'setting_key')->toArray();
    }

    // Set multiple settings at once
    public static function setSettings(array $keyValuePairs)
    {
        foreach ($keyValuePairs as $key => $value) {
            self::setSetting($key, $value);
        }
    }

    // Get an encrypted setting
    public static function getEncryptedSetting($key, $default = null)
    {
        $setting = self::where('setting_key', $key)->first();
        return $setting ? decrypt($setting->setting_value) : $default;
    }

    // Set an encrypted setting
    public static function setEncryptedSetting($key, $value)
    {
        return self::updateOrCreate(
            ['setting_key' => $key],
            ['setting_value' => encrypt($value), 'is_serialized' => false]
        );
    }

    // Get all settings as an associative array
    public static function allSettings()
    {
        return self::all()->pluck('setting_value', 'setting_key')->toArray();
    }
}
