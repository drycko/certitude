<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentralSetting extends Model
{
    // The table associated with the model.
    protected $table = 'central_settings';
    
    // Model properties can be defined here
    protected $fillable = [
        'key',
        'value',
        'is_serialized',
    ];

    protected $casts = [
        'value' => 'string',
        'is_serialized' => 'boolean',
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
        $this->attributes['value'] = $this->is_serialized ? serialize($value) : $value;
    }

    // Get a setting by key
    public static function getSetting($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    // Set a setting by key
    public static function setSetting($key, $value, $isSerialized = false)
    {
        return self::updateOrCreate(
            ['key' => $key],
            ['value' => $isSerialized ? serialize($value) : $value, 'is_serialized' => $isSerialized]
        );
    }

    // Get multiple settings as an associative array
    public static function getSettings(array $keys): array
    {
        $settings = self::whereIn('key', $keys)->get();
        return $settings->pluck('value', 'key')->toArray();
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
        $setting = self::where('key', $key)->first();
        return $setting ? decrypt($setting->value) : $default;
    }

    // Set an encrypted setting
    public static function setEncryptedSetting($key, $value)
    {
        return self::updateOrCreate(
            ['key' => $key],
            ['value' => encrypt($value), 'is_serialized' => false]
        );
    }

    public static function getValue($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public static function setValue($key, $value)
    {
        return self::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    public static function deleteByKey($key)
    {
        return self::where('key', $key)->delete();
    }

    public static function allSettings()
    {
        return self::all()->pluck('value', 'key')->toArray();
    }

    public function __toString()
    {
        return $this->value;
    }

    public function isEmpty(): bool
    {
        return empty($this->value);
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function scopeByKey($query, $key)
    {
        return $query->where('key', $key);
    }

    public function scopeNotKey($query, $key)
    {
        return $query->where('key', '!=', $key);
    }

    public function scopeWithValue($query)
    {
        return $query->whereNotNull('value')->where('value', '!=', '');
    }
}
