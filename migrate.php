#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

class DatabaseMigrator {
    private $pdo;
    private $migrationsPath;
    private $migrationsTable = 'migrations';

    public function __construct() {
        // Carrega variáveis de ambiente
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->load();

        // Conecta ao banco de dados
        $this->connect();
        
        // Define o caminho das migrações
        $this->migrationsPath = __DIR__ . '/database/migrations';
        
        // Cria a tabela de migrações se não existir
        $this->createMigrationsTable();
    }

    private function connect() {
        try {
            $this->pdo = new PDO(
                "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']}",
                $_ENV['DB_USER'],
                $_ENV['DB_PASS'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            die("Erro de conexão: " . $e->getMessage() . "\n");
        }
    }

    private function createMigrationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $this->pdo->exec($sql);
    }

    public function prepare() {
        echo "Preparando banco de dados...\n";
        
        // Importa o arquivo SQL inicial
        $sqlFile = __DIR__ . '/banco.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            try {
                $this->pdo->exec($sql);
                echo "Banco de dados preparado com sucesso!\n";
            } catch (PDOException $e) {
                die("Erro ao preparar banco de dados: " . $e->getMessage() . "\n");
            }
        } else {
            die("Arquivo banco.sql não encontrado!\n");
        }
    }

    public function migrate() {
        echo "Executando migrações...\n";
        
        // Obtém migrações já executadas
        $executed = $this->getExecutedMigrations();
        
        // Obtém todos os arquivos de migração
        $files = glob($this->migrationsPath . '/*.sql');
        sort($files);
        
        foreach ($files as $file) {
            $migration = basename($file);
            if (!in_array($migration, $executed)) {
                echo "Executando migração: {$migration}\n";
                
                try {
                    $sql = file_get_contents($file);
                    $this->pdo->exec($sql);
                    
                    // Registra a migração
                    $stmt = $this->pdo->prepare(
                        "INSERT INTO {$this->migrationsTable} (migration) VALUES (?)"
                    );
                    $stmt->execute([$migration]);
                    
                    echo "Migração {$migration} executada com sucesso!\n";
                } catch (PDOException $e) {
                    die("Erro na migração {$migration}: " . $e->getMessage() . "\n");
                }
            }
        }
        
        echo "Todas as migrações foram executadas!\n";
    }

    private function getExecutedMigrations() {
        $stmt = $this->pdo->query("SELECT migration FROM {$this->migrationsTable}");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

// Executa o comando apropriado
$migrator = new DatabaseMigrator();

if ($argc < 2) {
    die("Uso: php migrate.php [prepare|migrate]\n");
}

$command = $argv[1];

switch ($command) {
    case 'prepare':
        $migrator->prepare();
        break;
    case 'migrate':
        $migrator->migrate();
        break;
    default:
        die("Comando inválido. Use 'prepare' ou 'migrate'\n");
} 