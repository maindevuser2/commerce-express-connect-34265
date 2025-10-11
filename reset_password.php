<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/database.php'; // <-- Asegúrate de incluir esto si usas la clase Database
require_once 'controllers/AuthController.php';
require_once 'helpers/SecurityHelper.php';

use Controllers\AuthController;
use Helpers\SecurityHelper;

// Inicializa la conexión PDO
$database = new Database();
$pdo = $database->getConnection();

// Redirigir si ya está logueado
if (AuthController::isAuthenticated()) {
    if (AuthController::isAdmin()) {
        header('Location: views/admin/dashboard.php');
    } else {
        header('Location: views/client/home.php');
    }
    exit();
}

// Obtener token de la URL
$token = $_POST['token'] ?? $_GET['token'] ?? '';
$hashedToken = hash('sha256', $token);

$stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW()");
$stmt->execute([$hashedToken]);
$reset = $stmt->fetch(PDO::FETCH_ASSOC);

if ($reset) {
    error_log("DEBUG reset_password.php - Registro válido encontrado: " . print_r($reset, true));
} else {
    error_log("DEBUG reset_password.php - NO se encontró registro válido (token, used_at, expires_at)");
}

if (!$reset) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Token inválido, expirado o ya utilizado.'];
    header('Location: login.php');
    exit;
}

// Procesar reset de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $authController = new AuthController();
    $authController->processPasswordReset();
}

// Generar token CSRF
$csrfToken = SecurityHelper::generateCSRFToken();

// Obtener mensaje flash
$flash_message = AuthController::getFlashMessage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña - English Learning Platform</title>
    
    <!-- Meta tags de seguridad -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    <meta http-equiv="Referrer-Policy" content="strict-origin-when-cross-origin">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="public/css/styles.css">
    <link rel="stylesheet" href="public/css/client/auth-login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" integrity="sha512-Fo3rlrZj/k7ujTnHg4CGR2D7kSs0v4LLanw2qksYuRlEzO+tcaEPQogQ0KaoGN26/zrn20ImR1DfuLWnOo7aBA==" crossorigin="anonymous" referrerpolicy="no-referrer">
