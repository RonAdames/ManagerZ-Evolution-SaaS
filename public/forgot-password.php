<?php
require_once 'config.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Security.php';

// Função para enviar e-mail via SMTP diretamente
function sendEmail($to, $subject, $message, $headers) {
    global $logger;
    
    // Get SMTP settings from .env
    $smtp_host = getenv('SMTP_HOST');
    $smtp_port = getenv('SMTP_PORT');
    $smtp_user = getenv('SMTP_USER');
    $smtp_pass = getenv('SMTP_PASS');
    $smtp_from = getenv('SMTP_FROM');
    
    if (empty($smtp_host) || empty($smtp_port) || empty($smtp_user) || empty($smtp_pass)) {
        $logger->error("SMTP settings are missing in .env file");
        return false;
    }
    
    try {
        // Connect to SMTP server
        $socket = fsockopen($smtp_host, $smtp_port, $errno, $errstr, 30);
        if (!$socket) {
            $logger->error("Could not connect to SMTP server: $errstr ($errno)");
            return false;
        }
        
        // Wait for server greeting
        if (!serverResponse($socket, '220')) {
            fclose($socket);
            return false;
        }
        
        // Start TLS handshake for Gmail
        fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
        serverResponse($socket, '250');
        
        fputs($socket, "STARTTLS\r\n");
        if (!serverResponse($socket, '220')) {
            fclose($socket);
            return false;
        }
        
        // Upgrade connection to TLS
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        
        // Start SMTP conversation again over TLS
        fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
        serverResponse($socket, '250');
        
        // Authenticate
        fputs($socket, "AUTH LOGIN\r\n");
        serverResponse($socket, '334');
        fputs($socket, base64_encode($smtp_user) . "\r\n");
        serverResponse($socket, '334');
        fputs($socket, base64_encode($smtp_pass) . "\r\n");
        if (!serverResponse($socket, '235')) {
            fclose($socket);
            return false;
        }
        
        // Send mail
        fputs($socket, "MAIL FROM: <$smtp_from>\r\n");
        serverResponse($socket, '250');
        fputs($socket, "RCPT TO: <$to>\r\n");
        serverResponse($socket, '250');
        fputs($socket, "DATA\r\n");
        serverResponse($socket, '354');
        
        // Send headers and message
        fputs($socket, "Subject: $subject\r\n");
        fputs($socket, "To: $to\r\n");
        fputs($socket, $headers);
        fputs($socket, "\r\n\r\n");
        fputs($socket, $message);
        fputs($socket, "\r\n.\r\n");
        if (!serverResponse($socket, '250')) {
            fclose($socket);
            return false;
        }
        
        // Quit and close connection
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        return true;
        
    } catch (Exception $e) {
        $logger->error("Error sending email: " . $e->getMessage());
        return false;
    }
}

// Função auxiliar para verificar respostas do servidor SMTP
function serverResponse($socket, $expectedCode) {
    global $logger;
    $response = '';
    while (substr($response, 3, 1) != ' ') {
        if (!($response = fgets($socket, 256))) {
            $logger->error("SMTP server did not respond");
            return false;
        }
    }
    
    if (substr($response, 0, 3) != $expectedCode) {
        $logger->error("SMTP error: $response");
        return false;
    }
    
    return true;
}

// Start session securely
Session::start();

// If user is already logged in, redirect to dashboard
if (Session::isAuthenticated()) {
    header("Location: dashboard.php");
    exit;
}

// Generate CSRF token for forms
$csrf_token = Security::generateCsrfToken();

// Initialize variables
$error_message = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
$success_message = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
$step = isset($_GET['step']) ? $_GET['step'] : 'request';
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$username = isset($_GET['username']) ? urldecode(trim($_GET['username'])) : ''; // Decodificar o username da URL

// Adicionar logs para depuração
if ($token && $username) {
    $logger->info("Tentativa de redefinição de senha: Username: $username, Token: $token");
}

