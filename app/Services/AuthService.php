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
        // Verificar si el email ya existe
        if (User::where('email', $data['email'])->exists()) {
            throw new \Exception('El email ya está registrado');
        }

        // Crear usuario
        $user = User::create([
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'] ?? null,
        ]);

        // Generar token
        $token = $this->generateToken($user);

        return [
            'user' => $user->toArray(),
            'token' => $token
        ];
    }

    public function login(string $email, string $password): array
    {
        // Buscar usuario por email
        $user = User::where('email', $email)->first();
        
        if (!$user || !password_verify($password, $user->password)) {
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
            'email' => $user->email,
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