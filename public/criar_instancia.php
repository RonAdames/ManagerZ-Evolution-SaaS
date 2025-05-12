<?php
require_once 'config.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Security.php';
require_once __DIR__ . '/../src/Api.php';

// Iniciar sessão segura
Session::start();

// Verificar autenticação
if (!Session::isAuthenticated()) {
    header("Location: index.php");
    exit;
}

$title = "Criar Instância";
$user_id = Session::get('user_id');
$error_message = '';
$success_message = '';
$success_redirect = '';

// Busca informações do usuário
try {
    $stmt = $pdo->prepare("SELECT max_instancias FROM users WHERE id = :user_id AND active = 1");
    $stmt->execute(['user_id' => $user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Usuário não encontrado ou inativo
        Session::destroy();
        header("Location: index.php?error=" . urlencode("Conta de usuário inválida ou inativa"));
        exit;
    }
    
    $max_instancias = $user['max_instancias'];

    // Verifica o número de instâncias
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM instancias WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $count = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    $logger->error("Erro ao buscar informações do usuário: " . $e->getMessage());
    $error_message = "Erro ao carregar informações. Tente novamente mais tarde.";
    $count = 0;
    $max_instancias = 0;
}

// Processa o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica CSRF token
    if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
        $error_message = "Erro de segurança. Por favor, tente novamente.";
    } else {
        // Sanitiza e valida os dados
        $instanceName = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['instanceName'] ?? '');
        $qrcode = isset($_POST['qrcode']) && $_POST['qrcode'] === 'on' ? true : false;
        $integration = Security::sanitizeInput($_POST['integration'] ?? 'WHATSAPP-BAILEYS');
        $number = isset($_POST['number']) ? trim(Security::sanitizeInput($_POST['number'])) : '';
        
        // Configurações opcionais - convertendo para booleanos reais (true/false)
        $rejectCall = (isset($_POST['rejectCall']) && $_POST['rejectCall'] === 'on') ? true : false;
        $msgCall = $rejectCall ? Security::sanitizeInput($_POST['msgCall'] ?? '') : '';
        $groupsIgnore = (isset($_POST['groupsIgnore']) && $_POST['groupsIgnore'] === 'on') ? true : false;
        $alwaysOnline = (isset($_POST['alwaysOnline']) && $_POST['alwaysOnline'] === 'on') ? true : false;
        $readMessages = (isset($_POST['readMessages']) && $_POST['readMessages'] === 'on') ? true : false;
        $readStatus = (isset($_POST['readStatus']) && $_POST['readStatus'] === 'on') ? true : false;
        $syncFullHistory = (isset($_POST['syncFullHistory']) && $_POST['syncFullHistory'] === 'on') ? true : false;
        
        // Validação
        if (empty($instanceName)) {
            $error_message = "O nome da instância é obrigatório.";
        } elseif ($count >= $max_instancias) {
            $error_message = "Você atingiu o limite máximo de instâncias permitido.";
        } elseif (strlen($instanceName) < 3 || strlen($instanceName) > 50) {
            $error_message = "O nome da instância deve ter entre 3 e 50 caracteres.";
        } elseif (!preg_match('/^[a-zA-Z0-9]+$/', $instanceName)) {
            $error_message = "O nome da instância deve conter apenas letras e números.";
        } elseif (!empty($number) && !preg_match('/^\d+[\.@\w-]*$/', $number)) {
            $error_message = "O formato do número está inválido. Use apenas números e caracteres permitidos.";
        } else {
            try {
                // Verifica se a instância já existe
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM instancias WHERE instance_name = :instance_name");
                $stmt->execute(['instance_name' => $instanceName]);
                
                if ($stmt->fetchColumn() > 0) {
                    $error_message = "Uma instância com este nome já existe.";
                } else {
                    // Inicializa o cliente da API
                    $api = new Api();
                    
                    // Prepara os parâmetros para a API
                    $apiParams = [
                        'instanceName' => $instanceName,
                        'qrcode' => $qrcode,
                        'integration' => $integration,
                        'rejectCall' => $rejectCall,
                        'groupsIgnore' => $groupsIgnore,
                        'alwaysOnline' => $alwaysOnline,
                        'readMessages' => $readMessages,
                        'readStatus' => $readStatus,
                        'syncFullHistory' => $syncFullHistory
                    ];
                    
                    // Adiciona mensagem de rejeição apenas se rejectCall for true
                    if ($rejectCall && !empty($msgCall)) {
                        $apiParams['msgCall'] = $msgCall;
                    }
                    
                    // Adiciona número apenas se não estiver vazio
                    if (!empty($number)) {
                        $apiParams['number'] = $number;
                    }
                    
                    // Faz a requisição à API
                    try {
                        $response = $api->post('instance/create', $apiParams);
                        
                        // Verifica se a API retornou a instância
                        if (isset($response['instance']['instanceId'])) {
                            $instanceId = $response['instance']['instanceId'];
                            $status = $response['instance']['status'];
                            
                            // Extrair o token (hash) da resposta
                            $token = isset($response['hash']) ? $response['hash'] : '';
                            
                            // Insere a nova instância no banco de dados incluindo o token
                            $stmt = $pdo->prepare("
                                INSERT INTO instancias (
                                    user_id, 
                                    instance_name, 
                                    instance_id, 
                                    integration, 
                                    status,
                                    reject_call,
                                    msg_call,
                                    groups_ignore,
                                    always_online,
                                    read_messages,
                                    read_status,
                                    sync_full_history,
                                    token
                                ) VALUES (
                                    :user_id, 
                                    :instance_name, 
                                    :instance_id, 
                                    :integration, 
                                    :status,
                                    :reject_call,
                                    :msg_call,
                                    :groups_ignore,
                                    :always_online,
                                    :read_messages,
                                    :read_status,
                                    :sync_full_history,
                                    :token
                                )
                            ");
                            
                            $stmt->execute([
                                'user_id' => $user_id,
                                'instance_name' => $instanceName,
                                'instance_id' => $instanceId,
                                'integration' => $integration,
                                'status' => $status,
                                'reject_call' => $rejectCall ? 1 : 0,
                                'msg_call' => $msgCall,
                                'groups_ignore' => $groupsIgnore ? 1 : 0,
                                'always_online' => $alwaysOnline ? 1 : 0,
                                'read_messages' => $readMessages ? 1 : 0,
                                'read_status' => $readStatus ? 1 : 0,
                                'sync_full_history' => $syncFullHistory ? 1 : 0,
                                'token' => $token
                            ]);
                            
                            $success_redirect = "/admin/admin_instancia.php?instance_id=" . urlencode($instanceId);
                            header("Location: " . $success_redirect);
                            exit;
                        } else {
                            // Trata o erro caso a instância já exista ou outro erro ocorra
                            if (isset($response['error']) && $response['error'] === true) {
                                $error_message = "Erro: " . ($response['message'] ?? 'Erro desconhecido');
                            } else {
                                $error_message = "Erro ao criar a instância. Verifique o nome ou tente novamente.";
                            }
                        }
                    } catch (Exception $e) {
                        $error_message = "Erro na comunicação com a API: " . $e->getMessage();
                        $logger->error("Erro na API ao criar instância: " . $e->getMessage());
                    }
                }
            } catch (PDOException $e) {
                $logger->error("Erro ao verificar/criar instância no banco: " . $e->getMessage());
                $error_message = "Erro ao processar sua solicitação. Tente novamente mais tarde.";
            }
        }
    }
}

