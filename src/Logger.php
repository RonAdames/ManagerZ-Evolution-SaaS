<?php

class Logger {
    private $logFile;
    private $useErrorLog = false;
    
    public function __construct($filename = 'app.log') {
        // Use o caminho absoluto para o diretório de logs
        // Subindo um nível a partir do diretório src
        $this->logFile = realpath(dirname(__DIR__)) . '/logs/' . $filename;
        
        // Cria o diretório de logs se não existir
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            try {
                if (!@mkdir($logDir, 0755, true)) {
                    $this->useErrorLog = true;
                    error_log("Não foi possível criar o diretório de logs: " . $logDir);
                }
            } catch (Exception $e) {
                $this->useErrorLog = true;
                error_log("Erro ao criar diretório de logs: " . $e->getMessage());
            }
        }
        
        // Tenta criar o arquivo se não existir
        if (!$this->useErrorLog && !file_exists($this->logFile)) {
            try {
                if (!@touch($this->logFile)) {
                    $this->useErrorLog = true;
                    error_log("Não foi possível criar o arquivo de log: " . $this->logFile);
                } else {
                    @chmod($this->logFile, 0644);
                }
            } catch (Exception $e) {
                $this->useErrorLog = true;
                error_log("Erro ao criar arquivo de log: " . $e->getMessage());
            }
        }
        
        // Verifica se o arquivo é gravável
        if (!$this->useErrorLog && !is_writable($this->logFile)) {
            $this->useErrorLog = true;
            error_log("Arquivo de log não tem permissão de escrita: " . $this->logFile);
        }
    }
    
    public function log($message, $type = 'INFO') {
        try {
            $date = date('Y-m-d H:i:s');
            $logMessage = "[$date][$type] $message";
            
            if ($this->useErrorLog) {
                // Usa o error_log do PHP se não conseguir usar o arquivo próprio
                error_log($logMessage);
                return true;
            }
            
            $logMessage .= PHP_EOL;
            file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
            return true;
        } catch (Exception $e) {
            error_log("Erro ao escrever no log: " . $e->getMessage());
            return false;
        }
    }
    
    public function info($message) {
        return $this->log($message, 'INFO');
    }
    
    public function error($message) {
        return $this->log($message, 'ERROR');
    }
    
    public function debug($message) {
        return $this->log($message, 'DEBUG');
    }
    
    public function getLogPath() {
        return $this->useErrorLog ? 'php error log' : $this->logFile;
    }
    
    public function isUsingErrorLog() {
        return $this->useErrorLog;
    }
}