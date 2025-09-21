<?php

namespace BonVet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use DateTime;

class Pet extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'species',
        'breed',
        'gender',
        'birth_date',
        'weight',
        'color',
        'description',
        'photo_url',
        'is_active'
    ];

    protected $casts = [
        'birth_date' => 'date',
        'weight' => 'decimal:2',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class);
    }

    public function qrTokens(): HasMany
    {
        return $this->hasMany(QrToken::class);
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'fileable');
    }

    public function getAgeAttribute(): ?string
    {
        if (!$this->birth_date) {
            return null;
        }
        $now = new DateTime(date('Y-m-d'));
        $birthDate = new DateTime($this->birth_date);

        $diff = $now->diff($birthDate);

        $years = $diff->y;
        $months = $diff->m;
        $days = $diff->d;

        if ($years > 0) {
            return $years . ' año' . ($years > 1 ? 's' : '') . 
                ($months > 0 ? ' y ' . $months . ' mes' . ($months > 1 ? 'es' : '') : '');
        } elseif ($months > 0) {
            return $months . ' mes' . ($months > 1 ? 'es' : '');
        } else {
            return $days . ' día' . ($days > 1 ? 's' : '');
        }
    }

    public function toArray(): array
    {
        $array = parent::toArray();
        $array['age'] = $this->getAgeAttribute();
        return $array;
    }
}