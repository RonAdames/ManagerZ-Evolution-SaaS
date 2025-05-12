<?php
$title = "Configurações de Webhook";
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
    
    $logger->info("Carregando configurações de Webhook para a instância ID: {$instance_id}, Nome: {$instance['instance_name']}");
} catch (PDOException $e) {
    $logger->error("Erro ao buscar instância: " . $e->getMessage());
    Session::setFlash('error', 'Erro ao buscar informações da instância. Tente novamente mais tarde.');
    header("Location: ../dashboard.php");
    exit;
}

$instance_name = $instance['instance_name'];
$status = $instance['status'];

// Valores padrão para o webhook
$webhook_settings = [
    'enabled' => false,
    'url' => '',
    'headers' => [
        'Content-Type' => 'application/json'
    ],
    'webhookByEvents' => false,
    'webhookBase64' => false,
    'byEvents' => false,
    'base64' => false,
    'events' => []
];

// Flag para controlar disponibilidade do webhook
$webhook_api_enabled = true;
$webhook_enabled = false;

// Lista completa de eventos disponíveis
$available_events = [
    'APPLICATION_STARTUP' => 'Inicialização da Aplicação',
    'QRCODE_UPDATED' => 'QR Code Atualizado',
    'MESSAGES_SET' => 'Conjunto de Mensagens',
    'MESSAGES_UPSERT' => 'Inserção de Mensagens',
    'MESSAGES_UPDATE' => 'Atualização de Mensagens',
    'MESSAGES_DELETE' => 'Exclusão de Mensagens',
    'SEND_MESSAGE' => 'Envio de Mensagem',
    'CONTACTS_SET' => 'Conjunto de Contatos',
    'CONTACTS_UPSERT' => 'Inserção de Contatos',
    'CONTACTS_UPDATE' => 'Atualização de Contatos',
    'PRESENCE_UPDATE' => 'Atualização de Presença',
    'CHATS_SET' => 'Conjunto de Chats',
    'CHATS_UPSERT' => 'Inserção de Chats',
    'CHATS_UPDATE' => 'Atualização de Chats',
    'CHATS_DELETE' => 'Exclusão de Chats',
    'GROUPS_UPSERT' => 'Inserção de Grupos',
    'GROUP_UPDATE' => 'Atualização de Grupo',
    'GROUP_PARTICIPANTS_UPDATE' => 'Atualização de Participantes do Grupo',
    'CONNECTION_UPDATE' => 'Atualização de Conexão',
    'LABELS_EDIT' => 'Edição de Rótulos',
    'LABELS_ASSOCIATION' => 'Associação de Rótulos',
    'CALL' => 'Chamada',
    'TYPEBOT_START' => 'Início do Typebot',
    'TYPEBOT_CHANGE_STATUS' => 'Mudança de Status do Typebot',
    'LOGOUT_INSTANCE' => 'Logout da Instância',
    'REMOVE_INSTANCE' => 'Remoção da Instância'
];

// Inicializa a API
try {
    $api = new Api();
    
    try {
        $logger->info("Verificando status do webhook para {$instance_name}");
        // Endpoint correto: webhook/find/instance
        $response = $api->get("webhook/find/{$instance_name}");
        
        $logger->info("Resposta webhook/find: " . json_encode($response));
        
        if ($response === null) {
            // Resposta NULL é válida quando o webhook não está configurado
            $logger->info("Webhook não configurado para {$instance_name}");
        } else if (isset($response) && !isset($response['error'])) {
            $webhook_settings = $response;
            $webhook_enabled = isset($webhook_settings['enabled']) ? $webhook_settings['enabled'] : false;
            $logger->info("Webhook encontrado e carregado para {$instance_name}");
        } else {
            $logger->warning("Não foi possível obter status do webhook: " . json_encode($response));
            if (isset($response['status']) && $response['status'] === 404) {
                $logger->info("Webhook não encontrado para {$instance_name}");
            } else {
                $warning_message = "Erro ao verificar webhook. Configurações padrão serão usadas.";
            }
        }
    } catch (Exception $e) {
        $logger->error("Erro ao verificar status do webhook: " . $e->getMessage());
        $warning_message = "Erro ao verificar webhook: " . $e->getMessage();
    }
} catch (Exception $e) {
    $logger->error("Erro ao inicializar API: " . $e->getMessage());
    $webhook_api_enabled = false;
    $error_message = "Erro ao inicializar API. Algumas funcionalidades podem estar limitadas.";
}

