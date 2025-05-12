// Configuração dos níveis de força da senha
const strengthLevels = {
    0: { color: '#ef4444', text: 'Muito fraca' },
    1: { color: '#f97316', text: 'Fraca' },
    2: { color: '#eab308', text: 'Média' },
    3: { color: '#22c55e', text: 'Forte' }
};

// Verifica a força da senha
function checkPasswordStrength(password) {
    let strength = 0;
    
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
    if (password.match(/\d/)) strength++;
    if (password.match(/[^a-zA-Z\d]/)) strength++;
    
    return strength;
}

// Atualiza o indicador de força da senha
function updatePasswordStrength() {
    const password = document.getElementById('nova_senha').value;
    const strengthDiv = document.getElementById('passwordStrength');
    const strength = checkPasswordStrength(password);
    
    let barsHtml = '';
    for (let i = 0; i < 4; i++) {
        const color = i <= strength ? strengthLevels[strength].color : '#e1e1e1';
        barsHtml += `<div class="strength-bar" style="background-color: ${color}"></div>`;
    }
    
    strengthDiv.innerHTML = `
        ${barsHtml}
        <span style="color: ${strengthLevels[strength].color}">
            ${strength > 0 ? strengthLevels[strength].text : 'Digite sua senha'}
        </span>
    `;
}

// Verifica se as senhas coincidem
function checkPasswordMatch() {
    const password = document.getElementById('nova_senha').value;
    const confirm = document.getElementById('confirmar_senha').value;
    const matchDiv = document.getElementById('passwordMatch');
    
    if (confirm) {
        if (password === confirm) {
            matchDiv.style.color = '#22c55e';
            matchDiv.textContent = '✓ As senhas coincidem';
            return true;
        } else {
            matchDiv.style.color = '#ef4444';
            matchDiv.textContent = '✗ As senhas não coincidem';
            return false;
        }
    } else {
        matchDiv.textContent = '';
        return false;
    }
}

// Alterna a visibilidade da senha
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = input.parentElement.querySelector('.toggle-password');
    
    if (input.type === 'password') {
        input.type = 'text';
        button.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                <line x1="1" y1="1" x2="23" y2="23"></line>
            </svg>
        `;
    } else {
        input.type = 'password';
        button.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
            </svg>
        `;
    }
}

// Validação do formulário
function validateForm() {
    const password = document.getElementById('nova_senha').value;
    const confirm = document.getElementById('confirmar_senha').value;
    const currentPassword = document.getElementById('senha_atual').value;
    let isValid = true;
    
    // Verifica se a senha atual foi preenchida
    if (!currentPassword) {
        showError('senha_atual', 'A senha atual é obrigatória');
        isValid = false;
    }
    
    // Verifica a força da senha
    if (checkPasswordStrength(password) < 2) {
        showError('nova_senha', 'A senha deve ser mais forte');
        isValid = false;
    }
    
    // Verifica se as senhas coincidem
    if (password !== confirm) {
        showError('confirmar_senha', 'As senhas não coincidem');
        isValid = false;
    }
    
    if (isValid) {
        const submitButton = document.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.innerHTML = `
            <svg class="spinner" viewBox="0 0 50 50">
                <circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
            </svg>
            Atualizando...
        `;
    }
    
    return isValid;
}

// Mostra mensagem de erro
function showError(inputId, message) {
    const input = document.getElementById(inputId);
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.textContent = message;
    
    // Remove mensagens de erro anteriores
    const existingError = input.parentElement.querySelector('.error-message');
    if (existingError) {
        existingError.remove();
    }
    
    input.classList.add('error');
    input.parentElement.appendChild(errorDiv);
}

// Adiciona os listeners de eventos
document.addEventListener('DOMContentLoaded', function() {
    const newPassword = document.getElementById('nova_senha');
    const confirmPassword = document.getElementById('confirmar_senha');
    
    newPassword.addEventListener('input', function() {
        updatePasswordStrength();
        if (confirmPassword.value) {
            checkPasswordMatch();
        }
    });
    
    confirmPassword.addEventListener('input', checkPasswordMatch);
    
    // Remove as mensagens de erro quando o usuário começa a digitar
    document.querySelectorAll('input').forEach(input => {
        input.addEventListener('input', function() {
            this.classList.remove('error');
            const errorMessage = this.parentElement.querySelector('.error-message');
            if (errorMessage) {
                errorMessage.remove();
            }
        });
    });
});

// Adiciona os estilos do spinner
const style = document.createElement('style');
style.textContent = `
    .spinner {
        animation: rotate 2s linear infinite;
        width: 20px;
        height: 20px;
        margin-right: 10px;
    }

    .spinner .path {
        stroke: #ffffff;
        stroke-linecap: round;
        animation: dash 1.5s ease-in-out infinite;
    }

    @keyframes rotate {
        100% {
            transform: rotate(360deg);
        }
    }

    @keyframes dash {
        0% {
            stroke-dasharray: 1, 150;
            stroke-dashoffset: 0;
        }
        50% {
            stroke-dasharray: 90, 150;
            stroke-dashoffset: -35;
        }
        100% {
            stroke-dasharray: 90, 150;
            stroke-dashoffset: -124;
        }
    }

    .error {
        border-color: #ef4444 !important;
    }

    .error-message {
        color: #ef4444;
        font-size: 12px;
        margin-top: 5px;
        font-weight: 500;
    }

    button:disabled {
        opacity: 0.7;
        cursor: not-allowed;
    }
`;

document.head.appendChild(style);