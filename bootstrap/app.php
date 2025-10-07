<?php

use Slim\Factory\AppFactory;
use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container as IlluminateContainer;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use BonVet\Services\AuthService;
use BonVet\Services\FileService;
use BonVet\Services\QrService;

$basePath = defined('BASE_PATH') ? BASE_PATH : __DIR__ . '/..';
$baseUrl = defined('BASE_URL') ? BASE_URL : '';

// Actualizar todas las rutas que usen __DIR__ por $basePath:




require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Create Container
$containerBuilder = new ContainerBuilder();

// Add DI definitions
$containerBuilder->addDefinitions([
    'logger' => function () {
        Global $basePath;
        $logger = new Logger('bonvet');
        $logPath = $basePath . '/storage/logs/app.log';
        $logDir = dirname($logPath);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logger->pushHandler(new StreamHandler($logPath, Logger::DEBUG));
        return $logger;
    },
    
    'jwt_secret' => $_ENV['JWT_SECRET'] ?? 'default-secret-change-this',
    'jwt_ttl' => (int) ($_ENV['JWT_TTL_MINUTES'] ?? 1440),
    'qr_ttl' => (int) ($_ENV['QR_TOKEN_TTL_MINUTES'] ?? 15),
    
    'upload_config' => [
        'max_size' => (int) ($_ENV['UPLOAD_MAX_SIZE_MB'] ?? 10) * 1024 * 1024,
        'allowed_mime' => explode(',', $_ENV['UPLOAD_ALLOWED_MIME'] ?? 'image/jpeg,image/png,image/webp,application/pdf'),
        'storage_path' => $basePath . '/storage',
    ],

    // Services
    AuthService::class => \DI\factory(function (\DI\Container $c) {
        return new AuthService(
            $c->get('jwt_secret'),
            $c->get('jwt_ttl')
        );
    }),

    FileService::class => \DI\factory(function (\DI\Container $c) {
        return new FileService($c->get('upload_config'));
    }),

    QrService::class => \DI\factory(function (\DI\Container $c) {
        $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost:8000';
        return new QrService($c->get('qr_ttl'), $baseUrl);
    }),
]);

$container = $containerBuilder->build();

// Create App
AppFactory::setContainer($container);
$app = AppFactory::create();

// Setup Database
$capsule = new Capsule;

$dbConfig = require __DIR__ . '/../config/database.php';
$capsule->addConnection($dbConfig['connections'][$dbConfig['default']]);

// Set up events dispatcher for Eloquent
$capsule->setEventDispatcher(new Dispatcher(new IlluminateContainer));

$capsule->setAsGlobal();
$capsule->bootEloquent();

// Add error middleware
$errorMiddleware = $app->addErrorMiddleware(
    $_ENV['APP_DEBUG'] === 'true',
    true,
    true
);

// Custom error handler
$errorHandler = $errorMiddleware->getDefaultErrorHandler();
$errorHandler->registerErrorRenderer('application/json', function (\Throwable $exception) {
    return json_encode([
        'success' => false,
        'error' => $exception->getMessage(),
        'code' => $exception->getCode()
    ]);
});

// Add CORS middleware
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
});

// Handle preflight OPTIONS requests
$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

// Add routing middleware
$app->addRoutingMiddleware();

return $app;