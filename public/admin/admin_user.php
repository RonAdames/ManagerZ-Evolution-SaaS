<?php
$title = "Administração do Usuário";
require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_GET['user_id'];
$success_message = isset($_GET['success']) ? $_GET['success'] : '';
$error_message = isset($_GET['error']) ? $_GET['error'] : '';

// Lógica para atualizar o usuário
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_max'])) {
    $max_instancias = $_POST['max_instancias'];
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET max_instancias = :max_instancias WHERE id = :user_id");
        $stmt->execute(['max_instancias' => $max_instancias, 'user_id' => $user_id]);
        $success_message = "Configurações atualizadas com sucesso!";
    } catch (PDOException $e) {
        $error_message = "Erro ao atualizar as configurações.";
    }
}

// Busca informações do usuário
$stmt = $pdo->prepare("
    SELECT 
        u.*,
        COUNT(i.id) as total_instances,
        SUM(CASE WHEN i.status = 'connected' THEN 1 ELSE 0 END) as active_instances
    FROM users u
    LEFT JOIN instancias i ON u.id = i.user_id
    WHERE u.id = :user_id
    GROUP BY u.id
");
$stmt->execute(['user_id' => $user_id]);
$usuario = $stmt->fetch();

if (!$usuario) {
    header("Location: admin.php?error=" . urlencode("Usuário não encontrado"));
    exit;
}

// Busca instâncias do usuário
$stmt = $pdo->prepare("SELECT * FROM instancias WHERE user_id = :user_id ORDER BY created_at DESC");
$stmt->execute(['user_id' => $user_id]);
$instancias = $stmt->fetchAll();

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
        <span class="breadcrumb-current">Gerenciar Usuário</span>
    </div>

    <!-- Header com informações do usuário -->
    <div class="user-header">
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($usuario['username'], 0, 2)); ?>
            </div>
            <div class="user-details">
                <h1><?php echo htmlspecialchars($usuario['username']); ?></h1>
                <span class="user-role"><?php echo $usuario['skill'] == 2 ? 'Administrador' : 'Usuário'; ?></span>
            </div>
        </div>
        <a href="admin.php" class="btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Voltar
        </a>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
            <div class="alert-content">
                <h4>Sucesso!</h4>
                <p><?php echo $success_message; ?></p>
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
                <p><?php echo $error_message; ?></p>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid-layout">
        <!-- Configurações do Usuário -->
        <div class="card">
            <div class="card-header">
                <h2>Configurações</h2>
            </div>
            <div class="card-body">
                <form action="admin_user.php?user_id=<?php echo urlencode($user_id); ?>" method="POST" class="settings-form">
                    <div class="form-group">
                        <label for="max_instancias">Limite de Instâncias</label>
                        <div class="input-with-info">
                            <input type="number" 
                                   id="max_instancias" 
                                   name="max_instancias" 
                                   value="<?php echo htmlspecialchars($usuario['max_instancias']); ?>" 
                                   min="1">
                            <span class="input-info">
                                Em uso: <?php echo $usuario['total_instances']; ?>/<?php echo $usuario['max_instancias']; ?>
                            </span>
                        </div>
                    </div>
                    <button type="submit" name="update_max" class="btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                            <polyline points="7 3 7 8 15 8"></polyline>
                        </svg>
                        Salvar Alterações
                    </button>
                </form>
            </div>
        </div>

        <!-- Estatísticas -->
        <div class="card">
            <div class="card-header">
                <h2>Visão Geral</h2>
            </div>
            <div class="card-body">
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-label">Total de Instâncias</span>
                        <span class="stat-value"><?php echo $usuario['total_instances']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Instâncias Ativas</span>
                        <span class="stat-value success"><?php echo $usuario['active_instances']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Utilização</span>
                        <span class="stat-value">
                            <?php echo round(($usuario['total_instances'] / $usuario['max_instancias']) * 100); ?>%
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Instâncias -->
    <div class="card instances-card">
        <div class="card-header">
            <h2>Instâncias</h2>
            <div class="card-actions">
                <div class="search-box">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" id="searchInstances" placeholder="Buscar instância..." onkeyup="filterInstances()">
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="instancesTable">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>ID da Instância</th>
                            <th>Status</th>
                            <th>Criada em</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($instancias as $instancia): ?>
                            <tr>
                                <td>
                                    <div class="instance-name">
                                        <div class="instance-icon">
                                            <?php echo strtoupper(substr($instancia['instance_name'], 0, 2)); ?>
                                        </div>
                                        <span><?php echo htmlspecialchars($instancia['instance_name']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="instance-id">
                                        <?php echo htmlspecialchars($instancia['instance_id']); ?>
                                        <button onclick="copyToClipboard('<?php echo htmlspecialchars($instancia['instance_id']); ?>')" class="btn-copy" title="Copiar ID">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $instancia['status']; ?>">
                                        <?php echo $instancia['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y H:i', strtotime($instancia['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="admin_instancia.php?instance_id=<?php echo urlencode($instancia['instance_id']); ?>" class="btn-icon" title="Gerenciar">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 20h9"></path>
                                                <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                                            </svg>
                                        </a>
                                        <button onclick="confirmDeleteInstance('<?php echo htmlspecialchars($instancia['instance_name']); ?>', '<?php echo htmlspecialchars($instancia['instance_name']); ?>', '<?php echo $user_id; ?>')" class="btn-icon delete" title="Remover">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M3 6h18"></path>
                                                <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                                                <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                                            </svg>
                                        </button>



                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="/assets/css/admin_user.css">
<script src="/assets/js/admin_user.js"></script>

<script>
    const userId = <?php echo json_encode($user_id); ?>;
</script>