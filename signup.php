<?php
// Habilitar reporte de errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Cargar configuración
require_once 'config/config.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Incluir el controlador de autenticación
require_once 'controllers/AuthController.php';
require_once 'helpers/SecurityHelper.php';

use Controllers\AuthController;
use Helpers\SecurityHelper;

// Redirigir si ya está logueado
if (AuthController::isAuthenticated()) {
    if (AuthController::isAdmin()) {
        header('Location: views/admin/dashboard.php');
    } else {
        header('Location: views/client/home.php');
    }
    exit();
}

// Procesar registro si es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $authController = new AuthController();
        $authController->register();
    } catch (\Exception $e) {
        error_log("Error en signup.php: " . $e->getMessage());
        AuthController::setFlashMessage('error', 'Error interno del servidor. Intenta de nuevo.');
    }
}

// Obtener mensaje flash
$flash_message = AuthController::getFlashMessage();

// Generar token CSRF
$csrf_token = SecurityHelper::generateCSRFToken();

// Obtener claves de reCAPTCHA
$recaptcha_site_key = RECAPTCHA_SITE_KEY;

// Debug: Log de las claves para verificar que se cargan correctamente
error_log("reCAPTCHA Site Key: " . $recaptcha_site_key);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - English Learning Platform</title>
    <link rel="stylesheet" href="public/css/styles.css">
    <link rel="stylesheet" href="public/css/client/auth-signup.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        .recaptcha-container {
            margin: 20px 0;
            display: flex;
            justify-content: center;
        }
        
        .recaptcha-error {
            color: #dc3545;
            font-size: 14px;
            margin-top: 5px;
            text-align: center;
        }
        
        .flash-message {
            padding: 12px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
            text-align: center;
        }
        
        .flash-message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .flash-message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Signup Section -->
    <section class="auth-section">
        <div class="container">
            <div class="auth-container">
                <div class="auth-header">
                    <a href="index.php" class="btn-back" style="display: inline-flex; align-items: center; gap: 8px; color: #000000ff; text-decoration: none; margin-bottom: 20px; font-size: 14px;">
                        <i class="fas fa-arrow-left"></i>
                        Volver al inicio
                    </a>
                    <h1>Crear una Cuenta</h1>
                    <p>Únete a nuestra comunidad y comienza a aprender inglés hoy</p>
                </div>
                
                <?php if ($flash_message): ?>
                    <div class="flash-message <?php echo htmlspecialchars($flash_message['type']); ?>">
                        <?php echo htmlspecialchars($flash_message['message']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="auth-form">
                    <form id="signupForm" method="POST" novalidate>
                        <!-- Token CSRF -->
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="firstName">Nombre</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-user"></i>
                                    <input type="text" id="firstName" name="first_name" placeholder="Ingresa tu nombre" required maxlength="50">
                                </div>
                                <div class="error-message" id="firstNameError"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="lastName">Apellido</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-user"></i>
                                    <input type="text" id="lastName" name="last_name" placeholder="Ingresa tu apellido" required maxlength="50">
                                </div>
                                <div class="error-message" id="lastNameError"></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <div class="input-with-icon">
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="email" name="email" placeholder="Ingresa tu email" required maxlength="100">
                            </div>
                            <div class="error-message" id="emailError"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Contraseña</label>
                            <div class="input-with-icon">
                                <input type="password" id="password" name="password" placeholder="Crea una contraseña" required minlength="8">
                                <i class="fas fa-eye toggle-password"></i>
                            </div>
                            <div class="password-strength" id="passwordStrength">
                                <div class="strength-meter">
                                    <div class="strength-segment"></div>
                                    <div class="strength-segment"></div>
                                    <div class="strength-segment"></div>
                                    <div class="strength-segment"></div>
                                </div>
                                <span class="strength-text">Fuerza de la contraseña</span>
                            </div>
                            <div class="error-message" id="passwordError"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirmPassword">Confirmar Contraseña</label>
                            <div class="input-with-icon">
                                <input type="password" id="confirmPassword" name="confirm_password" placeholder="Confirma tu contraseña" required>
                                <i class="fas fa-eye toggle-password"></i>
                            </div>
                            <div class="error-message" id="confirmPasswordError"></div>
                        </div>
                        
                        <div class="form-group checkbox-group">
                            <input type="checkbox" id="termsAgree" name="termsAgree" required>
                            <label for="termsAgree">Acepto los <a href="#" target="_blank">Términos de Servicio</a> y <a href="#" target="_blank">Política de Privacidad</a></label>
                            <div class="error-message" id="termsAgreeError"></div>
                        </div>

                        <div class="form-group">
                            <div class="recaptcha-container" id="recaptchaContainer">
                                <div class="g-recaptcha" 
                                     data-sitekey="<?php echo htmlspecialchars($recaptcha_site_key); ?>" 
                                     data-callback="recaptchaCallback"
                                     data-expired-callback="recaptchaExpired"
                                     data-error-callback="recaptchaError">
                                </div>
                            </div>
                            <div class="recaptcha-error" id="recaptchaError" style="display: none;"></div>
                        </div>
                        
                        <button type="submit" class="btn-primary btn-full" id="submitBtn" disabled>
                            <span class="btn-text">Crear Cuenta</span>
                            <span class="btn-loading" style="display: none;">
                                <i class="fas fa-spinner fa-spin"></i> Creando cuenta...
                            </span>
                        </button>
                    </form>
                    
                    <div class="auth-footer">
                        <p>¿Ya tienes una cuenta? <a href="login.php">Inicia sesión</a></p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <!-- Scripts -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <script>
        // Variables globales
        let recaptchaCompleted = false;
        let formSubmitting = false;

        // Callback cuando reCAPTCHA se completa
        function recaptchaCallback(response) {
            console.log('reCAPTCHA completado:', response);
            recaptchaCompleted = true;
            document.getElementById('recaptchaError').style.display = 'none';
            validateForm();
        }

        // Callback cuando reCAPTCHA expira
        function recaptchaExpired() {
            console.log('reCAPTCHA expirado');
            recaptchaCompleted = false;
            document.getElementById('recaptchaError').textContent = 'reCAPTCHA expirado. Por favor, complétalo nuevamente.';
            document.getElementById('recaptchaError').style.display = 'block';
            validateForm();
        }

        // Callback cuando hay error en reCAPTCHA
        function recaptchaError() {
            console.log('Error en reCAPTCHA');
            recaptchaCompleted = false;
            document.getElementById('recaptchaError').textContent = 'Error al cargar reCAPTCHA. Recarga la página e intenta nuevamente.';
            document.getElementById('recaptchaError').style.display = 'block';
            validateForm();
        }

        // Validar formulario completo
        function validateForm() {
            const form = document.getElementById('signupForm');
            const submitBtn = document.getElementById('submitBtn');
            const firstName = document.getElementById('firstName').value.trim();
            const lastName = document.getElementById('lastName').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const termsAgree = document.getElementById('termsAgree').checked;

            const isValid = firstName && lastName && email && password && 
                          confirmPassword && password === confirmPassword && 
                          termsAgree && recaptchaCompleted && !formSubmitting;

            submitBtn.disabled = !isValid;
            
            if (isValid) {
                submitBtn.classList.add('btn-enabled');
            } else {
                submitBtn.classList.remove('btn-enabled');
            }
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('signupForm');
            const inputs = form.querySelectorAll('input[required]');
            const togglePasswordBtns = document.querySelectorAll('.toggle-password');

            // Validar en tiempo real
            inputs.forEach(input => {
                input.addEventListener('input', validateForm);
                input.addEventListener('blur', validateForm);
            });

            document.getElementById('termsAgree').addEventListener('change', validateForm);

            // Toggle password visibility
            togglePasswordBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const input = this.previousElementSibling;
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                });
            });

            // Validación de contraseña en tiempo real
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirmPassword');
            
            passwordInput.addEventListener('input', function() {
                validatePasswordStrength(this.value);
                validatePasswordMatch();
            });
            
            confirmPasswordInput.addEventListener('input', validatePasswordMatch);

            // Submit form
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (formSubmitting) {
                    return;
                }

                if (!recaptchaCompleted) {
                    document.getElementById('recaptchaError').textContent = 'Por favor, completa el reCAPTCHA.';
                    document.getElementById('recaptchaError').style.display = 'block';
                    return;
                }

                if (!validateFormData()) {
                    return;
                }

                formSubmitting = true;
                showLoading();
                
                // Enviar formulario
                this.submit();
            });
        });

        function validatePasswordStrength(password) {
            const strengthMeter = document.querySelector('.strength-meter');
            const strengthText = document.querySelector('.strength-text');
            const segments = strengthMeter.querySelectorAll('.strength-segment');
            
            let strength = 0;
            let text = 'Muy débil';
            
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            // Reset segments
            segments.forEach(segment => {
                segment.className = 'strength-segment';
            });
            
            // Apply strength
            for (let i = 0; i < Math.min(strength, 4); i++) {
                segments[i].classList.add('active');
            }
            
            switch (strength) {
                case 0:
                case 1:
                    text = 'Muy débil';
                    segments.forEach((seg, i) => {
                        if (i < strength) seg.classList.add('weak');
                    });
                    break;
                case 2:
                    text = 'Débil';
                    segments.forEach((seg, i) => {
                        if (i < strength) seg.classList.add('fair');
                    });
                    break;
                case 3:
                    text = 'Buena';
                    segments.forEach((seg, i) => {
                        if (i < strength) seg.classList.add('good');
                    });
                    break;
                case 4:
                case 5:
                    text = 'Fuerte';
                    segments.forEach((seg, i) => {
                        if (i < strength) seg.classList.add('strong');
                    });
                    break;
            }
            
            strengthText.textContent = text;
        }

        function validatePasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const errorDiv = document.getElementById('confirmPasswordError');
            
            if (confirmPassword && password !== confirmPassword) {
                errorDiv.textContent = 'Las contraseñas no coinciden';
                errorDiv.style.display = 'block';
            } else {
                errorDiv.style.display = 'none';
            }
            
            validateForm();
        }

        function validateFormData() {
            let isValid = true;
            
            // Validar nombre
            const firstName = document.getElementById('firstName').value.trim();
            if (!firstName) {
                showError('firstNameError', 'El nombre es requerido');
                isValid = false;
            }
            
            // Validar apellido
            const lastName = document.getElementById('lastName').value.trim();
            if (!lastName) {
                showError('lastNameError', 'El apellido es requerido');
                isValid = false;
            }
            
            // Validar email
            const email = document.getElementById('email').value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!email) {
                showError('emailError', 'El email es requerido');
                isValid = false;
            } else if (!emailRegex.test(email)) {
                showError('emailError', 'Formato de email inválido');
                isValid = false;
            }
            
            // Validar contraseña
            const password = document.getElementById('password').value;
            if (!password) {
                showError('passwordError', 'La contraseña es requerida');
                isValid = false;
            } else if (password.length < 8) {
                showError('passwordError', 'La contraseña debe tener al menos 8 caracteres');
                isValid = false;
            }
            
            // Validar confirmación de contraseña
            const confirmPassword = document.getElementById('confirmPassword').value;
            if (password !== confirmPassword) {
                showError('confirmPasswordError', 'Las contraseñas no coinciden');
                isValid = false;
            }
            
            // Validar términos
            if (!document.getElementById('termsAgree').checked) {
                showError('termsAgreeError', 'Debes aceptar los términos y condiciones');
                isValid = false;
            }
            
            return isValid;
        }

        function showError(elementId, message) {
            const errorDiv = document.getElementById(elementId);
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
        }

        function showLoading() {
            const submitBtn = document.getElementById('submitBtn');
            const btnText = submitBtn.querySelector('.btn-text');
            const btnLoading = submitBtn.querySelector('.btn-loading');
            const loadingOverlay = document.getElementById('loadingOverlay');
            
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline-block';
            submitBtn.disabled = true;
            loadingOverlay.style.display = 'flex';
        }

        // Debug: Verificar que las claves se carguen
        console.log('reCAPTCHA Site Key:', '<?php echo $recaptcha_site_key; ?>');
    </script>
</body>
</html>