// Processar o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF token
    if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
        $error_message = "Erro de segurança. Token inválido.";
        $logger->error("Token CSRF inválido na atualização do Webhook para {$instance_name}");
    } 
    // Verificar se a API está disponível
    else if (!$webhook_api_enabled) {
        $error_message = "API não inicializada. Por favor, tente novamente mais tarde.";
        $logger->error("Tentativa de atualizar webhook com API não disponível para {$instance_name}");
    } 
    else {
        $logger->info("Processando atualização de webhook para {$instance_name}");
        
        // Capturar os valores do formulário
        $enabled = isset($_POST['enabled']);
        $url = Security::sanitizeInput($_POST['url'] ?? '');
        $byEvents = isset($_POST['byEvents']);
        $base64 = isset($_POST['base64']);
        
        // Capturar headers do formulário
        $headerKeys = $_POST['header_key'] ?? [];
        $headerValues = $_POST['header_value'] ?? [];
        $headers = [];
        
        foreach ($headerKeys as $index => $key) {
            if (!empty($key) && isset($headerValues[$index])) {
                $headers[$key] = $headerValues[$index];
            }
        }
        
        // Capturar eventos selecionados
        $selectedEvents = $_POST['events'] ?? [];
        
        // Validação básica
        $validation_errors = [];
        
        if ($enabled && empty($url)) {
            $validation_errors[] = "URL do Webhook é obrigatória quando o webhook está ativado.";
        }
        
        if ($enabled && !filter_var($url, FILTER_VALIDATE_URL)) {
            $validation_errors[] = "URL do Webhook inválida. Por favor, forneça uma URL válida.";
        }
        
        if ($enabled && empty($selectedEvents)) {
            $validation_errors[] = "Selecione pelo menos um evento para o webhook.";
        }
        
        if (!empty($validation_errors)) {
            $error_message = "Erros de validação: " . implode(" ", $validation_errors);
            $logger->error("Erros de validação na configuração do Webhook: " . implode(" ", $validation_errors));
        } else {
            try {
                // Estrutura correta para a requisição
                $requestData = [
                    'webhook' => [
                        'enabled' => $enabled,
                        'url' => $url,
                        'headers' => $headers,
                        'webhookByEvents' => $byEvents,
                        'byEvents' => $byEvents,
                        'webhookBase64' => $base64,
                        'base64' => $base64,
                        'events' => $selectedEvents
                    ]
                ];
                
                $logger->info("Enviando configurações do webhook: " . json_encode($requestData));
                
                // Endpoint correto: webhook/set/instance
                $response = $api->post("webhook/set/{$instance_name}", $requestData);
                
                // Verificar resposta
                if (isset($response['id']) || isset($response['webhook']) || isset($response['url'])) {
                    $success_message = "Configurações de Webhook atualizadas com sucesso!";
                    $logger->info("Webhook atualizado com sucesso para {$instance_name}: " . json_encode($response));
                    
                    // Atualizar as configurações locais
                    if (isset($response['webhook'])) {
                        $webhook_settings = $response['webhook'];
                    } else {
                        $webhook_settings = $response;
                    }
                    $webhook_enabled = $enabled;
                } else {
                    // Se não encontrar formatos conhecidos mas não tiver mensagem de erro explícita,
                    // vamos considerar como sucesso também, já que a API pode retornar formatos diferentes
                    if (!isset($response['error']) || !$response['error']) {
                        $success_message = "Configurações de Webhook atualizadas com sucesso!";
                        $logger->warning("Resposta desconhecida da API, assumindo sucesso: " . json_encode($response));
                        $webhook_enabled = $enabled;
                    } else {
                        $error_message = isset($response['message']) ? $response['message'] : "Erro ao atualizar webhook. Verifique os dados e tente novamente.";
                        $logger->error("Erro na resposta da API ao atualizar webhook: " . json_encode($response));
                    }
                }
            } catch (Exception $e) {
                $logger->error("Exceção ao atualizar webhook: " . $e->getMessage());
                $error_message = "Erro ao atualizar webhook: " . $e->getMessage();
            }
        }
    }
}

// Garantir compatibilidade entre campos
if (isset($webhook_settings['webhookByEvents']) && !isset($webhook_settings['byEvents'])) {
    $webhook_settings['byEvents'] = $webhook_settings['webhookByEvents'];
}

