#!/bin/bash
set -e

# Função para criar o arquivo .env
create_env_file() {
    cat > .env << EOF
APP_NAME="${APP_NAME}"
APP_URL="${APP_URL}"
APP_TIMEZONE="${APP_TIMEZONE}"
BASE_URL="${BASE_URL}"
API_KEY="${API_KEY}"
DB_HOST="${DB_HOST}"
DB_NAME="${DB_NAME}"
DB_USER="${DB_USER}"
DB_PASS="${DB_PASS}"
SMTP_HOST="${SMTP_HOST}"
SMTP_PORT=${SMTP_PORT}
SMTP_USER="${SMTP_USER}"
SMTP_PASS="${SMTP_PASS}"
SMTP_FROM="${SMTP_FROM}"
SESSION_LIFETIME=${SESSION_LIFETIME}
MAX_LOGIN_ATTEMPTS=${MAX_LOGIN_ATTEMPTS}
LOGIN_LOCKOUT_TIME=${LOGIN_LOCKOUT_TIME}
CSRF_SECRET="${CSRF_SECRET}"
DISPLAY_ERRORS=${DISPLAY_ERRORS}
ERROR_REPORTING=${ERROR_REPORTING}
EOF
}

# Função para configurar permissões
setup_permissions() {
    chown www-data:www-data .env
    chmod 644 .env
}

# Função principal
main() {
    echo "Iniciando configuração do ambiente..."
    
    # Criar arquivo .env
    create_env_file
    
    # Configurar permissões
    setup_permissions
    
    echo "Configuração concluída. Iniciando Apache..."
    
    # Iniciar Apache em primeiro plano
    exec apache2-foreground
}

# Executar função principal
main 