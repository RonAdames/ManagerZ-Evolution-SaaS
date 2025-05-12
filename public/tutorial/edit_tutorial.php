<?php
require_once '../config.php';

$title = "Edit Tutoriais";
$error_message = '';
$success_message = '';

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $video_url = $_POST['video_url'];

        $stmt = $pdo->prepare("UPDATE tutorials SET title = :title, description = :description, video_url = :video_url WHERE id = :id");
        $stmt->execute([
            'title' => $title,
            'description' => $description,
            'video_url' => $video_url,
            'id' => $id
        ]);

        $success_message = "Tutorial atualizado com sucesso!";
    }

    $stmt = $pdo->prepare("SELECT * FROM tutorials WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $tutorial = $stmt->fetch();

    if (!$tutorial) {
        die("Tutorial não encontrado.");
    }
} else {
    die("ID de tutorial inválido.");
}
include '../base.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Editar Tutorial</h1>

    <?php if ($success_message): ?>
        <p class="success"><?php echo htmlspecialchars($success_message); ?></p>
    <?php elseif ($error_message): ?>
        <p class="error"><?php echo htmlspecialchars($error_message); ?></p>
    <?php endif; ?>

    <form action="edit_tutorial.php?id=<?php echo $id; ?>" method="POST">
        <label for="title">Título:</label>
        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($tutorial['title']); ?>" required>

        <label for="description">Descrição:</label>
        <textarea id="description" name="description"><?php echo htmlspecialchars($tutorial['description']); ?></textarea>

        <label for="video_url">URL do Vídeo:</label>
        <input type="url" id="video_url" name="video_url" value="<?php echo htmlspecialchars($tutorial['video_url']); ?>" required>

        <button type="submit">Salvar Alterações</button>
    </form>

    <a href="list_tutoriais.php">Voltar para Lista de Tutoriais</a>
</body>
</html>