if (isset($webhook_settings['webhookBase64']) && !isset($webhook_settings['base64'])) {
    $webhook_settings['base64'] = $webhook_settings['webhookBase64'];
}

// Garantir que o campo de eventos existe
if (!isset($webhook_settings['events']) || !is_array($webhook_settings['events'])) {
    $webhook_settings['events'] = [];
}

// Gerar token CSRF para formulários
$csrf_token = Security::generateCsrfToken();

include '../base.php';
?>

<link rel="stylesheet" href="/webhook/assets/webhook_config.css">

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
        <a href="../admin/admin_instancia.php?instance_id=<?php echo urlencode($instance_id); ?>" class="breadcrumb-link">
            Instância
        </a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Configurações de Webhook</span>
    </div>

    <div class="page-header">
        <div class="header-content">
            <h1>Configurações de Webhook</h1>
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

    <!-- Configurações do Webhook -->
    <div class="card">
        <div class="card-header">
            <div class="webhook-info">
                <div class="webhook-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                        <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                    </svg>
                </div>
                <h2>Configuração de Webhook</h2>
            </div>
            
            <span class="webhook-badge <?php echo $webhook_enabled ? 'active' : 'inactive'; ?>">
                <?php if ($webhook_enabled): ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    Webhook Ativo
                <?php else: ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg>
                    Webhook Inativo
                <?php endif; ?>
            </span>
        </div>
        
        <div class="card-body">
            <?php if ($status !== 'connected'): ?>
                <div class="alert alert-warning" style="margin-bottom: 20px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    <div class="alert-content">
                        <h4>Instância não conectada</h4>
                        <p>A instância não está conectada. Você pode configurar o webhook, mas ele só será ativado quando a instância estiver conectada.</p>
                    </div>
                </div>
            <?php endif; ?>
            
            <form action="webhook_config.php?instance_id=<?php echo urlencode($instance_id); ?>" method="POST" id="webhookForm">
                <!-- Token CSRF para proteger o formulário -->
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <!-- Habilitar/Desabilitar Webhook -->
                <div class="main-toggle">
                    <div class="switch-container">
                        <div class="switch-info">
                            <h4>Ativar Webhook</h4>
                            <p>Habilita ou desabilita o envio de eventos para o webhook configurado</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" id="enabled" name="enabled" value="1" 
                                <?php echo $webhook_enabled ? 'checked' : ''; ?> 
                                onchange="toggleWebhookForm()" 
                                <?php echo (!$webhook_api_enabled) ? 'disabled' : ''; ?>>
                            <span class="slider round"></span>
                        </label>
                    </div>
                </div>
                
                <div id="webhookConfigForm" class="<?php echo $webhook_enabled ? '' : 'disabled-form'; ?>">
                    <!-- Configurações básicas -->
                    <div class="settings-section">
                        <h3>Configurações Básicas</h3>
                        <div class="form-grid">
                            <div class="form-group url-group">
                                <label for="url">URL do Webhook <span class="required">*</span></label>
                                <input type="url" id="url" name="url" 
                                    value="<?php echo isset($webhook_settings['url']) ? htmlspecialchars($webhook_settings['url']) : ''; ?>" 
                                    placeholder="https://exemplo.com/webhook" required 
                                    <?php echo (!$webhook_api_enabled) ? 'disabled' : ''; ?>>
                                <small>URL completa para onde os eventos serão enviados</small>
                            </div>
                        </div>
                        
                        <div class="options-grid">
                            <div class="switch-container">
                                <div class="switch-info">
                                    <h4>Separar por Eventos</h4>
                                    <p>Envia cada evento para um endpoint específico [url/evento]</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" id="byEvents" name="byEvents" 
                                        <?php echo (isset($webhook_settings['byEvents']) && $webhook_settings['byEvents']) || 
                                                  (isset($webhook_settings['webhookByEvents']) && $webhook_settings['webhookByEvents']) ? 'checked' : ''; ?> 
                                        <?php echo (!$webhook_api_enabled) ? 'disabled' : ''; ?>>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                            
                            <div class="switch-container">
                                <div class="switch-info">
                                    <h4>Enviar Mídia em Base64</h4>
                                    <p>Envia mídia codificada em base64 junto com os eventos</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" id="base64" name="base64" 
                                        <?php echo (isset($webhook_settings['base64']) && $webhook_settings['base64']) || 
                                                  (isset($webhook_settings['webhookBase64']) && $webhook_settings['webhookBase64']) ? 'checked' : ''; ?> 
                                        <?php echo (!$webhook_api_enabled) ? 'disabled' : ''; ?>>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Headers personalizados -->
                    <div class="settings-section">
                        <h3>Headers Personalizados</h3>
                        <p class="section-desc">Configure headers HTTP personalizados para serem enviados com as requisições ao webhook</p>
                        
                        <div id="headersContainer">
                            <?php 
                            // Se não houver headers, adiciona um campo vazio
                            $headers = isset($webhook_settings['headers']) ? $webhook_settings['headers'] : [];
                            if (empty($headers) || $headers === null) {
                                $headers = ['Content-Type' => 'application/json'];
                            }
                            
                            $index = 0;
                            foreach ($headers as $key => $value): 
                            ?>
                                <div class="header-row">
                                    <div class="form-group">
                                        <input type="text" name="header_key[<?php echo $index; ?>]" 
                                            placeholder="Nome do Header" 
                                            value="<?php echo htmlspecialchars($key); ?>" 
                                            <?php echo (!$webhook_api_enabled) ? 'disabled' : ''; ?>>
                                    </div>
                                    <div class="form-group">
                                        <input type="text" name="header_value[<?php echo $index; ?>]" 
                                            placeholder="Valor do Header" 
                                            value="<?php echo htmlspecialchars($value); ?>" 
                                            <?php echo (!$webhook_api_enabled) ? 'disabled' : ''; ?>>
                                    </div>
                                    <button type="button" class="btn-remove-header" onclick="removeHeader(this)" 
                                        <?php echo (!$webhook_api_enabled) ? 'disabled' : ''; ?>>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <line x1="18" y1="6" x2="6" y2="18"></line>
                                            <line x1="6" y1="6" x2="18" y2="18"></line>
                                        </svg>
                                    </button>
                                </div>
                            <?php 
                                $index++;
                            endforeach; 
                            ?>
                        </div>
                        
                        <button type="button" class="btn-add-header" id="addHeader" 
                            <?php echo (!$webhook_api_enabled) ? 'disabled' : ''; ?>>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Adicionar Header
                        </button>
                    </div>
                    
                    <!-- Seleção de eventos -->
                    <div class="settings-section">
                        <h3>Eventos a Serem Notificados</h3>
                        <p class="section-desc">Selecione quais eventos devem ser enviados para o webhook</p>
                        
                        <div class="events-grid">
                            <?php 
                            $events = isset($webhook_settings['events']) ? $webhook_settings['events'] : []; 
                            foreach ($available_events as $event_key => $event_label): 
                            ?>
                                <div class="event-item">
                                    <label class="checkbox-container">
                                        <input type="checkbox" name="events[]" value="<?php echo $event_key; ?>" 
                                            <?php echo in_array($event_key, $events) ? 'checked' : ''; ?> 
                                            <?php echo (!$webhook_api_enabled) ? 'disabled' : ''; ?>>
                                        <span class="checkmark"></span>
                                        <div class="event-info">
                                            <span class="event-name"><?php echo htmlspecialchars($event_label); ?></span>
                                            <span class="event-key"><?php echo htmlspecialchars($event_key); ?></span>
                                        </div>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="events-actions">
                            <button type="button" class="btn-select-all" id="selectAllEvents" 
                                <?php echo (!$webhook_api_enabled) ? 'disabled' : ''; ?>>
                                Selecionar Todos
                            </button>
                            <button type="button" class="btn-unselect-all" id="unselectAllEvents" 
                                <?php echo (!$webhook_api_enabled) ? 'disabled' : ''; ?>>
                                Desmarcar Todos
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary" 
                        <?php echo (!$webhook_api_enabled) ? 'disabled' : ''; ?>>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                            <polyline points="7 3 7 8 15 8"></polyline>
                        </svg>
                        Salvar Configurações
                    </button>
                    <a href="../admin/admin_instancia.php?instance_id=<?php echo urlencode($instance_id); ?>" class="btn-secondary">
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
</div>

