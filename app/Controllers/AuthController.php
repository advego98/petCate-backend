<?php

namespace BonVet\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use BonVet\Services\AuthService;
use BonVet\Services\FileService;
use BonVet\Models\User;

class AuthController
{
    private AuthService $authService;
    private FileService $fileService;

    public function __construct(AuthService $authService, FileService $fileService)
    {
        $this->authService = $authService;
        $this->fileService = $fileService;
    }

    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        try {
            $result = $this->authService->register($data);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Usuario registrado exitosamente',
                'data' => $result
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

    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        try {
            $result = $this->authService->login($data['email'], $data['password']);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Inicio de sesión exitoso',
                'data' => $result
            ]));
            
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
            
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }
    }

    public function me(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $user->toArray()
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function uploadAvatar(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $uploadedFiles = $request->getUploadedFiles();

        if (!isset($uploadedFiles['avatar'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'No se proporcionó archivo de avatar'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            // Eliminar avatar anterior si existe
            $oldFiles = $user->files()->get();
            foreach ($oldFiles as $file) {
                $this->fileService->deleteFile($file);
            }

            // Subir nuevo avatar
            $file = $this->fileService->uploadFile($uploadedFiles['avatar'], $user, 'users');
            
            // Actualizar URL del avatar en el usuario
            $user->update(['avatar_url' => $file->url]);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Avatar actualizado exitosamente',
                'data' => [
                    'avatar_url' => $file->url,
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