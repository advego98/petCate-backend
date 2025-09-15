<?php

namespace BonVet\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use BonVet\Models\Pet;
use BonVet\Models\MedicalRecord;
use BonVet\Services\FileService;

class MedicalRecordController
{
    private FileService $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    public function index(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $petId = $args['petId'];

        // Verificar que la mascota pertenece al usuario
        $pet = Pet::where('id', $petId)->where('user_id', $userId)->first();
        
        if (!$pet) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Mascota no encontrada'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $records = MedicalRecord::where('pet_id', $petId)
            ->with(['files'])
            ->orderBy('record_date', 'desc')
            ->get();

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $records->toArray()
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $petId = $args['petId'];
        $recordId = $args['recordId'];

        // Verificar que la mascota pertenece al usuario
        $pet = Pet::where('id', $petId)->where('user_id', $userId)->first();
        
        if (!$pet) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Mascota no encontrada'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $record = MedicalRecord::where('id', $recordId)
            ->where('pet_id', $petId)
            ->with(['files', 'pet'])
            ->first();

        if (!$record) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Registro médico no encontrado'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $record->toArray()
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function store(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $petId = $args['petId'];
        $data = $request->getParsedBody();

        // Verificar que la mascota pertenece al usuario
        $pet = Pet::where('id', $petId)->where('user_id', $userId)->first();
        
        if (!$pet) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Mascota no encontrada'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        try {
            $record = MedicalRecord::create([
                'pet_id' => $petId,
                'type' => $data['type'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'record_date' => $data['record_date'],
                'veterinary_clinic' => $data['veterinary_clinic'] ?? null,
                'veterinarian_name' => $data['veterinarian_name'] ?? null,
                'weight_at_visit' => $data['weight_at_visit'] ?? null,
                'notes' => $data['notes'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Registro médico creado exitosamente',
                'data' => $record->toArray()
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
        $petId = $args['petId'];
        $recordId = $args['recordId'];
        $data = $request->getParsedBody();

        // Verificar que la mascota pertenece al usuario
        $pet = Pet::where('id', $petId)->where('user_id', $userId)->first();
        
        if (!$pet) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Mascota no encontrada'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $record = MedicalRecord::where('id', $recordId)->where('pet_id', $petId)->first();

        if (!$record) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Registro médico no encontrado'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        try {
            $record->update($data);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Registro médico actualizado exitosamente',
                'data' => $record->fresh()->toArray()
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
        $petId = $args['petId'];
        $recordId = $args['recordId'];

        // Verificar que la mascota pertenece al usuario
        $pet = Pet::where('id', $petId)->where('user_id', $userId)->first();
        
        if (!$pet) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Mascota no encontrada'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $record = MedicalRecord::where('id', $recordId)->where('pet_id', $petId)->first();

        if (!$record) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Registro médico no encontrado'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        try {
            // Eliminar archivos asociados
            $files = $record->files()->get();
            foreach ($files as $file) {
                $this->fileService->deleteFile($file);
            }

            // Eliminar registro
            $record->delete();

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Registro médico eliminado exitosamente'
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

    public function uploadFiles(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $petId = $args['petId'];
        $recordId = $args['recordId'];
        $uploadedFiles = $request->getUploadedFiles();

        // Verificar que la mascota pertenece al usuario
        $pet = Pet::where('id', $petId)->where('user_id', $userId)->first();
        
        if (!$pet) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Mascota no encontrada'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $record = MedicalRecord::where('id', $recordId)->where('pet_id', $petId)->first();

        if (!$record) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Registro médico no encontrado'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        if (empty($uploadedFiles['files'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'No se proporcionaron archivos'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $uploadedFileList = $uploadedFiles['files'];
            $savedFiles = [];

            // Manejar múltiples archivos
            if (!is_array($uploadedFileList)) {
                $uploadedFileList = [$uploadedFileList];
            }

            foreach ($uploadedFileList as $uploadedFile) {
                $file = $this->fileService->uploadFile($uploadedFile, $record, 'records');
                $savedFiles[] = $file->toArray();
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Archivos subidos exitosamente',
                'data' => $savedFiles
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