<script>
// Contador para IDs dos headers
let headerCounter = <?php 
$headers = isset($webhook_settings['headers']) ? $webhook_settings['headers'] : [];
if (empty($headers) || $headers === null) {
    echo 1;
} else {
    echo count($headers);
}
?>;

// Função para habilitar/desabilitar o formulário do Webhook
function toggleWebhookForm() {
    const enabledCheckbox = document.getElementById('enabled');
    const webhookForm = document.getElementById('webhookConfigForm');
    
    if (enabledCheckbox && webhookForm) {
        if (enabledCheckbox.checked) {
            webhookForm.classList.remove('disabled-form');
        } else {
            webhookForm.classList.add('disabled-form');
        }
    }
}

// Função para adicionar uma nova linha de header
function addHeader() {
    const container = document.getElementById('headersContainer');
    if (!container) return;
    
    const row = document.createElement('div');
    row.className = 'header-row';
    
    const isApiEnabled = <?php echo ($webhook_api_enabled) ? 'true' : 'false'; ?>;
    const disabledAttr = isApiEnabled ? '' : 'disabled';
    
    row.innerHTML = `
        <div class="form-group">
            <input type="text" name="header_key[${headerCounter}]" placeholder="Nome do Header" ${disabledAttr}>
        </div>
        <div class="form-group">
            <input type="text" name="header_value[${headerCounter}]" placeholder="Valor do Header" ${disabledAttr}>
        </div>
        <button type="button" class="btn-remove-header" onclick="removeHeader(this)" ${disabledAttr}>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
    `;
    
    container.appendChild(row);
    headerCounter++;
}

