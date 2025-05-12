<?php
// Garantir que a sessão está iniciada
require_once __DIR__ . '/../src/Session.php';

// Iniciar sessão de forma segura
if (!class_exists('Session')) {
    // Fallback para o método antigo se a classe não existir
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
} else {
    Session::start();
}

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Buscar informações do usuário
try {
    // Recupera informações do usuário e quantidade de instâncias
    $stmt = $pdo->prepare("SELECT users.*, 
                                 COUNT(DISTINCT instancias.id) as total_instances,
                                 SUM(CASE WHEN instancias.status = 'connected' THEN 1 ELSE 0 END) as active_instances
                          FROM users 
                          LEFT JOIN instancias ON users.id = instancias.user_id 
                          WHERE users.id = :id AND users.active = 1
                          GROUP BY users.id");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Usuário não encontrado ou inativo
        session_destroy();
        header("Location: index.php?error=" . urlencode("Conta de usuário inválida ou inativa"));
        exit;
    }

} catch (PDOException $e) {
    // Log do erro
    if (isset($logger)) {
        $logger->error("Erro ao buscar informações do usuário: " . $e->getMessage());
    }
    
    // Valores padrão caso ocorra um erro
    $user = [
        'username' => 'Usuário',
        'skill' => 1,
        'total_instances' => 0,
        'active_instances' => 0
    ];
}

// Define o role do usuário
$userRole = isset($user['skill']) && $user['skill'] == 2 ? 'Administrador' : 'Usuário';

// Identifica a página atual
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar_container">
    <div class="sidebar_header">
        <!-- <img src="assets/logo.png" alt="Logo" class="sidebar_logo"> -->
        <h2 class="sidebar_title"><?php echo APP_NAME; ?></h2>
    </div>

    <div class="sidebar_userProfile">
        <div class="sidebar_avatar">
            <?php echo strtoupper(substr($user['first_name'] ?? 'U', 0, 2)); ?>
        </div>
        <div class="sidebar_userInfo">
            <span class="sidebar_username"><?php echo htmlspecialchars(mb_strimwidth($user['first_name'] ?? 'Usuário', 0, 15, '...')); ?></span>
            <span class="sidebar_role"><?php echo $userRole; ?></span>
        </div>
    </div>

    <nav class="sidebar_nav">
        <div class="sidebar_navSection">
            <span class="sidebar_navTitle">PRINCIPAL</span>
            <a href="/dashboard.php" class="sidebar_navLink <?php echo $current_page == 'dashboard.php' ? 'sidebar_active' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                <span class="sidebar_navText">Dashboard</span>
            </a>

            <a href="/criar_instancia.php" class="sidebar_navLink <?php echo $current_page == 'criar_instancia.php' ? 'sidebar_active' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="16"></line>
                    <line x1="8" y1="12" x2="16" y2="12"></line>
                </svg>
                <span class="sidebar_navText">Criar Instância</span>
            </a>
        </div>

        <div class="sidebar_navSection">
            <span class="sidebar_navTitle">SUPORTE</span>
                <a href="https://api.whatsapp.com/send?phone=5511989929199&text=Estou precisando de suporte no Painel <?php echo urlencode(APP_NAME); ?>." 
                target="_blank" 
                class="sidebar_navLink <?php echo $current_page == 'suporte.php' ? 'sidebar_active' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    <span class="sidebar_navText">Suporte</span>
                </a>
                <a href="/tutorial/list_tutoriais.php" 
                class="sidebar_navLink <?php echo $current_page == 'list_tutoriais.php' ? 'sidebar_active' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    <span class="sidebar_navText">Tutorial</span>
                </a>
        </div>

        <?php if (isset($user['skill']) && $user['skill'] == 2): ?>
        <div class="sidebar_navSection">
            <span class="sidebar_navTitle">ADMINISTRAÇÃO</span>
            <a href="/admin/admin.php" class="sidebar_navLink <?php echo $current_page == 'admin.php' ? 'sidebar_active' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 20h9"></path>
                    <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                </svg>
                <span class="sidebar_navText">Painel Admin</span>
            </a>
        </div>
        <?php endif; ?>
    </nav>

    <div class="sidebar_footer">
        <div class="sidebar_stats">
            <div class="sidebar_stat">
                <small class="sidebar_statLabel">Total de Instâncias</small>
                <span class="sidebar_statValue"><?php echo $user['total_instances'] ?? 0; ?></span>
            </div>
            <div class="sidebar_stat">
                <small class="sidebar_statLabel">Instâncias Ativas</small>
                <span class="sidebar_statValue sidebar_activeInstances"><?php echo $user['active_instances'] ?? 0; ?></span>
            </div>
        </div>
        <a href="/logout.php" class="sidebar_logoutBtn">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            <span class="sidebar_logoutText">Sair</span>
        </a>
    </div>

    <?php
    // Adiciona a versão do aplicativo no final do menu
    $appVersion = getenv('APP_VERSION');
    if ($appVersion): ?>
    <div class="app-version">
        <p>Versão: <?php echo htmlspecialchars($appVersion); ?></p><span>OPEN SOURCE</span>
    </div>
    <?php endif; ?>
</div>

<!-- Adicione esse CSS complementar ao seu style.css -->
<link rel="stylesheet" href="/assets/css/sidebar.css">
<script src="/assets/js/sidebar.js"></script>