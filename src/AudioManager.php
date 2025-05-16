<?php

declare(strict_types=1);

namespace Plugin\ExactTime;

use Psr\Container\ContainerInterface;
use App\Entity\Station;
use App\Entity\StationMedia;
use App\Entity\Repository\StationMediaRepository;
use App\Flysystem\StationFilesystems;
use App\Logger;
use Carbon\CarbonImmutable;

/**
 * Classe utilitária para gerenciar arquivos de áudio do Exact Time
 */
class AudioManager
{
    public function __construct(
        private ContainerInterface $di,
        private StationMediaRepository $mediaRepo,
        private StationFilesystems $stationFilesystems,
        private Logger $logger
    ) {
    }

    /**
     * Verifica a estrutura de arquivos de áudio para uma estação
     */
    public function checkAudioStructure(Station $station): array
    {
        $filesystem = $this->stationFilesystems->getMediaFilesystem($station);
        $structure = [
            'voices' => [],
            'effects' => [],
            'missing_files' => [],
            'total_files' => 0,
            'complete_hours' => [],
        ];

        // Verificar arquivos de voz
        $voiceGenders = ['masculino', 'feminino', 'padrao'];
        
        foreach ($voiceGenders as $gender) {
            $genderData = [
                'hours' => [],
                'minutes' => [],
                'total' => 0,
            ];
            
            // Verificar arquivos apenas de hora (para minuto 00)
            for ($hour = 0; $hour < 24; $hour++) {
                $hourPadded = str_pad((string)$hour, 2, '0', STR_PAD_LEFT);
                $filename = "exact_time/voices/{$gender}_{$hourPadded}.mp3";
                
                if ($filesystem->fileExists($filename)) {
                    $genderData['hours'][] = $hourPadded;
                    $genderData['total']++;
                    
                    if (!in_array($hourPadded, $structure['complete_hours'])) {
                        $structure['complete_hours'][] = $hourPadded;
                    }
                }
            }
            
            // Verificar arquivos de hora e minuto
            for ($hour = 0; $hour < 24; $hour++) {
                for ($minute = 1; $minute < 60; $minute++) {
                    $hourPadded = str_pad((string)$hour, 2, '0', STR_PAD_LEFT);
                    $minutePadded = str_pad((string)$minute, 2, '0', STR_PAD_LEFT);
                    $filename = "exact_time/voices/{$gender}_{$hourPadded}_{$minutePadded}.mp3";
                    
                    if ($filesystem->fileExists($filename)) {
                        $genderData['minutes'][] = "{$hourPadded}:{$minutePadded}";
                        $genderData['total']++;
                    }
                }
            }
            
            $structure['voices'][$gender] = $genderData;
            $structure['total_files'] += $genderData['total'];
        }

        // Verificar arquivos de efeito
        $effectFiles = ['efeito_hora1.mp3', 'repetir.mp3'];
        foreach ($effectFiles as $file) {
            $filename = "exact_time/effects/{$file}";
            if ($filesystem->fileExists($filename)) {
                $structure['effects'][] = $file;
                $structure['total_files']++;
            }
        }

        // Identificar arquivos faltando importantes
        foreach ($voiceGenders as $gender) {
            for ($hour = 0; $hour < 24; $hour++) {
                $hourPadded = str_pad((string)$hour, 2, '0', STR_PAD_LEFT);
                $filename = "exact_time/voices/{$gender}_{$hourPadded}.mp3";
                
                if (!$filesystem->fileExists($filename)) {
                    $structure['missing_files'][] = $filename;
                }
            }
        }

        // Limitar arquivos faltando mostrados
        if (count($structure['missing_files']) > 20) {
            $structure['missing_files'] = array_slice($structure['missing_files'], 0, 20);
        }

        return $structure;
    }

    /**
     * Encontra arquivo de áudio específico
     */
    public function findAudioFile(Station $station, string $type, string $filename): ?StationMedia
    {
        $fullPath = "exact_time/{$type}/{$filename}";
        return $this->mediaRepo->findByPath($station, $fullPath);
    }

