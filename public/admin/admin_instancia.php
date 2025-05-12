<?php
$title = "Administração da Instância";
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

// Verificar permissão (opcional)
/*
if (!Session::isAdmin()) {
    Session::setFlash('error', 'Acesso negado. Você não tem permissão para acessar esta página.');
    header("Location: ../dashboard.php");
    exit;
}
*/

$user_id = Session::get('user_id');
$instance_id = isset($_GET['instance_id']) ? Security::sanitizeInput($_GET['instance_id']) : '';
$success_message = isset($_GET['success']) ? $_GET['success'] : '';
$error_message = isset($_GET['error']) ? $_GET['error'] : '';

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
} catch (PDOException $e) {
    $logger->error("Erro ao buscar instância: " . $e->getMessage());
    Session::setFlash('error', 'Erro ao buscar informações da instância. Tente novamente mais tarde.');
    header("Location: ../dashboard.php");
    exit;
}

$instance_name = $instance['instance_name'];
$qrcode_base64 = null;
$status = $instance['status'];

// Inicializa a API
$api = new Api();

// Verificação de estado de conexão
try {
    $connectionState = $api->getConnectionState($instance_name);
    
    if (isset($connectionState['instance']['state'])) {
        $apiState = $connectionState['instance']['state'];
        
        // Mapeamento de estados da API para status do banco
        $statusMap = [
            'open' => 'connected',
            'connecting' => 'connecting',
            'close' => 'disconnected',
            'logout' => 'disconnected'
        ];
        
        $newStatus = $statusMap[$apiState] ?? $status;
        
        if ($newStatus !== $status) {
            $logger->info("Atualizando status da instância {$instance_name} de {$status} para {$newStatus}");
            
            // Atualiza o status apenas no banco
            $stmt = $pdo->prepare("UPDATE instancias SET status = :status WHERE instance_id = :instance_id");
            $stmt->execute(['status' => $newStatus, 'instance_id' => $instance_id]);

            // Atualiza o status local
            $status = $newStatus;
        }
    }
} catch (Exception $e) {
    $logger->error("Erro ao verificar estado da conexão: " . $e->getMessage());
}

// Se a instância não estiver conectada, faz a chamada à API para obter o QR Code
if ($status !== 'connected') {
    try {
        $response = $api->connectInstance($instance_name);

        if (isset($response['base64'])) {
            $qrcode_base64 = $response['base64'];
            $logger->info("QR code obtido para a instância {$instance_name}");
        }
    } catch (Exception $e) {
        $logger->error("Erro ao obter QR code: " . $e->getMessage());
    }
}

// Gerar token CSRF para formulários
$csrf_token = Security::generateCsrfToken();

include '../base.php';
?>

