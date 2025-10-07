<?php

namespace BonVet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class User extends Model
{
    protected $fillable = [
        'nombre',
        'apellidos',
        'correo',
        'contrasena',
        'domicilio',
        'ciudad',
        'provincia',
        'cp',
    ];  

    protected $hidden = [
        'contrasena'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class);
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'fileable');
    }

    public function getFullNameAttribute(): string
    {
        return $this->nombre . ' ' . $this->apellido;
    }

    public function toArray(): array
    {
        $array = parent::toArray();
        $array['full_name'] = $this->getFullNameAttribute();
        return $array;
    }
}