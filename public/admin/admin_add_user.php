<?php
$title = "Criar Novo Usuário";
require_once '../config.php';
require_once __DIR__ . '/../../src/Session.php';
require_once __DIR__ . '/../../src/Security.php';

// Iniciar sessão de forma segura
Session::start();

// Verificar autenticação
if (!Session::isAuthenticated()) {
    header("Location: ../index.php");
    exit;
}

// Verificar se é administrador
if (!Session::isAdmin()) {
    Session::setFlash('error', 'Acesso negado. Você não tem permissão para acessar esta página.');
    header("Location: ../dashboard.php");
    exit;
}

// Para depuração temporária, se necessário
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

$error_message = '';
$success_message = '';

// Processar o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
        $error_message = "Erro de segurança. Por favor, tente novamente.";
    } else {
        // Sanitizar e validar os dados de entrada
        $username = trim(Security::sanitizeInput($_POST['username'] ?? ''));
        $password = $_POST['password'] ?? ''; // Senhas não são sanitizadas
        $confirm_password = $_POST['confirm_password'] ?? '';
        $max_instancias = (int)($_POST['max_instancias'] ?? 3);
        $skill = (int)($_POST['skill'] ?? 1);
        $first_name = trim(Security::sanitizeInput($_POST['first_name'] ?? ''));
        $last_name = trim(Security::sanitizeInput($_POST['last_name'] ?? ''));

        // Validações
        if (strlen($username) < 3 || strlen($username) > 50) {
            $error_message = "O nome de usuário deve ter entre 3 e 50 caracteres.";
        } elseif (!Security::isAlphanumeric(str_replace('_', '', $username))) {
            $error_message = "O nome de usuário deve conter apenas letras, números e underscore (_).";
        } elseif ($password !== $confirm_password) {
            $error_message = "As senhas não coincidem.";
        } elseif ($max_instancias < 1 || $max_instancias > 100) {
            $error_message = "O número de instâncias deve estar entre 1 e 100.";
        } elseif ($skill != 1 && $skill != 2) {
            $error_message = "Tipo de usuário inválido.";
        } elseif (strlen($first_name) < 2 || strlen($first_name) > 50) {
            $error_message = "O primeiro nome deve ter entre 2 e 50 caracteres.";
        } elseif (strlen($last_name) < 2 || strlen($last_name) > 50) {
            $error_message = "O sobrenome deve ter entre 2 e 50 caracteres.";
        } else {
            // Verificação de força da senha
            $passwordCheck = Security::checkPasswordStrength($password);
            if (!$passwordCheck['valid']) {
                $error_message = $passwordCheck['message'];
            } else {
                try {
                    // Verificar se o usuário já existe
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
                    $stmt->execute(['username' => $username]);
                    
                    if ($stmt->fetchColumn() > 0) {
                        $error_message = "Este nome de usuário já está em uso.";
                    } else {
                        // Criar hash seguro da senha
                        $password_hash = Security::hashPassword($password);
                        
                        // Inserir o novo usuário com os novos campos
                        $stmt = $pdo->prepare("
                            INSERT INTO users (
                                username, 
                                password, 
                                max_instancias, 
                                skill,
                                created_at,
                                active,
                                first_name,
                                last_name
                            ) VALUES (
                                :username, 
                                :password, 
                                :max_instancias, 
                                :skill,
                                NOW(),
                                1,
                                :first_name,
                                :last_name
                            )
                        ");
                        
                        $stmt->execute([
                            'username' => $username,
                            'password' => $password_hash,
                            'max_instancias' => $max_instancias,
                            'skill' => $skill,
                            'first_name' => $first_name,
                            'last_name' => $last_name
                        ]);
                        
                        // Registrar no log
                        $logger->info("Usuário '{$username}' criado pelo administrador " . Session::get('username'));
                        
                        // Definir mensagem de sucesso e redirecionar
                        Session::setFlash('success', "Usuário '{$username}' criado com sucesso.");
                        header("Location: admin.php");
                        exit;
                    }
                } catch (PDOException $e) {
                    $logger->error("Erro ao criar usuário: " . $e->getMessage());
                    $error_message = "Erro ao criar usuário. Por favor, tente novamente mais tarde.";
                }
            }
        }
    }
}