<div class="content-wrapper">
    <div class="page-header">
        <div class="header-content">
            <h1><?php echo htmlspecialchars($instance['instance_name']); ?></h1>
            <p class="text-muted">Gerencie sua instância do WhatsApp</p>
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

    <div class="instance-info-grid">
    <div class="info-card">
        <div class="card-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
                <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
                <line x1="6" y1="6" x2="6.01" y2="6"></line>
                <line x1="6" y1="18" x2="6.01" y2="18"></line>
            </svg>
        </div>
        <div class="card-content">
            <span class="card-label">ID da Instância</span>
            <span class="card-value"><?php echo htmlspecialchars($instance['instance_id']); ?></span>
        </div>
    </div>

    <div class="info-card">
        <div class="card-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
        </div>
        <div class="card-content">
            <span class="card-label">Token</span>
            <div class="token-container">
                <input type="password" class="token-input" id="tokenInput" value="<?php echo htmlspecialchars($instance['token'] ?? ''); ?>" readonly>
                <button type="button" class="toggle-token" onclick="toggleTokenVisibility()" title="Mostrar/Ocultar Token">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </button>
                <button type="button" class="copy-token" onclick="copyToken()" title="Copiar Token">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</div>

    <div class="action-buttons-container">
        <a href="admin_config_avancadas.php?instance_id=<?php echo urlencode($instance_id); ?>" class="btn-settings">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="3"></circle>
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
            </svg>
            Configurações Avançadas
        </a>
        <!-- Novo botão de Webhook -->
        <a href="../webhook/webhook_config.php?instance_id=<?php echo urlencode($instance_id); ?>" class="btn-webhook">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
            </svg>
            Webhook
        </a>
        <a href="admin_chatwoot.php?instance_id=<?php echo urlencode($instance_id); ?>" class="btn-chatwoot">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
            </svg>
            Chatwoot
        </a>
    </div>

    <?php if ($status !== 'connected'): ?>
        <div class="qrcode-section">
            <div class="qrcode-container">
                <h2>Conecte seu WhatsApp</h2>
                <p class="instructions">
                    1. Abra o WhatsApp no seu celular<br>
                    2. Toque em Menu ou Configurações e selecione Dispositivos Conectados<br>
                    3. Toque em Conectar um Dispositivo<br>
                    4. Aponte seu celular para esta tela para capturar o código
                </p>

                <?php if ($qrcode_base64): ?>
                    <div class="qrcode-wrapper">
                        <img src="<?php echo htmlspecialchars($qrcode_base64); ?>" alt="QR Code" id="qrcode-image" />
                        <div class="qrcode-overlay">
                            <button onclick="refreshQRCode()" class="refresh-button" id="refreshQrBtn">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.3" />
                                </svg>
                                Atualizar QR Code
                            </button>
                        </div>
                    </div><br><br>
                    <div class="action-buttons">
                        <button class="delete-button" onclick="confirmDeleteInstance()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 6h18"></path>
                                <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                                <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                            </svg>
                            Excluir Instância
                        </button>
                    </div>
                <?php else: ?>
                    <div class="error-message">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <p>Não foi possível carregar o QR Code. Tente novamente em alguns instantes.</p>
                        <button onclick="refreshQRCode()" class="refresh-button" id="refreshQrBtn">Tentar Novamente</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="connected-status">
            <div class="success-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </div>
            <h2>WhatsApp Conectado</h2>
            <p>Sua instância está conectada e pronta para uso!</p>
            <div class="action-buttons">
                <button class="disconnect-button" onclick="disconnectInstance()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path>
                        <line x1="12" y1="2" x2="12" y2="12"></line>
                    </svg>
                    Desconectar WhatsApp
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Adicionando estilos para o botão de configurações avançadas -->
<style>
.action-buttons-container {
    margin: 20px 0;
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.btn-settings, .btn-chatwoot {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.btn-settings {
    background-color: var(--primary-color);
    color: white;
}

.btn-chatwoot {
    background-color: #1f93ff;
    color: white;
}

.btn-settings:hover, .btn-chatwoot:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.btn-settings:hover {
    background-color: var(--primary-dark);
}

.btn-chatwoot:hover {
    background-color: #0075e6;
}

/* Estilos atualizados para o card de informações e container do token */
.info-card {
    display: flex;
    align-items: center;
    padding: 15px;
    background-color: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    margin-bottom: 15px;
    width: 100%;
}

.card-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background-color: rgba(0, 180, 216, 0.1);
    color: var(--primary-color);
    margin-right: 15px;
    flex-shrink: 0;
}

.card-content {
    display: flex;
    flex-direction: column;
    flex-grow: 1;
    width: calc(100% - 55px); /* Considera o ícone e margem */
}

.card-label {
    font-size: 14px;
    color: #666;
    margin-bottom: 5px;
}

.token-container {
    display: flex;
    align-items: center;
    background-color: #f5f5f5;
    border-radius: 6px;
    padding: 2px;
    width: 100%;
}

.token-input {
    flex-grow: 1;
    border: none;
    background: transparent;
    padding: 8px;
    font-family: monospace;
    color: #333;
    width: calc(100% - 70px); /* Considera o espaço para os botões */
}

.toggle-token, .copy-token {
    background: none;
    border: none;
    cursor: pointer;
    padding: 8px;
    color: #666;
    transition: color 0.2s;
    flex-shrink: 0;
}

.toggle-token:hover, .copy-token:hover {
    color: var(--primary-color);
}

/* Ajuste para todo o conjunto de informações da instância */
.instance-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
    width: 100%;
}

