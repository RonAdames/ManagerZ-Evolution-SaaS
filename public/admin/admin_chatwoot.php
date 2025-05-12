<?php
$title = "Configurações do Chatwoot";
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
$warning_message = '';

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
    
    $logger->info("Carregando configurações do Chatwoot para a instância ID: {$instance_id}, Nome: {$instance['instance_name']}");
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

// Flag para controlar se o Chatwoot está ativado e configurado
$chatwoot_enabled = false;
$chatwoot_api_enabled = false;
$chatwoot_settings = null;

// Verificar se o Chatwoot está ativado na API
try {
    $logger->info("Verificando status do Chatwoot para {$instance_name}");
    $response = $api->get("chatwoot/find/{$instance_name}");
    $logger->info("Resposta da verificação do Chatwoot: " . json_encode($response));
    
    if (isset($response['status']) && $response['status'] === 400) {
        // Chatwoot está desativado na API
        $chatwoot_api_enabled = false;
        $warning_message = "O Chatwoot não está ativado na API. Por favor, solicite ao administrador para ativar o Chatwoot na API.";
        $logger->warning("Chatwoot desativado na API para {$instance_name}: " . json_encode($response));
    } else {
        // Chatwoot está ativado na API
        $chatwoot_api_enabled = true;
        $chatwoot_settings = $response;
        $chatwoot_enabled = isset($chatwoot_settings['enabled']) ? $chatwoot_settings['enabled'] : false;
        $logger->info("Chatwoot ativado na API para {$instance_name}: " . json_encode($chatwoot_settings));
    }
} catch (Exception $e) {
    $logger->error("Erro ao verificar status do Chatwoot: " . $e->getMessage());
    $error_message = "Erro ao verificar status do Chatwoot: " . $e->getMessage();
}

// Processar o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logger->info("Requisição POST recebida para {$instance_name}. Dados: " . print_r($_POST, true));
}

