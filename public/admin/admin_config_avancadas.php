<?php
$title = "Configurações Avançadas";
require_once '../config.php';
require_once __DIR__ . '/../../src/Session.php';
require_once __DIR__ . '/../../src/Security.php';
require_once __DIR__ . '/../../src/Api.php';

// Iniciar sessão de forma segura
Session::start();

// Verificar autenticação
if (!Session::isAuthenticated()) {
    header("Location: ../index.php");
    exit;
}

$user_id = Session::get('user_id');
$instance_id = isset($_GET['instance_id']) ? Security::sanitizeInput($_GET['instance_id']) : '';
$success_message = '';
$error_message = '';

if (empty($instance_id)) {
    header("Location: ../dashboard.php?error=" . urlencode("ID de instância inválido"));
    exit;
}

// Verifica se a instância pertence ao usuário
try {
    $stmt = $pdo->prepare("SELECT * FROM instancias WHERE instance_id = :instance_id AND user_id = :user_id");
    $stmt->execute(['instance_id' => $instance_id, 'user_id' => $user_id]);
    $instance = $stmt->fetch();

    if (!$instance) {
        Session::setFlash('error', 'Instância não encontrada ou você não tem permissão para acessá-la.');
        header("Location: ../dashboard.php");
        exit;
    }
    
    $logger->info("Carregando configurações avançadas para a instância ID: {$instance_id}, Nome: {$instance['instance_name']}");
} catch (PDOException $e) {
    $logger->error("Erro ao buscar instância: " . $e->getMessage());
    Session::setFlash('error', 'Erro ao buscar informações da instância. Tente novamente mais tarde.');
    header("Location: ../dashboard.php");
    exit;
}

$instance_name = $instance['instance_name'];
$status = $instance['status'];