@media (max-width: 768px) {
    .instance-info-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- CSRF Token para operações AJAX -->
<input type="hidden" id="csrf_token" value="<?php echo $csrf_token; ?>">
<input type="hidden" id="instance_name" value="<?php echo htmlspecialchars($instance_name); ?>">
<input type="hidden" id="instance_id" value="<?php echo htmlspecialchars($instance_id); ?>">

<link rel="stylesheet" href="/admin/css/admin_instancia.css">

<script>
    // Função para atualizar o QR Code
    function refreshQRCode() {
        // Mostrar mensagem de processamento
        const refreshBtn = document.getElementById('refreshQrBtn');
        const originalBtnText = refreshBtn.innerHTML;

        refreshBtn.disabled = true;
        refreshBtn.innerHTML = `
        <svg class="spinner" viewBox="0 0 50 50">
            <circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
        </svg>
        GERANDO NOVO QR-CODE...
    `;

        const instanceName = document.getElementById('instance_name').value;
        const csrfToken = document.getElementById('csrf_token').value;

        // Primeiro, desconecta a instância atual
        fetch('../ajax/disconnect_instance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `instance_name=${encodeURIComponent(instanceName)}&csrf_token=${encodeURIComponent(csrfToken)}`
            })
            .then(response => response.json())
            .then(data => {
                // Após desconectar, gerar novo QR code
                refreshBtn.innerHTML = `
            <svg class="spinner" viewBox="0 0 50 50">
                <circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
            </svg>
            NOVO QR-CODE FOI GERADO
        `;

                // Agora, faz a requisição para obter o novo QR code
                return fetch('../ajax/get_qrcode.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `instance_name=${encodeURIComponent(instanceName)}&csrf_token=${encodeURIComponent(csrfToken)}`
                });
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.qrcode) {
                    // Atualiza o QR code na interface
                    const qrcodeContainer = document.querySelector('.qrcode-container');
                    qrcodeContainer.innerHTML = `
                <h2>Conecte seu WhatsApp</h2>
                <p class="instructions">
                    1. Abra o WhatsApp no seu celular<br>
                    2. Toque em Menu ou Configurações e selecione Dispositivos Conectados<br>
                    3. Toque em Conectar um Dispositivo<br>
                    4. Aponte seu celular para esta tela para capturar o código
                </p>
                <div class="qrcode-wrapper">
                    <img src="${data.qrcode}" alt="QR Code" id="qrcode-image"/>
                    <div class="qrcode-overlay">
                        <button onclick="refreshQRCode()" class="refresh-button" id="refreshQrBtn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.3"/>
                            </svg>
                            Atualizar QR Code
                        </button>
                    </div>
                </div>
            `;
                } else {
                    // Exibir mensagem de erro
                    const errorContainer = document.querySelector('.qrcode-container');
                    errorContainer.innerHTML = `
                <h2>Conecte seu WhatsApp</h2>
                <p class="instructions">
                    1. Abra o WhatsApp no seu celular<br>
                    2. Toque em Menu ou Configurações e selecione Dispositivos Conectados<br>
                    3. Toque em Conectar um Dispositivo<br>
                    4. Aponte seu celular para esta tela para capturar o código
                </p>
                <div class="error-message">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <p>${data.message || 'Erro ao obter o QR Code. Tente novamente em alguns instantes.'}</p>
                    <button onclick="refreshQRCode()" class="refresh-button" id="refreshQrBtn">Tentar Novamente</button>
                </div>
            `;
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                // Exibir mensagem de erro
                const errorContainer = document.querySelector('.qrcode-container');
                errorContainer.innerHTML = `
            <h2>Conecte seu WhatsApp</h2>
            <p class="instructions">
                1. Abra o WhatsApp no seu celular<br>
                2. Toque em Menu ou Configurações e selecione Dispositivos Conectados<br>
                3. Toque em Conectar um Dispositivo<br>
                4. Aponte seu celular para esta tela para capturar o código
            </p>
            <div class="error-message">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <p>Erro de conexão. Verifique sua internet e tente novamente.</p>
                <button onclick="refreshQRCode()" class="refresh-button" id="refreshQrBtn">Tentar Novamente</button>
            </div>
        `;
            });
    }

    // Função para desconectar a instância
    function disconnectInstance() {
        if (!confirm('Tem certeza que deseja desconectar esta instância do WhatsApp?')) {
            return;
        }

        const instanceName = document.getElementById('instance_name').value;
        const csrfToken = document.getElementById('csrf_token').value;

        // Mostrar indicador de carregamento
        const actionButton = document.querySelector('.disconnect-button');
        const originalButtonText = actionButton.innerHTML;

        actionButton.disabled = true;
        actionButton.innerHTML = `
        <svg class="spinner" viewBox="0 0 50 50">
            <circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
        </svg>
        Desconectando...
    `;

        // Fazer requisição AJAX para desconectar
        fetch('../ajax/disconnect_instance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `instance_name=${encodeURIComponent(instanceName)}&csrf_token=${encodeURIComponent(csrfToken)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Recarregar a página para mostrar o QR code
                    window.location.reload();
                } else {
                    alert(data.message || 'Erro ao desconectar instância. Tente novamente.');
                    // Restaurar botão
                    actionButton.disabled = false;
                    actionButton.innerHTML = originalButtonText;
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro de conexão. Verifique sua internet e tente novamente.');
                // Restaurar botão
                actionButton.disabled = false;
                actionButton.innerHTML = originalButtonText;
            });
    }

    // Função para confirmar e deletar a instância
    function confirmDeleteInstance() {
        if (!confirm('ATENÇÃO: Esta ação irá REMOVER PERMANENTEMENTE esta instância. Esta operação não pode ser desfeita. Deseja continuar?')) {
            return;
        }

        // Dupla confirmação para evitar exclusões acidentais
        if (!confirm('Tem certeza? Esta ação removerá a instância do banco de dados e do WhatsApp API.')) {
            return;
        }

        const instanceName = document.getElementById('instance_name').value;
        const instanceId = document.getElementById('instance_id').value;
        const csrfToken = document.getElementById('csrf_token').value;

        // Redirect to admin_delete_instance.php with the necessary parameters
        window.location.href = `../admin/admin_delete_instance.php?instance_name=${encodeURIComponent(instanceName)}&instance_id=${encodeURIComponent(instanceId)}&csrf_token=${encodeURIComponent(csrfToken)}`;
    }

    // Verificar status da conexão periodicamente
    function checkConnectionStatus() {
        const instanceName = document.getElementById('instance_name').value;
        const instanceId = document.getElementById('instance_id').value;
        const csrfToken = document.getElementById('csrf_token').value;

        fetch('../ajax/check_connection.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `instance_name=${encodeURIComponent(instanceName)}&instance_id=${encodeURIComponent(instanceId)}&csrf_token=${encodeURIComponent(csrfToken)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Se o status mudou, recarregar a página
                    const currentStatus = '<?php echo $status; ?>';
                    if (data.status !== currentStatus) {
                        window.location.reload();
                    }
                }
            })
            .catch(error => {
                console.error('Erro ao verificar status:', error);
            });
    }

    // Inicializar a página
    document.addEventListener('DOMContentLoaded', function() {
        // Verificar status a cada 10 segundos
        setInterval(checkConnectionStatus, 10000);
    });

    // Adicionar estas funções JavaScript ao final do arquivo admin_instancia.php
