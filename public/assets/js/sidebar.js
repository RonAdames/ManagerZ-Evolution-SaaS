/**
 * Sidebar Responsivo Otimizado
 * Solução para evitar sobreposição de elementos
 */
document.addEventListener('DOMContentLoaded', function() {
    // Cache de elementos DOM
    const body = document.body;
    const sidebar = document.querySelector('.sidebar_container');
    
    // Importante: verificar se o elemento content existe
    const content = document.querySelector('.content');
    if (!content) {
        console.warn('Elemento .content não encontrado. O deslocamento de conteúdo não funcionará corretamente.');
    }
    
    const mediaQueryMobile = window.matchMedia('(max-width: 768px)');
    
    // Criar botão de toggle se não existir
    let toggleBtn = document.querySelector('.sidebar_toggleBtn');
    if (!toggleBtn) {
        toggleBtn = document.createElement('button');
        toggleBtn.classList.add('sidebar_toggleBtn');
        toggleBtn.setAttribute('aria-label', 'Alternar Menu');
        toggleBtn.setAttribute('type', 'button');
        toggleBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>';
        body.appendChild(toggleBtn);
    }
    
    // Criar overlay se não existir
    let overlay = document.querySelector('.sidebar_overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.classList.add('sidebar_overlay');
        body.appendChild(overlay);
    }
    
    // Função para alternar a sidebar
    function toggleSidebar() {
        sidebar.classList.toggle('sidebar_open');
        overlay.classList.toggle('sidebar_active');
        
        // Alternar deslocamento de conteúdo apenas se o elemento content existir
        if (content && !mediaQueryMobile.matches) {
            content.classList.toggle('content_shifted');
        }
    }
    
    // Função para fechar a sidebar
    function closeSidebar() {
        sidebar.classList.remove('sidebar_open');
        overlay.classList.remove('sidebar_active');
        
        if (content) {
            content.classList.remove('content_shifted');
        }
    }
    
    // Toggle da sidebar ao clicar no botão
    toggleBtn.addEventListener('click', toggleSidebar);
    
    // Fechar sidebar ao clicar no overlay
    overlay.addEventListener('click', closeSidebar);
    
    // Gerenciar mudanças de tamanho de tela
    function handleScreenChange(e) {
        if (e.matches) {
            // Entrando no modo móvel
            sidebar.classList.remove('sidebar_open');
            
            if (content) {
                content.classList.remove('content_shifted');
            }
        } else {
            // Entrando no modo desktop
            closeSidebar();
            
            if (content) {
                // No desktop, o conteúdo não precisa ser deslocado por padrão
                // porque usamos padding-left no CSS
            }
        }
    }
    
    // Adicionar listener para mudanças de mídia
    mediaQueryMobile.addEventListener('change', handleScreenChange);
    
    // Configuração inicial com base no tamanho da tela
    if (mediaQueryMobile.matches) {
        // Começando em visualização móvel
        sidebar.classList.remove('sidebar_open');
        
        if (content) {
            content.classList.remove('content_shifted');
        }
    }
    
    // Adicionar manipulador de clique para navegação móvel
    const navLinks = document.querySelectorAll('.sidebar_navLink');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            // Fechar sidebar no celular após clicar em um link
            if (mediaQueryMobile.matches) {
                closeSidebar();
            }
        });
    });
    
    // IMPORTANTE: Verificar e ajustar sobreposições com outros elementos
    function fixZIndexConflicts() {
        // Lista de seletores potencialmente problemáticos
        const potentialConflicts = [
            '.header', '.navbar', '.topbar', '.app-header', 
            '.fixed-top', '.sticky-top', '.modal', '.dropdown-menu'
        ];
        
        // Verificar cada seletor
        potentialConflicts.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            if (elements.length > 0) {
                elements.forEach(el => {
                    // Garantir que esses elementos tenham z-index maior que a sidebar
                    const currentZIndex = window.getComputedStyle(el).zIndex;
                    if (currentZIndex === 'auto' || parseInt(currentZIndex) < 20) {
                        el.style.zIndex = '20';
                    }
                });
            }
        });
    }
    
    // Executar correção de z-index após o carregamento da página
    fixZIndexConflicts();
    
    // Também executar após qualquer alteração no DOM (como carregamento dinâmico)
    const observer = new MutationObserver(fixZIndexConflicts);
    observer.observe(document.body, { childList: true, subtree: true });
});