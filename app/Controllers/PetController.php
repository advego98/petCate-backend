<?php

namespace BonVet\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use BonVet\Models\Pet;
use BonVet\Services\FileService;

class PetController
{
    private FileService $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    public function index(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        
        $pets = Pet::where('user_id', $userId)
            ->where('is_active', true)
            ->with(['files'])
            ->orderBy('created_at', 'desc')
            ->get();

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $pets->toArray()
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $petId = $args['id'];

        $pet = Pet::where('id', $petId)
            ->where('user_id', $userId)
            ->with(['files', 'medicalRecords.files'])
            ->first();

        if (!$pet) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Mascota no encontrada'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $pet->toArray()
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function store(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        try {
            $pet = Pet::create([
                'user_id' => $userId,
                'name' => $data['name'],
                'species' => $data['species'],
                'breed' => $data['breed'] ?? null,
                'gender' => $data['gender'],
                'birth_date' => $data['birth_date'] ?? null,
                'weight' => $data['weight'] ?? null,
                'color' => $data['color'] ?? null,
                'description' => $data['description'] ?? null,
            ]);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Mascota creada exitosamente',
                'data' => $pet->toArray()
            ]));

            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));

            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $petId = $args['id'];
        $data = $request->getParsedBody();

        $pet = Pet::where('id', $petId)->where('user_id', $userId)->first();

        if (!$pet) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Mascota no encontrada'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        try {
            $pet->update($data);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Mascota actualizada exitosamente',
                'data' => $pet->fresh()->toArray()
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

    public function delete(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $petId = $args['id'];

        $pet = Pet::where('id', $petId)->where('user_id', $userId)->first();

        if (!$pet) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Mascota no encontrada'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        try {
            // Soft delete - marcar como inactiva
            $pet->update(['is_active' => false]);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Mascota eliminada exitosamente'
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

    public function uploadPhoto(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $petId = $args['id'];
        $uploadedFiles = $request->getUploadedFiles();

        $pet = Pet::where('id', $petId)->where('user_id', $userId)->first();

        if (!$pet) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Mascota no encontrada'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        if (!isset($uploadedFiles['photo'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'No se proporcionó archivo de foto'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            // Eliminar foto anterior si existe
            $oldFiles = $pet->files()->get();
            foreach ($oldFiles as $file) {
                $this->fileService->deleteFile($file);
            }

            // Subir nueva foto
            $file = $this->fileService->uploadFile($uploadedFiles['photo'], $pet, 'pets');
            
            // Actualizar URL de la foto en la mascota
            $pet->update(['photo_url' => $file->url]);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Foto actualizada exitosamente',
                'data' => [
                    'photo_url' => $file->url,
                    'file' => $file->toArray()
                ]
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