    /**
     * Gera arquivo exact time a partir de múltiplos arquivos
     */
    public function generateExactTime(Station $station, array $audioFiles, string $outputFilename = 'exact-time.mp3'): bool
    {
        if (empty($audioFiles)) {
            return false;
        }

        try {
            $mediaPath = $station->getRadioMediaDir();
            $tempDir = sys_get_temp_dir() . '/exact_time_' . $station->getId();
            
            // Criar diretório temporário
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $outputPath = $tempDir . '/' . $outputFilename;

            // Se há apenas um arquivo, copiar diretamente
            if (count($audioFiles) === 1) {
                $sourceFile = $mediaPath . '/' . $audioFiles[0]->getPath();
                if (file_exists($sourceFile)) {
                    copy($sourceFile, $outputPath);
                } else {
                    $this->logger->error('Arquivo fonte não encontrado: ' . $sourceFile);
                    return false;
                }
            } else {
                // Múltiplos arquivos: concatenar
                if (!$this->concatenateAudioFiles($station, $audioFiles, $outputPath)) {
                    return false;
                }
            }

            // Mover para destino final
            $finalDestination = $mediaPath . '/exact_time/' . $outputFilename;
            $this->ensureDirectoryExists(dirname($finalDestination));
            
            if (file_exists($finalDestination)) {
                unlink($finalDestination);
            }
            
            rename($outputPath, $finalDestination);
            
            // Limpar diretório temporário
            $this->cleanDirectory($tempDir);
            
            return true;
            
        } catch (\Throwable $e) {
            $this->logger->error('Erro ao gerar exact time', [
                'station' => $station->getShortName(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Concatena múltiplos arquivos de áudio usando ffmpeg
     */
    private function concatenateAudioFiles(Station $station, array $audioFiles, string $outputFile): bool
    {
        $mediaPath = $station->getRadioMediaDir();
        $tempListFile = tempnam(sys_get_temp_dir(), 'exact_time_list_');
        
        try {
            // Criar arquivo de lista para ffmpeg
            $listContent = '';
            foreach ($audioFiles as $file) {
                $filePath = $mediaPath . '/' . $file->getPath();
                if (file_exists($filePath)) {
                    $listContent .= "file '" . addslashes($filePath) . "'\n";
                } else {
                    $this->logger->warning('Arquivo não encontrado para concatenação: ' . $filePath);
                }
            }
            
            if (empty($listContent)) {
                $this->logger->error('Nenhum arquivo válido para concatenação');
                return false;
            }
            
            file_put_contents($tempListFile, $listContent);
            
            // Executar ffmpeg
            $command = sprintf(
                'ffmpeg -f concat -safe 0 -i %s -c copy %s -y 2>&1',
                escapeshellarg($tempListFile),
                escapeshellarg($outputFile)
            );
            
            $output = '';
            $returnCode = 0;
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                $this->logger->error('Erro no ffmpeg', [
                    'command' => $command,
                    'output' => implode("\n", $output),
                    'return_code' => $returnCode
                ]);
                return false;
            }
            
            return file_exists($outputFile);
            
        } finally {
            // Limpar arquivo temporário
            if (file_exists($tempListFile)) {
                unlink($tempListFile);
            }
        }
    }

    /**
     * Garante que um diretório existe
     */
    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    /**
     * Remove todos os arquivos de um diretório
     */
    private function cleanDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = array_diff(scandir($directory), ['.', '..']);
        foreach ($files as $file) {
            $filePath = $directory . '/' . $file;
            if (is_file($filePath)) {
                unlink($filePath);
            }
        }
        rmdir($directory);
    }

    /**
     * Obtém estatísticas do exact time para uma estação
     */
    public function getStationStats(Station $station): array
    {
        $settings = $station->getBackendConfig();
        $structure = $this->checkAudioStructure($station);
        
        $stats = [
            'enabled' => $settings['exact_time_enabled'] ?? false,
            'timezone' => $settings['exact_time_timezone'] ?? 'America/Fortaleza',
            'voice_gender' => $settings['exact_time_voice_gender'] ?? 'masculino',
            'include_effect' => $settings['exact_time_include_effect'] ?? false,
            'total_files' => $structure['total_files'],
            'complete_hours' => count($structure['complete_hours']),
            'missing_files' => count($structure['missing_files']),
            'last_update' => null,
        ];

        // Verificar último arquivo gerado
        $filesystem = $this->stationFilesystems->getMediaFilesystem($station);
        $generatedFile = 'exact_time/exact-time.mp3';
        
        if ($filesystem->fileExists($generatedFile)) {
            $stats['last_update'] = $filesystem->lastModified($generatedFile);
        }

        return $stats;
    }
}