// Processar o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica se é uma tentativa de atualização do Chatwoot de qualquer forma
    $is_chatwoot_update = isset($_POST['update_chatwoot']) || isset($_POST['csrf_token']);
    
    if ($is_chatwoot_update) {
        $logger->info("Processando atualização do Chatwoot para instância {$instance_name}");
        
        // Validar CSRF token
        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            $error_message = "Erro de segurança. Token inválido.";
            $logger->error("Token CSRF inválido na atualização do Chatwoot para {$instance_name}");
        } else {
            // Verificar se o Chatwoot está habilitado na API
            if (!$chatwoot_api_enabled) {
                $error_message = "O Chatwoot não está ativado na API. Por favor, solicite ao administrador para ativar o Chatwoot.";
                $logger->error("Tentativa de configurar Chatwoot desativado para {$instance_name}");
            } else {
                // Capturar os valores do formulário
                $enabled = isset($_POST['enabled']);
                $accountId = Security::sanitizeInput($_POST['accountId'] ?? '');
                $token = Security::sanitizeInput($_POST['token'] ?? '');
                $url = Security::sanitizeInput($_POST['url'] ?? '');
                $signMsg = isset($_POST['signMsg']);
                $reopenConversation = isset($_POST['reopenConversation']);
                $conversationPending = isset($_POST['conversationPending']);
                $nameInbox = Security::sanitizeInput($_POST['nameInbox'] ?? '');
                $mergeBrazilContacts = isset($_POST['mergeBrazilContacts']);
                $importContacts = isset($_POST['importContacts']);
                $importMessages = isset($_POST['importMessages']);
                $daysLimitImportMessages = intval($_POST['daysLimitImportMessages'] ?? 2);
                $signDelimiter = Security::sanitizeInput($_POST['signDelimiter'] ?? '\n');
                $autoCreate = isset($_POST['autoCreate']);
                $organization = Security::sanitizeInput($_POST['organization'] ?? '');
                $logo = Security::sanitizeInput($_POST['logo'] ?? '');
                $ignoreJids = isset($_POST['ignoreGroups']) ? ["@g.us"] : [];
                
                // Log dos valores capturados do formulário
                $logger->info("Valores do formulário Chatwoot: " . print_r([
                    'enabled' => $enabled,
                    'accountId' => $accountId,
                    'token' => substr($token, 0, 5) . '...',  // Não logar o token completo por segurança
                    'url' => $url,
                    'nameInbox' => $nameInbox,
                    // outros campos...
                ], true));
                
                // Validação básica
                $validation_errors = [];
                
                if ($enabled) {
                    if (empty($accountId)) {
                        $validation_errors[] = "ID da Conta é obrigatório.";
                    }
                    if (empty($token)) {
                        $validation_errors[] = "Token é obrigatório.";
                    }
                    if (empty($url)) {
                        $validation_errors[] = "URL é obrigatória.";
                    } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
                        $validation_errors[] = "URL inválida.";
                    }
                    if (empty($nameInbox)) {
                        $validation_errors[] = "Nome da Caixa de Entrada é obrigatório.";
                    }
                }
                
                if (!empty($validation_errors)) {
                    $error_message = "Erros de validação: " . implode(" ", $validation_errors);
                    $logger->error("Erros de validação na configuração do Chatwoot: " . implode(" ", $validation_errors));
                } else {
                    try {
                        // Preparar dados para a API
                        $requestData = [
                            "enabled" => $enabled,
                            "accountId" => $accountId,
                            "token" => $token,
                            "url" => $url,
                            "signMsg" => $signMsg,
                            "reopenConversation" => $reopenConversation,
                            "conversationPending" => $conversationPending,
                            "nameInbox" => $nameInbox,
                            "mergeBrazilContacts" => $mergeBrazilContacts,
                            "importContacts" => $importContacts,
                            "importMessages" => $importMessages,
                            "daysLimitImportMessages" => $daysLimitImportMessages,
                            "signDelimiter" => $signDelimiter,
                            "autoCreate" => $autoCreate,
                            "organization" => $organization,
                            "logo" => $logo,
                            "ignoreJids" => $ignoreJids
                        ];
                        
                        $jsonData = json_encode($requestData);
                        
                        // Log dos dados enviados
                        $logger->info("Enviando configurações do Chatwoot para a API ({$instance_name})");
                        
                        // Usar curl diretamente para garantir formato correto
                        $curl = curl_init();
                        curl_setopt_array($curl, [
                            CURLOPT_URL => rtrim($api->getBaseUrl(), '/') . '/chatwoot/set/' . $instance_name,
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
                        
                        $response = curl_exec($curl);
                        $err = curl_error($curl);
                        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                        
                        // Log detalhado da resposta
                        $logger->info("Resposta da API para configuração do Chatwoot - HTTP Code: {$httpCode}");
                        $logger->info("Resposta completa: " . ($response ?: "Vazia"));
                        
                        curl_close($curl);
                        
                        if ($err) {
                            $logger->error("Erro curl para configuração do Chatwoot {$instance_name}: {$err}");
                            $error_message = "Erro ao comunicar com a API: {$err}";
                        } else {
                            $responseData = json_decode($response, true);
                            
                            if ($httpCode >= 200 && $httpCode < 300) {
                                $success_message = "Configurações do Chatwoot atualizadas com sucesso!";
                                $logger->info("Configurações do Chatwoot atualizadas com sucesso para {$instance_name}");
                                
                                // Atualizar as configurações locais
                                $chatwoot_settings = $requestData;
                                $chatwoot_enabled = $enabled;
                            } else {
                                $error_message = "Erro ao atualizar configurações do Chatwoot. Código: {$httpCode}";
                                if (isset($responseData['message'])) {
                                    $error_message .= " - " . (is_array($responseData['message']) ? implode(" ", $responseData['message']) : $responseData['message']);
                                }
                                $logger->error("Erro ao atualizar configurações do Chatwoot: {$error_message}");
                            }
                        }
                    } catch (Exception $e) {
                        $logger->error("Exceção ao atualizar configurações do Chatwoot: " . $e->getMessage());
                        $error_message = "Erro ao atualizar configurações do Chatwoot: " . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Gerar token CSRF para formulários
$csrf_token = Security::generateCsrfToken();

include '../base.php';
?>

<link rel="stylesheet" href="../admin/css/admin_config_avancadas.css">
<link rel="stylesheet" href="../admin/css/admin_instancia.css">

<style>
.chatwoot-settings-card {
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-bottom: 30px;
    overflow: hidden;
}

.chatwoot-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background-color: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}

.chatwoot-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.chatwoot-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background-color: #1f93ff;
    color: white;
}

.chatwoot-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: 50px;
    font-size: 14px;
    font-weight: 500;
}

.chatwoot-badge.active {
    background-color: #d1fae5;
    color: #059669;
}

.chatwoot-badge.inactive {
    background-color: #fee2e2;
    color: #dc2626;
}

.chatwoot-badge.warning {
    background-color: #fff7dc;
    color: #b45309;
}

.settings-section {
    padding: 20px;
    margin-bottom: 20px;
    border-bottom: 1px solid #e9ecef;
}

.settings-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.settings-section h3 {
    margin-bottom: 15px;
    font-size: 18px;
    color: #333;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

.form-group input[type="text"],
.form-group input[type="number"],
.form-group input[type="url"] {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s;
}

.form-group input:focus {
    border-color: #1f93ff;
    outline: none;
    box-shadow: 0 0 0 3px rgba(31, 147, 255, 0.1);
}

.form-group small {
    display: block;
    margin-top: 5px;
    color: #6b7280;
    font-size: 12px;
}

.switch-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 15px;
}

.switch-info {
    display: flex;
    flex-direction: column;
}

.switch-info h4 {
    margin: 0 0 5px 0;
    font-size: 15px;
    color: #333;
}

.switch-info p {
    margin: 0;
    font-size: 13px;
    color: #6b7280;
}

.disabled-form {
    opacity: 0.7;
    pointer-events: none;
}

.disabled-message {
    background-color: #f3f4f6;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    margin-bottom: 20px;
}

.disabled-message svg {
    display: block;
    margin: 0 auto 15px;
    color: #9ca3af;
}

.disabled-message h3 {
    font-size: 18px;
    color: #4b5563;
    margin-bottom: 10px;
}

.disabled-message p {
    color: #6b7280;
    margin-bottom: 0;
    font-size: 14px;
}

.action-button {
    margin-top: 20px;
    text-align: center;
}

/* Estilo específico para o formulário do Chatwoot */
.main-toggle {
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e9ecef;
}

.main-toggle .switch-container {
    display: inline-flex;
    padding: 10px 15px;
    background-color: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.main-toggle .switch-info h4 {
    font-size: 16px;
}
</style>

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
        <span class="breadcrumb-current">Configurações do Chatwoot</span>
    </div>

    <div class="page-header">
        <div class="header-content">
            <h1>Configurações do Chatwoot</h1>
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

    <?php if ($warning_message): ?>
        <div class="alert alert-warning">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
            <div class="alert-content">
                <h4>Atenção</h4>
                <p><?php echo htmlspecialchars($warning_message); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Configurações do Chatwoot -->
    <div class="chatwoot-settings-card">
        <div class="chatwoot-card-header">
            <div class="chatwoot-info">
                <div class="chatwoot-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                    </svg>
                </div>
                <h2>Integração com Chatwoot</h2>
            </div>
            
            <?php if ($chatwoot_api_enabled): ?>
                <span class="chatwoot-badge <?php echo $chatwoot_enabled ? 'active' : 'inactive'; ?>">
                    <?php if ($chatwoot_enabled): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                        Integração Ativa
                    <?php else: ?>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="15" y1="9" x2="9" y2="15"></line>
                            <line x1="9" y1="9" x2="15" y2="15"></line>
                        </svg>
                        Integração Inativa
                    <?php endif; ?>
                </span>
            <?php else: ?>
                <span class="chatwoot-badge warning">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    Não Disponível na API
                </span>
            <?php endif; ?>
        </div>
        
        <div class="card-body">
            <?php if (!$chatwoot_api_enabled): ?>
                <div class="disabled-message">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    <h3>Integração com Chatwoot Indisponível</h3>
                    <p>O Chatwoot não está ativado na API. Por favor, solicite ao administrador do sistema para ativar a funcionalidade Chatwoot na API.</p>
                    <div class="action-button">
                        <a href="admin_instancia.php?instance_id=<?php echo urlencode($instance_id); ?>" class="btn-secondary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="19" y1="12" x2="5" y2="12"></line>
                                <polyline points="12 19 5 12 12 5"></polyline>
                            </svg>
                            Voltar para Instância
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <form action="admin_chatwoot.php?instance_id=<?php echo urlencode($instance_id); ?>" method="POST" id="chatwootForm">
                    <!-- Token CSRF para proteger o formulário -->
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <!-- Habilitar/Desabilitar Chatwoot -->
                    <div class="main-toggle">
                        <div class="switch-container">
                            <div class="switch-info">
                                <h4>Ativar Integração com Chatwoot</h4>
                                <p>Habilita ou desabilita a integração com o Chatwoot</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="enabled" name="enabled" value="1" <?php echo isset($chatwoot_settings['enabled']) && $chatwoot_settings['enabled'] ? 'checked' : ''; ?> onchange="toggleChatwootForm()">
                                <span class="slider round"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div id="chatwootConfigForm" class="<?php echo isset($chatwoot_settings['enabled']) && $chatwoot_settings['enabled'] ? '' : 'disabled-form'; ?>">
                        <!-- Configurações do Chatwoot -->
                        <div class="settings-section">
                            <h3>Configurações Básicas</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="url">URL do Chatwoot</label>
                                    <input type="url" id="url" name="url" value="<?php echo isset($chatwoot_settings['url']) ? htmlspecialchars($chatwoot_settings['url']) : ''; ?>" placeholder="Ex: https://chatwoot.seudominio.com">
                                    <small>Endereço completo do seu servidor Chatwoot</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="accountId">ID da Conta</label>
                                    <input type="text" id="accountId" name="accountId" value="<?php echo isset($chatwoot_settings['accountId']) ? htmlspecialchars($chatwoot_settings['accountId']) : ''; ?>" placeholder="Ex: 1">
                                    <small>ID da conta no Chatwoot</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="token">Token API</label>
                                    <input type="text" id="token" name="token" value="<?php echo isset($chatwoot_settings['token']) ? htmlspecialchars($chatwoot_settings['token']) : ''; ?>" placeholder="Seu token do Chatwoot">
                                    <small>Token de acesso para a API do Chatwoot</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="nameInbox">Nome da Caixa de Entrada</label>
                                    <input type="text" id="nameInbox" name="nameInbox" value="<?php echo isset($chatwoot_settings['nameInbox']) ? htmlspecialchars($chatwoot_settings['nameInbox']) : ''; ?>" placeholder="Ex: whatsapp">
                                    <small>Nome para identificar esta conexão no Chatwoot</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h3>Configurações da Organização</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="organization">Nome da Organização</label>
                                    <input type="text" id="organization" name="organization" value="<?php echo isset($chatwoot_settings['organization']) ? htmlspecialchars($chatwoot_settings['organization']) : ''; ?>" placeholder="Ex: Minha Empresa">
                                    <small>Nome da organização (exibido no Chatwoot)</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="logo">URL do Logo</label>
                                    <input type="url" id="logo" name="logo" value="<?php echo isset($chatwoot_settings['logo']) ? htmlspecialchars($chatwoot_settings['logo']) : ''; ?>" placeholder="Ex: https://seudominio.com/logo.png">
                                    <small>URL da imagem do logo da organização</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h3>Opções de Importação</h3>
                            <div class="form-grid">
                                <div class="switch-container">
                                    <div class="switch-info">
                                        <h4>Importar Contatos</h4>
                                        <p>Importar contatos do WhatsApp para o Chatwoot</p>
                                    </div>
                                    <label class="switch">
                                        <input type="checkbox" id="importContacts" name="importContacts" <?php echo isset($chatwoot_settings['importContacts']) && $chatwoot_settings['importContacts'] ? 'checked' : ''; ?>>
                                        <span class="slider round"></span>
                                    </label>
                                </div>
                                
                                <div class="switch-container">
                                    <div class="switch-info">
                                        <h4>Fundir Contatos Brasileiros</h4>
                                        <p>Mesclar contatos com DDD brasileiro</p>
                                    </div>
                                    <label class="switch">
                                        <input type="checkbox" id="mergeBrazilContacts" name="mergeBrazilContacts" <?php echo isset($chatwoot_settings['mergeBrazilContacts']) && $chatwoot_settings['mergeBrazilContacts'] ? 'checked' : ''; ?>>
                                        <span class="slider round"></span>
                                    </label>
                                </div>
                                
                                <div class="switch-container">
                                    <div class="switch-info">
                                        <h4>Importar Mensagens</h4>
                                        <p>Importar histórico de mensagens</p>
                                    </div>
                                    <label class="switch">
                                        <input type="checkbox" id="importMessages" name="importMessages" <?php echo isset($chatwoot_settings['importMessages']) && $chatwoot_settings['importMessages'] ? 'checked' : ''; ?>>
                                        <span class="slider round"></span>
                                    </label>
                                </div>
                                
                                <div class="form-group">
                                    <label for="daysLimitImportMessages">Limite de Dias para Importação</label>
                                    <input type="number" id="daysLimitImportMessages" name="daysLimitImportMessages" value="<?php echo isset($chatwoot_settings['daysLimitImportMessages']) ? htmlspecialchars($chatwoot_settings['daysLimitImportMessages']) : '2'; ?>" min="1" max="30">
                                    <small>Número de dias para importar o histórico de mensagens</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h3>Configurações de Comportamento</h3>
                            <div class="form-grid">
                                <div class="switch-container">
                                    <div class="switch-info">
                                        <h4>Assinar Mensagens</h4>
                                        <p>Adicionar assinatura nas mensagens enviadas</p>
                                    </div>
                                    <label class="switch">
                                        <input type="checkbox" id="signMsg" name="signMsg" <?php echo isset($chatwoot_settings['signMsg']) && $chatwoot_settings['signMsg'] ? 'checked' : ''; ?>>
                                        <span class="slider round"></span>
                                    </label>
                                </div>
                                
                                <div class="form-group">
                                    <label for="signDelimiter">Delimitador de Assinatura</label>
                                    <input type="text" id="signDelimiter" name="signDelimiter" value="<?php echo isset($chatwoot_settings['signDelimiter']) ? htmlspecialchars($chatwoot_settings['signDelimiter']) : '\n'; ?>" placeholder="Ex: \n">
                                    <small>Caractere(s) para separar mensagem e assinatura</small>
                                </div>
                                
                                <div class="switch-container">
                                    <div class="switch-info">
                                        <h4>Reabrir Conversas</h4>
                                        <p>Reabrir conversas resolvidas automaticamente</p>
                                    </div>
                                    <label class="switch">
                                        <input type="checkbox" id="reopenConversation" name="reopenConversation" <?php echo isset($chatwoot_settings['reopenConversation']) && $chatwoot_settings['reopenConversation'] ? 'checked' : ''; ?>>
                                        <span class="slider round"></span>
                                    </label>
                                </div>
                                
                                <div class="switch-container">
                                    <div class="switch-info">
                                        <h4>Conversas Pendentes</h4>
                                        <p>Marcar novas conversas como pendentes</p>
                                    </div>
                                    <label class="switch">
                                        <input type="checkbox" id="conversationPending" name="conversationPending" <?php echo isset($chatwoot_settings['conversationPending']) && $chatwoot_settings['conversationPending'] ? 'checked' : ''; ?>>
                                        <span class="slider round"></span>
                                    </label>
                                </div>
                                
                                <div class="switch-container">
                                    <div class="switch-info">
                                        <h4>Criação Automática</h4>
                                        <p>Criar automaticamente contatos e conversas</p>
                                    </div>
                                    <label class="switch">
                                        <input type="checkbox" id="autoCreate" name="autoCreate" <?php echo isset($chatwoot_settings['autoCreate']) && $chatwoot_settings['autoCreate'] ? 'checked' : ''; ?>>
                                        <span class="slider round"></span>
                                    </label>
                                </div>
                                
                                <div class="switch-container">
                                    <div class="switch-info">
                                        <h4>Ignorar Grupos</h4>
                                        <p>Não sincronizar conversas de grupos</p>
                                    </div>
                                    <label class="switch">
                                        <input type="checkbox" id="ignoreGroups" name="ignoreGroups" <?php echo isset($chatwoot_settings['ignoreJids']) && in_array('@g.us', $chatwoot_settings['ignoreJids']) ? 'checked' : ''; ?>>
                                        <span class="slider round"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_chatwoot" value="1" class="btn-primary">
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
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Função para habilitar/desabilitar o formulário do Chatwoot
function toggleChatwootForm() {
    const enabledCheckbox = document.getElementById('enabled');
    const chatwootForm = document.getElementById('chatwootConfigForm');
    
    if (enabledCheckbox && chatwootForm) {
        if (enabledCheckbox.checked) {
            chatwootForm.classList.remove('disabled-form');
        } else {
            chatwootForm.classList.add('disabled-form');
        }
    }
}

// Inicializar a página
document.addEventListener('DOMContentLoaded', function() {
    console.log('Página de configurações do Chatwoot carregada');
    
    // Inicializar o estado do formulário
    toggleChatwootForm();
    
    // Adicionar evento de submissão ao formulário
    const form = document.getElementById('chatwootForm');
    if (form) {
        console.log('Formulário do Chatwoot encontrado');
        
        form.addEventListener('submit', function(e) {
            console.log('Formulário do Chatwoot submetido');
            
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
        console.log('Formulário do Chatwoot não encontrado');
    }
});
</script>

<?php include '../footer.php'; ?>