// Inicializa a API
$api = new Api();

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logger->info("Recebendo requisição POST para instância {$instance_name}");
    $logger->info("POST data: " . json_encode($_POST));
    
    // Verifica se é uma atualização de configurações
    if (isset($_POST['update_settings']) || isset($_POST['debug_marker'])) { // Detectar submit pelo marker também
        $logger->info("Processando atualização de configurações para {$instance_name}");
        
        // Validar CSRF token
        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            $error_message = "Erro de segurança. Token inválido.";
            $logger->error("Token CSRF inválido na atualização de configurações: {$instance_name}");
        } else {
            // IMPORTANTE: Checkboxes não marcados não são enviados no POST
            // Capturar os valores do formulário com valor padrão false para checkboxes
            $rejectCall = isset($_POST['rejectCall']);
            $msgCall = isset($_POST['msgCall']) ? Security::sanitizeInput($_POST['msgCall']) : '';
            $groupsIgnore = isset($_POST['groupsIgnore']);
            $alwaysOnline = isset($_POST['alwaysOnline']);
            $readMessages = isset($_POST['readMessages']);
            $readStatus = isset($_POST['readStatus']);
            $syncFullHistory = isset($_POST['syncFullHistory']);
            
            // Log dos valores extraídos do POST para diagnóstico
            $logger->info("Valores extraídos do formulário para {$instance_name}: " . json_encode([
                'rejectCall' => $rejectCall,
                'msgCall' => $msgCall,
                'groupsIgnore' => $groupsIgnore,
                'alwaysOnline' => $alwaysOnline,
                'readMessages' => $readMessages,
                'readStatus' => $readStatus,
                'syncFullHistory' => $syncFullHistory
            ]));

            try {
                // Preparar dados no formato exato que a API espera
                $requestData = [
                    'rejectCall' => $rejectCall,
                    'msgCall' => $msgCall,
                    'groupsIgnore' => $groupsIgnore,
                    'alwaysOnline' => $alwaysOnline,
                    'readMessages' => $readMessages,
                    'readStatus' => $readStatus,
                    'syncFullHistory' => $syncFullHistory
                ];
                
                $jsonData = json_encode($requestData);
                
                // Log dos dados sendo enviados
                $logger->info("Enviando configurações para a API [{$instance_name}]: {$jsonData}");
                
                // Usar curl diretamente para garantir formato correto
                $curl = curl_init();
                curl_setopt_array($curl, [
                    CURLOPT_URL => rtrim($api->getBaseUrl(), '/') . '/settings/set/' . $instance_name,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => $jsonData,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'apikey: ' . $api->getApiKey()
                    ],
                ]);
                
                // Execute a requisição com tratamento de erro adicional
                $response = curl_exec($curl);
                $err = curl_error($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                
                // Log detalhado da resposta
                $logger->info("Resposta da API para {$instance_name} - HTTP Code: {$httpCode}");
                $logger->info("Resposta completa: " . ($response ?: "Vazia"));
                
                curl_close($curl);
                
                if ($err) {
                    $logger->error("Erro curl para {$instance_name}: {$err}");
                    $error_message = "Erro ao comunicar com a API: {$err}";
                } else {
                    $responseData = json_decode($response, true);
                    
                    if ($httpCode >= 200 && $httpCode < 300) {
                        $success_message = "Configurações atualizadas com sucesso!";
                        $logger->info("Configurações atualizadas com sucesso para {$instance_name}");
                        
                        // Atualizar as configurações no banco de dados local
                        try {
                            $stmt = $pdo->prepare("
                                UPDATE instancias SET 
                                    reject_call = :reject_call,
                                    msg_call = :msg_call,
                                    groups_ignore = :groups_ignore,
                                    always_online = :always_online,
                                    read_messages = :read_messages,
                                    read_status = :read_status,
                                    sync_full_history = :sync_full_history
                                WHERE instance_id = :instance_id
                            ");
                            
                            $stmt->execute([
                                'reject_call' => $rejectCall ? 1 : 0,
                                'msg_call' => $msgCall,
                                'groups_ignore' => $groupsIgnore ? 1 : 0,
                                'always_online' => $alwaysOnline ? 1 : 0,
                                'read_messages' => $readMessages ? 1 : 0,
                                'read_status' => $readStatus ? 1 : 0,
                                'sync_full_history' => $syncFullHistory ? 1 : 0,
                                'instance_id' => $instance_id
                            ]);
                            
                            $logger->info("Configurações também atualizadas no banco de dados local");
                        } catch (PDOException $e) {
                            $logger->error("Erro ao atualizar configurações no banco local: " . $e->getMessage());
                        }
                    } else {
                        $error_message = "Erro ao atualizar configurações. Código: {$httpCode}";
                        if (isset($responseData['message'])) {
                            $error_message .= " - " . $responseData['message'];
                        }
                        $logger->error("Erro ao atualizar configurações: {$error_message}");
                    }
                }
            } catch (Exception $e) {
                $logger->error("Exceção ao atualizar configurações: " . $e->getMessage());
                $error_message = "Erro ao processar solicitação: " . $e->getMessage();
            }
        }
    } else {
        $logger->warning("Requisição POST recebida mas sem parâmetro update_settings ou debug_marker");
    }
}

// Buscar configurações diretamente da API
$apiSettings = null;
$settingsFromApi = false;

try {
    // Buscar configurações da API
    $logger->info("Buscando configurações atuais da API para {$instance_name}");
    $apiResponse = $api->get("settings/find/{$instance_name}");
    
    if (isset($apiResponse) && !isset($apiResponse['error'])) {
        $apiSettings = $apiResponse;
        $settingsFromApi = true;
        $logger->info("Configurações obtidas com sucesso da API");
    } else {
        $logger->warning("Falha ao obter configurações da API: " . json_encode($apiResponse));
    }
} catch (Exception $e) {
    $logger->error("Erro ao obter configurações da API: " . $e->getMessage());
}

// Se não conseguiu obter da API, usa as configurações do banco
if (!$settingsFromApi && $status === 'connected') {
    $logger->info("Usando configurações do banco de dados local");
    $apiSettings = [
        'rejectCall' => (bool)$instance['reject_call'],
        'msgCall' => $instance['msg_call'],
        'groupsIgnore' => (bool)$instance['groups_ignore'],
        'alwaysOnline' => (bool)$instance['always_online'],
        'readMessages' => (bool)$instance['read_messages'],
        'readStatus' => (bool)$instance['read_status'],
        'syncFullHistory' => (bool)$instance['sync_full_history']
    ];
    $settingsFromApi = true;
}

// Gerar token CSRF para formulários
$csrf_token = Security::generateCsrfToken();

include '../base.php';
?>

<link rel="stylesheet" href="../admin/css/admin_config_avancadas.css">
<link rel="stylesheet" href="../admin/css/admin_instancia.css">

