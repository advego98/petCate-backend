<?php

namespace BonVet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class File extends Model
{
    protected $fillable = [
        'uuid',
        'tipo_archivo',      // antes: fileable_type
        'id_archivo',        // antes: fileable_id
        'nombre_original',   // antes: original_name
        'nombre_archivo',    // antes: filename
        'tipo_mime',         // antes: mime_type
        'tamaño',            // antes: size
        'ruta',              // antes: path
        'disco'              // antes: disk
    ];

    protected $casts = [
        'tamaño' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function fileable(): MorphTo
    {
        return $this->morphTo('fileable', 'tipo_archivo', 'id_archivo');
    }

    public function getUrlAttribute(): string
    {
        return '/api/files/' . $this->uuid;
    }

    public function getTamanoLegibleAttribute(): string
    {
        $bytes = $this->tamaño;
        
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    public function esImagen(): bool
    {
        return in_array($this->tipo_mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
    }

    public function esPdf(): bool
    {
        return $this->tipo_mime === 'application/pdf';
    }

    public function toArray(): array
    {
        $array = parent::toArray();
        $array['url'] = $this->getUrlAttribute();
        $array['tamano_legible'] = $this->getTamanoLegibleAttribute();
        $array['es_imagen'] = $this->esImagen();
        $array['es_pdf'] = $this->esPdf();
        return $array;
    }
}