// Gera token CSRF para o formulário
$csrf_token = Security::generateCsrfToken();

include './base.php';
?>

<div class="content-wrapper">
    <div class="page-header">
        <div class="header-content">
            <h1>Criar Nova Instância</h1>
            <p class="text-muted">Configure sua nova instância do WhatsApp</p>
        </div>
        <div class="instance-counter">
            <div class="counter-box">
                <div class="counter-info">
                    <span class="counter-label">Instâncias Utilizadas</span>
                    <div class="counter-numbers">
                        <span class="current"><?php echo $count; ?></span>
                        <span class="separator">/</span>
                        <span class="maximum"><?php echo $max_instancias; ?></span>
                    </div>
                </div>
                <div class="progress-bar">
                    <div class="progress" style="width: <?php echo ($count / ($max_instancias ?: 1)) * 100; ?>%"></div>
                </div>
            </div>
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

    <div class="create-instance-form">
        <form action="criar_instancia.php" method="POST" id="createInstanceForm" onsubmit="return validateForm()">
            <!-- Token CSRF para proteger o formulário -->
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="form-section">
                <h3>Informações Básicas</h3>
                <div class="form-group">
                    <label for="instanceName">Nome da Instância</label>
                    <input type="text" 
                           id="instanceName" 
                           name="instanceName" 
                           required 
                           pattern="[a-zA-Z0-9]+"
                           placeholder="Ex: 5511989929199"
                           maxlength="50"
                           oninput="this.value = this.value.replace(/[^a-zA-Z0-9]/g, '')">
                    <small>Sempre crie instância com o número do celular Ex: 5511989929199</small>
                    <div id="nameError" class="error-text"></div>
                </div>

                <div class="form-group">
                    <label for="number">Número (Opcional)</label>
                    <input type="text" 
                           id="number" 
                           name="number" 
                           placeholder="Ex: 5511989929199"
                           pattern="^\d+[\.@\w-]*$"
                           maxlength="20">
                    <small>Número do WhatsApp com código do país (opcional), começando com dígitos</small>
                </div>

                <div class="form-group">
                    <label for="integration">Tipo de Integração</label>
                    <div class="select-wrapper">
                        <select id="integration" name="integration" required>
                            <option value="WHATSAPP-BAILEYS">WHATSAPP</option>
                        </select>
                    </div>
                    <small>Selecione o tipo de conexão para esta instância</small>
                </div>

                <div class="form-group checkbox-group">
                    <label class="checkbox-wrapper">
                        <input type="checkbox" id="qrcode" name="qrcode" checked>
                        <span class="checkbox-custom"></span>
                        <span class="checkbox-label">Gerar QR Code imediatamente</span>
                    </label>
                    <small>O QR Code será gerado assim que a instância for criada</small>
                </div>
            </div>

            <div class="form-section">
                <h3>Configurações Avançadas</h3>
                <div class="settings-grid">
                    <div class="form-group checkbox-group">
                        <label class="checkbox-wrapper">
                            <input type="checkbox" id="rejectCall" name="rejectCall" onchange="toggleMsgCallField()">
                            <span class="checkbox-custom"></span>
                            <span class="checkbox-label">Rejeitar Chamadas</span>
                        </label>
                        <small>Rejeita todas as chamadas recebidas</small>
                    </div>

                    <div class="form-group" id="msgCallContainer" style="display:none;">
                        <label for="msgCall">Mensagem de Rejeição</label>
                        <input type="text" 
                               id="msgCall" 
                               name="msgCall" 
                               placeholder="Ex: Não estou disponível para chamadas"
                               maxlength="100">
                        <small>Mensagem enviada ao rejeitar chamadas</small>
                    </div>

                    <div class="form-group checkbox-group">
                        <label class="checkbox-wrapper">
                            <input type="checkbox" id="groupsIgnore" name="groupsIgnore">
                            <span class="checkbox-custom"></span>
                            <span class="checkbox-label">Ignorar Grupos</span>
                        </label>
                        <small>Ignora mensagens de grupos</small>
                    </div>

                    <div class="form-group checkbox-group">
                        <label class="checkbox-wrapper">
                            <input type="checkbox" id="alwaysOnline" name="alwaysOnline">
                            <span class="checkbox-custom"></span>
                            <span class="checkbox-label">Sempre Online</span>
                        </label>
                        <small>Mantém o status do WhatsApp sempre online</small>
                    </div>

                    <div class="form-group checkbox-group">
                        <label class="checkbox-wrapper">
                            <input type="checkbox" id="readMessages" name="readMessages">
                            <span class="checkbox-custom"></span>
                            <span class="checkbox-label">Ler Mensagens</span>
                        </label>
                        <small>Marca mensagens como lidas automaticamente</small>
                    </div>

                    <div class="form-group checkbox-group">
                        <label class="checkbox-wrapper">
                            <input type="checkbox" id="readStatus" name="readStatus">
                            <span class="checkbox-custom"></span>
                            <span class="checkbox-label">Ler Status</span>
                        </label>
                        <small>Marca status/stories como vistos automaticamente</small>
                    </div>

                    <div class="form-group checkbox-group">
                        <label class="checkbox-wrapper">
                            <input type="checkbox" id="syncFullHistory" name="syncFullHistory">
                            <span class="checkbox-custom"></span>
                            <span class="checkbox-label">Sincronizar Histórico Completo</span>
                        </label>
                        <small>Sincroniza todo o histórico de mensagens</small>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="16"></line>
                        <line x1="8" y1="12" x2="16" y2="12"></line>
                    </svg>
                    Criar Instância
                </button>
                <a href="dashboard.php" class="btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<link rel="stylesheet" href="/assets/css/criar_instancia.css">
