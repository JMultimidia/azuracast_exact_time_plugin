#!/bin/bash

# Script de Desinstalação do Plugin Hora Certa para AzuraCast
# Este script remove a configuração do docker-compose.override.yml

set -e

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Funções de output
echo_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

echo_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

echo_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

echo_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Função para mostrar header
show_header() {
    clear
    echo
    echo -e "${RED}################################################${NC}"
    echo -e "${RED}#   Plugin Hora Certa para AzuraCast         #${NC}"
    echo -e "${RED}#   Desconfigurador de Docker                 #${NC}"
    echo -e "${RED}################################################${NC}"
    echo
}

# Detectar diretório do AzuraCast
detect_azuracast_dir() {
    # Começar do diretório atual e subir procurando pelo AzuraCast
    CURRENT_DIR="$(pwd)"
    CHECK_DIR="$CURRENT_DIR"
    
    # Verificar diretório atual e pais até encontrar o AzuraCast
    while [[ "$CHECK_DIR" != "/" ]]; do
        if [[ -f "$CHECK_DIR/docker-compose.yml" ]] && [[ -d "$CHECK_DIR/.azuracast" ]]; then
            AZURACAST_DIR="$CHECK_DIR"
            echo_success "AzuraCast detectado em: $AZURACAST_DIR"
            return 0
        fi
        CHECK_DIR="$(dirname "$CHECK_DIR")"
    done
    
    # Tentar outros locais comuns
    POSSIBLE_DIRS=(
        "/var/azuracast"
        "$HOME/azuracast"
    )
    
    for dir in "${POSSIBLE_DIRS[@]}"; do
        if [[ -f "$dir/docker-compose.yml" ]] && [[ -d "$dir/.azuracast" ]]; then
            AZURACAST_DIR="$dir"
            echo_success "AzuraCast detectado em: $AZURACAST_DIR"
            return 0
        fi
    done
    
    return 1
}

# Solicitar diretório do AzuraCast manualmente
ask_azuracast_dir() {
    echo_warning "Não foi possível detectar automaticamente o diretório do AzuraCast."
    echo
    read -p "Por favor, informe o caminho completo do AzuraCast: " AZURACAST_DIR
    
    if [[ ! -f "$AZURACAST_DIR/docker-compose.yml" ]]; then
        echo_error "Diretório inválido! Arquivo docker-compose.yml não encontrado."
        exit 1
    fi
    
    if [[ ! -d "$AZURACAST_DIR/.azuracast" ]]; then
        echo_error "Diretório inválido! Pasta .azuracast não encontrada."
        exit 1
    fi
}

# Verificar se o plugin está configurado
check_plugin_configured() {
    OVERRIDE_FILE="$AZURACAST_DIR/docker-compose.override.yml"
    
    if [[ ! -f "$OVERRIDE_FILE" ]]; then
        echo_warning "Arquivo docker-compose.override.yml não encontrado."
        return 1
    fi
    
    if ! grep -q "hora-certa" "$OVERRIDE_FILE"; then
        echo_warning "Configuração do plugin não encontrada no docker-compose.override.yml"
        return 1
    fi
    
    echo_success "Configuração do plugin encontrada!"
    return 0
}

# Remover cron jobs do sistema
remove_cron_jobs() {
    echo_info "Removendo cron jobs do plugin..."
    
    # Obter crontab atual
    CURRENT_CRON=$(crontab -l 2>/dev/null || true)
    
    if [[ -z "$CURRENT_CRON" ]]; then
        echo_info "Nenhum cron job encontrado."
        return 0
    fi
    
    # Filtrar linhas que não contêm hora-certa
    NEW_CRON=$(echo "$CURRENT_CRON" | grep -v "hora-certa" || true)
    
    # Verificar se houve mudanças
    if [[ "$CURRENT_CRON" != "$NEW_CRON" ]]; then
        if [[ -n "$NEW_CRON" ]]; then
            echo "$NEW_CRON" | crontab -
        else
            crontab -r 2>/dev/null || true
        fi
        echo_success "Cron jobs removidos!"
    else
        echo_info "Nenhum cron job do plugin encontrado."
    fi
}

