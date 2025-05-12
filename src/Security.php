<?php
/**
 * Classe de segurança para gerenciar funções relacionadas à proteção da aplicação
 */
class Security {
    /**
     * Gera um token CSRF
     *
     * @return string Token CSRF
     */
    public static function generateCsrfToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        
        // Regenera o token após 30 minutos
        if (time() - $_SESSION['csrf_token_time'] > 1800) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verifica se o token CSRF é válido
     *
     * @param string $token Token CSRF recebido
     * @return bool Verdadeiro se o token for válido
     */
    public static function validateCsrfToken($token) {
        if (!isset($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Saneia entrada de texto
     *
     * @param string $input Texto a ser saneado
     * @return string Texto saneado
     */
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                $input[$key] = self::sanitizeInput($value);
            }
            return $input;
        }
        
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Verifica se a string contém apenas caracteres alfanuméricos
     *
     * @param string $input String para validar
     * @return bool Verdadeiro se só contiver caracteres alfanuméricos
     */
    public static function isAlphanumeric($input) {
        return ctype_alnum($input);
    }
    
    /**
     * Verifica se o usuário está bloqueado por tentativas de login
     *
     * @param string $username Nome de usuário
     * @return bool Verdadeiro se o usuário estiver bloqueado
     */
    public static function isUserLocked($username) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM login_attempts WHERE username = :username AND time > :time");
            $stmt->execute([
                'username' => $username,
                'time' => time() - LOGIN_LOCKOUT_TIME
            ]);
            
            $attempts = $stmt->fetchAll();
            return count($attempts) >= MAX_LOGIN_ATTEMPTS;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
 * Registra uma tentativa de login
 *
 * @param string $username Nome de usuário
 * @param string $ip Endereço IP
 * @param bool $success Se o login foi bem-sucedido
 * @return void
 */
public static function logLoginAttempt($username, $ip, $success = false) {
    global $pdo, $logger;
    
    try {
        // Verifica se a tabela existe
        $tableExists = false;
        try {
            $stmt = $pdo->query("SELECT 1 FROM login_attempts LIMIT 1");
            $tableExists = true;
        } catch (PDOException $e) {
            $tableExists = false;
        }
        
        // Cria a tabela se não existir
        if (!$tableExists) {
            $stmt = $pdo->prepare("
                CREATE TABLE IF NOT EXISTS login_attempts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(255) NOT NULL,
                    ip VARCHAR(45) NOT NULL,
                    success TINYINT(1) NOT NULL DEFAULT 0,
                    time INT NOT NULL
                )
            ");
            $stmt->execute();
        }
        
        // Registra a tentativa
        $stmt = $pdo->prepare("
            INSERT INTO login_attempts (username, ip, success, time)
            VALUES (:username, :ip, :success, :time)
        ");
        
        $stmt->execute([
            'username' => $username,
            'ip' => $ip,
            'success' => $success ? 1 : 0,
            'time' => time()
        ]);
        
        // Limpa tentativas antigas
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE time < :time");
        $stmt->execute(['time' => time() - 86400]); // Remove registros com mais de 24 horas
        
    } catch (PDOException $e) {
        if ($logger) {
            $logger->error("Erro ao registrar tentativa de login: " . $e->getMessage());
        }
        // Não propaga o erro para não interromper o fluxo
    }
}
    
    /**
     * Limpa as tentativas de login para um usuário
     *
     * @param string $username Nome de usuário
     * @return void
     */
    public static function clearLoginAttempts($username) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE username = :username");
            $stmt->execute(['username' => $username]);
        } catch (PDOException $e) {
            // Apenas log, não mostrar erro ao usuário
            global $logger;
            $logger->error("Erro ao limpar tentativas de login: " . $e->getMessage());
        }
    }
    
    /**
     * Valida um endereço de email
     *
     * @param string $email Email a ser validado
     * @return bool Verdadeiro se o email for válido
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Valida número de telefone
     *
     * @param string $phone Número de telefone
     * @return bool Verdadeiro se o telefone for válido
     */
    public static function validatePhone($phone) {
        // Remove caracteres não numéricos para fazer a validação
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        
        // Validação simples: mínimo 10 dígitos (DDD + número)
        return strlen($cleanPhone) >= 10;
    }
    
    /**
     * Gera hash seguro para senha
     *
     * @param string $password Senha em texto puro
     * @return string Hash da senha
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    /**
     * Verifica se a senha corresponde ao hash
     *
     * @param string $password Senha em texto puro
     * @param string $hash Hash da senha
     * @return bool Verdadeiro se a senha corresponder ao hash
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Verifica se a senha precisa ser rehashed
     *
     * @param string $hash Hash a ser verificado
     * @return bool Verdadeiro se a senha precisar ser rehashed
     */
    public static function passwordNeedsRehash($hash) {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    /**
     * Obtém o IP real do cliente considerando proxies
     *
     * @return string Endereço IP
     */
    public static function getClientIp() {
        // Verifica se existe proxy reverso
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ipList[0]);
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Verifica os requisitos de segurança de senha
     *
     * @param string $password Senha a verificar
     * @return array Array com resultado (bool) e mensagem (string)
     */
    public static function checkPasswordStrength($password) {
        // Pelo menos 8 caracteres
        if (strlen($password) < 8) {
            return [
                'valid' => false,
                'message' => 'A senha deve ter pelo menos 8 caracteres.'
            ];
        }
        
        // Pelo menos uma letra maiúscula
        if (!preg_match('/[A-Z]/', $password)) {
            return [
                'valid' => false,
                'message' => 'A senha deve conter pelo menos uma letra maiúscula.'
            ];
        }
        
        // Pelo menos uma letra minúscula
        if (!preg_match('/[a-z]/', $password)) {
            return [
                'valid' => false,
                'message' => 'A senha deve conter pelo menos uma letra minúscula.'
            ];
        }
        
        // Pelo menos um número
        if (!preg_match('/[0-9]/', $password)) {
            return [
                'valid' => false,
                'message' => 'A senha deve conter pelo menos um número.'
            ];
        }
        
        return [
            'valid' => true,
            'message' => 'Senha válida.'
        ];
    }
}