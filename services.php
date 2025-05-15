<?php

declare(strict_types=1);

use Plugin\ExactTime\AudioManager;
use Plugin\ExactTime\Controller\Admin\ExactTimeController as AdminExactTimeController;
use Plugin\ExactTime\Controller\Stations\ExactTimeController as StationsExactTimeController;
use Plugin\ExactTime\Controller\Api\ExactTimeController as ApiExactTimeController;

return [
    // Registrar AudioManager no container de DI
    AudioManager::class => function ($di) {
        return new AudioManager(
            $di,
            $di->get(\App\Entity\Repository\StationMediaRepository::class),
            $di->get(\App\Flysystem\StationFilesystems::class),
            $di->get(\Psr\Log\LoggerInterface::class)
        );
    },
    
    // Registrar controladores no container de DI
    AdminExactTimeController::class => function ($di) {
        return new AdminExactTimeController(
            $di->get(\App\Entity\Repository\StationRepository::class),
            $di->get(\App\Environment::class),
            $di->get(\App\Flysystem\StationFilesystems::class)
        );
    },
    
    StationsExactTimeController::class => function ($di) {
        return new StationsExactTimeController(
            $di->get(\App\Flysystem\StationFilesystems::class),
            $di->get(AudioManager::class)
        );
    },
    
    ApiExactTimeController::class => function ($di) {
        return new ApiExactTimeController(
            $di->get(\App\Entity\Repository\StationRepository::class),
            $di->get(AudioManager::class)
        );
    },
];