function toggleTokenVisibility() {
    const tokenInput = document.getElementById('tokenInput');
    const toggleButton = document.querySelector('.toggle-token svg');
    
    if (tokenInput.type === 'password') {
        tokenInput.type = 'text';
        toggleButton.innerHTML = `
            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
            <line x1="1" y1="1" x2="23" y2="23"></line>
        `;
    } else {
        tokenInput.type = 'password';
        toggleButton.innerHTML = `
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
            <circle cx="12" cy="12" r="3"></circle>
        `;
    }
}

function copyToken() {
    const tokenInput = document.getElementById('tokenInput');
    const copyButton = document.querySelector('.copy-token');
    const originalIcon = copyButton.innerHTML;
    
    // Temporariamente mudar para tipo text para poder copiar
    const currentType = tokenInput.type;
    tokenInput.type = 'text';
    
    // Selecionar e copiar
    tokenInput.select();
    document.execCommand('copy');
    
    // Desselecionar
    tokenInput.setSelectionRange(0, 0);
    
    // Restaurar tipo original
    tokenInput.type = currentType;
    
    // Feedback visual
    copyButton.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 6L9 17l-5-5"></path>
        </svg>
    `;
    
    setTimeout(() => {
        copyButton.innerHTML = originalIcon;
    }, 2000);
}

</script>

<?php include '../footer.php'; ?>