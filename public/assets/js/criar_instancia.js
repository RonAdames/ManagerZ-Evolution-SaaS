// Função para validar o nome da instância em tempo real
document.getElementById('instanceName').addEventListener('input', function(e) {
    const input = e.target;
    const nameError = document.getElementById('nameError');
    const validPattern = /^[a-zA-Z0-9]+$/;
    
    // Remove caracteres inválidos imediatamente
    input.value = input.value.replace(/[^a-zA-Z0-9]/g, '');
    
    // Valida o valor atual
    if (input.value.length === 0) {
        nameError.textContent = 'O nome da instância é obrigatório';
        input.classList.add('error');
    } else if (!validPattern.test(input.value)) {
        nameError.textContent = 'Use apenas letras e números, sem espaços ou caracteres especiais';
        input.classList.add('error');
    } else {
        nameError.textContent = '';
        input.classList.remove('error');
    }
});

// Função para validar o formulário antes do envio
function validateForm() {
    const instanceName = document.getElementById('instanceName');
    const nameError = document.getElementById('nameError');
    const validPattern = /^[a-zA-Z0-9]+$/;
    let isValid = true;
    
    // Valida o nome da instância
    if (!validPattern.test(instanceName.value)) {
        nameError.textContent = 'Use apenas letras e números, sem espaços ou caracteres especiais';
        instanceName.classList.add('error');
        isValid = false;
    }
    
    if (instanceName.value.length === 0) {
        nameError.textContent = 'O nome da instância é obrigatório';
        instanceName.classList.add('error');
        isValid = false;
    }
    
    // Se o formulário for válido, mostra o loading
    if (isValid) {
        const submitButton = document.querySelector('button[type="submit"]');
        const originalContent = submitButton.innerHTML;
        
        submitButton.disabled = true;
        submitButton.innerHTML = `
            <svg class="spinner" viewBox="0 0 50 50">
                <circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
            </svg>
            Criando...
        `;
        
        // Adiciona classes para animação
        submitButton.classList.add('loading');
    }
    
    return isValid;
}

// Estilos para o loading
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

    input.error {
        border-color: #dc2626 !important;
    }

    .error-text {
        color: #dc2626;
        font-size: 12px;
        margin-top: 5px;
        font-weight: 500;
    }

    .btn-primary.loading {
        opacity: 0.8;
        cursor: not-allowed;
    }
`;

document.head.appendChild(style);