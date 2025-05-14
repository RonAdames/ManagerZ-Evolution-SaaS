<?php
require_once '../config.php';
require_once __DIR__ . '/../../src/Session.php';
require_once __DIR__ . '/../../src/Security.php';
require_once __DIR__ . '/../../src/Api.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

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
        // Se a API retornar o QR Code em base64, usamos diretamente
        $base64 = $response['base64'];
        
        // Converter para preto e branco
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
        $image = imagecreatefromstring($imageData);
        
        if ($image !== false) {
            // Aplicar filtro preto e branco
            imagefilter($image, IMG_FILTER_GRAYSCALE);
            imagefilter($image, IMG_FILTER_CONTRAST, -100);
            
            // Capturar a saída em buffer
            ob_start();
            imagepng($image);
            $imageData = ob_get_clean();
            
            // Converter de volta para base64
            $base64 = 'data:image/png;base64,' . base64_encode($imageData);
            
            // Liberar memória
            imagedestroy($image);
        }
    } elseif (isset($response['code'])) {
        // Se a API retornar apenas o código, geramos o QR Code
        // Configurar opções do QR Code
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => QRCode::ECC_L,
            'scale' => 10,
            'imageBase64' => true,
            'moduleValues' => [
                // Módulos escuros (QR Code)
                1536 => '#000000', // QR_FINDER_DARK
                6    => '#000000', // QR_FINDER_DOT
                5632 => '#000000', // QR_ALIGNMENT_DARK
                2560 => '#000000', // QR_TIMING_DARK
                3072 => '#000000', // QR_FORMAT_DARK
                3584 => '#000000', // QR_VERSION_DARK
                4096 => '#000000', // QR_DATA_DARK
                1024 => '#000000', // QR_QUIETZONE
                // Módulos claros (fundo)
                512  => '#FFFFFF', // QR_FINDER_LIGHT
                8    => '#FFFFFF', // QR_FINDER_DOT_LIGHT
                5888 => '#FFFFFF', // QR_ALIGNMENT_LIGHT
                2816 => '#FFFFFF', // QR_TIMING_LIGHT
                3328 => '#FFFFFF', // QR_FORMAT_LIGHT
                3840 => '#FFFFFF', // QR_VERSION_LIGHT
                4608 => '#FFFFFF', // QR_DATA_LIGHT
                2048 => '#FFFFFF', // QR_QUIETZONE_LIGHT
            ],
        ]);
        
        // Gerar QR Code
        $qrcode = new QRCode($options);
        $base64 = $qrcode->render($response['code']);
    } else {
        throw new Exception('QR Code não disponível na resposta da API');
    }
    
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
        'qrcode' => $base64,
        'message' => 'QR Code gerado com sucesso'
    ]);
} catch (Exception $e) {
    $logger->error("Erro ao obter QR code: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao obter QR Code: ' . $e->getMessage()
    ]);
}