// Gerar novo token CSRF
$csrf_token = Security::generateCsrfToken();

include '../base.php';
?>

<div class="content-wrapper">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="admin.php" class="breadcrumb-link">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            Painel Admin
        </a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Criar Usuário</span>
    </div>

    <div class="page-header">
        <h1>Criar Novo Usuário</h1>
        <p class="text-muted">Adicione um novo usuário ao sistema</p>
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

    <div class="card">
        <div class="card-body">
            <form action="admin_add_user.php" method="POST" id="createUserForm" onsubmit="return validateForm()">
                <!-- Token CSRF para proteger o formulário -->
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="first_name">Primeiro Nome</label>
                        <input type="text" 
                               id="first_name" 
                               name="first_name" 
                               required 
                               minlength="2" 
                               maxlength="50"
                               placeholder="Ex: João">
                    </div>

                    <div class="form-group">
                        <label for="last_name">Sobrenome</label>
                        <input type="text" 
                               id="last_name" 
                               name="last_name" 
                               required 
                               minlength="2" 
                               maxlength="50"
                               placeholder="Ex: Silva">
                    </div>

                    <div class="form-group">
                        <label for="username">Nome de Usuário</label>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               required 
                               minlength="3" 
                               maxlength="50"
                               pattern="[a-zA-Z0-9_]+"
                               placeholder="Ex: joao_silva">
                        <small>Use apenas letras, números e underscore (_)</small>
                    </div>

                    <div class="form-group">
                        <label for="password">Senha</label>
                        <div class="password-input-wrapper">
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   required
                                   minlength="8">
                            <button type="button" class="toggle-password" onclick="togglePassword('password')">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                        <div id="passwordStrength"></div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirmar Senha</label>
                        <div class="password-input-wrapper">
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   required>
                            <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                        <div id="passwordMatch"></div>
                    </div>

                    <div class="form-group">
                        <label for="max_instancias">Limite de Instâncias</label>
                        <input type="number" 
                               id="max_instancias" 
                               name="max_instancias" 
                               value="3" 
                               min="1" 
                               max="100" 
                               required>
                        <small>Número máximo de instâncias que o usuário pode criar</small>
                    </div>

                    <div class="form-group">
                        <label for="skill">Tipo de Usuário</label>
                        <select id="skill" name="skill" required>
                            <option value="1">Usuário Padrão</option>
                            <option value="2">Administrador</option>
                        </select>
                        <small>Defina as permissões do usuário</small>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <line x1="20" y1="8" x2="20" y2="14"></line>
                            <line x1="23" y1="11" x2="17" y2="11"></line>
                        </svg>
                        Criar Usuário
                    </button>
                    <a href="admin.php" class="btn-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="19" y1="12" x2="5" y2="12"></line>
                            <polyline points="12 19 5 12 12 5"></polyline>
                        </svg>
                        Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.content-wrapper {
    padding: 20px;
    /* max-width: 800px; */
    margin: 0 auto;
}

/* Breadcrumb */
.breadcrumb {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    color: #666;
    font-size: 14px;
}

.breadcrumb-link {
    display: flex;
    align-items: center;
    gap: 5px;
    color: var(--primary-color);
    text-decoration: none;
}

.breadcrumb-separator {
    color: #ccc;
}

.page-header {
    margin-bottom: 30px;
}

.page-header h1 {
    font-size: 24px;
    color: #1a1a1a;
    margin-bottom: 8px;
}

.text-muted {
    color: #666;
    font-size: 14px;
}

/* Card */
.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    overflow: hidden;
}

.card-body {
    padding: 30px;
}

/* Form */
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    font-weight: 500;
    color: #333;
}

.form-group small {
    color: #666;
    font-size: 12px;
}

