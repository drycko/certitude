<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fbo extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'type',
        'ggn',
        'description',
        'is_active',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function documentsMany(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'document_fbo');
    }

    public function growers(): BelongsToMany
    {
        return $this->belongsToMany(Grower::class, 'grower_fbos', 'fbo_id', 'grower_id')->withTimestamps();
    }

    /**
     * Check if this is a Production Unit Code (PUC)
     */
    public function isPUC(): bool
    {
        return $this->type === 'PUC';
    }

    /**
     * Check if this is a Pack House Code (PHC)
     */
    public function isPHC(): bool
    {
        return $this->type === 'PHC';
    }

    /**
     * Check if this is a Chain of Custody (COC)
     */
    public function isCOC(): bool
    {
        return $this->type === 'COC';
    }

    /**
     * Get legacy metadata value by key
     */
    public function getLegacyMetadata($key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Set legacy metadata value
     */
    public function setLegacyMetadata($key, $value): void
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->metadata = $metadata;
    }

    /**
     * Scope to filter by FBO type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get only PUC codes
     */
    public function scopePUC($query)
    {
        return $query->where('type', 'PUC');
    }

    /**
     * Scope to get only PHC codes
     */
    public function scopePHC($query)
    {
        return $query->where('type', 'PHC');
    }

    /**
     * Scope to get only COC codes
     */
    public function scopeCOC($query)
    {
        return $query->where('type', 'COC');
    }

    /**
     * Scope active FBOs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by GGN (GlobalGAP Number)
     */
    public function scopeByGGN($query, $ggn)
    {
        return $query->where('ggn', $ggn);
    }

    /**
     * Get the full display name with code and type
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->code} - {$this->name} ({$this->type})";
    }
}