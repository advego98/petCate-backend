<?php

namespace BonVet\Tests;

use PHPUnit\Framework\TestCase;
use BonVet\Services\AuthService;
use BonVet\Models\User;
use Illuminate\Database\Capsule\Manager as Capsule;

class AuthTest extends TestCase
{
    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup in-memory SQLite database for testing
        $capsule = new Capsule;
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        // Create tables
        Capsule::schema()->create('users', function ($table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone')->nullable();
            $table->string('avatar_url')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });

        $this->authService = new AuthService('test-secret', 60);
    }

    public function testUserRegistration()
    {
        $userData = [
            'email' => 'test@example.com',
            'password' => 'password123',
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '123456789'
        ];

        $result = $this->authService->register($userData);

        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertEquals('test@example.com', $result['user']['email']);
        $this->assertNotEmpty($result['token']);
    }

    public function testUserLogin()
    {
        // First register a user
        $userData = [
            'email' => 'login@example.com',
            'password' => 'password123',
            'first_name' => 'Login',
            'last_name' => 'Test'
        ];

        $this->authService->register($userData);

        // Now try to login
        $result = $this->authService->login('login@example.com', 'password123');

        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertEquals('login@example.com', $result['user']['email']);
    }

    public function testInvalidLogin()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Credenciales incorrectas');

        $this->authService->login('nonexistent@example.com', 'wrongpassword');
    }

    public function testTokenValidation()
    {
        // Register and get token
        $userData = [
            'email' => 'token@example.com',
            'password' => 'password123',
            'first_name' => 'Token',
            'last_name' => 'Test'
        ];

        $result = $this->authService->register($userData);
        $token = $result['token'];

        // Validate token
        $user = $this->authService->validateToken($token);

        $this->assertNotNull($user);
        $this->assertEquals('token@example.com', $user->email);
    }

    public function testInvalidToken()
    {
        $user = $this->authService->validateToken('invalid-token');
        $this->assertNull($user);
    }
}