<?php

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

// Load environment
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Setup database
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => $_ENV['DB_DRIVER'] ?? 'mysql',
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'port' => $_ENV['DB_PORT'] ?? 3306,
    'database' => $_ENV['DB_DATABASE'] ?? 'bonvet',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

$schema = Capsule::schema();

echo "Running migrations...\n";

// Create users table
if (!$schema->hasTable('users')) {
    $schema->create('users', function (Blueprint $table) {
        $table->id();
        $table->string('email')->unique();
        $table->string('password');
        $table->string('first_name');
        $table->string('last_name');
        $table->string('phone')->nullable();
        $table->string('avatar_url')->nullable();
        $table->timestamp('email_verified_at')->nullable();
        $table->timestamps();
        
        $table->index(['email']);
    });
    echo "✓ Users table created\n";
}

// Create pets table
if (!$schema->hasTable('pets')) {
    $schema->create('pets', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->string('name');
        $table->string('species'); // dog, cat, bird, etc.
        $table->string('breed')->nullable();
        $table->enum('gender', ['male', 'female']);
        $table->date('birth_date')->nullable();
        $table->decimal('weight', 5, 2)->nullable(); // kg
        $table->string('color')->nullable();
        $table->text('description')->nullable();
        $table->string('photo_url')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();
        
        $table->index(['user_id', 'is_active']);
    });
    echo "✓ Pets table created\n";
}

// Create medical_records table
if (!$schema->hasTable('medical_records')) {
    $schema->create('medical_records', function (Blueprint $table) {
        $table->id();
        $table->foreignId('pet_id')->constrained()->onDelete('cascade');
        $table->string('type'); // vaccination, checkup, surgery, medication, etc.
        $table->string('title');
        $table->text('description')->nullable();
        $table->date('record_date');
        $table->string('veterinary_clinic')->nullable();
        $table->string('veterinarian_name')->nullable();
        $table->decimal('weight_at_visit', 5, 2)->nullable();
        $table->text('notes')->nullable();
        $table->json('metadata')->nullable(); // Additional structured data
        $table->timestamps();
        
        $table->index(['pet_id', 'record_date']);
        $table->index(['type']);
    });
    echo "✓ Medical records table created\n";
}

// Create files table (polymorphic)
if (!$schema->hasTable('files')) {
    $schema->create('files', function (Blueprint $table) {
        $table->id();
        $table->string('uuid')->unique();
        $table->morphs('fileable'); // fileable_type, fileable_id
        $table->string('original_name');
        $table->string('filename');
        $table->string('mime_type');
        $table->unsignedBigInteger('size');
        $table->string('path');
        $table->string('disk')->default('local');
        $table->timestamps();
        
        $table->index(['fileable_type', 'fileable_id']);
        $table->index(['uuid']);
    });
    echo "✓ Files table created\n";
}

// Create qr_tokens table
if (!$schema->hasTable('qr_tokens')) {
    $schema->create('qr_tokens', function (Blueprint $table) {
        $table->id();
        $table->string('token')->unique();
        $table->foreignId('pet_id')->constrained()->onDelete('cascade');
        $table->timestamp('expires_at');
        $table->boolean('is_active')->default(true);
        $table->timestamp('last_used_at')->nullable();
        $table->string('created_by_ip')->nullable();
        $table->timestamps();
        
        $table->index(['token', 'is_active']);
        $table->index(['pet_id']);
        $table->index(['expires_at']);
    });
    echo "✓ QR tokens table created\n";
}

// Create storage directories
$storageDirs = [
    __DIR__ . '/../storage/logs',
    __DIR__ . '/../storage/users',
    __DIR__ . '/../storage/pets',
    __DIR__ . '/../storage/records'
];

foreach ($storageDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "✓ Created directory: $dir\n";
    }
}

echo "\nMigrations completed successfully!\n";