<script>
// Função para exibir/esconder o campo de mensagem de rejeição de chamada
function toggleMsgCallField() {
    const rejectCallCheckbox = document.getElementById('rejectCall');
    const msgCallContainer = document.getElementById('msgCallContainer');
    
    if (rejectCallCheckbox.checked) {
        msgCallContainer.style.display = 'block';
    } else {
        msgCallContainer.style.display = 'none';
    }
}

// Função para validar o nome da instância em tempo real
document.getElementById('instanceName').addEventListener('input', function(e) {
    const input = e.target;
    const nameError = document.getElementById('nameError');
    const validPattern = /^[a-zA-Z0-9]+$/;
    
    // Remove caracteres inválidos imediatamente
    input.value = input.value.replace(/[^a-zA-Z0-9]/g, '');
    
    // Valida o valor atual
    if (input.value.length === 0) {
        nameError.textContent = 'O nome da instância é obrigatório';
        input.classList.add('error');
    } else if (input.value.length < 3) {
        nameError.textContent = 'O nome deve ter pelo menos 3 caracteres';
        input.classList.add('error');
    } else if (!validPattern.test(input.value)) {
        nameError.textContent = 'Use apenas letras e números, sem espaços ou caracteres especiais';
        input.classList.add('error');
    } else {
        nameError.textContent = '';
        input.classList.remove('error');
    }
});

