<?php
/**
 * BonVet API - Entry Point para Subdirectorio
 * URL Base: https://pablomonteserin.com/sites/borrame-bonvet
 */

// Definir rutas base
define('BASE_PATH', __DIR__);
define('BASE_URL', '/sites/borrame-bonvet');  // ← Importante para rutas

// Verificar composer
if (!file_exists(BASE_PATH . '/vendor/autoload.php')) {
    http_response_code(500);
    die(json_encode([
        'error' => 'Dependencias no instaladas. Ejecuta: composer install'
    ]));
}

// Cargar autoloader
require BASE_PATH . '/vendor/autoload.php';

// Cargar variables de entorno
try {
    $dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
    $dotenv->load();
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode([
        'error' => 'Error cargando .env: ' . $e->getMessage()
    ]));
}

// Bootstrap de la aplicación
try {
    $app = require BASE_PATH . '/bootstrap/app.php';
    
    // IMPORTANTE: Configurar base path para Slim
    $app->setBasePath(BASE_URL);
    
    // Cargar rutas
    $routes = require BASE_PATH . '/routes/api.php';
    $routes($app);
    
    // Ejecutar aplicación
    $app->run();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error en la aplicación',
        'message' => $_ENV['APP_DEBUG'] === 'true' ? $e->getMessage() : 'Error interno del servidor'
    ]);
}