.password-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.password-input-wrapper input,
.form-group input,
.form-group select {
    padding: 12px;
    border: 2px solid #e1e1e1;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.password-input-wrapper input:focus,
.form-group input:focus,
.form-group select:focus {
    border-color: var(--primary-color);
    outline: none;
    box-shadow: 0 0 0 3px rgba(0, 180, 216, 0.1);
}

.toggle-password {
    position: absolute;
    right: 12px;
    background: none;
    border: none;
    color: #666;
    cursor: pointer;
}

/* Password Strength */
#passwordStrength,
#passwordMatch {
    display: flex;
    gap: 5px;
    font-size: 12px;
}

.strength-bar {
    height: 4px;
    flex: 1;
    background: #e1e1e1;
    border-radius: 2px;
    transition: background-color 0.3s ease;
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 15px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.btn-primary,
.btn-secondary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
    cursor: pointer;
}

.btn-primary {
    background: var(--primary-color);
    color: white;
    border: none;
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
}

.btn-secondary {
    background: white;
    color: #666;
    border: 2px solid #e1e1e1;
    text-decoration: none;
}

.btn-secondary:hover {
    border-color: #ccc;
    color: #333;
}

/* Alert */
.alert {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 30px;
}

.alert-error {
    background: #fee2e2;
    border: 1px solid #ef4444;
    color: #dc2626;
}

.alert-content h4 {
    margin-bottom: 5px;
    font-weight: 600;
}

/* Responsive */
@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn-primary,
    .btn-secondary {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
// Definição dos níveis de força da senha
const strengthLevels = {
    0: { color: '#ef4444', text: 'Muito fraca' },
    1: { color: '#f97316', text: 'Fraca' },
    2: { color: '#eab308', text: 'Média' },
    3: { color: '#22c55e', text: 'Forte' }
};

// Verifica a força da senha
function checkPasswordStrength(password) {
    let strength = 0;
    
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
    if (password.match(/\d/)) strength++;
    if (password.match(/[^a-zA-Z\d]/)) strength++;
    
    return strength;
}

// Atualiza o indicador visual de força da senha
function updatePasswordStrength() {
    const password = document.getElementById('password').value;
    const strengthDiv = document.getElementById('passwordStrength');
    const strength = checkPasswordStrength(password);
    
    let barsHtml = '';
    for (let i = 0; i < 4; i++) {
        const color = i <= strength ? strengthLevels[strength].color : '#e1e1e1';
        barsHtml += `<div class="strength-bar" style="background-color: ${color}"></div>`;
    }
    
    strengthDiv.innerHTML = `
        ${barsHtml}
        <span style="color: ${strengthLevels[strength].color}">
            ${password.length > 0 ? strengthLevels[strength].text : 'Digite sua senha'}
        </span>
    `;
}

// Verifica se as senhas coincidem
function checkPasswordMatch() {
    const password = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;
    const matchDiv = document.getElementById('passwordMatch');
    
    if (confirm) {
        if (password === confirm) {
            matchDiv.style.color = '#22c55e';
            matchDiv.innerHTML = '✓ As senhas coincidem';
            return true;
        } else {
            matchDiv.style.color = '#ef4444';
            matchDiv.innerHTML = '✗ As senhas não coincidem';
            return false;
        }
    } else {
        matchDiv.innerHTML = '';
        return false;
    }
}

// Alterna a visibilidade da senha
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = input.nextElementSibling;
    
    if (input.type === 'password') {
        input.type = 'text';
        button.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                <line x1="1" y1="1" x2="23" y2="23"></line>
            </svg>
        `;
    } else {
        input.type = 'password';
        button.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
            </svg>
        `;
    }
}

// Validação do nome de usuário
function validateUsername(username) {
    return /^[a-zA-Z0-9_]+$/.test(username);
}

