<?php
require_once '../config.php';
require_once __DIR__ . '/../../src/Session.php';

// Iniciar sessão segura
Session::start();

// Verificar se o usuário está logado
if (!Session::isAuthenticated()) {
    header('Location: ../index.php?error=Você precisa estar logado para acessar esta página');
    exit;
}

// Buscar dados do usuário
try {
    $stmt = $pdo->prepare("SELECT skill FROM users WHERE id = ?");
    $stmt->execute([Session::get('user_id')]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    header('Location: ../index.php?error=Erro ao verificar permissões');
    exit;
}

$title = "Lista de Tutoriais";
$stmt = $pdo->prepare("SELECT * FROM tutorials ORDER BY created_at DESC");
$stmt->execute();
$tutorials = $stmt->fetchAll();

// Adicionar CSS específico para tutoriais
$additional_css = ['../assets/css/tutorials.css'];

// Remover HTML duplicado e usar apenas o base.php
include '../base.php';
?>

<div class="container">
    <div class="content-wrapper">
        <h1>Lista de Tutoriais</h1>
        <?php if ($user && $user['skill'] == 2): ?>
        <div class="action-buttons">
            <a href="add_tutorial.php">
                <button type="button" class="btn-primary">Adicionar Novo Tutorial</button>
            </a>
        </div>
        <?php endif; ?>
        
        <?php if ($tutorials): ?>
            <div class="table-container">
                <table>
                    <tr>
                        <th>Título</th>
                        <th>Descrição</th>
                        <th>URL do Vídeo</th>
                        <?php if ($user && $user['skill'] == 2): ?>
                            <th>Ações</th>
                        <?php endif; ?>
                    </tr>
                    <?php foreach ($tutorials as $tutorial): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($tutorial['title']); ?></td>
                            <td><?php echo htmlspecialchars($tutorial['description']); ?></td>
                            <td>
                                <a href="<?php echo htmlspecialchars($tutorial['video_url']); ?>" target="_blank">
                                    <button type="button" class="btn-primary">
                                        Assistir
                                    </button>
                                </a>
                            </td>
                            <?php if ($user && $user['skill'] == 2): ?>
                                <td>
                                    <a href="edit_tutorial.php?id=<?php echo $tutorial['id']; ?>">Editar</a> | 
                                    <a href="delete_tutorial.php?id=<?php echo $tutorial['id']; ?>" onclick="return confirm('Tem certeza que deseja deletar este tutorial?');">Excluir</a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php else: ?>
            <p>Nenhum tutorial encontrado.</p>
        <?php endif; ?>
    </div>
</div>
