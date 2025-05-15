<?php

declare(strict_types=1);

namespace Plugin\HoraCerta\Service;

use App\Environment;
use ZipArchive;

final class HoraCertaService
{
    private readonly string $pluginDir;
    private readonly string $audioDir;
    private readonly string $configDir;
    private readonly string $scriptsDir;

    public function __construct(
        private readonly Environment $environment
    ) {
        $this->pluginDir = $this->environment->getPluginsDirectory() . '/hora-certa';
        $this->audioDir = $this->pluginDir . '/audios';
        $this->configDir = $this->pluginDir . '/config';
        $this->scriptsDir = $this->pluginDir . '/scripts';
        
        $this->ensureDirectoriesExist();
    }

    public function getStationConfig(string $shortName): array
    {
        $configFile = $this->getConfigPath($shortName);
        
        if (!file_exists($configFile)) {
            return [
                'timezone' => 'America/Sao_Paulo',
                'voice_gender' => 'masculino',
                'enabled' => false,
            ];
        }
        
        $configData = file_get_contents($configFile);
        $parts = explode('|', trim($configData));
        
        return [
            'timezone' => $parts[0] ?? 'America/Sao_Paulo',
            'voice_gender' => $parts[1] ?? 'masculino',
            'enabled' => isset($parts[2]) ? (bool)$parts[2] : false,
        ];
    }

    public function saveStationConfig(string $shortName, array $config): void
    {
        $configFile = $this->getConfigPath($shortName);
        
        $configData = sprintf(
            "%s|%s|%s",
            $config['timezone'],
            $config['voice_gender'],
            $config['enabled'] ? '1' : '0'
        );
        
        file_put_contents($configFile, $configData);
        chmod($configFile, 0644);
    }

    public function createStationScript(string $shortName, array $config): void
    {
        $scriptPath = $this->getScriptPath($shortName);
        
        $scriptContent = $this->generateScriptContent($shortName, $config);
        
        file_put_contents($scriptPath, $scriptContent);
        chmod($scriptPath, 0755);
    }

    public function addCronJob(string $shortName): void
    {
        $scriptPath = $this->getScriptPath($shortName);
        $cronLine = "* * * * * $scriptPath";
        
        // Obter crontab atual
        $currentCron = [];
        exec('crontab -l 2>/dev/null', $currentCron);
        
        // Verificar se já existe
        foreach ($currentCron as $line) {
            if (str_contains($line, $scriptPath)) {
                return; // Já existe
            }
        }
        
        // Adicionar nova linha
        $currentCron[] = $cronLine;
        
        // Escrever crontab
        $tempFile = tempnam(sys_get_temp_dir(), 'crontab');
        file_put_contents($tempFile, implode("\n", $currentCron) . "\n");
        exec("crontab $tempFile");
        unlink($tempFile);
    }

    public function removeCronJob(string $shortName): void
    {
        $scriptPath = $this->getScriptPath($shortName);
        
        // Obter crontab atual
        $currentCron = [];
        exec('crontab -l 2>/dev/null', $currentCron);
        
        // Filtrar linhas que não contêm o script
        $newCron = array_filter($currentCron, function($line) use ($scriptPath) {
            return !str_contains($line, $scriptPath);
        });
        
        // Escrever crontab apenas se houve mudança
        if (count($newCron) !== count($currentCron)) {
            $tempFile = tempnam(sys_get_temp_dir(), 'crontab');
            file_put_contents($tempFile, implode("\n", $newCron) . "\n");
            exec("crontab $tempFile");
            unlink($tempFile);
        }
    }

    public function downloadAudios(): array
    {
        $audioUrl = 'https://jmhost.com.br/audios/hora-certa.zip';
        $zipFile = $this->audioDir . '/hora-certa.zip';
        $result = ['success' => false, 'message' => ''];
        
        try {
            // Verificar se já existem arquivos de áudio
            if ($this->audiosExist()) {
                $result['success'] = true;
                $result['message'] = 'Arquivos de áudio já existem.';
                return $result;
            }
            
            // Download do arquivo
            $context = stream_context_create([
                'http' => [
                    'timeout' => 120,
                    'user_agent' => 'AzuraCast-HoraCerta-Plugin/1.0'
                ]
            ]);
            
            $fileData = file_get_contents($audioUrl, false, $context);
            if ($fileData === false) {
                throw new \Exception('Falha ao baixar o arquivo de áudios');
            }
            
            file_put_contents($zipFile, $fileData);
            
            // Extrair arquivos
            if (!class_exists('ZipArchive')) {
                throw new \Exception('Extensão ZipArchive não está disponível');
            }
            
            $zip = new ZipArchive();
            if ($zip->open($zipFile) !== true) {
                throw new \Exception('Falha ao abrir o arquivo ZIP');
            }
            
            $zip->extractTo($this->audioDir);
            $zip->close();
            unlink($zipFile);
            
            $result['success'] = true;
            $result['message'] = 'Arquivos de áudio baixados e extraídos com sucesso.';
            
        } catch (\Exception $e) {
            $result['message'] = 'Erro ao baixar áudios: ' . $e->getMessage();
        }
        
        return $result;
    }