// Validação completa do formulário antes do envio
function validateForm() {
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const maxInstancias = document.getElementById('max_instancias').value;
    const firstName = document.getElementById('first_name').value;
    const lastName = document.getElementById('last_name').value;
    let isValid = true;
    let errorMessage = '';

    // Validar primeiro nome
    if (firstName.length < 2 || firstName.length > 50) {
        errorMessage = 'O primeiro nome deve ter entre 2 e 50 caracteres';
        isValid = false;
    }

    // Validar sobrenome
    if (lastName.length < 2 || lastName.length > 50) {
        errorMessage = 'O sobrenome deve ter entre 2 e 50 caracteres';
        isValid = false;
    }

    // Validar username
    if (!validateUsername(username)) {
        errorMessage = 'O nome de usuário deve conter apenas letras, números e underscore (_)';
        isValid = false;
    }

    // Validar comprimento do username
    if (username.length < 3) {
        errorMessage = 'O nome de usuário deve ter pelo menos 3 caracteres';
        isValid = false;
    }

    // Validar força da senha
    if (checkPasswordStrength(password) < 2) {
        errorMessage = 'A senha deve ser mais forte. Use pelo menos 8 caracteres, combinar letras maiúsculas e minúsculas, números e caracteres especiais.';
        isValid = false;
    }

    // Validar confirmação de senha
    if (password !== confirmPassword) {
        errorMessage = 'As senhas não coincidem';
        isValid = false;
    }

    // Validar número máximo de instâncias
    if (maxInstancias < 1 || maxInstancias > 100) {
        errorMessage = 'O número de instâncias deve estar entre 1 e 100';
        isValid = false;
    }

    if (!isValid) {
        showError(errorMessage);
        return false;
    }

    // Se tudo estiver válido, mostrar loading e prevenir duplo envio
    const form = document.getElementById('createUserForm');
    if (form.isSubmitting) {
        return false;
    }
    
    form.isSubmitting = true;
    
    const submitButton = document.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    submitButton.innerHTML = `
        <svg class="spinner" viewBox="0 0 50 50">
            <circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
        </svg>
        Criando Usuário...
    `;

    return true;
}

// Exibe mensagem de erro visualmente
function showError(message) {
    // Remove mensagem de erro anterior se existir
    const existingAlert = document.querySelector('.alert-error');
    if (existingAlert) {
        existingAlert.remove();
    }

    // Cria nova mensagem de erro
    const alert = document.createElement('div');
    alert.className = 'alert alert-error';
    alert.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <div class="alert-content">
            <h4>Erro</h4>
            <p>${message}</p>
        </div>
    `;

    // Insere a mensagem depois do header
    const pageHeader = document.querySelector('.page-header');
    pageHeader.insertAdjacentElement('afterend', alert);

    // Scroll para a mensagem
    alert.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// Adiciona os listeners quando o documento estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const usernameInput = document.getElementById('username');
    
    // Mostrar força da senha ao digitar
    passwordInput.addEventListener('input', function() {
        updatePasswordStrength();
        if (confirmPasswordInput.value) {
            checkPasswordMatch();
        }
    });
    
    // Verificar se senhas coincidem ao digitar
    confirmPasswordInput.addEventListener('input', checkPasswordMatch);
    
    // Validar caracteres de nome de usuário em tempo real
    usernameInput.addEventListener('input', function(e) {
        // Remove caracteres inválidos em tempo real
        e.target.value = e.target.value.replace(/[^a-zA-Z0-9_]/g, '');
    });
    
    // Inicializar indicadores se já houver valores
    if (passwordInput.value) {
        updatePasswordStrength();
    }
    
    if (confirmPasswordInput.value) {
        checkPasswordMatch();
    }
});

// Adiciona o estilo do spinner
const style = document.createElement('style');
style.textContent = `
    .spinner {
        animation: rotate 2s linear infinite;
        width: 20px;
        height: 20px;
        margin-right: 10px;
    }

    .spinner .path {
        stroke: #ffffff;
        stroke-linecap: round;
        animation: dash 1.5s ease-in-out infinite;
    }

    @keyframes rotate {
        100% {
            transform: rotate(360deg);
        }
    }

    @keyframes dash {
        0% {
            stroke-dasharray: 1, 150;
            stroke-dashoffset: 0;
        }
        50% {
            stroke-dasharray: 90, 150;
            stroke-dashoffset: -35;
        }
        100% {
            stroke-dasharray: 90, 150;
            stroke-dashoffset: -124;
        }
    }
`;

document.head.appendChild(style);
</script>

<?php include '../footer.php'; ?>