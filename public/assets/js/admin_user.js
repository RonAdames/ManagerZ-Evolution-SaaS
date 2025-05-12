// Função para filtrar instâncias
function filterInstances() {
    const input = document.getElementById('searchInstances');
    const filter = input.value.toLowerCase();
    const table = document.getElementById('instancesTable');
    const rows = table.getElementsByTagName('tr');

    for (let i = 1; i < rows.length; i++) {
        const nameCell = rows[i].getElementsByClassName('instance-name')[0];
        const idCell = rows[i].getElementsByClassName('instance-id')[0];
        if (nameCell && idCell) {
            const name = nameCell.textContent || nameCell.innerText;
            const id = idCell.textContent || idCell.innerText;
            if (name.toLowerCase().indexOf(filter) > -1 || id.toLowerCase().indexOf(filter) > -1) {
                rows[i].style.display = '';
            } else {
                rows[i].style.display = 'none';
            }
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

// Função para confirmar exclusão
function confirmDeleteInstance(instanceName, userId) {
    if (confirm(`Tem certeza que deseja excluir a instância "${instanceName}"?`)) {
        window.location.href = `admin_delete_instance.php?instance_name=${encodeURIComponent(instanceName)}&user_id=${encodeURIComponent(userId)}`;
    }
}

