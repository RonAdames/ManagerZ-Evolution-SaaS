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

// Obter parâmetros
$instance_name = isset($_POST['instance_name']) ? Security::sanitizeInput($_POST['instance_name']) : '';
$instance_id = isset($_POST['instance_id']) ? Security::sanitizeInput($_POST['instance_id']) : '';

if (empty($instance_name) || empty($instance_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Parâmetros inválidos'
    ]);
    exit;
}

// Verificar se a instância pertence ao usuário
try {
    $stmt = $pdo->prepare("
        SELECT * FROM instancias 
        WHERE instance_name = :instance_name AND instance_id = :instance_id AND user_id = :user_id
    ");
    $stmt->execute([
        'instance_name' => $instance_name,
        'instance_id' => $instance_id,
        'user_id' => Session::get('user_id')
    ]);
    
    $instance = $stmt->fetch();
    if (!$instance) {
        echo json_encode([
            'success' => false,
            'message' => 'Instância não encontrada ou sem permissão'
        ]);
        exit;
    }
    
    $current_status = $instance['status'];
    
} catch (PDOException $e) {
    $logger->error("Erro ao verificar instância: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao verificar instância'
    ]);
    exit;
}

// Tentar verificar o status da conexão
try {
    $api = new Api();
    $connectionState = $api->getConnectionState($instance_name);
    
    $new_status = $current_status;
    
    // Atualiza o status com base na resposta da API
    if (isset($connectionState['instance']['state'])) {
        if ($connectionState['instance']['state'] === 'open') {
            $new_status = 'connected';
        } elseif ($connectionState['instance']['state'] === 'connecting') {
            $new_status = 'connecting';
        } else {
            $new_status = 'disconnected';
        }
    }
    
    // Atualiza o banco de dados se o status mudou
    if ($new_status !== $current_status) {
        $stmt = $pdo->prepare("
            UPDATE instancias 
            SET status = :status 
            WHERE instance_id = :instance_id
        ");
        $stmt->execute([
            'status' => $new_status,
            'instance_id' => $instance_id
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'status' => $new_status
    ]);
    
} catch (Exception $e) {
    $logger->error("Erro ao verificar estado da conexão: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao verificar estado da conexão',
        'status' => $current_status
    ]);
}