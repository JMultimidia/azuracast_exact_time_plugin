#!/bin/bash

# Script de instalação do Plugin Exact Time para AzuraCast
# Configura docker-compose.override.yml para habilitar o plugin

# Cores
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m'

# Header
echo -e "${BLUE}${BOLD}════════════════════════════════════════════${NC}"
echo -e "${BLUE}${BOLD}        Plugin Exact Time - Instalador       ${NC}"
echo -e "${BLUE}${BOLD}════════════════════════════════════════════${NC}"
echo

# Verificar se estamos no diretório correto
if [ ! -f "docker-compose.yml" ]; then
    echo -e "${RED}❌ Erro: Execute este script no diretório raiz do AzuraCast${NC}"
    echo -e "${YELLOW}Exemplo: cd /var/azuracast && ./plugins/exact-time/install.sh${NC}"
    exit 1
fi

echo -e "${GREEN}✓${NC} Executando no diretório correto do AzuraCast"

# Verificar se o plugin está presente
if [ ! -d "plugins/exact-time" ]; then
    echo -e "${RED}❌ Diretório do plugin não encontrado${NC}"
    echo -e "${YELLOW}Clone primeiro: git clone [repo] plugins/exact-time${NC}"
    exit 1
fi

echo -e "${GREEN}✓${NC} Plugin encontrado"

# Configurar docker-compose.override.yml
setup_docker_override() {
    local override_file="docker-compose.override.yml"
    
    echo -e "${YELLOW}Configurando docker-compose.override.yml...${NC}"
    
    # Se não existe, criar novo
    if [ ! -f "$override_file" ]; then
        echo -e "${BLUE}Criando docker-compose.override.yml...${NC}"
        cat > "$override_file" << 'EOF'
services:
  web:
    environment:
      AZURACAST_PLUGIN_MODE: true
    volumes:
      - ./plugins/exact-time:/var/azuracast/www/plugins/exact-time
EOF
        echo -e "${GREEN}✓${NC} Arquivo criado com sucesso"
        return
    fi
    
    # Arquivo existe - verificar se já tem as configurações
    if grep -q "AZURACAST_PLUGIN_MODE.*true" "$override_file" && grep -q "exact-time" "$override_file"; then
        echo -e "${GREEN}✓${NC} Plugin já configurado no arquivo"
        return
    fi
    
    # Fazer backup
    echo -e "${BLUE}Fazendo backup do arquivo existente...${NC}"
    cp "$override_file" "${override_file}.backup.$(date +%Y%m%d_%H%M%S)"
    
    # Método simples: adicionar configuração no final
    echo -e "${BLUE}Atualizando arquivo existente...${NC}"
    
    # Verificar se já tem nossa configuração
    if ! grep -q "# Plugin Exact Time" "$override_file"; then
        # Adicionar nossa configuração
        {
            echo ""
            echo "# Plugin Exact Time - Configuração automática"
            echo "services:"
            echo "  web:"
            echo "    environment:"
            echo "      AZURACAST_PLUGIN_MODE: true"
            echo "    volumes:"
            echo "      - ./plugins/exact-time:/var/azuracast/www/plugins/exact-time"
        } >> "$override_file"
        
        echo -e "${GREEN}✓${NC} Configuração adicionada ao arquivo"
    else
        echo -e "${GREEN}✓${NC} Configuração já existe"
    fi
}

# Executar configuração
setup_docker_override

# Instruções finais
echo
echo -e "${GREEN}${BOLD}════════════════════════════════════════════${NC}"
echo -e "${GREEN}${BOLD}           INSTALAÇÃO CONCLUÍDA!             ${NC}"
echo -e "${GREEN}${BOLD}════════════════════════════════════════════${NC}"
echo
echo -e "${YELLOW}Próximos passos:${NC}"
echo
echo -e "${BLUE}1.${NC} Reiniciar os containers do AzuraCast:"
echo -e "   ${GREEN}./docker.sh restart${NC}"
echo
echo -e "${BLUE}2.${NC} Aguardar containers subirem completamente"
echo
echo -e "${BLUE}3.${NC} Acessar a interface web:"
echo -e "   • ${GREEN}Admin → Exact Time${NC} (configuração global)"
echo -e "   • ${GREEN}Estações → [Estação] → Exact Time${NC} (individual)"
echo
echo -e "${BLUE}4.${NC} Fazer upload dos arquivos de áudio na estrutura:"
echo -e "   ${YELLOW}exact_time/voices/${NC} e ${YELLOW}exact_time/effects/${NC}"
echo
echo -e "${BLUE}5.${NC} Configurar playlist de jingles com o arquivo:"
echo -e "   ${GREEN}exact_time/exact-time.mp3${NC}"
echo
echo -e "${GREEN}Plugin pronto para usar! 🎙️${NC}"