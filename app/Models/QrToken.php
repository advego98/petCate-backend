<?php

namespace BonVet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrToken extends Model
{
    protected $fillable = [
        'token',
        'pet_id',
        'expires_at',
        'is_active',
        'last_used_at',
        'created_by_ip'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    public function getRemainingTimeAttribute(): ?string
    {
        if ($this->isExpired()) {
            return null;
        }

        $now = now();
        $diff = $now->diff($this->expires_at);
        
        if ($diff->h > 0) {
            return $diff->h . 'h ' . $diff->i . 'm';
        } elseif ($diff->i > 0) {
            return $diff->i . 'm';
        } else {
            return '< 1m';
        }
    }

    public function toArray(): array
    {
        $array = parent::toArray();
        $array['is_expired'] = $this->isExpired();
        $array['is_valid'] = $this->isValid();
        $array['remaining_time'] = $this->getRemainingTimeAttribute();
        return $array;
    }
}