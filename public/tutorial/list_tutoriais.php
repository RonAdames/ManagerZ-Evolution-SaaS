<?php
require_once '../config.php';
$title = "Lista de Tutoriais";
$stmt = $pdo->prepare("SELECT * FROM tutorials ORDER BY created_at DESC");
$stmt->execute();
$tutorials = $stmt->fetchAll();
include '../base.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Lista de Tutoriais</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <h1>Lista de Tutoriais</h1>
    <?php if ($user['skill'] == 2): ?>
    <a href="add_tutorial.php" class="btn-primary">Adicionar Novo Tutorial</a><br><br><br>
    <?php endif; ?>
    <?php if ($tutorials): ?>
        <table>
            <tr>
                <th>Título</th>
                <th>Descrição</th>
                <th>URL do Vídeo</th>
                <?php if ($user['skill'] == 2): ?>
                    <th>Ações</th>
                <?php endif; ?>

            </tr>
            <?php foreach ($tutorials as $tutorial): ?>
                <tr>
                    <td><?php echo htmlspecialchars($tutorial['title']); ?></td>
                    <td><?php echo htmlspecialchars($tutorial['description']); ?></td>
                    <td>
                        <a href="<?php echo htmlspecialchars($tutorial['video_url']); ?>" target="_blank">
                            <button type="submit" class="btn-primary">
                                Assistir
                            </button>
                        </a>
                    </td>

                    <?php if ($user['skill'] == 2): ?>
                        <td>
                            <a href="edit_tutorial.php?id=<?php echo $tutorial['id']; ?>">Editar</a> | 
                            <a href="delete_tutorial.php?id=<?php echo $tutorial['id']; ?>" onclick="return confirm('Tem certeza que deseja deletar este tutorial?');">Excluir</a>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>Nenhum tutorial encontrado.</p>
    <?php endif; ?>
</body>
</html>
