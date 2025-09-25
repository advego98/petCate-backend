<?php

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use BonVet\Controllers\AuthController;
use BonVet\Controllers\PetController;
use BonVet\Controllers\MedicalRecordController;
use BonVet\Controllers\QrController;
use BonVet\Controllers\FileController;
use BonVet\Middlewares\AuthMiddleware;
use BonVet\Middlewares\ValidationMiddleware;

return function (App $app) {
    $container = $app->getContainer();

    // Instanciar servicios
    $authService = new \BonVet\Services\AuthService(
        $container->get('jwt_secret'),
        $container->get('jwt_ttl')
    );
    
    $fileService = new \BonVet\Services\FileService(
        $container->get('upload_config')
    );
    
    $qrService = new \BonVet\Services\QrService(
        $container->get('qr_ttl')
    );

    // Instanciar controladores
    $authController = new AuthController($authService, $fileService);
    $petController = new PetController($fileService);
    $medicalRecordController = new MedicalRecordController($fileService);
    $qrController = new QrController($qrService);
    $fileController = new FileController($fileService);

    // Rutas públicas de autenticación
    $app->group('/auth', function (RouteCollectorProxy $group) use ($authController) {
        $group->post('/register', [$authController, 'register'])
            ->add(new ValidationMiddleware([
                'nombre' => 'required|min:2|max:50',
                'apellidos' => 'required|min:2|max:50',
                'correo' => 'required|email',
                'contrasena' => 'required|min:6',
                'domicilio' => 'required|min:3|max:255',
                'ciudad' => 'required|min:3|max:255',
                'provincia' => 'required|min:3|max:255',
                'cp' => 'required|min:5|max:10'
            ]));

        $group->post('/login', [$authController, 'login'])
            ->add(new ValidationMiddleware([
                'correo' => 'required|email',
                'contrasena' => 'required'
            ]));
    });

    // Rutas protegidas de autenticación
    $app->group('/auth', function (RouteCollectorProxy $group) use ($authController) {
        $group->get('/me', [$authController, 'me']);
        $group->patch('/me/avatar', [$authController, 'uploadAvatar']);
    })->add(new AuthMiddleware($container));

    // Rutas de mascotas
    $app->group('/pets', function (RouteCollectorProxy $group) use ($petController) {
        $group->get('', [$petController, 'index']);
        $group->post('', [$petController, 'store'])
            ->add(new ValidationMiddleware([
            'nombre' => 'required|min:1|max:255',
            'especie' => 'required|in:perro,gato,ave,conejo,hamster,pez,reptil,otro',
            'raza' => 'required|min:1|max:255',
            'fecha_nacimiento' => 'required', // Validación personalizada en controller
            'genero' => 'required|in:macho,hembra',
            'peso' => 'required|numeric',
            'chip' => 'required', // Validación personalizada en controller
            'observaciones' => 'max:1000' // Opcional
        ]));
            
        $group->get('/{id}', [$petController, 'show']);
        $group->patch('/{id}', [$petController, 'update']);
        $group->delete('/{id}', [$petController, 'delete']);
        $group->patch('/{id}/foto', [$petController, 'uploadPhoto']); // Cambio: photo -> foto
        
        // Rutas auxiliares
        $group->get('/especies/lista', [$petController, 'getEspecies']); // GET /pets/especies/lista
        $group->post('/chip/validar', [$petController, 'validarChip']); // POST /pets/chip/validar
        
    })->add(new AuthMiddleware($container));

    // Rutas de registros médicos
    $app->group('/pets/{petId}/records', function (RouteCollectorProxy $group) use ($medicalRecordController) {
        $group->get('', [$medicalRecordController, 'index']);
        $group->post('', [$medicalRecordController, 'store'])
            ->add(new ValidationMiddleware([
                'type' => 'required|in:vaccination,checkup,surgery,medication,emergency,diagnostic,treatment,other',
                'title' => 'required|min:1|max:200',
                'record_date' => 'required|date',
                'veterinary_clinic' => 'max:200',
                'veterinarian_name' => 'max:200',
                'weight_at_visit' => 'numeric'
            ]));
        
        $group->get('/{recordId}', [$medicalRecordController, 'show']);
        $group->patch('/{recordId}', [$medicalRecordController, 'update']);
        $group->delete('/{recordId}', [$medicalRecordController, 'delete']);
        $group->post('/{recordId}/files', [$medicalRecordController, 'uploadFiles']);
    })->add(new AuthMiddleware($container));

    // Rutas de QR
    $app->group('/pets/{petId}', function (RouteCollectorProxy $group) use ($qrController) {
        $group->post('/qr', [$qrController, 'generateQr']);
        $group->delete('/qr', [$qrController, 'deactivateQr']);
        $group->get('/qr/tokens', [$qrController, 'getActiveTokens']);
    })->add(new AuthMiddleware($container));

    // Ruta pública para acceso por QR (sin autenticación)
    $app->get('/qr/access/{token}/records', [$qrController, 'accessRecords']);

    // Rutas de archivos
    $app->group('/files', function (RouteCollectorProxy $group) use ($fileController) {
        $group->get('/{uuid}', [$fileController, 'show']);
        $group->get('/{uuid}/download', [$fileController, 'download']);
        $group->delete('/{uuid}', [$fileController, 'delete']);
    });

    // Ruta de documentación Swagger
    $app->get('/docs', function ($request, $response) {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <title>BonVet API Documentation</title>
            <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@4.15.5/swagger-ui.css" />
        </head>
        <body>
            <div id="swagger-ui"></div>
            <script src="https://unpkg.com/swagger-ui-dist@4.15.5/swagger-ui-bundle.js"></script>
            <script>
                SwaggerUIBundle({
                    url: "/openapi.yaml",
                    dom_id: "#swagger-ui"
                });
            </script>
        </body>
        </html>';
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    });

    // Servir archivo OpenAPI
    $app->get('/openapi.yaml', function ($request, $response) {
        $yamlPath = __DIR__ . '/../openapi.yaml';
        
        if (!file_exists($yamlPath)) {
            $response->getBody()->write('OpenAPI file not found');
            return $response->withStatus(404);
        }
        
        $yamlContent = file_get_contents($yamlPath);
        $response->getBody()->write($yamlContent);
        return $response->withHeader('Content-Type', 'application/x-yaml');
    });

    // Ruta de salud
    $app->get('/health', function ($request, $response) {
        $response->getBody()->write(json_encode([
            'status' => 'healthy',
            'timestamp' => date('c'),
            'version' => '1.0.0'
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    });
};