// Check if we need to add a reset_token column to the users table
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'reset_token'");
    if ($stmt->rowCount() == 0) {
        // Column doesn't exist, so add it
        $pdo->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) DEFAULT NULL");
        $pdo->exec("ALTER TABLE users ADD COLUMN reset_token_expires TIMESTAMP DEFAULT NULL");
        $logger->info("Colunas reset_token e reset_token_expires adicionadas à tabela users");
    }
} catch (PDOException $e) {
    $error_message = "Erro no sistema. Por favor, tente novamente mais tarde.";
    $logger->error("Error checking/adding reset token columns: " . $e->getMessage());
}

// Process password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
        $error_message = "Erro de segurança. Por favor, tente novamente.";
    } else {
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        
        if (empty($username)) {
            $error_message = "Por favor, digite seu nome de usuário.";
        } else {
            try {
                // Check if user exists
                $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = :username AND active = 1");
                $stmt->execute(['username' => $username]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Generate secure token
                    $token = bin2hex(random_bytes(32));
                    // Aumentar para 24 horas (em vez de 1 hora)
                    $expires = date('Y-m-d H:i:s', time() + 86400); 
                    
                    // Save token to database
                    $stmt = $pdo->prepare("UPDATE users SET reset_token = :token, reset_token_expires = :expires WHERE id = :id");
                    $stmt->execute([
                        'token' => $token,
                        'expires' => $expires,
                        'id' => $user['id']
                    ]);
                    
                    $logger->info("Token gerado para usuário {$username}: $token - Expira em: $expires");
                    
                    // Get SMTP settings from .env
                    $smtp_from = getenv('SMTP_FROM');
                    $site_url = getenv('APP_URL');
                    
                    // Prepare email
                    $to = $username; // Assuming username is email address
                    $subject = "Redefinição de Senha";
                    $reset_link = "{$site_url}/forgot-password.php?step=reset&token={$token}&username=" . urlencode($username);
                    
                    $message = "Olá,\n\n";
                    $message .= "Recebemos uma solicitação para redefinir sua senha.\n\n";
                    $message .= "Para redefinir sua senha, clique no link abaixo ou copie e cole no seu navegador:\n";
                    $message .= $reset_link . "\n\n";
                    $message .= "Este link expirará em 24 horas.\n\n";
                    $message .= "Se você não solicitou esta redefinição, ignore este email.\n\n";
                    $message .= "Atenciosamente,\n";
                    $message .= "Equipe " . APP_NAME;
                    
                    // Set mail headers
                    $headers = "From: " . APP_NAME . " <{$smtp_from}>\r\n";
                    $headers .= "Reply-To: {$smtp_from}\r\n";
                    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                    
                    // Send mail usando nossa função personalizada
                    if (sendEmail($to, $subject, $message, $headers)) {
                        $success_message = "Enviamos instruções para redefinir sua senha. Por favor, verifique seu email.";
                        $logger->info("Password reset link sent to user: {$username} - Link: {$reset_link}");
                    } else {
                        $error_message = "Não foi possível enviar o email. Por favor, tente novamente mais tarde.";
                        $logger->error("Failed to send password reset email to: {$username}");
                    }
                } else {
                    // User not found, but we don't want to reveal that
                    $success_message = "Se sua conta existir, enviamos instruções para redefinir sua senha. Por favor, verifique seu email.";
                    $logger->warning("Password reset requested for non-existent user: {$username}");
                }
            } catch (PDOException $e) {
                $error_message = "Erro ao processar sua solicitação. Por favor, tente novamente mais tarde.";
                $logger->error("Database error during password reset request: " . $e->getMessage());
            } catch (Exception $e) {
                $error_message = "Erro ao processar sua solicitação. Por favor, tente novamente mais tarde.";
                $logger->error("Error during password reset request: " . $e->getMessage());
            }
        }
    }
}

