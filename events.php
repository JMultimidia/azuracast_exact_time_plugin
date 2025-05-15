<?php

declare(strict_types=1);

use Plugin\HoraCerta\Controller\HoraCertaController;

return function (\Azura\SlimCallableEventDispatcher\SlimCallableEventDispatcher $dispatcher) {
    // Registrar rotas do plugin
    $dispatcher->addListener(\App\Event\BuildRoutes::class, function(\App\Event\BuildRoutes $event) {
        $app = $event->getApp();
        
        // Grupo de rotas para o plugin da Hora Certa
        $app->group('/admin/plugins/hora-certa', function(\Slim\Routing\RouteCollectorProxy $group) {
            $group->get('', HoraCertaController::class . ':indexAction')
                ->setName('plugins:hora-certa:index');
            
            $group->get('/configure/{station_id}', HoraCertaController::class . ':configureAction')
                ->setName('plugins:hora-certa:configure');
            
            $group->post('/configure/{station_id}', HoraCertaController::class . ':configureProcessAction')
                ->setName('plugins:hora-certa:configure:process');
            
            $group->post('/toggle/{station_id}', HoraCertaController::class . ':toggleAction')
                ->setName('plugins:hora-certa:toggle');
            
            $group->post('/test/{station_id}', HoraCertaController::class . ':testAction')
                ->setName('plugins:hora-certa:test');
                
            $group->get('/download-audios', HoraCertaController::class . ':downloadAudiosAction')
                ->setName('plugins:hora-certa:download-audios');
        });
    }, -5);
    
    // Registrar link no menu administrativo
    $dispatcher->addListener(\App\Event\BuildAdminMenu::class, function(\App\Event\BuildAdminMenu $event) {
        $menu = $event->getMenu();
        
        // Adicionar item no menu plugins
        $pluginMenu = $menu->getChild('plugins');
        if (!$pluginMenu) {
            $pluginMenu = $menu->addChild('plugins', ['label' => __('Plugins')]);
        }
        
        $pluginMenu->addChild('hora_certa', [
            'label' => __('Hora Certa'),
            'uri' => '/admin/plugins/hora-certa',
            'extras' => [
                'icon' => 'fas fa-clock',
            ],
        ]);
    });
};