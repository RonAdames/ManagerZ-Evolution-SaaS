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

// Verificar se o usuário tem permissão (skill 2)
try {
    $stmt = $pdo->prepare("SELECT skill FROM users WHERE id = ?");
    $stmt->execute([Session::get('user_id')]);
    $user = $stmt->fetch();
    
    if (!$user || $user['skill'] != 2) {
        header('Location: list_tutoriais.php?error=Acesso negado');
        exit;
    }
} catch (PDOException $e) {
    header('Location: list_tutoriais.php?error=Erro ao verificar permissões');
    exit;
}

$title = "Editar Tutorial";

// Verificar se o ID foi fornecido
if (!isset($_GET['id'])) {
    header('Location: list_tutoriais.php?error=ID do tutorial não fornecido');
    exit;
}

$tutorial_id = $_GET['id'];

// Buscar dados do tutorial
try {
    $stmt = $pdo->prepare("SELECT * FROM tutorials WHERE id = ?");
    $stmt->execute([$tutorial_id]);
    $tutorial = $stmt->fetch();
    
    if (!$tutorial) {
        header('Location: list_tutoriais.php?error=Tutorial não encontrado');
        exit;
    }
} catch (PDOException $e) {
    header('Location: list_tutoriais.php?error=Erro ao buscar tutorial');
    exit;
}

// Processar o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $video_url = trim($_POST['video_url']);
    
    if (empty($title) || empty($description) || empty($video_url)) {
        Session::setFlash('error', 'Todos os campos são obrigatórios');
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE tutorials SET title = ?, description = ?, video_url = ? WHERE id = ?");
            $stmt->execute([$title, $description, $video_url, $tutorial_id]);
            
            header('Location: list_tutoriais.php?success=Tutorial atualizado com sucesso');
            exit;
        } catch (PDOException $e) {
            Session::setFlash('error', 'Erro ao atualizar tutorial: ' . $e->getMessage());
        }
    }
}

// Adicionar CSS específico para tutoriais
$additional_css = ['../assets/css/tutorials.css'];

include '../base.php';
?>

<div class="container">
    <div class="content-wrapper">
        <h1>Editar Tutorial</h1>
        
        <form method="POST" class="form">
            <div class="form-group">
                <label for="title">Título:</label>
                <input type="text" id="title" name="title" class="form-control" required value="<?php echo htmlspecialchars($tutorial['title']); ?>">
            </div>
            
            <div class="form-group">
                <label for="description">Descrição:</label>
                <input type="text" id="description" name="description" class="form-control" required value="<?php echo htmlspecialchars($tutorial['description']); ?>">
            </div>
            
            <div class="form-group">
                <label for="video_url">URL do Vídeo:</label>
                <input type="text" id="video_url" name="video_url" class="form-control" required value="<?php echo htmlspecialchars($tutorial['video_url']); ?>">
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-primary">Atualizar Tutorial</button>
                <a href="list_tutoriais.php">
                    <button type="button" class="btn-secondary">Cancelar</button>
                </a>
            </div>
        </form>
    </div>
</div>
