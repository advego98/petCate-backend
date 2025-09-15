<?php

namespace BonVet\Tests;

use PHPUnit\Framework\TestCase;
use BonVet\Models\User;
use BonVet\Models\Pet;
use BonVet\Models\QrToken;
use BonVet\Services\QrService;
use Illuminate\Database\Capsule\Manager as Capsule;

class QrTest extends TestCase
{
    private QrService $qrService;

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
            $table->timestamps();
        });

        Capsule::schema()->create('pets', function ($table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('species');
            $table->string('breed')->nullable();
            $table->enum('gender', ['male', 'female']);
            $table->date('birth_date')->nullable();
            $table->decimal('weight', 5, 2)->nullable();
            $table->string('color')->nullable();
            $table->text('description')->nullable();
            $table->string('photo_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Capsule::schema()->create('qr_tokens', function ($table) {
            $table->id();
            $table->string('token')->unique();
            $table->foreignId('pet_id')->constrained()->onDelete('cascade');
            $table->timestamp('expires_at');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->string('created_by_ip')->nullable();
            $table->timestamps();
        });

        $this->qrService = new QrService(15); // 15 minutes TTL
    }

    public function testQrGeneration()
    {
        // Create user and pet
        $user = User::create([
            'email' => 'qrtest@example.com',
            'password' => 'hashed_password',
            'first_name' => 'QR',
            'last_name' => 'Test'
        ]);

        $pet = Pet::create([
            'user_id' => $user->id,
            'name' => 'QR Pet',
            'species' => 'dog',
            'gender' => 'male'
        ]);

        // Generate QR
        $qrData = $this->qrService->generateQrForPet($pet);

        $this->assertIsArray($qrData);
        $this->assertArrayHasKey('token', $qrData);
        $this->assertArrayHasKey('access_url', $qrData);
        $this->assertArrayHasKey('qr_code_base64', $qrData);
        $this->assertArrayHasKey('expires_at', $qrData);
        $this->assertEquals(15, $qrData['expires_in_minutes']);

        // Verify token was created in database
        $token = QrToken::where('token', $qrData['token'])->first();
        $this->assertNotNull($token);
        $this->assertEquals($pet->id, $token->pet_id);
        $this->assertTrue($token->is_active);
    }

    public function testTokenValidation()
    {
        // Create user and pet
        $user = User::create([
            'email' => 'tokentest@example.com',
            'password' => 'hashed_password',
            'first_name' => 'Token',
            'last_name' => 'Test'
        ]);

        $pet = Pet::create([
            'user_id' => $user->id,
            'name' => 'Token Pet',
            'species' => 'cat',
            'gender' => 'female'
        ]);

        // Generate QR token
        $qrData = $this->qrService->generateQrForPet($pet);
        $token = $qrData['token'];

        // Validate token
        $qrToken = $this->qrService->validateToken($token);

        $this->assertNotNull($qrToken);
        $this->assertEquals($pet->id, $qrToken->pet_id);
        $this->assertTrue($qrToken->isValid());
        $this->assertNotNull($qrToken->last_used_at);
    }

    public function testInvalidToken()
    {
        $qrToken = $this->qrService->validateToken('invalid-token');
        $this->assertNull($qrToken);
    }

    public function testExpiredToken()
    {
        // Create user and pet
        $user = User::create([
            'email' => 'expiredtest@example.com',
            'password' => 'hashed_password',
            'first_name' => 'Expired',
            'last_name' => 'Test'
        ]);

        $pet = Pet::create([
            'user_id' => $user->id,
            'name' => 'Expired Pet',
            'species' => 'dog',
            'gender' => 'male'
        ]);

        // Create an expired token manually
        $expiredToken = QrToken::create([
            'token' => 'expired-token-123',
            'pet_id' => $pet->id,
            'expires_at' => now()->subHours(1), // Expired 1 hour ago
            'is_active' => true
        ]);

        // Try to validate expired token
        $result = $this->qrService->validateToken('expired-token-123');
        $this->assertNull($result);
    }

    public function testTokenDeactivation()
    {
        // Create user and pet
        $user = User::create([
            'email' => 'deactivate@example.com',
            'password' => 'hashed_password',
            'first_name' => 'Deactivate',
            'last_name' => 'Test'
        ]);

        $pet = Pet::create([
            'user_id' => $user->id,
            'name' => 'Deactivate Pet',
            'species' => 'bird',
            'gender' => 'female'
        ]);

        // Generate QR token
        $qrData = $this->qrService->generateQrForPet($pet);
        $token = $qrData['token'];

        // Deactivate token
        $result = $this->qrService->deactivateToken($token);
        $this->assertTrue($result);

        // Try to validate deactivated token
        $qrToken = $this->qrService->validateToken($token);
        $this->assertNull($qrToken);
    }

    public function testPreviousTokensDeactivation()
    {
        // Create user and pet
        $user = User::create([
            'email' => 'previous@example.com',
            'password' => 'hashed_password',
            'first_name' => 'Previous',
            'last_name' => 'Test'
        ]);

        $pet = Pet::create([
            'user_id' => $user->id,
            'name' => 'Previous Pet',
            'species' => 'rabbit',
            'gender' => 'male'
        ]);

        // Generate first QR token
        $qrData1 = $this->qrService->generateQrForPet($pet);
        $token1 = $qrData1['token'];

        // Generate second QR token (should deactivate first one)
        $qrData2 = $this->qrService->generateQrForPet($pet);
        $token2 = $qrData2['token'];

        // First token should be deactivated
        $firstToken = QrToken::where('token', $token1)->first();
        $this->assertFalse($firstToken->is_active);

        // Second token should be active
        $secondToken = QrToken::where('token', $token2)->first();
        $this->assertTrue($secondToken->is_active);
    }
}