// Adicionar validação para o campo número
document.getElementById('number').addEventListener('input', function(e) {
    const input = e.target;
    const numberValue = input.value.trim();
    
    // Se estiver vazio, não valida
    if (numberValue === '') {
        input.classList.remove('error');
        return;
    }
    
    // Valida o formato do número
    const validPattern = /^\d+[\.@\w-]*$/;
    if (!validPattern.test(numberValue)) {
        input.classList.add('error');
        // Opcional: adicionar mensagem de erro
        if (!input.nextElementSibling.classList.contains('number-error')) {
            const errorMsg = document.createElement('div');
            errorMsg.className = 'error-text number-error';
            errorMsg.textContent = 'Formato inválido. Use apenas números, letras, ponto, arroba ou hífen.';
            input.parentNode.insertBefore(errorMsg, input.nextElementSibling);
        }
    } else {
        input.classList.remove('error');
        // Remover mensagem de erro se existir
        const errorMsg = input.parentNode.querySelector('.number-error');
        if (errorMsg) {
            errorMsg.remove();
        }
    }
});

// Função para validar o formulário antes do envio
function validateForm() {
    const instanceName = document.getElementById('instanceName');
    const nameError = document.getElementById('nameError');
    const number = document.getElementById('number');
    const validNamePattern = /^[a-zA-Z0-9]+$/;
    const validNumberPattern = /^\d+[\.@\w-]*$/;
    let isValid = true;
    
    // Valida o nome da instância
    if (instanceName.value.length === 0) {
        nameError.textContent = 'O nome da instância é obrigatório';
        instanceName.classList.add('error');
        isValid = false;
    } else if (instanceName.value.length < 3) {
        nameError.textContent = 'O nome deve ter pelo menos 3 caracteres';
        instanceName.classList.add('error');
        isValid = false;
    } else if (!validNamePattern.test(instanceName.value)) {
        nameError.textContent = 'Use apenas letras e números, sem espaços ou caracteres especiais';
        instanceName.classList.add('error');
        isValid = false;
    }
    
    // Valida o número se estiver preenchido
    if (number.value.trim() !== '') {
        if (!validNumberPattern.test(number.value.trim())) {
            // Adicionar mensagem de erro para o número
            let numberError = number.parentNode.querySelector('.number-error');
            if (!numberError) {
                numberError = document.createElement('div');
                numberError.className = 'error-text number-error';
                number.parentNode.insertBefore(numberError, number.nextElementSibling);
            }
            numberError.textContent = 'Formato inválido. Use apenas números, letras, ponto, arroba ou hífen.';
            number.classList.add('error');
            isValid = false;
        }
    }
    
    // Se o formulário for válido, mostra o loading
    if (isValid) {
        const submitButton = document.querySelector('button[type="submit"]');
        const originalContent = submitButton.innerHTML;
        
        submitButton.disabled = true;
        submitButton.innerHTML = `
            <svg class="spinner" viewBox="0 0 50 50">
                <circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
            </svg>
            Criando...
        `;
        
        // Adiciona classes para animação
        submitButton.classList.add('loading');
    }
    
    return isValid;
}

// Adiciona verificação anti-duplo clique no formulário
document.getElementById('createInstanceForm').addEventListener('submit', function(e) {
    // Previne envio duplo
    if (this.submitting) {
        e.preventDefault();
        return;
    }
    
    if (validateForm()) {
        this.submitting = true;
    }
});

// Inicializa o estado do campo de mensagem de rejeição de chamada
document.addEventListener('DOMContentLoaded', function() {
    toggleMsgCallField();
});
</script>

<?php include './footer.php'; ?>