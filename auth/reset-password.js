/* Reset Password */
document.getElementById('resetPasswordForm').addEventListener('submit', async function (e) {
e.preventDefault();

const emailInput = document.getElementById('resetEmail');
const messagesDiv = document.getElementById('resetMessages');
const btn = document.getElementById('resetSubmitBtn');
const spinner = document.getElementById('resetSpinner');
const btnText = document.getElementById('resetBtnText');

spinner.style.display = 'inline-block';
btnText.textContent = 'Enviando...';

const formData = new FormData(this);

try {
    const response = await fetch('send-reset-email.php', {
        method: 'POST',
        body: formData
    });

    const data = await response.json();

    messagesDiv.innerHTML = '';
    if (data.success) {
        messagesDiv.innerHTML = `<div class="flash-message success"><i class="fas fa-check-circle"></i> ${data.success}</div>`;
        emailInput.value = '';
    } else {
        messagesDiv.innerHTML = `<div class="flash-message error"><i class="fas fa-exclamation-circle"></i> ${data.error}</div>`;
    }
} catch (error) {
    messagesDiv.innerHTML = `<div class="flash-message error"><i class="fas fa-exclamation-circle"></i> Ocurrió un error inesperado.</div>`;
} finally {
    spinner.style.display = 'none';
    btnText.textContent = 'Enviar Contraseña';
}
});