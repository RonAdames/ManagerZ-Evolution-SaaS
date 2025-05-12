<?php
/**
 * Classe para gerenciamento seguro de sessões
 */
class Session {
    /**
     * Inicia ou resume uma sessão existente
     *
     * @return void
     */
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            // Configurações de segurança para cookies de sessão
            ini_set('session.use_strict_mode', 1);
            ini_set('session.use_cookies', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_httponly', 1);
            
            // Utiliza secure cookies se estiver em HTTPS
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                ini_set('session.cookie_secure', 1);
            }
            
            // Define o tempo de vida da sessão
            ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
            session_set_cookie_params(SESSION_LIFETIME);
            
            // Inicia a sessão
            session_start();
            
            // Verifica se é necessário regenerar o ID da sessão
            self::checkSessionRegen();
            
            // Verifica se a sessão expirou
            self::checkSessionExpiry();
        }
    }
    
    /**
     * Verifica se é necessário regenerar o ID da sessão
     *
     * @return void
     */
    private static function checkSessionRegen() {
        // Regenera o ID da sessão a cada 30 minutos para evitar ataques de fixação
        if (!isset($_SESSION['last_regen']) || time() - $_SESSION['last_regen'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['last_regen'] = time();
        }
    }
    
    /**
     * Verifica se a sessão expirou
     *
     * @return void
     */
    private static function checkSessionExpiry() {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
            // A sessão expirou, fazer logout
            self::destroy();
            header("Location: /index.php?expired=1");
            exit;
        }
        
        // Atualiza o tempo da última atividade
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * Destrói a sessão atual
     *
     * @return void
     */
    public static function destroy() {
        // Limpa todos os dados da sessão
        $_SESSION = [];
        
        // Destrói o cookie de sessão se existir
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        // Destrói a sessão
        session_destroy();
    }
    
    /**
     * Define um valor na sessão
     *
     * @param string $key Chave da sessão
     * @param mixed $value Valor da sessão
     * @return void
     */
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    /**
     * Obtém um valor da sessão
     *
     * @param string $key Chave da sessão
     * @param mixed $default Valor padrão caso a chave não exista
     * @return mixed Valor da sessão ou valor padrão
     */
    public static function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Verifica se uma chave existe na sessão
     *
     * @param string $key Chave da sessão
     * @return bool Verdadeiro se a chave existir
     */
    public static function has($key) {
        return isset($_SESSION[$key]);
    }
    
    /**
     * Remove uma chave da sessão
     *
     * @param string $key Chave da sessão
     * @return void
     */
    public static function remove($key) {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
    
    /**
     * Define uma mensagem flash na sessão
     *
     * @param string $type Tipo da mensagem (success, error, info, warning)
     * @param string $message Texto da mensagem
     * @return void
     */
    public static function setFlash($type, $message) {
        $_SESSION['flash_messages'][$type] = $message;
    }
    
    /**
     * Obtém e remove uma mensagem flash da sessão
     *
     * @param string $type Tipo da mensagem (success, error, info, warning)
     * @return string|null Mensagem flash ou null se não existir
     */
    public static function getFlash($type) {
        if (isset($_SESSION['flash_messages'][$type])) {
            $message = $_SESSION['flash_messages'][$type];
            unset($_SESSION['flash_messages'][$type]);
            return $message;
        }
        return null;
    }
    
    /**
     * Verifica se existe uma mensagem flash
     *
     * @param string $type Tipo da mensagem (success, error, info, warning)
     * @return bool Verdadeiro se existir mensagem flash
     */
    public static function hasFlash($type) {
        return isset($_SESSION['flash_messages'][$type]);
    }
    
    /**
     * Verifica se o usuário está autenticado
     *
     * @return bool Verdadeiro se o usuário estiver autenticado
     */
    public static function isAuthenticated() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Verifica se o usuário é administrador
     *
     * @return bool Verdadeiro se o usuário for administrador
     */
    public static function isAdmin() {
        if (!self::isAuthenticated()) {
            return false;
        }
        
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("SELECT skill FROM users WHERE id = :id");
            $stmt->execute(['id' => $_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            return $user && $user['skill'] == 2;
        } catch (PDOException $e) {
            return false;
        }
    }
}