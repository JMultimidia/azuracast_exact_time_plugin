#!/bin/bash

# Script de Instalação do Plugin Hora Certa para AzuraCast
# Este script apenas configura o docker-compose.override.yml
# Os arquivos do plugin devem estar no diretório atual

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
    echo -e "${BLUE}################################################${NC}"
    echo -e "${BLUE}#   Plugin Hora Certa para AzuraCast         #${NC}"
    echo -e "${BLUE}#   Configurador de Docker                    #${NC}"
    echo -e "${BLUE}################################################${NC}"
    echo
}

# Verificar se estamos no diretório correto do plugin
check_plugin_directory() {
    if [[ ! -f "services.php" ]] || [[ ! -f "events.php" ]] || [[ ! -d "src" ]]; then
        echo_error "Este script deve ser executado no diretório do plugin!"
        echo_info "Certifique-se de estar no diretório 'azuracast-hora-certa-plugin'"
        echo_info "e que os arquivos do plugin estão presentes."
        exit 1
    fi
    
    echo_success "Diretório do plugin verificado!"
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

# Verificar se Docker está rodando
check_docker() {
    echo_info "Verificando se o Docker está em execução..."
    
    if ! docker info >/dev/null 2>&1; then
        echo_error "Docker não está em execução ou não está acessível."
        echo_info "Por favor, inicie o Docker e execute o script novamente."
        exit 1
    fi
    
    echo_success "Docker está em execução!"
}

# Criar ou atualizar docker-compose.override.yml
setup_docker_override() {
    OVERRIDE_FILE="$AZURACAST_DIR/docker-compose.override.yml"
    PLUGIN_PATH="$(realpath "$(pwd)")"
    
    echo_info "Configurando docker-compose.override.yml..."
    echo_info "Plugin localizado em: $PLUGIN_PATH"
    
    # Backup do arquivo existente se houver
    if [[ -f "$OVERRIDE_FILE" ]]; then
        echo_info "Fazendo backup do arquivo existente..."
        cp "$OVERRIDE_FILE" "$OVERRIDE_FILE.backup.$(date +%Y%m%d_%H%M%S)"
    fi
    
    # Verificar se já existe a configuração do plugin
    if [[ -f "$OVERRIDE_FILE" ]] && grep -q "hora-certa" "$OVERRIDE_FILE"; then
        echo_warning "Configuração do plugin já existe no docker-compose.override.yml"
        echo_info "Atualizando caminho do plugin..."
        
        # Atualizar o caminho do plugin no arquivo existente
        sed -i "s|.*hora-certa.*|      - $PLUGIN_PATH:/var/azuracast/www/plugins/hora-certa:ro|" "$OVERRIDE_FILE"
        echo_success "Caminho do plugin atualizado!"
        return 0
    fi
    
    # Se o arquivo não existe, criar novo
    if [[ ! -f "$OVERRIDE_FILE" ]]; then
        cat > "$OVERRIDE_FILE" << EOF
version: '3.8'

services:
  web:
    environment:
      AZURACAST_PLUGIN_MODE: true
    volumes:
      - $PLUGIN_PATH:/var/azuracast/www/plugins/hora-certa:ro
EOF
        echo_success "Arquivo docker-compose.override.yml criado!"
    else
        # Arquivo existe, vamos modificá-lo
        echo_info "Atualizando arquivo docker-compose.override.yml existente..."
        
        # Usar Python para manipular YAML se disponível
        if command -v python3 >/dev/null 2>&1; then
            python3 << EOF
import yaml
import os

override_file = '$OVERRIDE_FILE'
plugin_path = '$PLUGIN_PATH'

# Carregar arquivo existente
with open(override_file, 'r') as f:
    data = yaml.safe_load(f) or {}

# Garantir estrutura
if 'services' not in data:
    data['services'] = {}
if 'web' not in data['services']:
    data['services']['web'] = {}
if 'environment' not in data['services']['web']:
    data['services']['web']['environment'] = {}
if 'volumes' not in data['services']['web']:
    data['services']['web']['volumes'] = []

# Adicionar configurações do plugin
data['services']['web']['environment']['AZURACAST_PLUGIN_MODE'] = True

# Verificar se o volume já existe e remover
new_volumes = []
for volume in data['services']['web']['volumes']:
    if 'hora-certa' not in str(volume):
        new_volumes.append(volume)

# Adicionar o novo volume
new_volumes.append(f'{plugin_path}:/var/azuracast/www/plugins/hora-certa:ro')
data['services']['web']['volumes'] = new_volumes

# Salvar arquivo
with open(override_file, 'w') as f:
    yaml.dump(data, f, default_flow_style=False)

print("Arquivo atualizado com sucesso!")
EOF
        else
            # Fallback se Python não estiver disponível
            echo_warning "Python não encontrado. Usando método alternativo..."
            
            # Criar arquivo temporário com as modificações
            temp_file=$(mktemp)
            
            # Copiar arquivo original
            cp "$OVERRIDE_FILE" "$temp_file"
            
            # Verificar se já tem a seção services
            if ! grep -q "services:" "$temp_file"; then
                echo "services:" >> "$temp_file"
            fi
            
            # Verificar se já tem a seção web
            if ! grep -q "  web:" "$temp_file"; then
                sed -i '/services:/a\  web:' "$temp_file"
            fi
            
            # Adicionar environment se não existir
            if ! grep -q "    environment:" "$temp_file"; then
                sed -i '/  web:/a\    environment:' "$temp_file"
            fi
            
            # Adicionar AZURACAST_PLUGIN_MODE se não existir
            if ! grep -q "AZURACAST_PLUGIN_MODE:" "$temp_file"; then
                sed -i '/    environment:/a\      AZURACAST_PLUGIN_MODE: true' "$temp_file"
            fi
            
            # Adicionar volumes se não existir
            if ! grep -q "    volumes:" "$temp_file"; then
                sed -i '/      AZURACAST_PLUGIN_MODE: true/a\    volumes:' "$temp_file"
            fi
            
            # Adicionar volume do plugin
            volume_line="      - $PLUGIN_PATH:/var/azuracast/www/plugins/hora-certa:ro"
            if ! grep -q "hora-certa" "$temp_file"; then
                sed -i "/    volumes:/a\\$volume_line" "$temp_file"
            fi
            
            # Substituir arquivo original
            mv "$temp_file" "$OVERRIDE_FILE"
        fi
        
        echo_success "Arquivo docker-compose.override.yml atualizado!"
    fi
}

# Restart containers
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

# Verificar instalação
verify_installation() {
    echo_info "Verificando instalação..."
    
    # Esperar um pouco para os containers iniciarem
    sleep 10
    
    cd "$AZURACAST_DIR"
    
    # Tentar acessar o container web
    if docker-compose exec -T web ls /var/azuracast/www/plugins/hora-certa >/dev/null 2>&1; then
        echo_success "Plugin instalado com sucesso!"
        echo_info "Verificando arquivos específicos..."
        
        if docker-compose exec -T web ls /var/azuracast/www/plugins/hora-certa/services.php >/dev/null 2>&1; then
            echo_success "Todos os arquivos do plugin estão presentes!"
        else
            echo_warning "Alguns arquivos podem estar ausentes."
        fi
    else
        echo_warning "Plugin pode não estar completamente instalado."
        echo_info "Verifique se o caminho está correto e os containers foram reiniciados."
    fi
}

# Mostrar instruções finais
show_final_instructions() {
    echo
    echo_success "=== CONFIGURAÇÃO CONCLUÍDA ==="
    echo
    echo_info "O plugin Hora Certa foi configurado no AzuraCast!"
    echo
    echo_info "Próximos passos:"
    echo "1. Acesse o painel administrativo do AzuraCast"
    echo "2. Vá para 'Plugins → Hora Certa'"
    echo "3. Clique em 'Baixar Arquivos de Áudio'"
    echo "4. Configure suas estações"
    echo
    echo_warning "Importante:"
    echo "- Mantenha este diretório do plugin no local atual"
    echo "- Não mova ou remova os arquivos while o plugin estiver ativo"
    echo
    echo_info "Caminho do plugin: $(pwd)"
    echo_info "Configuração do Docker: $AZURACAST_DIR/docker-compose.override.yml"
    echo
    echo_success "Instalação do Plugin Hora Certa finalizada!"
}

# Função principal
main() {
    show_header
    
    echo_info "Iniciando configuração do Plugin Hora Certa..."
    echo
    
    # Verificações
    check_plugin_directory
    check_docker
    
    # Detectar ou solicitar diretório do AzuraCast
    if ! detect_azuracast_dir; then
        ask_azuracast_dir
    fi
    
    # Confirmar instalação
    echo
    echo_info "Plugin localizado em: $(pwd)"
    echo_info "Diretório do AzuraCast: $AZURACAST_DIR"
    echo_warning "Isso irá:"
    echo "  - Modificar/criar $AZURACAST_DIR/docker-compose.override.yml"
    echo "  - Reiniciar os containers do AzuraCast"
    echo "  - Configurar o mount do plugin no Docker"
    echo
    read -p "Deseja continuar? (y/N): " -n 1 -r
    echo
    
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo_info "Configuração cancelada pelo usuário."
        exit 0
    fi
    
    # Executar configuração
    setup_docker_override
    restart_containers
    verify_installation
    show_final_instructions
}

# Executar função principal
main "$@"