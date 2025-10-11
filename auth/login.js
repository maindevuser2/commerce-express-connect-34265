// Elementos del DOM
const loadingOverlay = document.getElementById('loadingOverlay');
const forgotPasswordLink = document.getElementById('forgotPassword');
const passwordResetModal = document.getElementById('passwordResetModal');
const closeModalBtn = document.querySelector('.close-modal');
const resetPasswordForm = document.getElementById('resetPasswordForm');
const resetSubmitBtn = document.getElementById('resetSubmitBtn');
const resetSpinner = document.getElementById('resetSpinner');
const resetBtnText = document.getElementById('resetBtnText');
const resetMessages = document.getElementById('resetMessages');

// Mostrar/ocultar contraseña
document.querySelectorAll('.toggle-password').forEach(icon => {
    icon.addEventListener('click', () => {
        const input = icon.previousElementSibling;
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });
});

// Función para mostrar loading
function showLoading() {
    loadingOverlay.classList.add('show');
}

// Función para ocultar loading
function hideLoading() {
    loadingOverlay.classList.remove('show');
}

// Función para mostrar mensaje en el modal
function showResetMessage(type, message) {
    resetMessages.innerHTML = `
        <div class="${type}-message">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
            ${message}
        </div>
    `;
}

// Función para limpiar mensajes
function clearResetMessages() {
    resetMessages.innerHTML = '';
}

// Modal de restablecimiento de contraseña
forgotPasswordLink.addEventListener('click', (e) => {
    e.preventDefault();
    passwordResetModal.classList.add('show');
    clearResetMessages();
    document.getElementById('resetEmail').value = '';
});

closeModalBtn.addEventListener('click', () => {
    passwordResetModal.classList.remove('show');
});

passwordResetModal.addEventListener('click', (e) => {
    if (e.target === passwordResetModal) {
        passwordResetModal.classList.remove('show');
    }
});

// Manejar envío del formulario de reset
resetPasswordForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const email = document.getElementById('resetEmail').value.trim();
    
    // Validar email
    if (!email) {
        showResetMessage('error', 'Por favor ingresa tu email.');
        return;
    }

    if (!isValidEmail(email)) {
        showResetMessage('error', 'Por favor ingresa un email válido.');
        return;
    }

    // Mostrar loading
    resetSubmitBtn.disabled = true;
    resetSpinner.style.display = 'inline-block';
    resetBtnText.textContent = 'Enviando...';
    clearResetMessages();

    try {
        // Enviar formulario a send-reset-email.php
        const formData = new FormData();
        formData.append('reset_email', email);
        formData.append('csrf_token', window.APP_CONFIG.csrfToken);

        const response = await fetch('send-reset-email.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showResetMessage('success', data.success);
            document.getElementById('resetEmail').value = '';
            
            // Cerrar modal después de 3 segundos
            setTimeout(() => {
                passwordResetModal.classList.remove('show');
                clearResetMessages();
            }, 3000);
        } else {
            showResetMessage('error', data.error || 'Error al procesar la solicitud.');
        }

    } catch (error) {
        console.error('Error:', error);
        showResetMessage('error', 'Error al procesar la solicitud. Intenta nuevamente.');
    } finally {
        // Ocultar loading
        resetSubmitBtn.disabled = false;
        resetSpinner.style.display = 'none';
        resetBtnText.textContent = 'Enviar Contraseña';
    }
});

// Función para validar email
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Cerrar modal con Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && passwordResetModal.classList.contains('show')) {
        passwordResetModal.classList.remove('show');
    }
});