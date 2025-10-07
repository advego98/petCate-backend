<?php

namespace BonVet\Services;

use BonVet\Models\File;
use Psr\Http\Message\UploadedFileInterface;
use Ramsey\Uuid\Uuid;

class FileService
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function uploadFile(
        UploadedFileInterface $uploadedFile, 
        $fileable, 
        string $directory = 'general'
    ): File {
        // Validar archivo
        $this->validateFile($uploadedFile);

        // Generar nombres únicos
        $uuid = Uuid::uuid4()->toString();
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $filename = $uuid . '.' . $extension;
        
        // Crear directorio si no existe
        $uploadPath = $this->config['storage_path'] . '/' . $directory;
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        // Mover archivo
        $filePath = $uploadPath . '/' . $filename;
        $uploadedFile->moveTo($filePath);

        // Crear registro en base de datos
        return File::create([
            'uuid' => $uuid,
            'fileable_type' => get_class($fileable),
            'fileable_id' => $fileable->id,
            'original_name' => $uploadedFile->getClientFilename(),
            'filename' => $filename,
            'mime_type' => $uploadedFile->getClientMediaType(),
            'size' => $uploadedFile->getSize(),
            'path' => $directory . '/' . $filename,
            'disk' => 'local'
        ]);
    }

    public function deleteFile(File $file): bool
    {
        $fullPath = $this->config['storage_path'] . '/' . $file->path;
        
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        return $file->delete();
    }

    public function getFileContent(File $file): string
    {
        $fullPath = $this->config['storage_path'] . '/' . $file->path;
        
        if (!file_exists($fullPath)) {
            throw new \Exception('Archivo no encontrado');
        }

        return file_get_contents($fullPath);
    }

    private function validateFile(UploadedFileInterface $uploadedFile): void
    {
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            throw new \Exception('Error al subir el archivo');
        }

        if ($uploadedFile->getSize() > $this->config['max_size']) {
            throw new \Exception('El archivo es demasiado grande');
        }

        if (!in_array($uploadedFile->getClientMediaType(), $this->config['allowed_mime'])) {
            throw new \Exception('Tipo de archivo no permitido');
        }
    }
}