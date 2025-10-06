<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cargar dependencias
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../models/User.php';
require_once 'config.php';

use Controllers\AuthController;
use Models\User;

// Verificar autenticación de administrador
if (!AuthController::isAdmin()) {
    header('Location: ../../login.php');
    exit();
}

$database = new \Database();
$db = $database->getConnection();
$userModel = new User($db);

// Cargar modelo de información de contacto
require_once __DIR__ . '/../../models/AdminContactInfo.php';
$contactModel = new \Models\AdminContactInfo($db);

if (!isset($_SESSION['user']) && isset($_SESSION['user_id'])) {
    $_SESSION['user'] = [
        'id' => $_SESSION['user_id'],
        'email' => $_SESSION['user_email'] ?? '',
        'first_name' => $_SESSION['user_first_name'] ?? '',
        'last_name' => $_SESSION['user_last_name'] ?? '',
        'name' => $_SESSION['user_name'] ?? '',
        'role' => $_SESSION['user_role'] ?? 'user'
    ];
}

// Obtener usuario actual
$currentUser = $_SESSION['user'] ?? null;

if (!$currentUser || !isset($currentUser['id'])) {
    // No hay sesión válida o falta el ID del usuario
    header('Location: ../../login.php');
    exit();
}

$currentUserId = $currentUser['id'];
$userId = $currentUser['id'] ?? 0; // <- esta línea debe ir aquí, antes de usarse

$refreshed = $userModel->findById($userId);
if ($refreshed) {
    $currentUser = $refreshed;
    $_SESSION['user'] = $currentUser;
}

$userId = $currentUser['id'] ?? 0;

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
        $error = '¡Todos los campos son obligatorios!';
    } elseif ($newPassword !== $confirmPassword) {
        $error = '¡Las nuevas contraseñas no coinciden!';
    } elseif (strlen($newPassword) < 6) {
        $error = '¡La nueva contraseña debe tener al menos 6 caracteres!';
    } else {
        // Verificar contraseña actual
        $userData = $userModel->findByEmail($currentUser['email']);
        if ($userData && password_verify($currentPassword, $userData['password'])) {
            // Actualizar contraseña
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$hashedPassword, $userId])) {
                $success = '¡Contraseña actualizada exitosamente! 🎉';
                // Refrescar datos del usuario
                $currentUser = AuthController::getCurrentUser();
            } else {
                $error = '¡Error al actualizar la contraseña! Inténtalo de nuevo.';
            }
        } else {
            $error = '¡La contraseña actual es incorrecta!';
        }
    }
}

// Procesar actualización de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    // Validaciones básicas
    if (empty($firstName) || empty($lastName) || empty($email)) {
        $error = '¡Todos los campos del perfil son obligatorios!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '¡El email no tiene un formato válido!';
    } else {
        // Verificar si el email ya existe (excepto el actual)
        $existingUser = $userModel->findByEmail($email);
        if ($existingUser && $existingUser['id'] != $userId) {
            $error = '¡Este email ya está en uso por otro usuario!';
        } else {
            // Actualizar perfil
            $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$firstName, $lastName, $email, $userId])) {
                // Actualizar teléfono en admin_contact_info
                if (!empty($phone)) {
                    $contactInfo = $contactModel->get();
                    $contactData = [
                        'phone' => $phone,
                        'instagram_url' => $contactInfo['instagram_url'] ?? 'https://instagram.com/profehernan',
                        'facebook_url' => $contactInfo['facebook_url'] ?? 'https://facebook.com/profehernan',
                        'youtube_url' => $contactInfo['youtube_url'] ?? 'https://youtube.com/@profehernan',
                        'whatsapp_number' => $contactInfo['whatsapp_number'] ?? $phone
                    ];
                    $contactModel->update($contactData);
                }
                
                $success = '¡Perfil actualizado exitosamente! 🎊';
                // Actualizar datos en sesión
                $_SESSION['user']['first_name'] = $firstName;
                $_SESSION['user']['last_name'] = $lastName;
                $_SESSION['user']['email'] = $email;

                // Obtener datos frescos de la base de datos
                $currentUser = $userModel->findById($userId);
                $_SESSION['user'] = $currentUser;
            } else {
                $error = '¡Error al actualizar el perfil! Inténtalo de nuevo.';
            }
        }
    }
}

