<?php
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

// Verificar se é administrador
if (!Session::isAdmin()) {
    Session::setFlash('error', 'Acesso negado. Você não tem permissão para executar esta ação.');
    header("Location: ../dashboard.php");
    exit;
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin.php");
    exit;
}

// Verificar CSRF token
if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
    Session::setFlash('error', 'Erro de segurança. Token inválido.');
    header("Location: admin.php");
    exit;
}

$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

// Validar ID do usuário
if ($user_id <= 0) {
    Session::setFlash('error', 'ID de usuário inválido.');
    header("Location: admin.php");
    exit;
}

// Não permitir excluir a si mesmo
if ($user_id == Session::get('user_id')) {
    Session::setFlash('error', 'Você não pode excluir seu próprio usuário.');
    header("Location: admin.php");
    exit;
}

try {
    // Buscar informações do usuário a ser excluído
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = :id");
    $stmt->execute(['id' => $user_id]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        Session::setFlash('error', 'Usuário não encontrado.');
        header("Location: admin.php");
        exit;
    }
    
    $username = $usuario['username'];
    
    // Iniciar uma transação para garantir que todas as alterações sejam feitas ou nenhuma
    $pdo->beginTransaction();
    
    // Em vez de excluir o usuário, apenas desativá-lo
    $stmt = $pdo->prepare("UPDATE users SET active = 0 WHERE id = :id");
    $stmt->execute(['id' => $user_id]);
    
    // Registrar no log
    $logger->info("Usuário '{$username}' (ID: {$user_id}) desativado pelo administrador " . Session::get('username'));
    
    // Commit da transação
    $pdo->commit();
    
    // Mensagem de sucesso
    Session::setFlash('success', "Usuário '{$username}' foi desativado com sucesso.");
    
} catch (PDOException $e) {
    // Em caso de erro, reverter todas as alterações
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $logger->error("Erro ao desativar usuário ID {$user_id}: " . $e->getMessage());
    Session::setFlash('error', 'Ocorreu um erro ao processar sua solicitação. Por favor, tente novamente.');
}

// Redirecionar de volta à listagem
header("Location: admin.php");
exit;