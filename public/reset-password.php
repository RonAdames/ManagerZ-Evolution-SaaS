<?php
require_once 'config.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Security.php';
require_once __DIR__ . '/../src/Database.php';

// Iniciar sessão segura
Session::start();

// Se já estiver logado, redireciona para o dashboard
if (Session::isAuthenticated()) {
    header("Location: dashboard.php");
    exit;
}

// Gera um novo token CSRF
$csrf_token = Security::generateCsrfToken();

// Obtém o token da URL
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

// Se não houver token, redireciona para a página de esqueceu senha
if (empty($token)) {
    header("Location: forgot-password.php");
    exit;
}

try {
    // Conecta ao banco de dados
    $db = Database::getInstance();
    
    // Verifica se o token é válido e não expirou
    $stmt = $db->prepare("
        SELECT pr.*, u.username, u.email 
        FROM password_resets pr 
        JOIN usuarios u ON pr.user_id = u.id 
        WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used = 0
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reset) {
        header("Location: forgot-password.php?error=" . urlencode("Link inválido ou expirado. Por favor, solicite um novo link de redefinição de senha."));
        exit;
    }
    
    // Se for uma requisição POST, processa a redefinição de senha
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Valida o token CSRF
        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            throw new Exception("Token de segurança inválido");
        }
        
        // Obtém e valida a nova senha
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (strlen($password) < 8) {
            throw new Exception("A senha deve ter pelo menos 8 caracteres");
        }
        
        if ($password !== $confirm_password) {
            throw new Exception("As senhas não coincidem");
        }
        
        // Hash da nova senha
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Atualiza a senha do usuário
        $stmt = $db->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $reset['user_id']]);
        
        // Marca o token como usado
        $stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
        $stmt->execute([$reset['id']]);
        
        // Redireciona para a página de login com mensagem de sucesso
        header("Location: index.php?success=" . urlencode("Senha redefinida com sucesso! Você já pode fazer login com sua nova senha."));
        exit;
    }
    
} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Redefinição de Senha - Painel de Controle WhatsApp">
    <meta name="author" content="Sonho Digital">
    
    <title>Redefinir Senha - <?php echo APP_NAME; ?></title>
    
    <!-- Prevenção de armazenamento em cache para páginas sensíveis -->
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <!-- Link para o arquivo CSS externo -->
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <div class="login-container">
        <div class="welcome-container">
            <h2 class="welcome-title">Redefinir Senha</h2>
            <p class="welcome-text">Crie uma nova senha para sua conta</p>
        </div>
        
        <div class="form-container">
            <h1 class="login-title">🔑 Nova senha</h1>
            <p>Digite sua nova senha abaixo</p>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <div class="alert-content">
                        <p><?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <form action="reset-password.php?token=<?php echo htmlspecialchars($token); ?>" method="POST" id="resetForm" autocomplete="off">
                <!-- Token CSRF para proteger o formulário -->
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </span>
                        <input type="password" id="password" name="password" placeholder="Nova senha" required minlength="8">
                        <button type="button" class="toggle-password" aria-label="Mostrar senha" onclick="togglePassword('password')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                    <div class="password-strength">
                        <div class="password-strength-bar" id="strengthBar"></div>
                    </div>
                    <div class="password-feedback" id="passwordFeedback"></div>
                </div>

                <div class="form-group">
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </span>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirmar nova senha" required minlength="8">
                        <button type="button" class="toggle-password" aria-label="Mostrar senha" onclick="togglePassword('confirm_password')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                    <div class="password-match" id="passwordMatch"></div>
                </div>

                <button type="submit" class="login-button">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    REDEFINIR SENHA
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
        // Toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const button = input.nextElementSibling;
            
            if (input.type === 'password') {
                input.type = 'text';
                button.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                `;
            } else {
                input.type = 'password';
                button.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                `;
            }
        }

        // Check password strength
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const confirmInput = document.getElementById('confirm_password');
            const strengthBar = document.getElementById('strengthBar');
            const feedback = document.getElementById('passwordFeedback');
            const matchFeedback = document.getElementById('passwordMatch');
            
            if (passwordInput) {
                passwordInput.addEventListener('input', function() {
                    const password = this.value;
                    let strength = 0;
                    let color = '';
                    let message = '';
                    
                    // Check password length
                    if (password.length > 0) {
                        strength += password.length >= 8 ? 1 : 0;
                        // Check for uppercase and lowercase
                        strength += /[A-Z]/.test(password) && /[a-z]/.test(password) ? 1 : 0;
                        // Check for numbers
                        strength += /\d/.test(password) ? 1 : 0;
                        // Check for special characters
                        strength += /[^A-Za-z0-9]/.test(password) ? 1 : 0;
                    }
                    
                    // Set color and message based on strength
                    switch(strength) {
                        case 0:
                            color = '#f8f9fa';
                            message = '';
                            break;
                        case 1:
                            color = '#dc3545';
                            message = 'Senha muito fraca';
                            break;
                        case 2:
                            color = '#ffc107';
                            message = 'Senha fraca';
                            break;
                        case 3:
                            color = '#0d6efd';
                            message = 'Senha média';
                            break;
                        case 4:
                            color = '#198754';
                            message = 'Senha forte';
                            break;
                    }
                    
                    // Update UI
                    strengthBar.style.width = (strength * 25) + '%';
                    strengthBar.style.backgroundColor = color;
                    feedback.textContent = message;
                    feedback.style.color = color;
                    
                    // Check match if confirm password has value
                    if (confirmInput && confirmInput.value) {
                        checkPasswordMatch();
                    }
                });
            }
            
            if (confirmInput) {
                confirmInput.addEventListener('input', checkPasswordMatch);
            }
            
            function checkPasswordMatch() {
                if (passwordInput.value === confirmInput.value) {
                    matchFeedback.textContent = '✓ As senhas coincidem';
                    matchFeedback.style.color = '#198754';
                } else {
                    matchFeedback.textContent = '✗ As senhas não coincidem';
                    matchFeedback.style.color = '#dc3545';
                }
            }
        });

        // Form submission loading state
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            // Basic validation
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('As senhas não coincidem.');
                return;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                alert('A senha deve ter pelo menos 8 caracteres.');
                return;
            }
            
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
                Processando...
            `;
        });
        
        // Previne reenvio de formulário em caso de F5 ou refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html> 