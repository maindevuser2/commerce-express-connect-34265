<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cargar dependencias
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../models/User.php';

use Controllers\AuthController;
use Models\User;

// Verificar autenticación
if (!AuthController::isAuthenticated()) {
    header('Location: ../../login.php');
    exit();
}

$database = new \Database();
$db = $database->getConnection();
$userModel = new User($db);

// Obtener usuario actual
$currentUser = AuthController::getCurrentUser();
$userId = $currentUser['id'] ?? 0;

// Refrescar datos del usuario desde la base de datos
$refreshed = $userModel->findById($userId);
if ($refreshed) {
    $currentUser = $refreshed;
    $_SESSION['user'] = $currentUser;
}

// Verificar que el usuario existe
if (!$currentUser || !isset($currentUser['id'])) {
    AuthController::logout();
    exit();
}

$error = '';
$success = '';

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validaciones
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'Todos los campos son obligatorios.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Las nuevas contraseñas no coinciden.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'La nueva contraseña debe tener al menos 6 caracteres.';
    } else {
        // Verificar contraseña actual
        $userData = $userModel->findByEmail($currentUser['email']);
        if ($userData && password_verify($currentPassword, $userData['password'])) {
            // Actualizar contraseña
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$hashedPassword, $userId])) {
                $success = 'Contraseña actualizada exitosamente.';
            } else {
                $error = 'Error al actualizar la contraseña. Inténtalo de nuevo.';
            }
        } else {
            $error = 'La contraseña actual es incorrecta.';
        }
    }
}

// Procesar actualización de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    // Validaciones básicas
    if (empty($firstName) || empty($lastName) || empty($email)) {
        $error = 'Todos los campos del perfil son obligatorios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El email no tiene un formato válido.';
    } else {
        // Verificar si el email ya existe (excepto el actual)
        $existingUser = $userModel->findByEmail($email);
        if ($existingUser && $existingUser['id'] != $userId) {
            $error = 'Este email ya está en uso por otro usuario.';
        } else {
            // Actualizar perfil
            $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$firstName, $lastName, $email, $userId])) {
                $success = 'Perfil actualizado exitosamente.';
                
                // Obtener datos frescos desde la base de datos
                $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

                // Actualizar datos en sesión completamente
                $_SESSION['user'] = $currentUser;

            } else {
                $error = 'Error al actualizar el perfil. Inténtalo de nuevo.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Profesor Hernán</title>
    <link rel="stylesheet" href="../../public/css/client/client-profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>

    </style>
</head>
<body>
    <div class="profile-container">
        <a href="home.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Volver al Inicio
        </a>

        <?php if ($error): ?>
            <div class="flash-message error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="flash-message success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="profile-sections">
            <!-- Información Personal -->
            <div class="profile-section">
                <h2 class="section-title">
                    <i class="fas fa-user"></i>
                    Información Personal
                </h2>
                
                <form method="POST" class="auth-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name" class="form-label">Nombre</label>
                            <div class="input-with-icon">
                                <i class="fas fa-user"></i>
                                <input 
                                    type="text" 
                                    id="first_name" 
                                    name="first_name" 
                                    class="form-input"
                                    value="<?php echo htmlspecialchars($currentUser['first_name'] ?? ''); ?>"
                                    required
                                >
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name" class="form-label">Apellido</label>
                            <div class="input-with-icon">
                                <i class="fas fa-user"></i>
                                <input 
                                    type="text" 
                                    id="last_name" 
                                    name="last_name" 
                                    class="form-input"
                                    value="<?php echo htmlspecialchars($currentUser['last_name'] ?? ''); ?>"
                                    required
                                >
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Correo Electrónico</label>
                        <div class="input-with-icon">
                            <i class="fas fa-envelope"></i>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                class="form-input"
                                value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>"
                                required
                            >
                        </div>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary btn-full">
                        <i class="fas fa-save"></i>
                        Guardar Cambios
                    </button>
                </form>
            </div>

            <!-- Cambiar Contraseña -->
            <div class="profile-section">
                <h2 class="section-title">
                    <i class="fas fa-lock"></i>
                    Cambiar Contraseña
                </h2>
                
                <form method="POST" class="auth-form">
                    <div class="form-group">
                        <label for="current_password" class="form-label">Contraseña Actual</label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock"></i>
                            <input 
                                type="password" 
                                id="current_password" 
                                name="current_password" 
                                class="form-input"
                                placeholder="Ingresa tu contraseña actual"
                                required
                            >
                            <i class="fas fa-eye toggle-password" onclick="togglePassword('current_password')"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password" class="form-label">Nueva Contraseña</label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock"></i>
                            <input 
                                type="password" 
                                id="new_password" 
                                name="new_password" 
                                class="form-input"
                                placeholder="Ingresa tu nueva contraseña"
                                required
                                minlength="6"
                            >
                            <i class="fas fa-eye toggle-password" onclick="togglePassword('new_password')"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirmar Nueva Contraseña</label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock"></i>
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                class="form-input"
                                placeholder="Confirma tu nueva contraseña"
                                required
                                minlength="6"
                            >
                            <i class="fas fa-eye toggle-password" onclick="togglePassword('confirm_password')"></i>
                        </div>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-primary btn-full">
                        <i class="fas fa-key"></i>
                        Cambiar Contraseña
                    </button>
                </form>
            </div>

            <!-- Información de la Cuenta -->
            <div class="profile-section">
                <h2 class="section-title">
                    <i class="fas fa-info-circle"></i>
                    Información de la Cuenta
                </h2>
                
                <div class="account-info">
                    <div class="info-item">
                        <strong>Fecha de Registro:</strong>
                        <?php echo date('d/m/Y', strtotime($currentUser['created_at'] ?? 'now')); ?>
                    </div>
                    <div class="info-item">
                        <strong>Estado:</strong>
                        <span class="status <?php echo ($currentUser['is_active'] ?? 1) ? 'active' : 'inactive'; ?>">
                            <?php echo ($currentUser['is_active'] ?? 1) ? 'Activa' : 'Inactiva'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling;
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Validación de contraseñas en tiempo real
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && newPassword !== confirmPassword) {
                this.setCustomValidity('Las contraseñas no coinciden');
            } else {
                this.setCustomValidity('');
            }
        });

        // Auto-ocultar mensajes flash después de 5 segundos
        setTimeout(() => {
            const flashMessages = document.querySelectorAll('.flash-message');
            flashMessages.forEach(msg => {
                msg.style.transition = 'opacity 0.5s ease';
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 500);
            });
        }, 5000);
    </script>

    <style>
        .account-info {
            display: grid;
            gap: 1rem;
        }
        
        .info-item {
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .status.active {
            color: #28a745;
            font-weight: 600;
        }
        
        .status.inactive {
            color: #dc3545;
            font-weight: 600;
        }
        
        .flash-message {
            margin-bottom: 1.5rem;
            padding: 1rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .flash-message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .flash-message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</body>
</html>