<?php
require_once 'config.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Security.php';

// Inicializa a sessão
Session::start();

// Se já estiver logado, redireciona para o dashboard
if (Session::isAuthenticated()) {
    header("Location: dashboard.php");
    exit;
}

// Processa o formulário de login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verifica CSRF token
    try {
        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            header("Location: index.php?error=" . urlencode("Erro de segurança. Por favor, tente novamente."));
            exit;
        }
    } catch (Exception $e) {
        error_log("Erro ao validar CSRF: " . $e->getMessage());
        header("Location: index.php?error=" . urlencode("Erro de segurança. Por favor, tente novamente."));
        exit;
    }
    
    // Obtém os dados do formulário
    $username = isset($_POST['username']) ? $_POST['username'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Validação básica
    if (empty($username) || empty($password)) {
        header("Location: index.php?error=" . urlencode("Todos os campos são obrigatórios"));
        exit;
    }
    
    // Obtém o IP do cliente de forma segura
    try {
        $clientIp = Security::getClientIp();
    } catch (Exception $e) {
        $clientIp = '0.0.0.0';
    }
    
    // Verifica bloqueio por excesso de tentativas
    try {
        if (Security::isUserLocked($username)) {
            header("Location: index.php?error=" . urlencode("Conta temporariamente bloqueada por excesso de tentativas. Tente novamente mais tarde."));
            exit;
        }
    } catch (Exception $e) {
        error_log("Erro ao verificar bloqueio: " . $e->getMessage());
        // Continua mesmo se falhar
    }
    
    try {
        // Consulta o usuário
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username AND active = 1");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();
        
        // Verifica a senha
        if ($user && Security::verifyPassword($password, $user['password'])) {
            // Login bem-sucedido
            
            // Tenta limpar tentativas de login
            try {
                Security::clearLoginAttempts($username);
            } catch (Exception $e) {
                error_log("Erro ao limpar tentativas: " . $e->getMessage());
                // Continua mesmo se falhar
            }
            
            // Verifica se a senha precisa ser rehashed
            try {
                if (Security::passwordNeedsRehash($user['password'])) {
                    $newHash = Security::hashPassword($password);
                    $stmtUpdate = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id");
                    $stmtUpdate->execute([
                        'password' => $newHash,
                        'id' => $user['id']
                    ]);
                }
            } catch (Exception $e) {
                error_log("Erro ao atualizar hash da senha: " . $e->getMessage());
                // Continua mesmo se falhar
            }
            
            // Regenera o ID da sessão
            session_regenerate_id(true);
            
            // Armazena dados na sessão
            Session::set('user_id', $user['id']);
            Session::set('username', $user['username']);
            Session::set('user_skill', $user['skill']);
            Session::set('last_activity', time());
            Session::set('last_login_ip', $clientIp);
            
            // Redireciona para o dashboard
            header("Location: dashboard.php");
            exit;
        } else {
            // Falha no login
            
            // Tenta registrar a tentativa
            try {
                Security::logLoginAttempt($username, $clientIp);
            } catch (Exception $e) {
                error_log("Erro ao registrar tentativa: " . $e->getMessage());
                // Continua mesmo se falhar
            }
            
            // Redireciona com erro
            header("Location: index.php?error=" . urlencode("Usuário ou senha incorretos"));
            exit;
        }
    } catch (Exception $e) {
        // Registra erro no log e redireciona
        error_log("Erro no login: " . $e->getMessage());
        header("Location: index.php?error=" . urlencode("Erro no sistema. Tente novamente mais tarde."));
        exit;
    }
}

// Acesso direto ou após processamento
header("Location: index.php");
exit;