<div class="content-wrapper">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="../dashboard.php" class="breadcrumb-link">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            Dashboard
        </a>
        <span class="breadcrumb-separator">/</span>
        <a href="admin_instancia.php?instance_id=<?php echo urlencode($instance_id); ?>" class="breadcrumb-link">
            Instância
        </a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Configurações Avançadas</span>
    </div>

    <div class="page-header">
        <div class="header-content">
            <h1>Configurações Avançadas</h1>
            <p class="text-muted">Instância: <?php echo htmlspecialchars($instance_name); ?></p>
        </div>
        <div class="instance-status <?php echo htmlspecialchars($status); ?>">
            <span class="status-indicator"></span>
            <span class="status-text"><?php echo ucfirst(htmlspecialchars($status)); ?></span>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
            <div class="alert-content">
                <h4>Sucesso!</h4>
                <p><?php echo htmlspecialchars($success_message); ?></p>
            </div>
        </div>
    <?php endif; ?>

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

    <!-- Configurações Avançadas -->
    <div class="settings-card">
        <div class="card-header">
            <h2>Comportamento</h2>
            <?php if (!$settingsFromApi): ?>
            <div class="settings-info">
                <span class="badge warning">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    As configurações só podem ser carregadas quando a instância está conectada
                </span>
            </div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($settingsFromApi): ?>
                <form action="admin_config_avancadas.php?instance_id=<?php echo urlencode($instance_id); ?>" method="POST" id="settingsForm">
                    <!-- Token CSRF para proteger o formulário -->
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <!-- Campo de diagnóstico -->
                    <input type="hidden" name="debug_marker" value="form-config-avancadas-<?php echo time(); ?>">
                    <!-- Campos hidden para garantir que valores sejam enviados mesmo quando os checkboxes não forem marcados -->
                    <input type="hidden" name="rejectCall_default" value="0">
                    <input type="hidden" name="groupsIgnore_default" value="0">
                    <input type="hidden" name="alwaysOnline_default" value="0">
                    <input type="hidden" name="readMessages_default" value="0">
                    <input type="hidden" name="readStatus_default" value="0">
                    <input type="hidden" name="syncFullHistory_default" value="0">

                    <div class="settings-group">
                        <div class="setting-item">
                            <div class="setting-info">
                                <h3>Rejeitar Chamadas</h3>
                                <p>Rejeitar todas as chamadas recebidas</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="rejectCall" name="rejectCall" value="1" <?php echo isset($apiSettings['rejectCall']) && $apiSettings['rejectCall'] ? 'checked' : ''; ?> onchange="toggleMsgCallField()">
                                <span class="slider round"></span>
                            </label>
                        </div>

                    <div class="setting-item msg-call-container" id="msgCallContainer" style="display:<?php echo isset($apiSettings['rejectCall']) && $apiSettings['rejectCall'] ? 'flex' : 'none'; ?>">
                        <div class="setting-info">
                            <h3>Mensagem de Rejeição</h3>
                            <p>Mensagem enviada ao rejeitar chamadas</p>
                        </div>
                        <div class="setting-control">
                            <input type="text"
                                id="msgCall"
                                name="msgCall"
                                value="<?php echo isset($apiSettings['msgCall']) ? htmlspecialchars($apiSettings['msgCall']) : ''; ?>"
                                placeholder="Ex: Não estou disponível para chamadas"
                                maxlength="100">
                        </div>
                    </div>

                    <div class="setting-item">
                        <div class="setting-info">
                            <h3>Ignorar Grupos</h3>
                            <p>Ignora mensagens de grupos</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" id="groupsIgnore" name="groupsIgnore" <?php echo isset($apiSettings['groupsIgnore']) && $apiSettings['groupsIgnore'] ? 'checked' : ''; ?>>
                            <span class="slider round"></span>
                        </label>
                    </div>

                    <div class="setting-item">
                        <div class="setting-info">
                            <h3>Sempre Online</h3>
                            <p>Mantém o status do WhatsApp sempre online</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" id="alwaysOnline" name="alwaysOnline" <?php echo isset($apiSettings['alwaysOnline']) && $apiSettings['alwaysOnline'] ? 'checked' : ''; ?>>
                            <span class="slider round"></span>
                        </label>
                    </div>

                    <div class="setting-item">
                        <div class="setting-info">
                            <h3>Ler Mensagens</h3>
                            <p>Marca mensagens como lidas automaticamente</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" id="readMessages" name="readMessages" <?php echo isset($apiSettings['readMessages']) && $apiSettings['readMessages'] ? 'checked' : ''; ?>>
                            <span class="slider round"></span>
                        </label>
                    </div>

                    <div class="setting-item">
                        <div class="setting-info">
                            <h3>Ler Status</h3>
                            <p>Marca status/stories como vistos automaticamente</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" id="readStatus" name="readStatus" <?php echo isset($apiSettings['readStatus']) && $apiSettings['readStatus'] ? 'checked' : ''; ?>>
                            <span class="slider round"></span>
                        </label>
                    </div>

                    <div class="setting-item">
                        <div class="setting-info">
                            <h3>Sincronizar Histórico Completo</h3>
                            <p>Sincroniza todo o histórico de mensagens</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" id="syncFullHistory" name="syncFullHistory" <?php echo isset($apiSettings['syncFullHistory']) && $apiSettings['syncFullHistory'] ? 'checked' : ''; ?>>
                            <span class="slider round"></span>
                        </label>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="update_settings" value="1" class="btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                            <polyline points="7 3 7 8 15 8"></polyline>
                        </svg>
                        Salvar Configurações
                    </button>
                    <a href="admin_instancia.php?instance_id=<?php echo urlencode($instance_id); ?>" class="btn-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="19" y1="12" x2="5" y2="12"></line>
                            <polyline points="12 19 5 12 12 5"></polyline>
                        </svg>
                        Voltar
                    </a>
                </div>
            </form>
            <?php else: ?>
            <div class="settings-placeholder">
                <div class="placeholder-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                </div>
                <h3>Configurações não disponíveis</h3>
                <p>As configurações avançadas só podem ser gerenciadas quando a instância está conectada ao WhatsApp.</p>
                <p>Por favor, conecte sua instância para acessar estas configurações.</p>
                <a href="admin_instancia.php?instance_id=<?php echo urlencode($instance_id); ?>" class="btn-secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                    Voltar para Instância
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Função para exibir/esconder o campo de mensagem de rejeição de chamada
function toggleMsgCallField() {
    const rejectCallCheckbox = document.getElementById('rejectCall');
    const msgCallContainer = document.getElementById('msgCallContainer');
    
    if (rejectCallCheckbox && msgCallContainer) {
        if (rejectCallCheckbox.checked) {
            msgCallContainer.style.display = 'flex';
        } else {
            msgCallContainer.style.display = 'none';
        }
    }
}

