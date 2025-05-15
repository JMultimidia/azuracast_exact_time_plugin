<?php

declare(strict_types=1);

use App\Environment;
use App\Entity\Repository\StationRepository;
use Plugin\HoraCerta\Controller\HoraCertaController;
use Plugin\HoraCerta\Service\HoraCertaService;

return function (\DI\Container $container) {
    $container->set(HoraCertaService::class, function (Container $container) {
        return new HoraCertaService(
            $container->get(Environment::class)
        );
    });
    
    $container->set(HoraCertaController::class, function (Container $container) {
        return new HoraCertaController(
            $container->get(Environment::class),
            $container->get(StationRepository::class),
            $container->get(HoraCertaService::class)
        );
    });
};