// Process setting new password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
        $error_message = "Erro de segurança. Por favor, tente novamente.";
    } else {
        $token = isset($_POST['token']) ? trim($_POST['token']) : '';
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        
        if (empty($token) || empty($username) || empty($password) || empty($confirm_password)) {
            $error_message = "Todos os campos são obrigatórios.";
        } elseif ($password !== $confirm_password) {
            $error_message = "As senhas não coincidem.";
        } else {
            try {
                // Check password strength
                $passwordCheck = Security::checkPasswordStrength($password);
                if (!$passwordCheck['valid']) {
                    $error_message = $passwordCheck['message'];
                } else {
                    // Verificar e logar dados do usuário para debug
                    $checkStmt = $pdo->prepare("SELECT id, username, reset_token, reset_token_expires FROM users WHERE username = :username");
                    $checkStmt->execute(['username' => $username]);
                    $checkUser = $checkStmt->fetch();
                    
                    if ($checkUser) {
                        $logger->info("Verificando token para usuário {$username}: 
                            Token do BD: {$checkUser['reset_token']} 
                            Token recebido: {$token}
                            Expiração: {$checkUser['reset_token_expires']}
                            Hora atual: " . date('Y-m-d H:i:s'));
                    } else {
                        $logger->error("Usuário não encontrado: {$username}");
                    }
                    
                    // Verify token
                    $stmt = $pdo->prepare("
                        SELECT id FROM users 
                        WHERE username = :username 
                        AND reset_token = :token 
                        AND reset_token_expires > NOW() 
                        AND active = 1
                    ");
                    $stmt->execute([
                        'username' => $username,
                        'token' => $token
                    ]);
                    $user = $stmt->fetch();
                    
                    if ($user) {
                        // Hash new password
                        $password_hash = Security::hashPassword($password);
                        
                        // Update password and clear token
                        $stmt = $pdo->prepare("
                            UPDATE users 
                            SET password = :password, reset_token = NULL, reset_token_expires = NULL 
                            WHERE id = :id
                        ");
                        $stmt->execute([
                            'password' => $password_hash,
                            'id' => $user['id']
                        ]);
                        
                        $success_message = "Sua senha foi redefinida com sucesso! Você pode agora <a href='index.php'>fazer login</a> com sua nova senha.";
                        $logger->info("Password reset successful for user ID: {$user['id']}");
                        
                        // Update step to prevent resubmitting
                        $step = 'success';
                    } else {
                        $error_message = "O link de redefinição de senha é inválido ou expirou. Por favor, solicite um novo link.";
                        $logger->warning("Invalid token used for password reset: {$token}");
                    }
                }
            } catch (PDOException $e) {
                $error_message = "Erro ao redefinir sua senha. Por favor, tente novamente mais tarde.";
                $logger->error("Database error during password reset: " . $e->getMessage());
            } catch (Exception $e) {
                $error_message = "Erro ao redefinir sua senha. Por favor, tente novamente mais tarde.";
                $logger->error("Error during password reset: " . $e->getMessage());
            }
        }
    }
}

// Verify the token if we're on the reset step
if ($step === 'reset' && !empty($token) && !empty($username)) {
    try {
        // Verificar e logar dados do usuário para debug
        $checkStmt = $pdo->prepare("SELECT id, username, reset_token, reset_token_expires FROM users WHERE username = :username");
        $checkStmt->execute(['username' => $username]);
        $checkUser = $checkStmt->fetch();
        
        if ($checkUser) {
            $logger->info("Verificando token para usuário {$username}: 
                Token do BD: {$checkUser['reset_token']} 
                Token recebido: {$token}
                Expiração: {$checkUser['reset_token_expires']}
                Hora atual: " . date('Y-m-d H:i:s'));
        } else {
            $logger->error("Usuário não encontrado: {$username}");
        }
        
        // Consulta ajustada para debug
        $stmt = $pdo->prepare("
            SELECT id, reset_token, reset_token_expires 
            FROM users 
            WHERE username = :username AND active = 1
        ");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $error_message = "Usuário não encontrado ou inativo.";
            $step = 'request';
        } else if ($user['reset_token'] !== $token) {
            $error_message = "Token inválido. Por favor, solicite um novo link de redefinição.";
            $logger->error("Token inválido. Esperado: {$user['reset_token']}, Recebido: {$token}");
            $step = 'request';
        } else if (strtotime($user['reset_token_expires']) < time()) {
            $error_message = "O link de redefinição expirou. Por favor, solicite um novo link.";
            $logger->error("Token expirado. Expiração: {$user['reset_token_expires']}, Agora: " . date('Y-m-d H:i:s'));
            $step = 'request';
        }
    } catch (PDOException $e) {
        $error_message = "Erro ao verificar o link de redefinição. Por favor, tente novamente mais tarde.";
        $logger->error("Database error verifying reset token: " . $e->getMessage());
        $step = 'request';
    }
}

// Page title
$title = "Esqueceu a Senha";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Recuperação de Senha - Painel de Controle WhatsApp">
    <meta name="author" content="Sonho Digital">

    <title>Recuperar Senha - <?php echo APP_NAME; ?></title>

    <!-- Prevenção de armazenamento em cache para páginas sensíveis -->
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link rel="icon" href="favicon.ico" type="image/png">
    
    <!-- Link para o arquivo CSS externo -->
    <link rel="stylesheet" href="assets/css/login.css">
    
    <style>
        /* Additional styles for password reset */
        .password-form {
            margin-bottom: 20px;
        }
        
        .password-strength {
            margin-top: 10px;
            height: 5px;
            background-color: #eaeaea;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            transition: width 0.3s ease, background-color 0.3s ease;
        }
        
        .password-feedback {
            font-size: 12px;
            margin-top: 5px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .alert p {
            margin: 0;
        }
        
        .login-description {
            color: #6b7280;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="welcome-container">
            <h2 class="welcome-title">Precisa de ajuda?</h2>
            <p class="welcome-text">Entre em contato com nosso suporte!</p>
            <a href="https://wa.me/5515991728242/?text=Olá!%20Preciso%20de%20ajuda%20para%20recuperar%20minha%20senha." class="signup-button">Chame o Suporte!</a>
        </div>
        
        <div class="form-container">
            <h1 class="login-title">🔑 Recuperar senha</h1>
            <p>Digite seu e-mail para receber as instruções</p>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <div class="alert-content">
                        <p><?php echo $error_message; ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <div class="alert-content">
                        <p><?php echo $success_message; ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <form action="reset-password.php" method="POST" id="resetForm" autocomplete="off">
                <!-- Token CSRF para proteger o formulário -->
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                            </svg>
                        </span>
                        <input type="email" id="email" name="email" placeholder="Digite seu e-mail" required>
                    </div>
                </div>

                <button type="submit" class="login-button">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    ENVIAR INSTRUÇÕES
                </button>

                <div class="back-to-login">
                    <a href="index.php">Voltar para o login</a>
                </div>
            </form>
        </div>
        
        <!-- Rodapé com direitos autorais -->
        <footer class="site-footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?> | Desenvolvido por Sonho Digital LTDA. Todos os direitos reservados.</p>
            <p>v<?php echo APP_VERSION; ?></p> 
        </footer>
    </div>

    <script>
        // Adiciona loading no botão durante o submit
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            // Desativa duplo envio
            if (this.submitting) {
                e.preventDefault();
                return;
            }
            
            this.submitting = true;
            const button = this.querySelector('button[type="submit"]');
            button.disabled = true;
            button.innerHTML = `
                <svg class="spinner" viewBox="0 0 50 50">
                    <circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
                </svg>
                Enviando...
            `;
        });
        
        // Previne reenvio de formulário em caso de F5 ou refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>