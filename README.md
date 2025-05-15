# Plugin Hora Certa para AzuraCast

Este plugin adiciona funcionalidade de "Hora Certa" ao AzuraCast, permitindo que você configure automaticamente anúncios de horário para suas estações de rádio.

## Recursos

- ✅ **Configuração individual por estação** - Cada estação pode ter suas próprias configurações
- ✅ **Suporte a múltiplos fusos horários** - Configure o fuso horário específico para cada estação
- ✅ **Voz masculina e feminina** - Escolha entre diferentes tipos de voz para o anúncio
- ✅ **Atualização automática** - O arquivo de áudio é atualizado automaticamente a cada minuto
- ✅ **Interface web amigável** - Configure tudo através do painel administrativo do AzuraCast
- ✅ **Teste em tempo real** - Teste a funcionalidade diretamente pela interface

## Instalação

### Método 1: Instalação Automática (Recomendado)

```bash
# Clone o repositório do plugin
git clone https://github.com/JMultimidia/azuracast_exact_time_plugin.git
cd azuracast_exact_time_plugin

# Execute o script de configuração
chmod +x install.sh
./install.sh
```

O script de instalação automaticamente:
- ✅ Detecta o diretório do AzuraCast
- ✅ Cria/atualiza o `docker-compose.override.yml`
- ✅ Configura o mount do plugin no Docker
- ✅ Reinicia os containers
- ✅ Verifica a instalação

### Método 2: Instalação Manual

Se preferir configurar manualmente:

#### 1. Baixar o Plugin

```bash
# Clone o repositório em qualquer diretório
git clone https://github.com/JMultimidia/azuracast_exact_time_plugin.git
cd azuracast_exact_time_plugin
```

#### 2. Configurar Docker

Edite ou crie o arquivo `docker-compose.override.yml` na raiz do AzuraCast:

```yaml
version: '3.8'

services:
  web:
    environment:
      AZURACAST_PLUGIN_MODE: true
    volumes:
      - ./plugins/hora-certa:/var/azuracast/www/plugins/hora-certa:ro
```

**Importante:** Substitua `/caminho/para/azuracast-hora-certa-plugin` pelo caminho absoluto onde você clonou o plugin.

#### 3. Reiniciar Containers

```bash
cd /var/azuracast  # ou seu diretório do AzuraCast
./docker.sh restart
```

## Estrutura do Plugin

```
azuracast-hora-certa-plugin/
├── src/
│   ├── Controller/
│   │   └── HoraCertaController.php
│   └── Service/
│       └── HoraCertaService.php
├── templates/
│   ├── index.phtml
│   └── configure.phtml
├── services.php
├── events.php
├── install.sh
├── uninstall.sh
└── README.md
```

## Como Usar

### 1. Acessar o Plugin

1. Faça login no painel administrativo do AzuraCast
2. Vá para **Plugins → Hora Certa**

### 2. Baixar Arquivos de Áudio

1. Na tela principal do plugin, clique em **"Baixar Arquivos de Áudio"**
2. O sistema baixará automaticamente todos os arquivos de voz necessários

### 3. Configurar uma Estação

1. Clique no botão **"Configurar"** da estação desejada
2. Configure:
   - **Fuso Horário**: Selecione o fuso horário da sua região
   - **Tipo de Voz**: Escolha entre masculino ou feminino
   - **Ativar**: Marque para habilitar a Hora Certa
3. Clique em **"Salvar Configuração"**

### 4. Configurar no AzuraCast

1. Vá para **Estação → Playlists** no painel da estação
2. Crie uma nova playlist do tipo **"Intervalo"**
3. Configure a frequência de reprodução (recomendado: a cada 15-30 minutos)
4. Adicione o arquivo `/media/Hora-Certa.mp3` na playlist
5. Salve e ative a playlist

## Funcionamento Técnico

### Arquivos Gerados

- **Configuração**: `/plugins/hora-certa/config/{shortname}.conf`
- **Script**: `/plugins/hora-certa/scripts/hora-certa-{shortname}.sh`
- **Áudios**: `/plugins/hora-certa/audios/`

### Cron Job

O plugin adiciona automaticamente uma entrada no cron para executar o script a cada minuto:

```bash
* * * * * /path/to/hora-certa-{shortname}.sh
```

### Arquivo de Saída

O arquivo `Hora-Certa.mp3` é criado automaticamente em:
```
/var/lib/docker/volumes/azuracast_station_data/_data/{shortname}/media/Hora-Certa.mp3
```

## Desinstalação

### Método 1: Desinstalação Automática (Recomendado)

```bash
# No diretório do plugin
./uninstall.sh
```

O script de desinstalação automaticamente:
- ✅ Remove cron jobs relacionados ao plugin
- ✅ Remove arquivos do plugin (com opção de backup)  
- ✅ Atualiza o `docker-compose.override.yml`
- ✅ Reinicia os containers
- ✅ Verifica a remoção

### Método 2: Desinstalação Manual

Para remover completamente o plugin manualmente:

#### 1. Remover arquivos do plugin
```bash
cd /var/azuracast  # ou seu diretório do AzuraCast
rm -rf plugins/hora-certa
```

#### 2. Editar docker-compose.override.yml
Remova ou comente as linhas relacionadas ao plugin:

```yaml
# Remover ou comentar estas linhas:
# - ./plugins/hora-certa:/var/azuracast/www/plugins/hora-certa:ro
```

#### 3. Reiniciar containers
```bash
./docker.sh restart
```

#### 4. Limpeza opcional
Remove também os cron jobs criados (se houver):
```bash
crontab -l | grep -v hora-certa | crontab -
``` crontab -
```

## Resolução de Problemas

### Hora Certa não está funcionando

1. **Verifique se o plugin está ativo** para a estação
2. **Teste manualmente** usando o botão "Testar" na interface
3. **Verifique os logs** do container AzuraCast
4. **Confirme se o cron está funcionando** no sistema host

### Arquivo não encontrado

1. **Baixe os arquivos de áudio** novamente
2. **Verifique as permissões** dos diretórios
3. **Confirme se o Docker está em execução**

### Fuso horário incorreto

1. **Reconfigure o fuso horário** na página de configuração da estação
2. **Teste** para verificar se está funcionando corretamente

## Requisitos

- AzuraCast (versão atual)
- Docker em execução
- Permissões de escrita no diretório de mídia
- Cron configurado no sistema host

## Créditos

Este plugin é baseado no script original de Johannes Nogueira, adaptado para funcionar como um plugin nativo do AzuraCast.

## Licença

MIT License - Veja o arquivo LICENSE para mais detalhes.

## Contribuições

Contribuições são bem-vindas! Por favor, abra issues ou envie pull requests para melhorar este plugin.

## Changelog

### v1.0.0
- Lançamento inicial
- Suporte a configuração individual por estação
- Interface web completa
- Suporte a voz masculina e feminina
- Múltiplos fusos horários brasileiros