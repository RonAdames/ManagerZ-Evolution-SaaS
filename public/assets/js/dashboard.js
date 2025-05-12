// Função para filtrar instâncias
function filterInstances() {
    const input = document.getElementById('searchInstances');
    const filter = input.value.toLowerCase();
    const grid = document.querySelector('.instances-grid');
    const cards = grid.getElementsByClassName('instance-card');

    for (let card of cards) {
        const name = card.querySelector('h3').textContent;
        const id = card.querySelector('.instance-id').textContent;
        if (name.toLowerCase().includes(filter) || id.toLowerCase().includes(filter)) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    }
}

// Função para copiar ID para a área de transferência
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        // Feedback visual
        const button = event.currentTarget;
        const originalHTML = button.innerHTML;
        button.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        `;
        button.style.color = '#16a34a';
        
        setTimeout(() => {
            button.innerHTML = originalHTML;
            button.style.color = '';
        }, 2000);
    });
}

// Atualizar status das instâncias periodicamente
function updateInstancesStatus() {
    // Esta função pode ser implementada para atualizar o status via AJAX
    // Exemplo de implementação futura
}

// Atualizar a cada 30 segundos
// setInterval(updateInstancesStatus, 30000);