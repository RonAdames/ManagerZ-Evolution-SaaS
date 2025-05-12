<?php
$title = "Painel Administrativo";
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

// Verificar se o usuário é um ADMIN
if (!Session::isAdmin()) {
    Session::setFlash('error', 'Acesso negado. Você não tem permissão para acessar esta área.');
    header("Location: ../dashboard.php");
    exit;
}

// Para depuração temporária, se necessário
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

// Busca estatísticas gerais
try {
    $stats = [
        'total_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE active = 1")->fetchColumn(),
        'total_instances' => $pdo->query("SELECT COUNT(*) FROM instancias")->fetchColumn(),
        'active_instances' => $pdo->query("SELECT COUNT(*) FROM instancias WHERE status = 'connected'")->fetchColumn()
    ];
} catch (PDOException $e) {
    $logger->error("Erro ao buscar estatísticas: " . $e->getMessage());
    $stats = ['total_users' => 0, 'total_instances' => 0, 'active_instances' => 0];
}

// Busca todos os usuários com suas instâncias
try {
    $stmt = $pdo->query("
        SELECT 
            u.*,
            COUNT(i.id) as total_instances,
            SUM(CASE WHEN i.status = 'connected' THEN 1 ELSE 0 END) as active_instances,
            MAX(CASE WHEN i.status = 'connected' THEN 1 ELSE 0 END) as has_active_instance
        FROM users u
        LEFT JOIN instancias i ON u.id = i.user_id
        WHERE u.active = 1
        GROUP BY u.id
        ORDER BY u.id DESC
    ");
    $usuarios = $stmt->fetchAll();
} catch (PDOException $e) {
    $logger->error("Erro ao buscar usuários: " . $e->getMessage());
    $usuarios = [];
}

// Gerar token CSRF para formulários de ação
$csrf_token = Security::generateCsrfToken();

include '../base.php';
?>

<div class="admin-wrapper">
    <!-- Cabeçalho da página -->
    <div class="admin-header">
        <div class="header-content">
            <h1>Painel Administrativo</h1>
            <p class="text-muted">Gerencie usuários e monitore o sistema</p>
        </div>
        <a href="admin_add_user.php" class="btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="8.5" cy="7" r="4"></circle>
                <line x1="20" y1="8" x2="20" y2="14"></line>
                <line x1="23" y1="11" x2="17" y2="11"></line>
            </svg>
            Adicionar Usuário
        </a>
    </div>

    <!-- Cards de estatísticas -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background-color: rgba(0, 180, 216, 0.1); color: var(--primary-color);">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?php echo $stats['total_users']; ?></span>
                <span class="stat-label">Usuários</span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background-color: rgba(16, 185, 129, 0.1); color: #10b981;">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
                    <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
                    <line x1="6" y1="6" x2="6.01" y2="6"></line>
                    <line x1="6" y1="18" x2="6.01" y2="18"></line>
                </svg>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?php echo $stats['total_instances']; ?></span>
                <span class="stat-label">Total de Instâncias</span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background-color: rgba(22, 163, 74, 0.1); color: #16a34a;">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?php echo $stats['active_instances']; ?></span>
                <span class="stat-label">Instâncias Ativas</span>
            </div>
        </div>
    </div>

    <!-- Tabela de usuários -->
    <div class="table-container">
        <div class="table-header">
            <h2>Usuários do Sistema</h2>
            <div class="table-actions">
                <div class="search-box">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" id="searchInput" placeholder="Buscar usuário..." onkeyup="filterTable()">
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table id="usersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuário</th>
                        <th>Limite de Instâncias</th>
                        <th>Instâncias Ativas</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td>#<?php echo str_pad($usuario['id'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($usuario['username'], 0, 2)); ?>
                                    </div>
                                    <div class="user-details">
                                        <span class="username" style="color: #000 !important;"><?php echo htmlspecialchars($usuario['username']); ?></span>
                                        <span class="user-role"><?php echo $usuario['skill'] == 2 ? 'Administrador' : 'Usuário'; ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="instance-limit">
                                    <div class="progress-bar">
                                        <?php 
                                        $max_value = max(1, $usuario['max_instancias']); // Previne divisão por zero
                                        $usage_percent = min(100, ($usuario['total_instances'] / $max_value) * 100);
                                        $color = $usage_percent >= 90 ? '#ef4444' : ($usage_percent >= 70 ? '#f59e0b' : '#10b981');
                                        ?>
                                        <div class="progress" style="width: <?php echo $usage_percent; ?>%; background-color: <?php echo $color; ?>"></div>
                                    </div>
                                    <span><?php echo $usuario['total_instances']; ?>/<?php echo $usuario['max_instancias']; ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?php echo $usuario['active_instances'] > 0 ? 'success' : 'neutral'; ?>">
                                    <?php echo $usuario['active_instances']; ?> ativas
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $usuario['has_active_instance'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $usuario['has_active_instance'] ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="admin_user.php?user_id=<?php echo urlencode($usuario['id']); ?>" class="btn-icon" title="Editar">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M12 20h9"></path>
                                            <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                                        </svg>
                                    </a>
                                    <?php if ($usuario['id'] != Session::get('user_id')): ?>
                                        <button 
                                            onclick="confirmDelete(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['username']); ?>', '<?php echo $csrf_token; ?>')" 
                                            class="btn-icon delete" 
                                            title="Remover">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M3 6h18"></path>
                                                <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                                                <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                                                <line x1="10" y1="11" x2="10" y2="17"></line>
                                                <line x1="14" y1="11" x2="14" y2="17"></line>
                                            </svg>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<link rel="stylesheet" href="/assets/css/admin.css">

<script>
// Função para filtrar a tabela
function filterTable() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toLowerCase();
    const table = document.getElementById('usersTable');
    const rows = table.getElementsByTagName('tr');

    for (let i = 1; i < rows.length; i++) {
        const username = rows[i].getElementsByClassName('username')[0];
        if (username) {
            const txtValue = username.textContent || username.innerText;
            if (txtValue.toLowerCase().indexOf(filter) > -1) {
                rows[i].style.display = '';
            } else {
                rows[i].style.display = 'none';
            }
        }
    }
}

// Função para confirmar exclusão
function confirmDelete(userId, username, csrfToken) {
    if (confirm(`Tem certeza que deseja excluir o usuário ${username}? Esta ação não pode ser desfeita.`)) {
        // Criar formulário dinamicamente para fazer uma requisição POST segura
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'admin_delete_user.php';
        form.style.display = 'none';
        
        // Adicionar ID do usuário
        const userIdInput = document.createElement('input');
        userIdInput.type = 'hidden';
        userIdInput.name = 'user_id';
        userIdInput.value = userId;
        form.appendChild(userIdInput);
        
        // Adicionar token CSRF
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = csrfToken;
        form.appendChild(csrfInput);
        
        // Adicionar ao documento e enviar
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../footer.php'; ?>