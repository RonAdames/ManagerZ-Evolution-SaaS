<?php
$title = "Dashboard";
require_once 'config.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Security.php';

// Iniciar e validar a sessão de forma segura
Session::start();

// Verificar se o usuário está autenticado
if (!Session::isAuthenticated()) {
    header("Location: index.php");
    exit;
}

// Adicionar código para exibir erros temporariamente durante o debug
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

$user_id = Session::get('user_id');

// Verificar se o user_id é válido
if (empty($user_id)) {
    Session::destroy();
    header("Location: index.php?error=" . urlencode("Sessão inválida. Por favor, faça login novamente."));
    exit;
}

try {
    // Recupera informações do usuário e estatísticas
    $stmt = $pdo->prepare("
        SELECT 
            u.*,
            COUNT(i.id) as total_instances,
            SUM(CASE WHEN i.status = 'connected' THEN 1 ELSE 0 END) as active_instances,
            SUM(CASE WHEN i.status = 'connecting' THEN 1 ELSE 0 END) as connecting_instances,
            SUM(CASE WHEN i.status = 'disconnected' THEN 1 ELSE 0 END) as disconnected_instances
        FROM users u
        LEFT JOIN instancias i ON u.id = i.user_id
        WHERE u.id = :user_id
        GROUP BY u.id
    ");
    $stmt->execute(['user_id' => $user_id]);
    $user_stats = $stmt->fetch();

    // Verificar se o usuário existe e está ativo
    if (!$user_stats || !isset($user_stats['active']) || $user_stats['active'] != 1) {
        Session::destroy();
        header("Location: index.php?error=" . urlencode("Conta inativa ou não encontrada"));
        exit;
    }

    // Definir valores padrão para evitar erros
    $user_stats['active_instances'] = $user_stats['active_instances'] ?? 0;
    $user_stats['connecting_instances'] = $user_stats['connecting_instances'] ?? 0;
    $user_stats['disconnected_instances'] = $user_stats['disconnected_instances'] ?? 0;
    $user_stats['total_instances'] = $user_stats['total_instances'] ?? 0;
    $user_stats['max_instancias'] = $user_stats['max_instancias'] ?? 0;

    // Recupera as instâncias do usuário
    $stmt = $pdo->prepare("
        SELECT * 
        FROM instancias 
        WHERE user_id = :user_id 
        ORDER BY 
            CASE 
                WHEN status = 'connected' THEN 1
                WHEN status = 'connecting' THEN 2
                ELSE 3
            END,
            created_at DESC
    ");
    $stmt->execute(['user_id' => $user_id]);
    $instancias = $stmt->fetchAll();
    
} catch (PDOException $e) {
    // Log do erro
    $logger->error("Erro no dashboard: " . $e->getMessage());
    
    // Definir arrays vazios para evitar erros
    $user_stats = [
        'active_instances' => 0,
        'connecting_instances' => 0,
        'disconnected_instances' => 0,
        'total_instances' => 0,
        'max_instancias' => 0,
    ];
    $instancias = [];
    
    // Opcional: Redirecionar com mensagem de erro
    // header("Location: index.php?error=" . urlencode("Erro ao carregar o dashboard. Tente novamente mais tarde."));
    // exit;
}

// Gerar token CSRF para uso em formulários
$csrf_token = Security::generateCsrfToken();

include 'base.php';
?>

<div class="dashboard-wrapper">
    <!-- Cabeçalho do Dashboard -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1>Bem-vindo ao Dashboard</h1>
            <p class="text-muted">Gerencie suas instâncias do WhatsApp</p>
        </div>
        <div class="header-actions">
            <a href="trocar_senha.php" class="btn-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                Trocar Senha
            </a>
            <a href="criar_instancia.php" class="btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="16"></line>
                    <line x1="8" y1="12" x2="16" y2="12"></line>
                </svg>
                Nova Instância
            </a>
        </div>
    </div>

    <!-- Estatísticas -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background-color: rgba(16, 185, 129, 0.1); color: #10b981;">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </div>
            <div class="stat-content">
                <span class="stat-label">Instâncias Ativas</span>
                <span class="stat-value success"><?php echo $user_stats['active_instances']; ?></span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background-color: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="6" x2="12" y2="10"></line>
                    <line x1="12" y1="14" x2="12.01" y2="14"></line>
                </svg>
            </div>
            <div class="stat-content">
                <span class="stat-label">Conectando</span>
                <span class="stat-value warning"><?php echo $user_stats['connecting_instances']; ?></span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background-color: rgba(239, 68, 68, 0.1); color: #ef4444;">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </div>
            <div class="stat-content">
                <span class="stat-label">Desconectadas</span>
                <span class="stat-value danger"><?php echo $user_stats['disconnected_instances']; ?></span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background-color: rgba(0, 180, 216, 0.1); color: var(--primary-color);">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="8.5" cy="7" r="4"></circle>
                    <line x1="20" y1="8" x2="20" y2="14"></line>
                    <line x1="23" y1="11" x2="17" y2="11"></line>
                </svg>
            </div>
            <div class="stat-content">
                <span class="stat-label">Limite de Instâncias</span>
                <div class="limit-info">
                    <span class="stat-value"><?php echo $user_stats['total_instances']; ?>/<?php echo $user_stats['max_instancias']; ?></span>
                    <div class="progress-bar">
                        <?php
                        $max_value = max(1, $user_stats['max_instancias']); // Previne divisão por zero
                        $usage_percent = min(100, ($user_stats['total_instances'] / $max_value) * 100);
                        $progress_color = $usage_percent >= 90 ? '#ef4444' : ($usage_percent >= 70 ? '#f59e0b' : '#10b981');
                        ?>
                        <div class="progress" style="width: <?php echo $usage_percent; ?>%; background-color: <?php echo $progress_color; ?>"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Instâncias -->
    <?php if (!empty($instancias)): ?>
    <div class="instances-section">
        <div class="section-header">
            <h2>Suas Instâncias</h2>
            <div class="search-box">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" id="searchInstances" placeholder="Buscar instância..." onkeyup="filterInstances()">
            </div>
        </div>

        <div class="instances-grid">
            <?php foreach ($instancias as $instancia): ?>
                <div class="instance-card">
                    <div class="instance-header">
                        <div class="instance-icon">
                            <?php echo strtoupper(substr($instancia['instance_name'], 0, 2)); ?>
                        </div>
                        <span class="status-badge <?php echo htmlspecialchars($instancia['status']); ?>">
                            <?php echo htmlspecialchars($instancia['status']); ?>
                        </span>
                    </div>
                    <div class="instance-body">
                        <h3><?php echo htmlspecialchars($instancia['instance_name']); ?></h3>
                        <div class="instance-id">
                            <?php echo htmlspecialchars($instancia['instance_id']); ?>
                            <button onclick="copyToClipboard('<?php echo htmlspecialchars($instancia['instance_id']); ?>')" class="btn-copy" title="Copiar ID">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="instance-info">
                            <span class="info-label">Criada em:</span>
                            <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($instancia['created_at'])); ?></span>
                        </div>
                    </div>
                    <div class="instance-footer">
                        <a href="/admin/admin_instancia.php?instance_id=<?php echo urlencode($instancia['instance_id']); ?>" class="btn-manage">
                            Gerenciar
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 12h14"></path>
                                <path d="m12 5 7 7-7 7"></path>
                            </svg>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <div class="empty-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="16"></line>
                <line x1="8" y1="12" x2="16" y2="12"></line>
            </svg>
        </div>
        <h2>Nenhuma instância encontrada</h2>
        <p>Comece criando sua primeira instância do WhatsApp</p>
        <a href="criar_instancia.php" class="btn-primary">
            Criar Instância
        </a>
    </div>
    <?php endif; ?>
</div>
<link rel="stylesheet" href="/assets/css/dashboard.css">
<script src="/assets/js/dashboard.js"></script>

<?php include 'footer.php'; ?>