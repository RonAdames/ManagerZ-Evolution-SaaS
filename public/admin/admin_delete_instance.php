<?php
require_once '../config.php';
require_once __DIR__ . '/../../src/Session.php';
require_once __DIR__ . '/../../src/Security.php';
require_once __DIR__ . '/../../src/Api.php';

// Iniciar sessão segura
Session::start();

// Verificar autenticação
if (!Session::isAuthenticated()) {
    header("Location: ../index.php");
    exit;
}

// Verificar se é administrador (opcional, se quiser restringir essa função apenas a admins)
// Se você quiser permitir que usuários normais também possam excluir suas próprias instâncias, remova ou comente este bloco
/*
if (!Session::isAdmin()) {
    header("Location: ../dashboard.php?error=" . urlencode("Acesso negado"));
    exit;
}
*/

// Verificar CSRF token
if (!isset($_GET['csrf_token']) || !Security::validateCsrfToken($_GET['csrf_token'])) {
    header("Location: ../dashboard.php?error=" . urlencode("Erro de segurança"));
    exit;
}

// Obter e validar parâmetros
$instance_name = isset($_GET['instance_name']) ? Security::sanitizeInput($_GET['instance_name']) : '';
$instance_id = isset($_GET['instance_id']) ? Security::sanitizeInput($_GET['instance_id']) : '';

if (empty($instance_name) || empty($instance_id)) {
    header("Location: ../dashboard.php?error=" . urlencode("Parâmetros inválidos"));
    exit;
}

// Verificar se a instância existe e pertence ao usuário
try {
    $stmt = $pdo->prepare("
        SELECT * FROM instancias 
        WHERE instance_name = :instance_name 
        AND instance_id = :instance_id 
        AND user_id = :user_id
    ");
    $stmt->execute([
        'instance_name' => $instance_name,
        'instance_id' => $instance_id,
        'user_id' => Session::get('user_id')
    ]);
    
    $instance = $stmt->fetch();
    if (!$instance) {
        header("Location: ../dashboard.php?error=" . urlencode("Instância não encontrada ou sem permissão"));
        exit;
    }
    
} catch (PDOException $e) {
    $logger->error("Erro ao verificar instância: " . $e->getMessage());
    header("Location: ../dashboard.php?error=" . urlencode("Erro ao verificar instância"));
    exit;
}

// Tentar excluir a instância via API
try {
    $api = new Api();
    $response = $api->deleteInstance($instance_name);
    
    // Log da resposta da API para diagnóstico
    $logger->info("Resposta da API ao excluir instância {$instance_name}: " . json_encode($response));
    
    // Independentemente da resposta da API, vamos excluir do banco de dados
    // Isso permite que mesmo que a API falhe, o usuário possa limpar suas instâncias
    $deleteSuccess = false;
    
    try {
        // Exclusão da instância do banco de dados
        $stmt = $pdo->prepare("DELETE FROM instancias WHERE instance_id = :instance_id AND user_id = :user_id");
        $stmt->execute([
            'instance_id' => $instance_id,
            'user_id' => Session::get('user_id')
        ]);
        
        $deleteSuccess = true;
    } catch (PDOException $e) {
        $logger->error("Erro ao excluir instância do banco: " . $e->getMessage());
        // Continua a execução para tentar responder ao usuário
    }
    
    // Responder com base no resultado
    if ($deleteSuccess) {
        // Redireciona para o dashboard com mensagem de sucesso
        header("Location: ../dashboard.php?success=" . urlencode("Instância removida com sucesso!"));
    } else {
        // Redireciona com mensagem de erro
        header("Location: ../dashboard.php?error=" . urlencode("Falha ao remover a instância do banco de dados"));
    }
    
} catch (Exception $e) {
    $logger->error("Erro ao excluir instância via API: " . $e->getMessage());
    
    // Mesmo com erro na API, tenta excluir do banco de dados
    try {
        $stmt = $pdo->prepare("DELETE FROM instancias WHERE instance_id = :instance_id AND user_id = :user_id");
        $stmt->execute([
            'instance_id' => $instance_id,
            'user_id' => Session::get('user_id')
        ]);
        
        // Redireciona com aviso
        header("Location: ../dashboard.php?warning=" . urlencode("Instância removida do banco de dados, mas pode ter ocorrido um erro na API"));
    } catch (PDOException $e2) {
        $logger->error("Erro ao excluir instância do banco: " . $e2->getMessage());
        header("Location: ../dashboard.php?error=" . urlencode("Falha ao excluir a instância"));
    }
    
    exit;
}