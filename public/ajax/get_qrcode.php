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

// Inicializar a API
try {
    $api = new Api();
    
    // Na função JavaScript, já fizemos a desconexão antes de chamar este endpoint
    // Agora, tentamos obter um novo QR Code
    $response = $api->connectInstance($instance_name);
    
    if (isset($response['base64'])) {
        // Atualiza o status no banco de dados para "connecting"
        $stmt = $pdo->prepare("
            UPDATE instancias 
            SET status = 'connecting' 
            WHERE instance_name = :instance_name AND user_id = :user_id
        ");
        $stmt->execute([
            'instance_name' => $instance_name,
            'user_id' => Session::get('user_id')
        ]);
        
        echo json_encode([
            'success' => true,
            'qrcode' => $response['base64'],
            'message' => 'QR Code gerado com sucesso'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'QR Code não disponível. Tente novamente em alguns instantes.'
        ]);
    }
} catch (Exception $e) {
    $logger->error("Erro ao obter QR code: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao obter QR Code: ' . $e->getMessage()
    ]);
}