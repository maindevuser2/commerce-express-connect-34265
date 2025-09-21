document.addEventListener('DOMContentLoaded', function () {
    // Elementos del DOM
    const loadingOverlay = document.getElementById('loadingOverlay');
    const passwordInput = document.getElementById('password');
    const passwordStrength = document.getElementById('passwordStrength');
    const strengthSegments = passwordStrength.querySelectorAll('.strength-segment');
    const strengthText = passwordStrength.querySelector('.strength-text');

document.querySelectorAll(".toggle-password").forEach((icon) => {
icon.addEventListener("click", () => {
    const input = icon.closest('.input-with-icon').querySelector('input');
        if (input.type === "password") {
            input.type = "text";
            icon.classList.remove("fa-eye");
            icon.classList.add("fa-eye-slash");
        } else {
            input.type = "password";
            icon.classList.remove("fa-eye-slash");
            icon.classList.add("fa-eye");
        }
    });
});

    // Verificador de fuerza de contraseña
    function checkPasswordStrength(password) {
        let strength = 0;

        if (password.length >= 8) strength += 1;
        if (/[a-z]/.test(password)) strength += 1;
        if (/[A-Z]/.test(password)) strength += 1;
        if (/[0-9]/.test(password)) strength += 1;
        if (/[^a-zA-Z0-9]/.test(password)) strength += 1;

        strengthSegments.forEach((segment, index) => {
            segment.className = 'strength-segment';
            if (index < strength) {
                if (strength <= 2) {
                    segment.classList.add('weak');
                } else if (strength <= 3) {
                    segment.classList.add('medium');
                } else {
                    segment.classList.add('strong');
                }
            }
        });

        if (password.length === 0) {
            strengthText.textContent = 'Fuerza de la contraseña';
        } else if (strength <= 2) {
            strengthText.textContent = 'Contraseña débil';
        } else if (strength <= 3) {
            strengthText.textContent = 'Contraseña media';
        } else {
            strengthText.textContent = 'Contraseña fuerte';
        }

        return strength;
    }

    passwordInput.addEventListener('input', () => {
        checkPasswordStrength(passwordInput.value);
    });

    // Función para mostrar loading
    function showLoading() {
        loadingOverlay.classList.add('show');
    }

    // Función para ocultar loading
    function hideLoading() {
        loadingOverlay.classList.remove('show');
    }

    // Callback para reCAPTCHA
    window.recaptchaCallback = function(response) {
    const container = document.getElementById('recaptchaContainer');
    if (response && container) {
        container.classList.add('recaptcha-completed');
    }
    };

    // Reset reCAPTCHA styling si expira
    window.recaptchaExpiredCallback = function() {
    const container = document.getElementById('recaptchaContainer');
    if (container) {
        container.classList.remove('recaptcha-completed');
    }
    };
});