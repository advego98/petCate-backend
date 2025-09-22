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
        'especie',
        'raza',
        'fecha_nacimiento',
        'genero',
        'peso',
        'chip',
        'observaciones',
        'photo_url',
        'is_active'
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'peso' => 'decimal:2',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

        // Especies válidas
    public static function getEspeciesValidas(): array
    {
        return [
            'perro' => 'Perro',
            'gato' => 'Gato', 
            'ave' => 'Ave',
            'conejo' => 'Conejo',
            'hamster' => 'Hámster',
            'pez' => 'Pez',
            'reptil' => 'Reptil',
            'otro' => 'Otro'
        ];
    }

    // Géneros válidos
    public static function getGenerosValidos(): array
    {
        return ['macho', 'hembra'];
    }

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

    // Formatear fecha de nacimiento como dd/mm/yyyy
    public function getFechaNacimientoFormateadaAttribute(): ?string
    {
        if (!$this->fecha_nacimiento) {
            return null;
        }
        
        return $this->fecha_nacimiento->format('d/m/Y');
    }

    // Validar formato de chip
    public static function validarChip(string $chip): array
    {
        $errores = [];

        // Verificar longitud
        if (strlen($chip) !== 15) {
            $errores[] = 'El chip debe tener exactamente 15 caracteres';
        }

        // Verificar que solo sean números
        if (!ctype_digit($chip)) {
            $errores[] = 'El chip debe contener solo números';
        }

        // Verificar prefijo (primeros 3 dígitos)
        if (strlen($chip) >= 3) {
            $prefijo = (int) substr($chip, 0, 3);
            if ($prefijo < 900 || $prefijo > 985) {
                $errores[] = 'Los primeros 3 dígitos del chip deben estar entre 900 y 985';
            }
        }

        return [
            'valido' => empty($errores),
            'errores' => $errores
        ];
    }

    public function toArray(): array
    {
        $array = parent::toArray();
        return $array;
    }

        // Scope para buscar por chip
    public function scopePorChip($query, string $chip)
    {
        return $query->where('chip', $chip);
    }

     // Scope para mascotas activas
    public function scopeActivas($query)
    {
        return $query->where('is_active', true);
    }

    // Scope por especie
    public function scopePorEspecie($query, string $especie)
    {
        return $query->where('especie', $especie);
    }
}