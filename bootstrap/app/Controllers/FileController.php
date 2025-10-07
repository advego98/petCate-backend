<?php

namespace BonVet\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use BonVet\Models\File;
use BonVet\Services\FileService;

class FileController
{
    private FileService $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $uuid = $args['uuid'];

        $file = File::where('uuid', $uuid)->first();

        if (!$file) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Archivo no encontrado'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        try {
            $content = $this->fileService->getFileContent($file);

            $response->getBody()->write($content);
            
            return $response
                ->withHeader('Content-Type', $file->mime_type)
                ->withHeader('Content-Length', (string)$file->size)
                ->withHeader('Content-Disposition', 'inline; filename="' . $file->original_name . '"')
                ->withHeader('Cache-Control', 'public, max-age=3600');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));

            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
    }

    public function download(Request $request, Response $response, array $args): Response
    {
        $uuid = $args['uuid'];

        $file = File::where('uuid', $uuid)->first();

        if (!$file) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Archivo no encontrado'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        try {
            $content = $this->fileService->getFileContent($file);

            $response->getBody()->write($content);
            
            return $response
                ->withHeader('Content-Type', 'application/octet-stream')
                ->withHeader('Content-Length', (string)$file->size)
                ->withHeader('Content-Disposition', 'attachment; filename="' . $file->original_name . '"');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));

            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $uuid = $args['uuid'];
        $userId = $request->getAttribute('user_id');

        $file = File::where('uuid', $uuid)->first();

        if (!$file) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Archivo no encontrado'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Verificar permisos - el archivo debe pertenecer a una entidad del usuario
        $hasPermission = false;
        
        if ($file->fileable_type === 'BonVet\\Models\\User' && $file->fileable_id == $userId) {
            $hasPermission = true;
        } elseif ($file->fileable_type === 'BonVet\\Models\\Pet') {
            $pet = $file->fileable;
            if ($pet && $pet->user_id == $userId) {
                $hasPermission = true;
            }
        } elseif ($file->fileable_type === 'BonVet\\Models\\MedicalRecord') {
            $record = $file->fileable;
            if ($record && $record->pet && $record->pet->user_id == $userId) {
                $hasPermission = true;
            }
        }

        if (!$hasPermission) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'No tienes permisos para eliminar este archivo'
            ]));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        try {
            $this->fileService->deleteFile($file);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Archivo eliminado exitosamente'
            ]));

            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));

            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
    }
}