<?php
require_once 'config.php';
require_once __DIR__ . '/../src/Session.php';

// Inicializar sessão de forma segura
Session::start();

// Registra o logout no log se o usuário estava autenticado
if (Session::isAuthenticated()) {
    $username = Session::get('username', 'Desconhecido');
    $user_id = Session::get('user_id', 0);
    
    if (isset($logger)) {
        $logger->info("Logout do usuário: {$username} (ID: {$user_id})");
    }
}

// Destrói a sessão de forma segura
Session::destroy();

// Redireciona para a página de login
header("Location: index.php?logout=1");
exit;