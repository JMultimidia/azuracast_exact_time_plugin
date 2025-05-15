# Plugin Exact Time para AzuraCast

Plugin profissional que adiciona funcionalidade de anúncio automático da hora ao AzuraCast. 

## 🎤 Funcionalidades

- ✅ **Interface Web Integrada** - Configuração via painel administrativo
- ✅ **Múltiplas Estações** - Gerenciamento individual ou global
- ✅ **Fusos Horários Brasileiros** - Suporte completo
- ✅ **Vozes Personalizadas** - Masculino, feminino, padrão
- ✅ **Efeitos Sonoros** - Opcionais e configuráveis
- ✅ **Sistema Robusto** - Logs, API REST, validações
- ✅ **Migração Automática** - Do sistema antigo para o plugin

## 🚀 Instalação Rápida

### 1. Baixar o Plugin
```bash
cd /var/azuracast
git clone https://github.com/JMultimidia/azuracast_exact_time_plugin plugins/exact-time
```

### 2. Executar Instalador
```bash
chmod +x plugins/exact-time/install.sh
./plugins/exact-time/install.sh
```

### 3. Reiniciar AzuraCast
```bash
./docker.sh restart
```

### 4. Configurar via Interface Web
- Acesse **Admin → Exact Time** (configuração global)
- Ou **Estações → [Sua Estação] → Exact Time** (por estação)

## 🛠️ O que o Instalador Faz

O script `install.sh` automaticamente:

1. **Verifica** se está no diretório correto do AzuraCast
2. **Detecta** se o plugin está presente
3. **Configura** o `docker-compose.override.yml`:
   - Cria novo arquivo se não existir
   - Atualiza arquivo existente (com backup automático)
   - Adiciona `AZURACAST_PLUGIN_MODE: true`
   - Configura o volume do plugin
4. **Fornece instruções** claras dos próximos passos

## 📁 Estrutura de Arquivos de Áudio

Organize seus arquivos na seguinte estrutura dentro do diretório de mídia de cada estação:

```
exact_time/
├── voices/
│   ├── masculino_00.mp3      # Para 14:00
│   ├── masculino_00_01.mp3   # Para 14:01
│   ├── masculino_00_02.mp3   # Para 14:02
│   ├── ...
│   ├── masculino_23_59.mp3   # Para 23:59
│   ├── feminino_00.mp3       # Versão feminina
│   └── padrao_00.mp3         # Versão padrão
└── effects/
    └── efeito_hora1.mp3      # Efeito antes da hora
```

## ⚙️ Configuração

### Opções Disponíveis
- **Status** - Habilitar/desabilitar por estação
- **Fuso Horário** - Todos os fusos brasileiros disponíveis
- **Gênero da Voz** - masculino, feminino ou padrão
- **Efeito Sonoro** - Opcional, reproduzido antes da hora

### Nomenclatura dos Arquivos
- **Para minuto 00**: `masculino_14.mp3` (apenas a hora)
- **Para outros minutos**: `masculino_14_30.mp3` (hora + minuto)
- **Formatos disponíveis**: masculino, feminino, padrao

## 🛠️ Como Funciona

1. **Execução Automática**: A cada minuto o plugin verifica estações ativas
2. **Seleção de Arquivo**: 
   - Para minuto 00: usa arquivo apenas da hora
   - Para outros: usa arquivo hora_minuto específico
3. **Efeitos**: Adiciona efeito sonoro antes (se habilitado)
4. **Geração**: Combina arquivos com ffmpeg quando necessário
5. **Disponibilização**: Salva como `exact_time/exact-time.mp3`

## 📝 Configuração da Playlist

1. Crie uma **Playlist** do tipo "Jingle/Station ID"
2. Configure para tocar **a cada X músicas**
3. Adicione o arquivo `exact_time/exact-time.mp3`
4. O anúncio será reproduzido automaticamente

## 📊 API REST

### Endpoints Disponíveis

#### Status de uma Estação
```bash
GET /api/stations/{id}/exact-time/status
```

#### Gerar Exact Time Manualmente
```bash
POST /api/stations/{id}/exact-time/generate
```

#### Estatísticas Globais
```bash
GET /api/admin/exact-time/stats
```

### Exemplo de Resposta
```json
{
  "enabled": true,
  "timezone": "America/Fortaleza",
  "voice_gender": "masculino",
  "include_effect": false,
  "last_update": "2024-01-15T14:30:00Z",
  "audio_structure": {
    "total_files": 1440,
    "complete_hours": 24,
    "missing_files": 0
  }
}
```

## 🔄 Migração do Sistema Antigo

Se você já usa scripts externos:

```bash
cd /var/azuracast
./plugins/exact-time/scripts/migrate.sh
```

O script irá:
- 📦 Fazer backup do sistema antigo
- 🔄 Migrar configurações existentes  
- 📁 Copiar arquivos de áudio
- ❌ Desabilitar cron antigo
- ✅ Configurar o plugin

## 🔍 Troubleshooting

### Plugin não aparece na interface
```bash
# Verificar configuração
cat docker-compose.override.yml | grep AZURACAST_PLUGIN_MODE

# Reiniciar containers
./docker.sh restart

# Verificar logs
docker-compose logs web | grep -i plugin
```

### Exact Time não toca
1. **Verificar arquivos**: Use a interface da estação para ver status
2. **Testar API**: `curl localhost/api/stations/1/exact-time/status`
3. **Verificar playlist**: Confirme se está configurada corretamente
4. **Ver logs**: `docker-compose logs web | grep exact`

### Arquivos não encontrados
- Use a verificação visual na interface da estação
- Confirme a nomenclatura dos arquivos
- Verifique as permissões (devem ser 1000:1000)

## 📋 Requisitos

- AzuraCast 0.19.0 ou superior
- PHP 8.1 ou superior
- FFmpeg (para concatenação de áudio)
- Docker configurado corretamente

## 🆚 Vantagens Sobre Scripts Externos

| Aspecto | Script Externo | Plugin |
|---------|----------------|---------|
| **Instalação** | SSH + configuração manual | Um script |
| **Configuração** | Editar arquivos via SSH | Interface web |
| **Múltiplas Estações** | Um arquivo por estação | Painel único |
| **Monitoramento** | Logs manuais via SSH | Interface visual + API |
| **Debugging** | SSH + tail logs | Interface gráfica |
| **Atualizações** | Recriar manualmente | Git pull + restart |
| **Integração** | Externa ao AzuraCast | Nativa do sistema |

## 📞 Suporte

- **GitHub Issues**: [https://github.com/JMultimidia/azuracast_exact_time_plugin/issues](https://github.com/JMultimidia/azuracast_exact_time_plugin/issues)
- **AzuraCast Discord**: [https://discord.gg/azuracast](https://discord.gg/azuracast)

## 📄 Licença

Este plugin está licenciado sob a Apache License 2.0, a mesma licença do AzuraCast.

---

**🎙️ Plugin desenvolvido pela comunidade AzuraCast para facilitar o uso de anúncios de hora em rádios brasileiras**