<?php

namespace BonVet\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use BonVet\Models\Pet;
use BonVet\Models\MedicalRecord;
use BonVet\Services\QrService;
use DateTime;

class QrController
{
    private QrService $qrService;

    public function __construct(QrService $qrService)
    {
        $this->qrService = $qrService;
    }

    public function generateQr(Request $request, Response $response, array $args): Response
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

        try {
            // Obtener IP del cliente
            $serverParams = $request->getServerParams();
            $clientIp = $serverParams['HTTP_X_FORWARDED_FOR'] ?? $serverParams['REMOTE_ADDR'] ?? null;

            $qrData = $this->qrService->generateQrForPet($pet, $clientIp);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Código QR generado exitosamente',
                'data' => $qrData
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

    public function accessRecords(Request $request, Response $response, array $args): Response
    {
        $token = $args['token'];

        try {
            // Validar token
            $qrToken = $this->qrService->validateToken($token);

            if (!$qrToken) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Token inválido o expirado'
                ]));
                return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
            }

            // Obtener mascota y registros médicos
            $pet = $qrToken->pet()->with(['user', 'files'])->first();
            $records = MedicalRecord::where('pet_id', $pet->id)
                ->with(['files'])
                ->orderBy('record_date', 'desc')
                ->get();

            // Preparar datos para vista pública (solo lectura)
            $publicData = [
                'pet' => [
                    'name' => $pet->name,
                    'species' => $pet->species,
                    'breed' => $pet->breed,
                    'gender' => $pet->gender,
                    'birth_date' => $pet->birth_date,
                    'age' => $pet->age,
                    'weight' => $pet->weight,
                    'color' => $pet->color,
                    'description' => $pet->description,
                    'photo_url' => $pet->photo_url,
                ],
                'owner' => [
                    'full_name' => $pet->user->full_name,
                    'phone' => $pet->user->phone,
                ],
                'medical_records' => $records->map(function ($record) {
                    return [
                        'id' => $record->id,
                        'type' => $record->type,
                        'title' => $record->title,
                        'description' => $record->description,
                        'record_date' => $record->record_date,
                        'veterinary_clinic' => $record->veterinary_clinic,
                        'veterinarian_name' => $record->veterinarian_name,
                        'weight_at_visit' => $record->weight_at_visit,
                        'notes' => $record->notes,
                        'files' => $record->files->toArray(),
                        'created_at' => $record->created_at,
                    ];
                })->toArray(),
                'access_info' => [
                    'token' => $token,
                    'expires_at' => $qrToken->expires_at,
                    'remaining_time' => $qrToken->remaining_time,
                    'accessed_at' => new DateTime(),
                ]
            ];

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Acceso concedido a registros médicos',
                'data' => $publicData
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

    public function deactivateQr(Request $request, Response $response, array $args): Response
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

        try {
            // Desactivar todos los tokens activos de la mascota
            $pet->qrTokens()->where('is_active', true)->update(['is_active' => false]);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Códigos QR desactivados exitosamente'
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

    public function getActiveTokens(Request $request, Response $response, array $args): Response
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

        $activeTokens = $pet->qrTokens()
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->get();

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $activeTokens->toArray()
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}