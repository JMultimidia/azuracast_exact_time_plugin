<?php

declare(strict_types=1);

namespace Plugin\ExactTime\Controller\Api;

use App\Controller\AbstractController;
use App\Http\Response;
use App\Http\ServerRequest;
use App\Entity\Repository\StationRepository;
use Plugin\ExactTime\AudioManager;
use Plugin\ExactTime\Config;
use Psr\Http\Message\ResponseInterface;
use Carbon\CarbonImmutable;

final class ExactTimeController extends AbstractController
{
    public function __construct(
        private StationRepository $stationRepo,
        private AudioManager $audioManager
    ) {
    }

    public function statusAction(
        ServerRequest $request,
        Response $response
    ): ResponseInterface {
        $station = $request->getStation();
        $settings = $station->getBackendConfig();
        
        // Obter estrutura de arquivos
        $audioStructure = $this->audioManager->checkAudioStructure($station);
        
        // Calcular estatísticas
        $stats = $this->audioManager->getStationStats($station);
        
        $responseData = [
            'enabled' => $settings['exact_time_enabled'] ?? false,
            'timezone' => $settings['exact_time_timezone'] ?? Config::DEFAULT_TIMEZONE,
            'voice_gender' => $settings['exact_time_voice_gender'] ?? Config::DEFAULT_VOICE_GENDER,
            'include_effect' => $settings['exact_time_include_effect'] ?? Config::DEFAULT_INCLUDE_EFFECT,
            'last_update' => $stats['last_update'] ? 
                CarbonImmutable::createFromTimestamp($stats['last_update'])->toISOString() : null,
            'audio_structure' => $audioStructure,
            'statistics' => [
                'total_files' => $audioStructure['total_files'],
                'complete_hours' => count($audioStructure['complete_hours']),
                'missing_files_count' => count($audioStructure['missing_files']),
                'completeness_percentage' => $this->calculateCompleteness($audioStructure),
            ],
        ];

        return $response->withJson($responseData);
    }

    public function generateAction(
        ServerRequest $request,
        Response $response
    ): ResponseInterface {
        $station = $request->getStation();
        $settings = $station->getBackendConfig();
        
        if (!($settings['exact_time_enabled'] ?? false)) {
            return $response->withJson([
                'success' => false,
                'error' => 'Exact Time não está habilitado para esta estação',
            ], 400);
        }

        $timezone = $settings['exact_time_timezone'] ?? Config::DEFAULT_TIMEZONE;
        $voiceGender = $settings['exact_time_voice_gender'] ?? Config::DEFAULT_VOICE_GENDER;
        $includeEffect = $settings['exact_time_include_effect'] ?? Config::DEFAULT_INCLUDE_EFFECT;

        try {
            $now = CarbonImmutable::now($timezone);
            $hour = $now->format('H');
            $minute = $now->format('i');
            
            // Determinar arquivos de áudio
            $audioFiles = [];
            
            if ($includeEffect) {
                $effectFile = $this->audioManager->findAudioFile($station, 'effects', 'efeito_hora1.mp3');
                if ($effectFile) {
                    $audioFiles[] = $effectFile;
                }
            }
            
            if ($minute === '00') {
                $hourFile = $this->audioManager->findAudioFile($station, 'voices', "{$voiceGender}_{$hour}.mp3");
            } else {
                $hourFile = $this->audioManager->findAudioFile($station, 'voices', "{$voiceGender}_{$hour}_{$minute}.mp3");
            }
            
            if ($hourFile) {
                $audioFiles[] = $hourFile;
            }
            
            if (empty($audioFiles)) {
                return $response->withJson([
                    'success' => false,
                    'error' => 'Nenhum arquivo de áudio encontrado para o horário atual',
                    'details' => [
                        'hour' => $hour,
                        'minute' => $minute,
                        'voice_gender' => $voiceGender,
                    ],
                ], 404);
            }
            
            $success = $this->audioManager->generateExactTime($station, $audioFiles);
            
            if ($success) {
                return $response->withJson([
                    'success' => true,
                    'message' => 'Exact time gerado com sucesso',
                    'generated_at' => $now->toISOString(),
                    'file_path' => 'exact_time/exact-time.mp3',
                    'details' => [
                        'hour' => $hour,
                        'minute' => $minute,
                        'voice_gender' => $voiceGender,
                        'include_effect' => $includeEffect,
                        'files_used' => count($audioFiles),
                    ],
                ]);
            } else {
                return $response->withJson([
                    'success' => false,
                    'error' => 'Falha ao gerar arquivo exact time',
                ], 500);
            }
            
        } catch (\Exception $e) {
            return $response->withJson([
                'success' => false,
                'error' => 'Erro interno ao gerar exact time',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function statsAction(
        ServerRequest $request,
        Response $response
    ): ResponseInterface {
        $stations = $this->stationRepo->findAll();
        $globalStats = [
            'plugin_version' => Config::getPluginInfo()['version'],
            'total_stations' => count($stations),
            'active_stations' => 0,
            'total_generations_today' => 0,
            'errors_today' => 0,
            'stations' => [],
        ];

        foreach ($stations as $station) {
            $settings = $station->getBackendConfig();
            $enabled = $settings['exact_time_enabled'] ?? false;
            
            if ($enabled) {
                $globalStats['active_stations']++;
            }
            
            $stationStats = $this->audioManager->getStationStats($station);
            $audioStructure = $this->audioManager->checkAudioStructure($station);
            
            $stationData = [
                'id' => $station->getId(),
                'name' => $station->getName(),
                'short_name' => $station->getShortName(),
                'enabled' => $enabled,
                'timezone' => $settings['exact_time_timezone'] ?? Config::DEFAULT_TIMEZONE,
                'voice_gender' => $settings['exact_time_voice_gender'] ?? Config::DEFAULT_VOICE_GENDER,
                'last_generation' => $stationStats['last_update'] ? 
                    CarbonImmutable::createFromTimestamp($stationStats['last_update'])->toISOString() : null,
                'status' => $this->getStationStatus($audioStructure, $enabled),
                'completeness' => $this->calculateCompleteness($audioStructure),
                'total_files' => $audioStructure['total_files'],
                'missing_files' => count($audioStructure['missing_files']),
            ];
            
            $globalStats['stations'][] = $stationData;
        }

        return $response->withJson($globalStats);
    }

    private function calculateCompleteness(array $audioStructure): float
    {
        if (empty($audioStructure['voices'])) {
            return 0.0;
        }

        $totalRequired = 24 * 60; // 24 horas * 60 minutos
        $totalFound = $audioStructure['total_files'];
        
        return min(100.0, ($totalFound / $totalRequired) * 100);
    }

    private function getStationStatus(array $audioStructure, bool $enabled): string
    {
        if (!$enabled) {
            return 'disabled';
        }
        
        if (count($audioStructure['missing_files']) === 0) {
            return 'ok';
        }
        
        if (count($audioStructure['complete_hours']) < 12) {
            return 'critical';
        }
        
        return 'warning';
    }
}