# Atualizar docker-compose.override.yml
update_docker_override() {
    OVERRIDE_FILE="$AZURACAST_DIR/docker-compose.override.yml"
    
    echo_info "Removendo configuração do docker-compose.override.yml..."
    
    # Fazer backup
    cp "$OVERRIDE_FILE" "$OVERRIDE_FILE.backup.$(date +%Y%m%d_%H%M%S)"
    echo_info "Backup criado: $OVERRIDE_FILE.backup.*"
    
    # Usar Python para manipular YAML se disponível
    if command -v python3 >/dev/null 2>&1; then
        python3 << EOF
import yaml
import os

override_file = '$OVERRIDE_FILE'

# Carregar arquivo existente
with open(override_file, 'r') as f:
    data = yaml.safe_load(f) or {}

# Remover configuração do plugin
if 'services' in data and 'web' in data['services']:
    if 'volumes' in data['services']['web']:
        # Filtrar volumes que não são do hora-certa
        new_volumes = []
        for volume in data['services']['web']['volumes']:
            if 'hora-certa' not in str(volume):
                new_volumes.append(volume)
        data['services']['web']['volumes'] = new_volumes
        
        # Se não há mais volumes, remover a chave
        if not data['services']['web']['volumes']:
            del data['services']['web']['volumes']
    
    # Verificar se ainda tem AZURACAST_PLUGIN_MODE
    if 'environment' in data['services']['web']:
        if 'AZURACAST_PLUGIN_MODE' in data['services']['web']['environment']:
            # Manter se houver outros plugins, remover se for o único
            if not data['services']['web'].get('volumes'):
                # Se não há volumes, provavelmente não há outros plugins
                # Mas vamos manter para ser conservador
                pass

# Salvar arquivo
with open(override_file, 'w') as f:
    yaml.dump(data, f, default_flow_style=False)

print("Configuração removida com sucesso!")
EOF
    else
        # Fallback se Python não estiver disponível
        echo_warning "Python não encontrado. Usando método alternativo..."
        
        # Remover linhas relacionadas ao plugin
        sed -i '/hora-certa/d' "$OVERRIDE_FILE"
    fi
    
    echo_success "Configuração do plugin removida!"
    
    # Verificar se o arquivo ficou muito vazio
    if [[ $(wc -l < "$OVERRIDE_FILE") -le 5 ]]; then
        echo_warning "O arquivo docker-compose.override.yml ficou quase vazio."
        echo_info "Você pode querer removê-lo se não estiver usando outros plugins."
        echo_info "Localização: $OVERRIDE_FILE"
    fi
}

# Reiniciar containers
restart_containers() {
    echo_info "Reiniciando containers do AzuraCast..."
    echo_warning "Isso pode levar alguns minutos..."
    
    cd "$AZURACAST_DIR"
    
    if [[ -f "docker.sh" ]]; then
        ./docker.sh restart
    else
        docker-compose down
        docker-compose up -d
    fi
    
    echo_success "Containers reiniciados!"
}

# Verificar remoção
verify_removal() {
    echo_info "Verificando remoção..."
    
    # Esperar um pouco para os containers iniciarem
    sleep 10
    
    cd "$AZURACAST_DIR"
    
    # Tentar acessar o container web
    if ! docker-compose exec -T web ls /var/azuracast/www/plugins/hora-certa >/dev/null 2>&1; then
        echo_success "Plugin foi desmontado com sucesso!"
    else
        echo_warning "Plugin ainda está montado."
        echo_info "Verifique o docker-compose.override.yml manualmente."
    fi
}

# Mostrar instruções finais
show_final_instructions() {
    echo
    echo_success "=== DESCONFIGURAÇÃO CONCLUÍDA ==="
    echo
    echo_info "A configuração do Plugin Hora Certa foi removida do Docker."
    echo
    echo_warning "Importante:"
    echo "1. Os arquivos do plugin permanecem em: $(pwd)"
    echo "2. Para usar novamente, execute: ./install.sh"
    echo "3. Os cron jobs do sistema foram removidos (se existiam)"
    echo "4. Backup criado: $AZURACAST_DIR/docker-compose.override.yml.backup.*"
    echo
    echo_info "O plugin foi desabilitado mas não removido."
    echo_info "Para remover completamente, delete o diretório do plugin."
    echo
    echo_success "Desconfiguração do Plugin Hora Certa finalizada!"
}

# Função principal
main() {
    show_header
    
    echo_info "Iniciando desconfiguração do Plugin Hora Certa..."
    echo
    
    # Detectar ou solicitar diretório do AzuraCast
    if ! detect_azuracast_dir; then
        ask_azuracast_dir
    fi
    
    # Verificar se plugin está configurado
    if ! check_plugin_configured; then
        echo_info "Plugin não está configurado. Nada para fazer."
        exit 0
    fi
    
    # Confirmar desconfiguração
    echo
    echo_warning "Isso irá remover a configuração do Plugin Hora Certa!"
    echo_info "Diretório do AzuraCast: $AZURACAST_DIR"
    echo_warning "Isso irá:"
    echo "  - Remover configuração do docker-compose.override.yml"
    echo "  - Remover cron jobs relacionados ao plugin"
    echo "  - Reiniciar os containers do AzuraCast"
    echo
    echo_info "Os arquivos do plugin NÃO serão removidos."
    echo
    read -p "Deseja continuar? (y/N): " -n 1 -r
    echo
    
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo_info "Desconfiguração cancelada pelo usuário."
        exit 0
    fi
    
    # Executar desconfiguração
    remove_cron_jobs
    update_docker_override
    restart_containers
    verify_removal
    show_final_instructions
}

# Executar função principal
main "$@"