// Obtener información de contacto actual
$contactInfo = $contactModel->get();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        
        .profile-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 0;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: white;
            color: #4a5568;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
            border: 1px solid #e2e8f0;
        }
        
        .back-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            background: #f7fafc;
            color: #2d3748;
        }
        
        .profile-header {
            background: white;
            color: #2d3748;
            padding: 2rem;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 600;
            margin: 0 auto 1rem;
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .profile-header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            color: #2d3748;
            font-weight: 600;
        }
        
        .profile-header p {
            font-size: 1rem;
            color: #718096;
            margin-bottom: 1rem;
        }
        
        .admin-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.9rem;
            box-shadow: 0 2px 10px rgba(66, 153, 225, 0.3);
        }
        
        .flash-message {
            margin-bottom: 1.5rem;
            padding: 1rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
            font-size: 0.95rem;
            border: 1px solid;
        }
        
        .flash-message.success {
            background: #f0fff4;
            color: #22543d;
            border-color: #68d391;
        }
        
        .flash-message.error {
            background: #fed7d7;
            color: #c53030;
            border-color: #fc8181;
        }
        
        .profile-sections {
            display: grid;
            gap: 1.5rem;
        }
        
        .profile-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
        }
        
        .section-title {
            color: #2d3748;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .section-title i {
            color: #4299e1;
            font-size: 1.3rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #4a5568;
            font-size: 0.95rem;
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid #cbd5e0;
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
            background: white;
            transition: all 0.2s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }
        
        .input-with-icon i {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            font-size: 1rem;
        }
        
        .toggle-password {
            right: 0.75rem !important;
            left: auto !important;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .toggle-password:hover {
            color: #4a5568;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .btn {
            background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: inherit;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(66, 153, 225, 0.4);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .account-info {
            display: grid;
            gap: 1rem;
        }
        
        .info-item {
            padding: 1rem;
            background: #f7fafc;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #e2e8f0;
        }
        
        .info-item strong {
            color: #4a5568;
            font-size: 0.95rem;
        }
        
        .status.active {
            color: #38a169;
            font-weight: 500;
        }
        
        .status.inactive {
            color: #e53e3e;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .profile-header {
                padding: 2rem;
            }
            
            .profile-header h1 {
                font-size: 1.8rem;
            }
            
            .profile-section {
                padding: 1.5rem;
            }
        }
        
        /* Elementos decorativos */
        .floating-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }
        
        .shape {
            position: absolute;
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
        }
        
        .shape:nth-child(1) {
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .shape:nth-child(2) {
            top: 20%;
            right: 10%;
            animation-delay: 2s;
        }
        
        .shape:nth-child(3) {
            bottom: 10%;
            left: 20%;
            animation-delay: 4s;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
    </style>
</head>
<body>

    <div class="profile-container">
        <a href="<?php echo $baseUrl; ?>index.php?page=admin&action=dashboard" class="back-link">
            <i class="fas fa-arrow-left"></i>
            ¡Volver al Dashboard!
        </a>
        
        <div class="profile-header">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($currentUser['first_name'] ?? 'A', 0, 1)); ?>
            </div>
            <h1>¡Hola <?php echo htmlspecialchars(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')); ?>! 👋</h1>
            <p><?php echo htmlspecialchars($currentUser['email'] ?? ''); ?></p>
            <div class="admin-badge">
                <i class="fas fa-crown"></i>
                ¡Súper Admin!
            </div>
        </div>

        <?php if ($error): ?>
            <div class="flash-message error">
                <i class="fas fa-exclamation-triangle"></i>
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
                    <i class="fas fa-user-edit"></i>
                    ¡Actualiza tu Información!
                </h2>
                
                <form method="POST">
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
                    
                    <div class="form-group">
                        <label for="phone" class="form-label">Número de Teléfono</label>
                        <div class="input-with-icon">
                            <i class="fas fa-phone"></i>
                            <input 
                                type="text"
                                id="phone" 
                                name="phone" 
                                class="form-input"
                                value="<?php echo htmlspecialchars($contactInfo['phone'] ?? ''); ?>"
                                placeholder="573123456789"
                            >
                        </div>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn">
                        <i class="fas fa-save"></i>
                        ¡Guardar Cambios!
                    </button>
                </form>
            </div>

            <!-- Cambiar Contraseña -->
            <div class="profile-section">
                <h2 class="section-title">
                    <i class="fas fa-key"></i>
                    ¡Cambia tu Contraseña!
                </h2>
                
                <form method="POST">
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
                    
                    <button type="submit" name="change_password" class="btn">
                        <i class="fas fa-shield-alt"></i>
                        ¡Cambiar Contraseña!
                    </button>
                </form>
            </div>

            <!-- Información de la Cuenta -->
            <div class="profile-section">
                <h2 class="section-title">
                    <i class="fas fa-info-circle"></i>
                    ¡Información de tu Cuenta!
                </h2>
                
                <div class="account-info">
                    <div class="info-item">
                        <strong>📅 Fecha de Registro:</strong>
                        <span><?php echo date('d/m/Y', strtotime($currentUser['created_at'] ?? 'now')); ?></span>
                    </div>
                    <div class="info-item">
                        <strong>✅ Estado:</strong>
                        <span class="status <?php echo ($currentUser['is_active'] ?? 1) ? 'active' : 'inactive'; ?>">
                            <?php echo ($currentUser['is_active'] ?? 1) ? '¡Activa!' : 'Inactiva'; ?>
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
                msg.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                msg.style.opacity = '0';
                msg.style.transform = 'translateX(100%)';
                setTimeout(() => msg.remove(), 500);
            });
        }, 5000);

        // Efectos de partículas al hacer clic
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('btn')) {
                createParticles(e.pageX, e.pageY);
            }
        });

        function createParticles(x, y) {
            const particles = ['⭐', '🌟', '✨', '💫', '🎉'];
            for (let i = 0; i < 5; i++) {
                const particle = document.createElement('div');
                particle.innerHTML = particles[Math.floor(Math.random() * particles.length)];
                particle.style.position = 'fixed';
                particle.style.left = x + 'px';
                particle.style.top = y + 'px';
                particle.style.pointerEvents = 'none';
                particle.style.fontSize = '20px';
                particle.style.zIndex = '9999';
                particle.style.transition = 'all 1s ease-out';
                document.body.appendChild(particle);
                
                setTimeout(() => {
                    particle.style.transform = `translate(${(Math.random() - 0.5) * 200}px, ${(Math.random() - 0.5) * 200}px)`;
                    particle.style.opacity = '0';
                }, 10);
                
                setTimeout(() => particle.remove(), 1000);
            }
        }
    </script>
</body>
</html>