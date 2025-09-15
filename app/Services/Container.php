<?php

namespace BonVet\Services;

use DI\ContainerBuilder;
use BonVet\Services\AuthService;
use BonVet\Services\FileService;
use BonVet\Services\QrService;
use BonVet\Controllers\AuthController;
use BonVet\Controllers\PetController;
use BonVet\Controllers\MedicalRecordController;
use BonVet\Controllers\QrController;
use BonVet\Controllers\FileController;

class Container
{
    public static function build(): \DI\Container
    {
        $containerBuilder = new ContainerBuilder();

        $containerBuilder->addDefinitions([
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
                return new QrService($c->get('qr_ttl'));
            }),

            // Controllers
            AuthController::class => \DI\factory(function (\DI\Container $c) {
                return new AuthController(
                    $c->get(AuthService::class),
                    $c->get(FileService::class)
                );
            }),

            PetController::class => \DI\factory(function (\DI\Container $c) {
                return new PetController($c->get(FileService::class));
            }),

            MedicalRecordController::class => \DI\factory(function (\DI\Container $c) {
                return new MedicalRecordController($c->get(FileService::class));
            }),

            QrController::class => \DI\factory(function (\DI\Container $c) {
                return new QrController($c->get(QrService::class));
            }),

            FileController::class => \DI\factory(function (\DI\Container $c) {
                return new FileController($c->get(FileService::class));
            }),
        ]);

        return $containerBuilder->build();
    }
}