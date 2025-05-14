<?php
// Carregar o arquivo de configuração
require_once __DIR__ . '/config.php';

// Verificar se as variáveis de ambiente foram carregadas
echo "Verificando variáveis de ambiente:\n";
echo "APP_NAME: " . getenv('APP_NAME') . "\n";
echo "DB_HOST: " . getenv('DB_HOST') . "\n";
echo "DB_NAME: " . getenv('DB_NAME') . "\n";
echo "DB_USER: " . getenv('DB_USER') . "\n";
echo "DB_PASS: " . getenv('DB_PASS') . "\n";

// Verificar se o arquivo .env existe
echo "\nVerificando arquivo .env:\n";
$envPath = __DIR__ . '/../.env';
echo "Caminho do .env: " . $envPath . "\n";
echo "Arquivo existe? " . (file_exists($envPath) ? "Sim" : "Não") . "\n";

// Verificar permissões do arquivo .env
if (file_exists($envPath)) {
    echo "Permissões do arquivo: " . substr(sprintf('%o', fileperms($envPath)), -4) . "\n";
    echo "Proprietário: " . posix_getpwuid(fileowner($envPath))['name'] . "\n";
    echo "Grupo: " . posix_getgrgid(filegroup($envPath))['name'] . "\n";
}

// Verificar se o PHP pode ler o arquivo
if (file_exists($envPath)) {
    echo "\nConteúdo do arquivo .env:\n";
    echo file_get_contents($envPath);
} 