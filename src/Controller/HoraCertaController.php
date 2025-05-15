<?php

declare(strict_types=1);

namespace Plugin\HoraCerta\Controller;

use App\Controller\AbstractLoggedInAction;
use App\Http\Response;
use App\Http\ServerRequest;
use App\Entity\Repository\StationRepository;
use App\Environment;
use Plugin\HoraCerta\Service\HoraCertaService;
use Psr\Http\Message\ResponseInterface;

final class HoraCertaController extends AbstractLoggedInAction
{
    public function __construct(
        private readonly Environment $environment,
        private readonly StationRepository $stationRepo,
        private readonly HoraCertaService $horaCertaService
    ) {}

    public function indexAction(ServerRequest $request, Response $response): ResponseInterface
    {
        $stations = $this->stationRepo->findAll();
        $configurations = [];
        
        foreach ($stations as $station) {
            $configurations[$station->getId()] = $this->horaCertaService->getStationConfig($station->getShortName());
        }
        
        return $response->withView('plugins/hora-certa/index', [
            'stations' => $stations,
            'configurations' => $configurations,
        ]);
    }

    public function configureAction(ServerRequest $request, Response $response): ResponseInterface
    {
        $stationId = (int)$request->getAttribute('station_id');
        $station = $this->stationRepo->find($stationId);
        
        if (!$station) {
            throw new \App\Exception\NotFoundException('Station not found');
        }
        
        $config = $this->horaCertaService->getStationConfig($station->getShortName());
        
        return $response->withView('plugins/hora-certa/configure', [
            'station' => $station,
            'config' => $config,
            'timezones' => $this->horaCertaService->getTimezones(),
        ]);
    }

    public function configureProcessAction(ServerRequest $request, Response $response): ResponseInterface
    {
        $stationId = (int)$request->getAttribute('station_id');
        $station = $this->stationRepo->find($stationId);
        
        if (!$station) {
            throw new \App\Exception\NotFoundException('Station not found');
        }
        
        $params = $request->getParsedBody();
        $timezone = $params['timezone'] ?? 'America/Sao_Paulo';
        $voice_gender = $params['voice_gender'] ?? 'masculino';
        $enabled = (bool)($params['enabled'] ?? false);
        
        $config = [
            'timezone' => $timezone,
            'voice_gender' => $voice_gender,
            'enabled' => $enabled,
        ];
        
        // Salvar configuração
        $this->horaCertaService->saveStationConfig($station->getShortName(), $config);
        
        // Criar/atualizar script se habilitado
        if ($enabled) {
            $this->horaCertaService->createStationScript($station->getShortName(), $config);
            $this->horaCertaService->addCronJob($station->getShortName());
        } else {
            $this->horaCertaService->removeCronJob($station->getShortName());
        }
        
        $request->getFlash()->addMessage('success', sprintf(
            __('Configuração da Hora Certa atualizada para a estação %s'),
            $station->getName()
        ));
        
        return $response->withRedirect($request->getRouter()->named('plugins:hora-certa:index'));
    }

    public function toggleAction(ServerRequest $request, Response $response): ResponseInterface
    {
        $stationId = (int)$request->getAttribute('station_id');
        $station = $this->stationRepo->find($stationId);
        
        if (!$station) {
            throw new \App\Exception\NotFoundException('Station not found');
        }
        
        $config = $this->horaCertaService->getStationConfig($station->getShortName());
        $config['enabled'] = !($config['enabled'] ?? false);
        
        $this->horaCertaService->saveStationConfig($station->getShortName(), $config);
        
        if ($config['enabled']) {
            $this->horaCertaService->createStationScript($station->getShortName(), $config);
            $this->horaCertaService->addCronJob($station->getShortName());
        } else {
            $this->horaCertaService->removeCronJob($station->getShortName());
        }
        
        return $response->withJson(['success' => true, 'enabled' => $config['enabled']]);
    }

    public function testAction(ServerRequest $request, Response $response): ResponseInterface
    {
        $stationId = (int)$request->getAttribute('station_id');
        $station = $this->stationRepo->find($stationId);
        
        if (!$station) {
            throw new \App\Exception\NotFoundException('Station not found');
        }
        
        $result = $this->horaCertaService->testScript($station->getShortName());
        
        return $response->withJson($result);
    }

    public function downloadAudiosAction(ServerRequest $request, Response $response): ResponseInterface
    {
        $result = $this->horaCertaService->downloadAudios();
        
        if ($result['success']) {
            $request->getFlash()->addMessage('success', $result['message']);
        } else {
            $request->getFlash()->addMessage('error', $result['message']);
        }
        
        return $response->withRedirect($request->getRouter()->named('plugins:hora-certa:index'));
    }
}