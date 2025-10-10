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
              // Validar campos obligatorios
            $camposObligatorios = ['nombre', 'especie', 'raza', 'fecha_nacimiento', 'genero', 'peso', 'chip'];
            foreach ($camposObligatorios as $campo) {
                if (!isset($data[$campo]) || trim($data[$campo]) === '') {
                    throw new \Exception("El campo '$campo' es obligatorio");
                }
            }

            // Validar especie
            $especiesValidas = array_keys(Pet::getEspeciesValidas());
            if (!in_array($data['especie'], $especiesValidas)) {
                throw new \Exception('Especie no válida. Opciones: ' . implode(', ', $especiesValidas));
            }

            // Validar género
            if (!in_array($data['genero'], Pet::getGenerosValidos())) {
                throw new \Exception('Género no válido. Opciones: macho, hembra');
            }

            // Validar y convertir fecha de nacimiento
            $fechaNacimiento = $this->convertirFecha($data['fecha_nacimiento']);
            if (!$fechaNacimiento) {
                throw new \Exception('Formato de fecha inválido. Use dd/mm/yyyy');
            }

            // Verificar que la fecha no sea futura
            $now = new \DateTime();
            if ($fechaNacimiento > $now) {
                throw new \Exception('La fecha de nacimiento no puede ser futura');
            }

            // Validar peso
            $peso = (float) $data['peso'];
            if ($peso <= 0) {
                throw new \Exception('El peso debe ser mayor a 0');
            }

            // Validar chip
            $chip = trim($data['chip']);
            $validacionChip = Pet::validarChip($chip);
            if (!$validacionChip['valido']) {
                throw new \Exception('Chip inválido: ' . implode(', ', $validacionChip['errores']));
            }

            // Verificar que el chip no esté duplicado
            if (Pet::where('chip', $chip)->exists()) {
                throw new \Exception('Ya existe una mascota con este número de chip');
            }

            // Crear mascota
            $pet = Pet::create([
                'user_id' => $userId,
                'nombre' => trim($data['nombre']),
                'especie' => $data['especie'],
                'raza' => trim($data['raza']),
                'fecha_nacimiento' => $fechaNacimiento->format('Y-m-d'),
                'genero' => $data['genero'],
                'peso' => $peso,
                'chip' => $chip,
                'observaciones' => isset($data['observaciones']) ? trim($data['observaciones']) : null,
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
        $input = $request->getBody()->getContents();
        parse_str($input, $data);

        $pet = Pet::where('id', $petId)->where('user_id', $userId)->first();

        if (!$pet) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Mascota no encontrada'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        try {
            $updateData = [];

            // Validar y procesar cada campo (igual que en store, pero todos opcionales)
            if (!empty($data['nombre'])) {
                if (strlen(trim($data['nombre'])) === 0) {
                    throw new \Exception('El nombre no puede estar vacío');
                }
                $updateData['nombre'] = trim($data['nombre']);
            }

            if (!empty($data['especie'])) {
                $especiesValidas = array_keys(Pet::getEspeciesValidas());
                if (!in_array($data['especie'], $especiesValidas)) {
                    throw new \Exception('Especie no válida. Opciones: ' . implode(', ', $especiesValidas));
                }
                $updateData['especie'] = $data['especie'];
            }

            if (!empty($data['raza'])) {
                $updateData['raza'] = trim($data['raza']);
            }

            if (!empty($data['fecha_nacimiento'])) {
                $fechaNacimiento = $this->convertirFecha($data['fecha_nacimiento']);
                if (!$fechaNacimiento) {
                    throw new \Exception('Formato de fecha inválido. Use dd/mm/yyyy');
                }
                
                $now = new \DateTime();
                if ($fechaNacimiento > $now) {
                    throw new \Exception('La fecha de nacimiento no puede ser futura');
                }
                
                $updateData['fecha_nacimiento'] = $fechaNacimiento->format('Y-m-d');
            }

            if (!empty($data['genero'])) {
                if (!in_array($data['genero'], Pet::getGenerosValidos())) {
                    throw new \Exception('Género no válido. Opciones: macho, hembra');
                }
                $updateData['genero'] = $data['genero'];
            }

            if (!empty($data['peso'])) {
                $peso = (float) $data['peso'];
                if ($peso <= 0) {
                    throw new \Exception('El peso debe ser mayor a 0');
                }
                $updateData['peso'] = $peso;
            }

            // Bloquear actualización de chip
            if (isset($data['chip']) && !empty($data['chip'])) {
                throw new \Exception('El chip no puede ser modificado una vez asignado a la mascota');
            }

            // Observaciones: permitir vaciar el campo
            if (isset($data['observaciones'])) {
                $updateData['observaciones'] = !empty($data['observaciones']) ? trim($data['observaciones']) : null;
            }

            // Actualizar solo si hay campos válidos
            if (!empty($updateData)) {
                $pet->update($updateData);
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Mascota actualizada exitosamente',
                'data' => $pet->fresh()->toArray(),
                'updated_fields' => array_keys($updateData)
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

        if (!isset($uploadedFiles['foto'])) {
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
            $file = $this->fileService->uploadFile($uploadedFiles['foto'], $pet, 'pets');
            
            // Actualizar URL de la foto en la mascota
            $pet->foto_url = $file->url;
            $pet->save();

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Foto actualizada exitosamente',
                'data' => [
                    'foto_url' => $file->url,
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

    // Método auxiliar para convertir fecha dd/mm/yyyy a DateTime
    private function convertirFecha(string $fecha): ?\DateTime
    {
        try {
            // Intentar formato dd/mm/yyyy
            $dateTime = \DateTime::createFromFormat('d/m/Y', $fecha);
            if ($dateTime && $dateTime->format('d/m/Y') === $fecha) {
                return $dateTime;
            }

            // Intentar formato yyyy-mm-dd (por si viene del frontend en este formato)
            $dateTime = \DateTime::createFromFormat('Y-m-d', $fecha);
            if ($dateTime && $dateTime->format('Y-m-d') === $fecha) {
                return $dateTime;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    // Endpoint para obtener especies válidas
    public function getEspecies(Request $request, Response $response): Response
    {
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => Pet::getEspeciesValidas()
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    // Endpoint para validar chip
    public function validarChip(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $chip = $data['chip'] ?? '';

        $validacion = Pet::validarChip($chip);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $validacion
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}