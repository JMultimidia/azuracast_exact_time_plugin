<?php

declare(strict_types=1);

namespace Plugin\ExactTime\Controller\Stations;

use App\Controller\AbstractController;
use App\Http\Response;
use App\Http\ServerRequest;
use App\Entity\Station;
use App\Http\RouterInterface;
use App\Flysystem\StationFilesystems;
use Plugin\ExactTime\Config;
use Plugin\ExactTime\AudioManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface;

final class ExactTimeController extends AbstractController
{
    public function __construct(
        private StationFilesystems $stationFilesystems,
        private AudioManager $audioManager,
        private EntityManagerInterface $em
    ) {
    }

    public function indexAction(
        ServerRequest $request,
        Response $response,
        RouterInterface $router
    ): ResponseInterface {
        $station = $request->getStation();
        $settings = Config::mergeStationConfig($station->getBackendConfig());

        // Verificar estrutura de arquivos de áudio
        $audioStructure = $this->audioManager->checkAudioStructure($station);

        return $response->withView('exact_time::stations/exact_time/index.phtml', [
            'title' => __('Configuração do Exact Time'),
            'station' => $station,
            'settings' => $settings,
            'audio_structure' => $audioStructure,
            'timezones' => Config::BRAZILIAN_TIMEZONES,
            'voice_genders' => Config::VOICE_GENDERS,
            'router' => $router,
        ]);
    }

    public function editAction(
        ServerRequest $request,
        Response $response,
        RouterInterface $router
    ): ResponseInterface {
        $station = $request->getStation();
        $data = $request->getParsedBody();

        $settings = $station->getBackendConfig();
        $settings['exact_time_enabled'] = (bool)($data['enabled'] ?? false);
        $settings['exact_time_timezone'] = $data['timezone'] ?? Config::DEFAULT_TIMEZONE;
        $settings['exact_time_voice_gender'] = $data['voice_gender'] ?? Config::DEFAULT_VOICE_GENDER;
        $settings['exact_time_include_effect'] = (bool)($data['include_effect'] ?? false);

        // Validar configurações
        $errors = Config::validateStationConfig($settings);
        if (!empty($errors)) {
            $request->getFlash()->addMessage('error', implode(', ', $errors));
            return $response->withRedirect(
                $router->named('stations:exact_time:index', ['station_id' => $station->getId()])
            );
        }

        $station->setBackendConfig($settings);
        $this->em->persist($station);
        $this->em->flush();

        $request->getFlash()->addMessage(
            'success',
            __('Configurações do Exact Time salvas com sucesso!')
        );

        return $response->withRedirect(
            $router->named('stations:exact_time:index', ['station_id' => $station->getId()])
        );
    }
}