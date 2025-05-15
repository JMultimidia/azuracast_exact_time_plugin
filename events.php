<?php

declare(strict_types=1);

namespace Plugin\ExactTime;

use App\CallableEventDispatcherInterface;
use App\Environment;
use App\Event\GetSyncTasks;
use App\Event\Radio\WriteLiquidsoapConfiguration;
use App\Event\BuildRoutes;
use App\Event\BuildView;
use App\Sync\Task\AbstractTask;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Psr\Container\ContainerInterface;
use App\Entity\Station;
use App\Entity\StationMedia;
use App\Entity\Repository\StationRepository;
use App\Entity\Repository\StationMediaRepository;
use App\Radio\Enums\LiquidsoapQueues;

return [
    GetSyncTasks::class => [
        GetSyncTasks::class,
        -5,
        function (GetSyncTasks $event, string $eventName, CallableEventDispatcherInterface $dispatcher) {
            $event->addTask(ExactTimeTask::class);
        },
    ],

    BuildView::class => [
        BuildView::class,
        -5,
        function (BuildView $event, string $eventName, CallableEventDispatcherInterface $dispatcher) {
            $view = $event->getView();
            
            // Registrar diretório de templates do plugin
            $pluginTemplatesDir = __DIR__ . '/templates';
            if (is_dir($pluginTemplatesDir)) {
                $view->addFolder('exact_time', $pluginTemplatesDir);
            }
        },
    ],

    BuildRoutes::class => [
        BuildRoutes::class,
        -5,
        function (BuildRoutes $event, string $eventName, CallableEventDispatcherInterface $dispatcher) {
            $app = $event->getApp();
            
            // Rota para configuração admin
            $app->get('/admin/exact-time', Controller\Admin\ExactTimeController::class . ':indexAction')
                ->setName('admin:exact_time:index');
            
            $app->post('/admin/exact-time', Controller\Admin\ExactTimeController::class . ':editAction')
                ->setName('admin:exact_time:edit');
                
            // Rota para configuração por estação
            $app->get('/manage/station/{station_id}/exact-time', Controller\Stations\ExactTimeController::class . ':indexAction')
                ->setName('stations:exact_time:index');
                
            $app->post('/manage/station/{station_id}/exact-time', Controller\Stations\ExactTimeController::class . ':editAction')
                ->setName('stations:exact_time:edit');

            // API Routes
            $app->get('/api/stations/{station_id}/exact-time/status', Controller\Api\ExactTimeController::class . ':statusAction')
                ->setName('api:stations:exact_time:status');
                
            $app->post('/api/stations/{station_id}/exact-time/generate', Controller\Api\ExactTimeController::class . ':generateAction')
                ->setName('api:stations:exact_time:generate');
                
            $app->get('/api/admin/exact-time/stats', Controller\Api\ExactTimeController::class . ':statsAction')
                ->setName('api:admin:exact_time:stats');
        },
    ],

    WriteLiquidsoapConfiguration::class => [
        WriteLiquidsoapConfiguration::class,
        -5,
        function (WriteLiquidsoapConfiguration $event, string $eventName, CallableEventDispatcherInterface $dispatcher) {
            $station = $event->getStation();
            $config = $event->buildConfiguration();
            
            // Verificar se a estação tem exact time habilitada
            $settings = $station->getBackendConfig();
            if (isset($settings['exact_time_enabled']) && $settings['exact_time_enabled']) {
                // Adicionar configuração ao Liquidsoap
                $exactTimeConfig = [
                    '# Configuração do Exact Time',
                    'exact_time_enabled = ref(true)',
                    'exact_time_file = ref("' . $station->getRadioMediaDir() . '/exact_time/exact-time.mp3")',
                    'exact_time_last_update = ref(0.)',
                    '',
                    '# Função para verificar se o arquivo existe',
                    'def check_exact_time_file() =',
                    '  if file.exists(!exact_time_file) then',
                    '    !exact_time_file',
                    '  else',
                    '    ""',
                    '  end',
                    'end',
                    '',
                    '# Verificar arquivo a cada minuto',
                    'thread.run(delay=60., {',
                    '  exact_time_last_update := time()',
                    '})',
                    ''
                ];
                
                $event->appendLines($exactTimeConfig);
            }
        },
    ],
];

class ExactTimeTask extends AbstractTask
{
    public function __construct(
        private ContainerInterface $di,
        private StationRepository $stationRepo,
        private StationMediaRepository $mediaRepo,
        private Environment $environment,
        private AudioManager $audioManager
    ) {
        parent::__construct($di);
    }

    public static function getSchedulePattern(): string
    {
        return self::SCHEDULE_EVERY_MINUTE;
    }

    public function run(bool $force = false): void
    {
        $stations = $this->stationRepo->findAll();
        
        foreach ($stations as $station) {
            try {
                $this->processStation($station);
            } catch (\Throwable $e) {
                $this->logger->error('Erro ao processar estação ' . $station->getShortName(), [
                    'exception' => $e,
                ]);
            }
        }
    }

    private function processStation(Station $station): void
    {
        $settings = $station->getBackendConfig();
        
        // Verificar se o exact time está habilitado para esta estação
        if (!isset($settings['exact_time_enabled']) || !$settings['exact_time_enabled']) {
            return;
        }

        $timezone = $settings['exact_time_timezone'] ?? 'America/Fortaleza';
        $voiceGender = $settings['exact_time_voice_gender'] ?? 'masculino';
        $includeEffect = $settings['exact_time_include_effect'] ?? false;
        
        try {
            $now = CarbonImmutable::now($timezone);
            $hour = $now->format('H');
            $minute = $now->format('i');
            
            // Determinar qual arquivo de áudio usar
            $audioFiles = [];
            
            // Adicionar efeito se configurado
            if ($includeEffect) {
                $effectFile = $this->audioManager->findAudioFile($station, 'effects', 'efeito_hora1.mp3');
                if ($effectFile) {
                    $audioFiles[] = $effectFile;
                }
            }
            
            // Adicionar arquivo principal da hora
            if ($minute === '00') {
                // Para minuto 00, usar apenas arquivo da hora
                $hourFile = $this->audioManager->findAudioFile($station, 'voices', "{$voiceGender}_{$hour}.mp3");
            } else {
                // Para outros minutos, usar arquivo específico
                $hourFile = $this->audioManager->findAudioFile($station, 'voices', "{$voiceGender}_{$hour}_{$minute}.mp3");
            }
            
            if ($hourFile) {
                $audioFiles[] = $hourFile;
            }
            
            if (!empty($audioFiles)) {
                $success = $this->audioManager->generateExactTime($station, $audioFiles);
                
                if ($success) {
                    $this->logger->info(
                        'Exact Time atualizado',
                        [
                            'station' => $station->getShortName(),
                            'timezone' => $timezone,
                            'time' => $now->format('H:i'),
                            'voice' => $voiceGender,
                        ]
                    );
                } else {
                    $this->logger->error(
                        'Falha ao gerar Exact Time',
                        [
                            'station' => $station->getShortName(),
                            'timezone' => $timezone,
                            'time' => $now->format('H:i'),
                        ]
                    );
                }
            } else {
                $this->logger->warning(
                    'Nenhum arquivo de áudio encontrado para Exact Time',
                    [
                        'station' => $station->getShortName(),
                        'voice' => $voiceGender,
                        'hour' => $hour,
                        'minute' => $minute,
                    ]
                );
            }
            
        } catch (\Exception $e) {
            $this->logger->error(
                'Erro ao processar Exact Time',
                [
                    'station' => $station->getShortName(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );
        }
    }
}