<?php
// Configurações básicas de segurança
ini_set('display_errors', getEnvVariable('DISPLAY_ERRORS', 0));
error_reporting(getEnvVariable('ERROR_REPORTING', E_ALL));

// Definir fuso horário
date_default_timezone_set(getEnvVariable('APP_TIMEZONE', 'America/Sao_Paulo'));

// Carregar o Logger
require_once __DIR__ . '/../src/Logger.php';
$logger = new Logger('app.log');

// Carregar variáveis de ambiente
function loadEnv($path) {
    if (!file_exists($path)) {
        global $logger;
        $logger->error("Arquivo .env não encontrado: $path");
        die("Erro de configuração. Contate o administrador.");
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Remover aspas se existirem
            if (preg_match('/^"(.+)"$/', $value, $matches)) {
                $value = $matches[1];
            } elseif (preg_match("/^'(.+)'$/", $value, $matches)) {
                $value = $matches[1];
            }
            
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
        }
    }
}

// Carregar configurações de ambiente
loadEnv(__DIR__ . '/../.env');

// Funções auxiliares para obter variáveis de ambiente com segurança
function getEnvVariable($key, $default = null) {
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    return $value;
}

// Configuração da conexão com o banco de dados
$host = getEnvVariable('DB_HOST');
$dbname = getEnvVariable('DB_NAME');
$user = getEnvVariable('DB_USER');
$pass = getEnvVariable('DB_PASS');

// Conexão com o banco de dados usando PDO
try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];
    
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Verificar conexão com uma consulta simples
    $pdo->query("SELECT 1");
    
} catch (PDOException $e) {
    $logger->error("Erro na conexão com o banco de dados: " . $e->getMessage());
    die("Não foi possível conectar ao banco de dados. Por favor, tente novamente mais tarde.");
}

// Definição de constantes para uso em toda a aplicação
define('APP_NAME', getEnvVariable('APP_NAME', 'NOMEEMPRESA'));
define('APP_VERSION', getEnvVariable('APP_VERSION', '1.0.1'));
define('APP_URL', getEnvVariable('APP_URL', 'http://localhost'));
define('API_URL', getEnvVariable('BASE_URL', 'https://EXEMPLO.sonho.digital'));
define('API_KEY', getEnvVariable('API_KEY', ''));
define('SESSION_LIFETIME', getEnvVariable('SESSION_LIFETIME', 3600));
define('MAX_LOGIN_ATTEMPTS', getEnvVariable('MAX_LOGIN_ATTEMPTS', 5));
define('LOGIN_LOCKOUT_TIME', getEnvVariable('LOGIN_LOCKOUT_TIME', 900));
define('CSRF_TOKEN_SECRET', getEnvVariable('CSRF_SECRET', 'change-this-to-a-random-secret'));

// Inclui auxiliares de segurança
require_once __DIR__ . '/../src/Security.php';
require_once __DIR__ . '/../src/Session.php';