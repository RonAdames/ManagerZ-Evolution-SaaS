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

// Verificar se o ID foi fornecido
if (!isset($_GET['id'])) {
    header('Location: list_tutoriais.php?error=ID do tutorial não fornecido');
    exit;
}

$tutorial_id = $_GET['id'];

try {
    // Verificar se o tutorial existe
    $stmt = $pdo->prepare("SELECT id FROM tutorials WHERE id = ?");
    $stmt->execute([$tutorial_id]);
    
    if (!$stmt->fetch()) {
        header('Location: list_tutoriais.php?error=Tutorial não encontrado');
        exit;
    }
    
    // Deletar o tutorial
    $stmt = $pdo->prepare("DELETE FROM tutorials WHERE id = ?");
    $stmt->execute([$tutorial_id]);
    
    header('Location: list_tutoriais.php?success=Tutorial excluído com sucesso');
    exit;
} catch (PDOException $e) {
    header('Location: list_tutoriais.php?error=Erro ao excluir tutorial');
    exit;
}
?>