</head>
<body>
    <section class="auth-section">
        <div class="container">
            <div class="auth-container">
                <div class="auth-header">
                    <h1>Restablecer Contraseña</h1>
                    <p>Ingresa tu nueva contraseña</p>
                </div>
                
                <?php if ($flash_message): ?>
                    <div class="flash-message <?php echo htmlspecialchars($flash_message['type']); ?>">
                        <?php if ($flash_message['type'] === 'success'): ?>
                            <i class="fas fa-check-circle"></i>
                        <?php else: ?>
                            <i class="fas fa-exclamation-triangle"></i>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($flash_message['message']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="auth-form">
                    <form id="resetForm" method="POST" novalidate>
                        <!-- Tokens de seguridad -->
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        
                        <div class="form-group">
                            <label for="new_password">Nueva Contraseña</label>
                            <div class="input-with-icon">
                                <input type="password" 
                                       id="new_password" 
                                       name="new_password" 
                                       placeholder="Ingresa tu nueva contraseña" 
                                       required 
                                       autocomplete="new-password"
                                       minlength="8"
                                       maxlength="255">
                                <i class="fas fa-eye toggle-password" tabindex="0" role="button" aria-label="Mostrar contraseña"></i>
                            </div>
                            <div class="password-requirements">
                                <small>La contraseña debe tener al menos:</small>
                                <ul>
                                    <li id="length-req">8 caracteres</li>
                                    <li id="lowercase-req">Una letra minúscula</li>
                                    <li id="uppercase-req">Una letra mayúscula</li>
                                    <li id="number-req">Un número</li>
                                </ul>
                            </div>
                            <div class="error-message" id="passwordError"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirmar Contraseña</label>
                            <div class="input-with-icon">
                                <input type="password" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       placeholder="Confirma tu nueva contraseña" 
                                       required 
                                       autocomplete="new-password"
                                       minlength="8"
                                       maxlength="255">
                                <i class="fas fa-eye toggle-password" tabindex="0" role="button" aria-label="Mostrar contraseña"></i>
                            </div>
                            <div class="error-message" id="confirmPasswordError"></div>
                        </div>
                        
                        <button type="submit" class="btn-primary btn-full" id="resetBtn">
                            <span class="loading-spinner" id="resetSpinner"></span>
                            <span id="resetBtnText">Restablecer Contraseña</span>
                        </button>
                    </form>
                    
                    <div class="auth-footer">
                        <p><a href="login.php">Volver al Login</a></p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay" aria-hidden="true">
        <div class="spinner"></div>
        <p>Procesando...</p>
    </div>
    
    <script>
        // Validación de contraseña en tiempo real
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const resetBtn = document.getElementById('resetBtn');
            
            // Elementos de requisitos
            const lengthReq = document.getElementById('length-req');
            const lowercaseReq = document.getElementById('lowercase-req');
            const uppercaseReq = document.getElementById('uppercase-req');
            const numberReq = document.getElementById('number-req');
            
            function validatePassword() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                // Validar requisitos
                const hasLength = password.length >= 8;
                const hasLowercase = /[a-z]/.test(password);
                const hasUppercase = /[A-Z]/.test(password);
                const hasNumber = /[0-9]/.test(password);
                const passwordsMatch = password === confirmPassword && password.length > 0;
                
                // Actualizar UI de requisitos
                lengthReq.classList.toggle('valid', hasLength);
                lowercaseReq.classList.toggle('valid', hasLowercase);
                uppercaseReq.classList.toggle('valid', hasUppercase);
                numberReq.classList.toggle('valid', hasNumber);
                
                // Validar coincidencia de contraseñas
                const confirmError = document.getElementById('confirmPasswordError');
                if (confirmPassword.length > 0 && !passwordsMatch) {
                    confirmError.textContent = 'Las contraseñas no coinciden';
                    confirmError.style.display = 'block';
                } else {
                    confirmError.style.display = 'none';
                }
                
                // Habilitar/deshabilitar botón
                const allValid = hasLength && hasLowercase && hasUppercase && hasNumber && passwordsMatch;
                resetBtn.disabled = !allValid;
                
                return allValid;
            }
            
            passwordInput.addEventListener('input', validatePassword);
            confirmPasswordInput.addEventListener('input', validatePassword);
            
            // Toggle password visibility
            document.querySelectorAll('.toggle-password').forEach(icon => {
                icon.addEventListener('click', function() {
                    const input = this.previousElementSibling;
                    if (input.type === 'password') {
                        input.type = 'text';
                        this.classList.remove('fa-eye');
                        this.classList.add('fa-eye-slash');
                    } else {
                        input.type = 'password';
                        this.classList.remove('fa-eye-slash');
                        this.classList.add('fa-eye');
                    }
                });
            });
            
            // Manejar envío del formulario
            document.getElementById('resetForm').addEventListener('submit', function(e) {
                if (!validatePassword()) {
                    e.preventDefault();
                    return false;
                }
                
                // Mostrar loading
                resetBtn.disabled = true;
                document.getElementById('resetSpinner').style.display = 'inline-block';
                document.getElementById('resetBtnText').textContent = 'Procesando...';
            });
        });
    </script>
    
    <style>
        .password-requirements {
            margin-top: 10px;
            font-size: 12px;
        }
        
        .password-requirements ul {
            list-style: none;
            padding: 0;
            margin: 5px 0;
        }
        
        .password-requirements li {
            padding: 2px 0;
            color: #dc3545;
        }
        
        .password-requirements li.valid {
            color: #28a745;
        }
        
        .password-requirements li.valid::before {
            content: '✓ ';
            font-weight: bold;
        }
        
        .password-requirements li:not(.valid)::before {
            content: '✗ ';
            font-weight: bold;
        }
        
        .loading-spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid #ffffff;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 8px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</body>
</html>
