<?php

namespace BonVet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class MedicalRecord extends Model
{
    protected $fillable = [
        'mascota_id',
        'tipo',
        'titulo',
        'descripción',
        'fecha',
        'clinica',
        'nombre',
        'peso_visita',
        'notas',
        'metadata '
    ];

    protected $casts = [
        'fecha' => 'date',
        'peso_visita' => 'decimal:2',
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