// Função para remover uma linha de header
function removeHeader(button) {
    const row = button.parentNode;
    if (!row || !row.parentNode) return;
    
    if (row.parentNode.children.length > 1) {
        row.parentNode.removeChild(row);
    } else {
        // Se for o último, apenas limpa os campos
        const inputs = row.querySelectorAll('input');
        inputs.forEach(input => {
            input.value = '';
        });
    }
}

// Função para selecionar todos os eventos
function selectAllEvents() {
    const checkboxes = document.querySelectorAll('input[name="events[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
}

// Função para desmarcar todos os eventos
function unselectAllEvents() {
    const checkboxes = document.querySelectorAll('input[name="events[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
}

// Adicionar ouvintes de eventos quando o documento estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar o estado do formulário
    toggleWebhookForm();
    
    // Adicionar ouvinte para o botão de adicionar header
    const addHeaderBtn = document.getElementById('addHeader');
    if (addHeaderBtn) {
        addHeaderBtn.addEventListener('click', addHeader);
    }
    
    // Adicionar ouvintes para os botões de selecionar/desmarcar eventos
    const selectAllBtn = document.getElementById('selectAllEvents');
    const unselectAllBtn = document.getElementById('unselectAllEvents');
    
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', selectAllEvents);
    }
    
    if (unselectAllBtn) {
        unselectAllBtn.addEventListener('click', unselectAllEvents);
    }
    
    // Adicionar evento de submissão ao formulário para mostrar indicador de carregamento
    const form = document.getElementById('webhookForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            // Verificar se a API está disponível
            const isApiEnabled = <?php echo ($webhook_api_enabled) ? 'true' : 'false'; ?>;
            if (!isApiEnabled) {
                alert('API não está disponível no momento. Por favor, tente novamente mais tarde.');
                e.preventDefault();
                return false;
            }
            
            // Validar formulário antes de submeter
            if (this.submitting) {
                e.preventDefault();
                return false;
            }
            
            const enabled = document.getElementById('enabled')?.checked;
            if (enabled) {
                const url = document.getElementById('url')?.value.trim();
                if (!url) {
                    alert('URL do Webhook é obrigatória quando o webhook está ativado.');
                    e.preventDefault();
                    return false;
                }
                
                // Verificar se pelo menos um evento está selecionado
                const eventCheckboxes = document.querySelectorAll('input[name="events[]"]:checked');
                if (eventCheckboxes.length === 0) {
                    alert('Selecione pelo menos um evento para o webhook.');
                    e.preventDefault();
                    return false;
                }
            }
            
            // Mostrar indicador de carregamento
            this.submitting = true;
            const submitButton = this.querySelector('.btn-primary');
            if (submitButton) {
                const originalHTML = submitButton.innerHTML;
                submitButton.disabled = true;
                submitButton.innerHTML = `
                    <svg class="spinner" viewBox="0 0 50 50">
                        <circle class="spinner-path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
                    </svg>
                    Salvando...
                `;
                
                // Timeout para restaurar o botão após 10s caso a requisição falhe
                setTimeout(function() {
                    if (submitButton.disabled) {
                        submitButton.disabled = false;
                        submitButton.innerHTML = originalHTML;
                        form.submitting = false;
                    }
                }, 10000);
            }
            
            return true;
        });
    }
});
</script>

<?php include '../footer.php'; ?>