// Inicializar a página
document.addEventListener('DOMContentLoaded', function() {
    console.log('Página de configurações avançadas carregada');
    
    // Inicializar o campo de mensagem de rejeição
    toggleMsgCallField();
    
    // Adicionando prevenção de duplo envio no formulário
    const form = document.getElementById('settingsForm');
    if (form) {
        console.log('Formulário de configurações encontrado');
        
        form.addEventListener('submit', function(e) {
            console.log('Formulário submetido');
            
            // Se quiser ver o conteúdo do formulário
            const formData = new FormData(this);
            const formValues = {};
            for (let [key, value] of formData.entries()) {
                formValues[key] = value;
            }
            console.log('Valores do formulário:', formValues);
            
            if (this.submitting) {
                console.log('Prevenindo duplo envio');
                e.preventDefault();
                return;
            }

            this.submitting = true;
            const submitButton = this.querySelector('button[type="submit"]');
            if (submitButton) {
                const originalHTML = submitButton.innerHTML;
                submitButton.disabled = true;
                submitButton.innerHTML = `
                    <svg class="spinner" viewBox="0 0 50 50">
                        <circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
                    </svg>
                    Salvando...
                `;
                
                // Restaurar botão após 10 segundos se não houver resposta (fallback)
                setTimeout(function() {
                    if (submitButton.disabled) {
                        console.log('Tempo limite de envio atingido, restaurando botão');
                        submitButton.disabled = false;
                        submitButton.innerHTML = originalHTML;
                    }
                }, 10000);
            }
        });
    } else {
        console.log('Formulário de configurações não encontrado');
    }
});

// Estilo para o spinner
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