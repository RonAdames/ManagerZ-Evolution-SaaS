<?php
$title = "Trocar Senha";
require_once 'config.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Security.php';

// Iniciar sessão de forma segura
Session::start();

// Verificar se o usuário está autenticado
if (!Session::isAuthenticated()) {
    header("Location: index.php");
    exit;
}

$user_id = Session::get('user_id');
$success_message = '';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $senha_atual = $_POST['senha_atual'];
        $nova_senha = $_POST['nova_senha'];
        $confirmar_senha = $_POST['confirmar_senha'];

        if ($nova_senha !== $confirmar_senha) {
            $error_message = "A nova senha e a confirmação não coincidem.";
        } else {
            // Validar força da senha
            $passwordCheck = Security::checkPasswordStrength($nova_senha);
            if (!$passwordCheck['valid']) {
                $error_message = $passwordCheck['message'];
            } else {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = :user_id");
                $stmt->execute(['user_id' => $user_id]);
                $user = $stmt->fetch();

                if ($user && Security::verifyPassword($senha_atual, $user['password'])) {
                    // Usar a função Security::hashPassword garante o uso do custo 12
                    $nova_senha_hash = Security::hashPassword($nova_senha);
                    
                    $stmt = $pdo->prepare("UPDATE users SET password = :nova_senha WHERE id = :user_id");
                    $stmt->execute(['nova_senha' => $nova_senha_hash, 'user_id' => $user_id]);
                    
                    $success_message = "Senha atualizada com sucesso!";
                    
                    // Log da alteração de senha (opcional)
                    $logger->info("Senha alterada com sucesso para o usuário ID: {$user_id}");
                } else {
                    $error_message = "A senha atual está incorreta.";
                    
                    // Log da tentativa falha (opcional)
                    $logger->warning("Tentativa falha de alteração de senha para o usuário ID: {$user_id} - Senha atual incorreta");
                }
            }
        }
    } catch (PDOException $e) {
        // Tratar erros de banco de dados
        $logger->error("Erro ao atualizar senha: " . $e->getMessage());
        $error_message = "Ocorreu um erro ao atualizar a senha. Tente novamente mais tarde.";
    } catch (Exception $e) {
        // Tratar outros erros
        $logger->error("Erro geral ao trocar senha: " . $e->getMessage());
        $error_message = "Ocorreu um erro inesperado. Tente novamente mais tarde.";
    }
}

include 'base.php';
?>


<div class="content-wrapper">
    <div class="page-header">
        <div class="header-content">
            <h1>Trocar Senha</h1>
            <p class="text-muted">Atualize sua senha de acesso</p>
        </div>
    </div>

    <?php if ($error_message): ?>
    <div class="alert alert-error">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <div class="alert-content">
            <h4>Erro</h4>
            <p><?php echo htmlspecialchars($error_message); ?></p>
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
            <h4>Sucesso</h4>
            <p><?php echo htmlspecialchars($success_message); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <div class="password-form">
        <form action="trocar_senha.php" method="POST" id="changePasswordForm" onsubmit="return validateForm()">
            <div class="form-section">
                <div class="form-group">
                    <label for="senha_atual">Senha Atual</label>
                    <div class="password-input-wrapper">
                        <input type="password" 
                               id="senha_atual" 
                               name="senha_atual" 
                               required>
                        <button type="button" class="toggle-password" onclick="togglePassword('senha_atual')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="nova_senha">Nova Senha</label>
                    <div class="password-input-wrapper">
                        <input type="password" 
                               id="nova_senha" 
                               name="nova_senha" 
                               required>
                        <button type="button" class="toggle-password" onclick="togglePassword('nova_senha')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                    <div class="password-strength" id="passwordStrength"></div>
                </div>

                <div class="form-group">
                    <label for="confirmar_senha">Confirmar Nova Senha</label>
                    <div class="password-input-wrapper">
                        <input type="password" 
                               id="confirmar_senha" 
                               name="confirmar_senha" 
                               required>
                        <button type="button" class="toggle-password" onclick="togglePassword('confirmar_senha')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                    <div id="passwordMatch" class="password-match"></div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path>
                    </svg>
                    Atualizar Senha
                </button>
                <a href="dashboard.php" class="btn-secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                    Voltar
                </a>
            </div>
        </form>
    </div>
</div>

<link rel="stylesheet" href="/assets/css/trocar_senha.css">
<script src="/assets/js/trocar_senha.js"></script>