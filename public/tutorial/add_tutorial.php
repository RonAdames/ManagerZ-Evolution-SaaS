<?php
require_once '../config.php';
$error_message = '';
$success_message = '';
$title = "Adicionar Tutoriais";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $video_url = trim($_POST['video_url']);

    if (empty($title) || empty($video_url)) {
        $error_message = "O título e o link do vídeo são obrigatórios.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO tutorials (title, description, video_url) VALUES (:title, :description, :video_url)");
        $stmt->execute([
            'title' => $title,
            'description' => $description,
            'video_url' => $video_url
        ]);
        $success_message = "Tutorial adicionado com sucesso!";
    }
}
include '../base.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Adicionar Tutorial</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>

    <h1>Adicionar Tutorial</h1>
    <?php if ($user['skill'] == 2): ?>
    <?php if ($error_message): ?>
        <p class="error"><?php echo htmlspecialchars($error_message); ?></p>
    <?php elseif ($success_message): ?>
        <p class="success"><?php echo htmlspecialchars($success_message); ?></p>
    <?php endif; ?>
<br><br>
    <form action="add_tutorial.php" method="POST">
        <label for="title">Título:</label>
        <input type="text" id="title" name="title" required>

        <label for="description">Descrição:</label>
        <textarea id="description" name="description"></textarea>

        <label for="video_url">URL do Vídeo:</label>
        <input type="url" id="video_url" name="video_url" required>
            <br><br>
        <button type="submit" class="btn-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="16"></line>
                <line x1="8" y1="12" x2="16" y2="12"></line>
            </svg>
            Adicionar Tutorial
        </button>
    </form>
    <?php endif; ?>
    <a href="list_tutoriais.php" class="btn-primary">Ver Todos os Tutoriais</a>
    
</body>
</html>
