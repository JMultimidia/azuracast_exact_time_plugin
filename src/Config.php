<?php

declare(strict_types=1);

namespace Plugin\ExactTime;

/**
 * Classe de configuração e constantes do plugin Exact Time
 */
class Config
{
    // Configurações padrão
    public const DEFAULT_TIMEZONE = 'America/Fortaleza';
    public const DEFAULT_VOICE_GENDER = 'masculino';
    public const DEFAULT_INCLUDE_EFFECT = false;
    
    // Formatos suportados
    public const SUPPORTED_AUDIO_FORMATS = ['mp3'];
    
    // Gêneros de voz disponíveis
    public const VOICE_GENDERS = [
        'masculino' => 'Masculino',
        'feminino' => 'Feminino',
        'padrao' => 'Padrão',
    ];
    
    // Fusos horários brasileiros
    public const BRAZILIAN_TIMEZONES = [
        'America/Fortaleza' => 'Fortaleza (UTC-3)',
        'America/Sao_Paulo' => 'São Paulo (UTC-3)',
        'America/Rio_Branco' => 'Rio Branco (UTC-5)',
        'America/Cuiaba' => 'Cuiabá (UTC-4)',
        'America/Campo_Grande' => 'Campo Grande (UTC-4)',
        'America/Boa_Vista' => 'Boa Vista (UTC-4)',
        'America/Manaus' => 'Manaus (UTC-4)',
        'America/Recife' => 'Recife (UTC-3)',
        'America/Bahia' => 'Salvador (UTC-3)',
        'America/Belem' => 'Belém (UTC-3)',
        'America/Maceio' => 'Maceió (UTC-3)',
        'America/Porto_Velho' => 'Porto Velho (UTC-4)',
    ];
    
    // Arquivos de efeitos disponíveis
    public const EFFECT_FILES = [
        'efeito_hora1.mp3' => 'Efeito Principal',
        'repetir.mp3' => 'Repetição',
    ];
    
    // Estrutura de diretórios
    public const DIRECTORY_STRUCTURE = [
        'base' => 'exact_time',
        'voices' => 'exact_time/voices',
        'effects' => 'exact_time/effects',
        'output' => 'exact_time',
    ];
    
    /**
     * Valida configurações de uma estação
     */
    public static function validateStationConfig(array $config): array
    {
        $errors = [];
        
        // Validar timezone
        if (isset($config['exact_time_timezone'])) {
            if (!array_key_exists($config['exact_time_timezone'], self::BRAZILIAN_TIMEZONES)) {
                $errors[] = 'Fuso horário inválido: ' . $config['exact_time_timezone'];
            }
        }
        
        // Validar gênero de voz
        if (isset($config['exact_time_voice_gender'])) {
            if (!array_key_exists($config['exact_time_voice_gender'], self::VOICE_GENDERS)) {
                $errors[] = 'Gênero de voz inválido: ' . $config['exact_time_voice_gender'];
            }
        }
        
        // Validar booleanos
        $booleanFields = ['exact_time_enabled', 'exact_time_include_effect'];
        foreach ($booleanFields as $field) {
            if (isset($config[$field]) && !is_bool($config[$field])) {
                $errors[] = "Campo {$field} deve ser booleano";
            }
        }
        
        return $errors;
    }
    
    /**
     * Obtém configuração padrão para uma estação
     */
    public static function getDefaultStationConfig(): array
    {
        return [
            'exact_time_enabled' => false,
            'exact_time_timezone' => self::DEFAULT_TIMEZONE,
            'exact_time_voice_gender' => self::DEFAULT_VOICE_GENDER,
            'exact_time_include_effect' => self::DEFAULT_INCLUDE_EFFECT,
        ];
    }
    
    /**
     * Mescla configuração padrão com configuração existente
     */
    public static function mergeStationConfig(array $existingConfig): array
    {
        $defaultConfig = self::getDefaultStationConfig();
        
        // Filtrar apenas configurações do exact time
        $exactTimeConfig = array_filter(
            $existingConfig,
            fn($key) => str_starts_with($key, 'exact_time_'),
            ARRAY_FILTER_USE_KEY
        );
        
        return array_merge($defaultConfig, $exactTimeConfig);
    }
    
    /**
     * Obtém nome do arquivo de áudio baseado nos parâmetros
     */
    public static function getAudioFileName(string $gender, int $hour, ?int $minute = null): string
    {
        $hourPadded = str_pad((string)$hour, 2, '0', STR_PAD_LEFT);
        
        if ($minute === null || $minute === 0) {
            return "{$gender}_{$hourPadded}.mp3";
        }
        
        $minutePadded = str_pad((string)$minute, 2, '0', STR_PAD_LEFT);
        return "{$gender}_{$hourPadded}_{$minutePadded}.mp3";
    }
    
    /**
     * Obtém informações sobre o plugin
     */
    public static function getPluginInfo(): array
    {
        return [
            'name' => 'Exact Time',
            'version' => '1.0.0',
            'description' => 'Plugin para anunciar a hora atual automaticamente',
            'author' => 'Desenvolvido para AzuraCast',
            'min_azuracast_version' => '0.19.0',
            'supported_features' => [
                'multiple_stations',
                'custom_timezones',
                'voice_selection',
                'audio_effects',
                'automatic_generation',
            ],
        ];
    }
}