    public function testScript(string $shortName): array
    {
        $scriptPath = $this->getScriptPath($shortName);
        
        if (!file_exists($scriptPath)) {
            return [
                'success' => false,
                'message' => 'Script não encontrado',
                'output' => ''
            ];
        }
        
        // Executar script
        $output = [];
        $returnVar = 0;
        exec("bash $scriptPath 2>&1", $output, $returnVar);
        
        return [
            'success' => $returnVar === 0,
            'message' => $returnVar === 0 ? 'Hora Certa executada com sucesso!' : 'Erro ao executar script',
            'output' => implode("\n", $output)
        ];
    }

    public function getTimezones(): array
    {
        return [
            'America/Sao_Paulo' => 'São Paulo (UTC-3)',
            'America/Fortaleza' => 'Fortaleza (UTC-3)',
            'America/Recife' => 'Recife (UTC-3)',
            'America/Salvador' => 'Salvador (UTC-3)',
            'America/Belem' => 'Belém (UTC-3)',
            'America/Manaus' => 'Manaus (UTC-4)',
            'America/Rio_Branco' => 'Rio Branco (UTC-5)',
            'America/Campo_Grande' => 'Campo Grande (UTC-4)',
            'America/Cuiaba' => 'Cuiabá (UTC-4)',
            'America/Porto_Velho' => 'Porto Velho (UTC-4)',
            'America/Boa_Vista' => 'Boa Vista (UTC-4)',
            'America/Maceio' => 'Maceió (UTC-3)',
            'America/Araguaina' => 'Araguaína (UTC-3)',
        ];
    }

    private function ensureDirectoriesExist(): void
    {
        $directories = [$this->pluginDir, $this->audioDir, $this->configDir, $this->scriptsDir];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    private function getConfigPath(string $shortName): string
    {
        return $this->configDir . "/{$shortName}.conf";
    }

    private function getScriptPath(string $shortName): string
    {
        return $this->scriptsDir . "/hora-certa-{$shortName}.sh";
    }

    private function audiosExist(): bool
    {
        // Verificar se alguns arquivos de áudio essenciais existem
        $testFiles = [
            $this->audioDir . '/masculino_00.mp3',
            $this->audioDir . '/feminino_00.mp3',
            $this->audioDir . '/masculino_12.mp3'
        ];
        
        foreach ($testFiles as $file) {
            if (file_exists($file)) {
                return true;
            }
        }
        
        return false;
    }

    private function generateScriptContent(string $shortName, array $config): string
    {
        return <<<SCRIPT
#!/bin/bash

# Script da Hora Certa para a estação {$shortName}
# Gerado automaticamente pelo plugin

SHORTCODE="{$shortName}"
TIMEZONE="{$config['timezone']}"
VOICE_GENDER="{$config['voice_gender']}"

# Obter hora atual no fuso da estação
HOUR=\$(TZ="\$TIMEZONE" date +%H)
MINUTE=\$(TZ="\$TIMEZONE" date +%M)

# Paths
AUDIO_DIR="{$this->audioDir}"
STATION_DIR="/var/lib/docker/volumes/azuracast_station_data/_data/\$SHORTCODE/media"

# Criar diretório se não existir
mkdir -p "\$STATION_DIR"
chown 1000:1000 "\$STATION_DIR"

# Determinar arquivo de áudio
if [ "\$MINUTE" = "00" ]; then
    # Para hora exata, usar apenas hora
    AUDIO_FILE="\$AUDIO_DIR/\${VOICE_GENDER}_\${HOUR}.mp3"
else
    # Para outros minutos, usar hora_minuto
    AUDIO_FILE="\$AUDIO_DIR/\${VOICE_GENDER}_\${HOUR}_\${MINUTE}.mp3"
fi

# Copiar arquivo se existir
if [ -f "\$AUDIO_FILE" ]; then
    cp "\$AUDIO_FILE" "\$STATION_DIR/Hora-Certa.mp3"
    chown 1000:1000 "\$STATION_DIR/Hora-Certa.mp3"
    echo "[\$(date)] [\$SHORTCODE] [\$TIMEZONE] [\$(TZ="\$TIMEZONE" date +%H:%M)] [\$VOICE_GENDER] Hora Certa atualizada"
else
    echo "[\$(date)] [\$SHORTCODE] ERRO: Arquivo não encontrado: \$AUDIO_FILE"
    
    # Tentar buscar arquivo alternativo (apenas hora para minutos especiais)
    if [ "\$MINUTE" != "00" ]; then
        AUDIO_FILE_ALT="\$AUDIO_DIR/\${VOICE_GENDER}_\${HOUR}.mp3"
        if [ -f "\$AUDIO_FILE_ALT" ]; then
            cp "\$AUDIO_FILE_ALT" "\$STATION_DIR/Hora-Certa.mp3"
            chown 1000:1000 "\$STATION_DIR/Hora-Certa.mp3"
            echo "[\$(date)] [\$SHORTCODE] [\$TIMEZONE] [\$(TZ="\$TIMEZONE" date +%H:%M)] [\$VOICE_GENDER] Hora Certa atualizada (arquivo alternativo)"
            exit 0
        fi
    fi
    
    exit 1
fi

SCRIPT;
    }
}