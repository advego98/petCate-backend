<?php

namespace BonVet\Middlewares;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Container\ContainerInterface;
use BonVet\Models\User;

class AuthMiddleware
{
    private string $jwtSecret;

    public function __construct(ContainerInterface $container)
    {
        $this->jwtSecret = $container->get('jwt_secret');
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader)) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode([
                'error' => 'Token no proporcionado'
            ]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        if (!str_starts_with($authHeader, 'Bearer ')) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode([
                'error' => 'Formato de token inválido'
            ]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $token = substr($authHeader, 7);

        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            
            // Verificar que el usuario existe
            $user = User::find($decoded->user_id);
            if (!$user) {
                throw new \Exception('Usuario no encontrado');
            }

            // Agregar el usuario al request
            $request = $request->withAttribute('user', $user);
            $request = $request->withAttribute('user_id', $user->id);

            return $handler->handle($request);
            
        } catch (\Exception $e) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode([
                'error' => 'Token inválido: ' . $e->getMessage()
            ]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }
    }
}