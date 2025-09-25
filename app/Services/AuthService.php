<?php

namespace BonVet\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use BonVet\Models\User;

class AuthService
{
    private string $jwtSecret;
    private int $jwtTtl;

    public function __construct(string $jwtSecret, int $jwtTtl)
    {
        $this->jwtSecret = $jwtSecret;
        $this->jwtTtl = $jwtTtl;
    }

    public function register(array $data): array
    {
        // Verificar si el correo ya existe
        if (User::where('correo', $data['correo'])->exists()) {
            throw new \Exception('El correo ya está registrado');
        }

        // Crear usuario
        $user = User::create([
            'nombre' => trim($data['nombre']),
            'apellidos' => trim($data['apellidos']),
            'correo' => $data['correo'],
            'contrasena' => password_hash($data['contrasena'], PASSWORD_DEFAULT),
            'domicilio' => trim($data['domicilio']),
            'ciudad' => trim($data['ciudad']),
            'provincia' => trim($data['provincia']),
            'cp' => trim($data['cp']),
        ]);

        // Generar token
        $token = $this->generateToken($user);

        return [
            'user' => $user->toArray(),
            'token' => $token
        ];
    }

    public function login(string $correo, string $contrasena): array
    {
        // Buscar usuario por correo
        $user = User::where('correo', $correo)->first();
        
        if (!$user || !password_verify($contrasena, $user->contrasena)) {
            throw new \Exception('Credenciales incorrectas');
        }

        // Generar token
        $token = $this->generateToken($user);

        return [
            'user' => $user->toArray(),
            'token' => $token
        ];
    }

    public function generateToken(User $user): string
    {
        $payload = [
            'user_id' => $user->id,
            'correo' => $user->correo,
            'iat' => time(),
            'exp' => time() + ($this->jwtTtl * 60)
        ];

        return JWT::encode($payload, $this->jwtSecret, 'HS256');
    }

    public function validateToken(string $token): ?User
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            return User::find($decoded->user_id);
        } catch (\Exception $e) {
            return null;
        }
    }
}