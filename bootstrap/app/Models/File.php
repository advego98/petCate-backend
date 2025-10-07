<?php

namespace BonVet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class File extends Model
{
    protected $fillable = [
        'uuid',
        'fileable_type',
        'fileable_id',
        'original_name',
        'filename',
        'mime_type',
        'size',
        'path',
        'disk'
    ];

    protected $casts = [
        'size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getUrlAttribute(): string
    {
        return '/api/files/' . $this->uuid;
    }

    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->size;
        
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

    public function isImage(): bool
    {
        return in_array($this->mime_type, ['image/jpeg', 'image/png', 'image/webp']);
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function toArray(): array
    {
        $array = parent::toArray();
        $array['url'] = $this->getUrlAttribute();
        $array['human_size'] = $this->getHumanSizeAttribute();
        $array['is_image'] = $this->isImage();
        $array['is_pdf'] = $this->isPdf();
        return $array;
    }
}