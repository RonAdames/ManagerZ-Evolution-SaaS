<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <title><?php echo isset($title) ? htmlspecialchars($title) . ' - ' . APP_NAME : APP_NAME; ?></title>
    
    <!-- Prevenir armazenamento em cache para páginas que precisam de segurança -->
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <!-- CSS Geral -->
    <link rel="stylesheet" href="/assets/css/style.css">
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
    
    <!-- Proteção contra Clickjacking -->
    <style id="antiClickjack">body{display:none !important;}</style>
    <script>
        if (self === top) {
            var antiClickjack = document.getElementById("antiClickjack");
            antiClickjack.parentNode.removeChild(antiClickjack);
        } else {
            top.location = self.location;
        }
    </script>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="content">
        <!-- Exibir mensagens flash se existirem -->
        <?php if (Session::hasFlash('error')): ?>
            <div class="alert alert-error">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <div class="alert-content">
                    <h4>Erro</h4>
                    <p><?php echo htmlspecialchars(Session::getFlash('error')); ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (Session::hasFlash('success')): ?>
            <div class="alert alert-success">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <div class="alert-content">
                    <h4>Sucesso</h4>
                    <p><?php echo htmlspecialchars(Session::getFlash('success')); ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (Session::hasFlash('warning')): ?>
            <div class="alert alert-warning">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                <div class="alert-content">
                    <h4>Atenção</h4>
                    <p><?php echo htmlspecialchars(Session::getFlash('warning')); ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (Session::hasFlash('info')): ?>
            <div class="alert alert-info">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
                <div class="alert-content">
                    <h4>Informação</h4>
                    <p><?php echo htmlspecialchars(Session::getFlash('info')); ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Verificar mensagens de erro ou sucesso via URL (modo antigo) -->
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <div class="alert-content">
                    <h4>Erro</h4>
                    <p><?php echo htmlspecialchars($_GET['error']); ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <div class="alert-content">
                    <h4>Sucesso</h4>
                    <p><?php echo htmlspecialchars($_GET['success']); ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['warning'])): ?>
            <div class="alert alert-warning">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                <div class="alert-content">
                    <h4>Atenção</h4>
                    <p><?php echo htmlspecialchars($_GET['warning']); ?></p>
                </div>
            </div>
        <?php endif; ?>