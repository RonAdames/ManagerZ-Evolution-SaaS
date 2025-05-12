<?php
require_once '../config.php';
require_once __DIR__ . '/../../src/Session.php';
require_once __DIR__ . '/../../src/Security.php';
require_once __DIR__ . '/../../src/Api.php';

// Iniciar sessão de forma segura
Session::start();

// Verificar autenticação
if (!Session::isAuthenticated()) {
    echo json_encode([
        'success' => false,
        'message' => 'Não autorizado'
    ]);
    exit;
}

// Verificar CSRF
if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Token inválido'
    ]);
    exit;
}

// Obter nome da instância
$instance_name = isset($_POST['instance_name']) ? Security::sanitizeInput($_POST['instance_name']) : '';

if (empty($instance_name)) {
    echo json_encode([
        'success' => false,
        'message' => 'Nome da instância não informado'
    ]);
    exit;
}

// Verificar se a instância pertence ao usuário
try {
    $stmt = $pdo->prepare("
        SELECT * FROM instancias 
        WHERE instance_name = :instance_name AND user_id = :user_id
    ");
    $stmt->execute([
        'instance_name' => $instance_name,
        'user_id' => Session::get('user_id')
    ]);
    
    if (!$stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Instância não encontrada ou sem permissão'
        ]);
        exit;
    }
} catch (PDOException $e) {
    $logger->error("Erro ao verificar instância: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao verificar instância'
    ]);
    exit;
}

// Tenta desconectar a instância através da API
try {
    $api = new Api();
    $response = $api->logoutInstance($instance_name);
    
    // Verifica resposta da API
    $success = true;
    $message = 'Instância desconectada com sucesso';
    
    if (isset($response['error']) && $response['error'] === true) {
        $logger->warning("API retornou erro ao desconectar: " . ($response['message'] ?? 'Erro desconhecido'));
        $success = false;
        $message = $response['message'] ?? 'Erro ao desconectar instância';
    }
    
    // Atualiza o status no banco de dados, mesmo se houver erro na API
    $stmt = $pdo->prepare("
        UPDATE instancias 
        SET status = 'disconnected' 
        WHERE instance_name = :instance_name AND user_id = :user_id
    ");
    $stmt->execute([
        'instance_name' => $instance_name,
        'user_id' => Session::get('user_id')
    ]);
    
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    
} catch (Exception $e) {
    $logger->error("Erro ao desconectar instância: " . $e->getMessage());
    
    // Mesmo com erro na API, tenta atualizar o banco
    try {
        $stmt = $pdo->prepare("
            UPDATE instancias 
            SET status = 'disconnected' 
            WHERE instance_name = :instance_name AND user_id = :user_id
        ");
        $stmt->execute([
            'instance_name' => $instance_name,
            'user_id' => Session::get('user_id')
        ]);
    } catch (PDOException $e2) {
        $logger->error("Erro ao atualizar status da instância: " . $e2->getMessage());
    }
    
    echo json_encode([
        'success' => true, // Retornamos true mesmo com erro, para permitir continuidade da operação
        'message' => 'A instância foi marcada como desconectada no sistema'
    ]);
}