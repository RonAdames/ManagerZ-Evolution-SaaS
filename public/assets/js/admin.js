// Função para filtrar a tabela
function filterTable() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toLowerCase();
    const table = document.getElementById('usersTable');
    const rows = table.getElementsByTagName('tr');

    for (let i = 1; i < rows.length; i++) {
        const username = rows[i].getElementsByClassName('username')[0];
        if (username) {
            const txtValue = username.textContent || username.innerText;
            if (txtValue.toLowerCase().indexOf(filter) > -1) {
                rows[i].style.display = '';
            } else {
                rows[i].style.display = 'none';
            }
        }
    }
}

// Função para confirmar exclusão
function confirmDelete(userId, username) {
    if (confirm(`Tem certeza que deseja excluir o usuário ${username}?`)) {
        window.location.href = `admin_delete_user.php?user_id=${userId}`;
    }
}