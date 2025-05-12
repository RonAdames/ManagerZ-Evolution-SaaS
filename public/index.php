<?php
require_once 'config.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Security.php';

// Iniciar sessão segura
Session::start();

// Se já estiver logado, redireciona para o dashboard
if (Session::isAuthenticated()) {
    header("Location: dashboard.php");
    exit;
}

// Gera um novo token CSRF
$csrf_token = Security::generateCsrfToken();

// Mensagens de erro ou sucesso
$error_message = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
$success_message = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';

// Verifica se a conta foi bloqueada
$account_locked = isset($_GET['locked']) && $_GET['locked'] == 1;

// Verifica se a sessão expirou
$session_expired = isset($_GET['expired']) && $_GET['expired'] == 1;

// Mensagem de expiração de sessão
if ($session_expired) {
    $error_message = "Sua sessão expirou por inatividade. Por favor, faça login novamente.";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Painel de Controle WhatsApp">
    <meta name="author" content="Sonho Digital">
    
    <title>Login - <?php echo APP_NAME; ?></title>
    
    <!-- Prevenção de armazenamento em cache para páginas sensíveis -->
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <!-- Link para o arquivo CSS externo -->
    <link rel="stylesheet" href="assets/css/login.css">
    <style>

        
    

    </style>
</head>
<body>
    <div class="login-container">
        <div class="welcome-container">
            <h2 class="welcome-title">Bem-vindo!</h2>
            <p class="welcome-text">Ainda não tem um Login?🤔</p>
            <a href="https://wa.me/5515991728242/?text=Olá!%20Gostaria%20de%20informações%20sobre%20a%20Painel." class="signup-button">Chame o Suporte!</a>
        </div>
        
        <div class="form-container">
            <h1 class="login-title">Olá, 👋</h1>
            
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

            <form action="login.php" method="POST" id="loginForm" autocomplete="off">
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
                        <input type="text" id="username" name="username" placeholder="Digite seu ID ou Email" required>
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </span>
                        <input type="password" id="password" name="password" placeholder="Digite sua senha" required>
                        <button type="button" class="toggle-password" aria-label="Mostrar senha" onclick="togglePassword()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="remember-forgot">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" id="remember"> Lembre-me
                    </label>
                    <a href="forgot-password.php" class="forgot-password">Esqueceu a senha?</a>
                </div>

                <button type="submit" class="login-button">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                        <polyline points="10 17 15 12 10 7"></polyline>
                        <line x1="15" y1="12" x2="3" y2="12"></line>
                    </svg>
                    ENTRAR
                </button>
            </form>
        </div>
            <!-- Rodapé com direitos autorais -->
            <footer class="site-footer">
                <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?> | Desenvolvido por Sonho Digital LTDA. Todos os direitos reservados.</p>
                <p>v<?php echo APP_VERSION; ?></p> 
            </footer>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const button = document.querySelector('.toggle-password');
            
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

        // Adiciona loading no botão durante o submit
        document.getElementById('loginForm').addEventListener('submit', function(e) {
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
                Entrando...
            `;
        });
        
        // Previne reenvio de formulário em caso de F5 ou refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>