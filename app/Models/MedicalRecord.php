<?php

namespace BonVet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class MedicalRecord extends Model
{
    protected $fillable = [
        'pet_id',
        'type',
        'title',
        'description',
        'record_date',
        'veterinary_clinic',
        'veterinarian_name',
        'weight_at_visit',
        'notes',
        'metadata'
    ];

    protected $casts = [
        'record_date' => 'date',
        'weight_at_visit' => 'decimal:2',
        'metadata' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'fileable');
    }

    public static function getTypes(): array
    {
        return [
            'vaccination' => 'Vacunación',
            'checkup' => 'Revisión',
            'surgery' => 'Cirugía',
            'medication' => 'Medicación',
            'emergency' => 'Emergencia',
            'diagnostic' => 'Diagnóstico',
            'treatment' => 'Tratamiento',
            'other' => 'Otro'
        ];
    }
}