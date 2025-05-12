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
    
} catch (PDOException $e) {
    $logger->error("Erro ao verificar instância: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao verificar instância'
    ]);
    exit;
}

// Buscar configurações da API
try {
    $api = new Api();
    $logger->info("Sincronizando configurações da API para a instância {$instance_name}");
    $apiSettings = $api->get("settings/find/{$instance_name}");
    
    if (isset($apiSettings) && !isset($apiSettings['error'])) {
        $logger->info("Configurações obtidas da API: " . json_encode($apiSettings));
        
        // Converter valores booleanos da API para inteiros (0/1) para o banco
        $db_reject_call = isset($apiSettings['rejectCall']) && $apiSettings['rejectCall'] === true ? 1 : 0;
        $db_msg_call = $apiSettings['msgCall'] ?? '';
        $db_groups_ignore = isset($apiSettings['groupsIgnore']) && $apiSettings['groupsIgnore'] === true ? 1 : 0;
        $db_always_online = isset($apiSettings['alwaysOnline']) && $apiSettings['alwaysOnline'] === true ? 1 : 0;
        $db_read_messages = isset($apiSettings['readMessages']) && $apiSettings['readMessages'] === true ? 1 : 0;
        $db_read_status = isset($apiSettings['readStatus']) && $apiSettings['readStatus'] === true ? 1 : 0;
        $db_sync_full_history = isset($apiSettings['syncFullHistory']) && $apiSettings['syncFullHistory'] === true ? 1 : 0;
        
        // Verificar se há diferenças
        $changed = (
            $db_reject_call != $instance['reject_call'] ||
            $db_msg_call != $instance['msg_call'] ||
            $db_groups_ignore != $instance['groups_ignore'] ||
            $db_always_online != $instance['always_online'] ||
            $db_read_messages != $instance['read_messages'] ||
            $db_read_status != $instance['read_status'] ||
            $db_sync_full_history != $instance['sync_full_history']
        );
        
        if ($changed) {
            $logger->info("Configurações alteradas na API, atualizando no banco de dados");
            
            // Atualizar no banco de dados
            $stmt = $pdo->prepare("
                UPDATE instancias SET 
                    reject_call = :reject_call,
                    msg_call = :msg_call,
                    groups_ignore = :groups_ignore,
                    always_online = :always_online,
                    read_messages = :read_messages,
                    read_status = :read_status,
                    sync_full_history = :sync_full_history
                WHERE instance_id = :instance_id
            ");

            $stmt->execute([
                'reject_call' => $db_reject_call,
                'msg_call' => $db_msg_call,
                'groups_ignore' => $db_groups_ignore,
                'always_online' => $db_always_online,
                'read_messages' => $db_read_messages,
                'read_status' => $db_read_status,
                'sync_full_history' => $db_sync_full_history,
                'instance_id' => $instance_id
            ]);
            
            echo json_encode([
                'success' => true,
                'changed' => true,
                'message' => 'Configurações sincronizadas com sucesso',
                'settings' => [
                    'reject_call' => $db_reject_call,
                    'msg_call' => $db_msg_call,
                    'groups_ignore' => $db_groups_ignore,
                    'always_online' => $db_always_online,
                    'read_messages' => $db_read_messages,
                    'read_status' => $db_read_status,
                    'sync_full_history' => $db_sync_full_history
                ]
            ]);
        } else {
            $logger->info("Configurações já estão sincronizadas");
            echo json_encode([
                'success' => true,
                'changed' => false,
                'message' => 'Configurações já estão sincronizadas'
            ]);
        }
    } else {
        $logger->warning("Não foi possível obter configurações válidas da API. Resposta: " . json_encode($apiSettings));
        echo json_encode([
            'success' => false,
            'message' => 'Não foi possível obter configurações da API',
            'api_response' => $apiSettings
        ]);
    }
} catch (Exception $e) {
    $logger->error("Erro ao sincronizar configurações: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao sincronizar configurações: ' . $e->getMessage()
    ]);
}