<?php
// Incluir configuración de URL base
include __DIR__ . '/config.php';

// Verificar autenticación
require_once __DIR__ . '/../../controllers/AuthController.php';
use Controllers\AuthController;

if (!AuthController::isAdmin()) {
    header('Location: ../../login.php');
    exit();
}

// Cargar el modelo
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../models/AdminContactInfo.php';

$database = new \Database();
$db = $database->getConnection();
$contactModel = new \Models\AdminContactInfo($db);

// Manejar actualización
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'phone' => $_POST['phone'] ?? '',
        'instagram_url' => $_POST['instagram_url'] ?? '',
        'facebook_url' => $_POST['facebook_url'] ?? '',
        'youtube_url' => $_POST['youtube_url'] ?? '',
        'whatsapp_number' => $_POST['whatsapp_number'] ?? '',
        'whatsapp_channel' => $_POST['whatsapp_channel'] ?? ''
    ];

    if ($contactModel->update($data)) {
        $success = 'Información de contacto actualizada exitosamente';
    } else {
        $error = 'Error al actualizar la información de contacto';
    }
}

// Obtener información actual
$contactInfo = $contactModel->get();
$currentUser = AuthController::getCurrentUser();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Información de Contacto - Admin Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: #2d3748;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);
        }

        .sidebar-header {
            padding: 30px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-item {
            padding: 15px 25px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .menu-item:hover,
        .menu-item.active {
            background: rgba(255, 255, 255, 0.1);
            border-left-color: white;
            color: white;
        }

        .menu-item i {
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 40px;
        }

        .page-header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 2rem;
            color: #1a202c;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #718096;
            font-size: 1rem;
        }

        /* Form Container */
        .form-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        }

        .form-section {
            margin-bottom: 40px;
        }

        .form-section:last-child {
            margin-bottom: 0;
        }

        .form-section h3 {
            color: #1a202c;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #4a5568;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            color: #718096;
            font-size: 0.85rem;
        }

        .btn-submit {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }
        }

                /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 1rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 500;
            border-left: 3px solid transparent;
            position: relative;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 0;
            background: rgba(255, 255, 255, 0.1);
            transition: width 0.3s ease;
        }

        .nav-link:hover::before {
            width: 100%;
        }

        .nav-link:hover {
            color: white;
            transform: translateX(5px);
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border-left-color: #fbbf24;
            backdrop-filter: blur(10px);
        }

        .nav-link i {
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-graduation-cap"></i> Admin Panel</h2>
            </div>
            <nav class="sidebar-nav">
                <a href="<?php echo $baseUrl; ?>index.php?page=admin&action=dashboard" class="nav-link active">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="<?php echo $baseUrl; ?>index.php?page=admin&action=users" class="nav-link">
                    <i class="fas fa-users"></i> Usuarios
                </a>
                <a href="<?php echo $baseUrl; ?>index.php?page=admin&action=courses" class="nav-link">
                    <i class="fas fa-book"></i> Cursos
                </a>
                <a href="<?php echo $baseUrl; ?>index.php?page=admin&action=sync-classes" class="nav-link">
                    <i class="fas fa-video"></i> Clases Sincrónicas
                </a>
                <a href="<?php echo $baseUrl; ?>index.php?page=admin&action=orders" class="nav-link">
                    <i class="fas fa-shopping-cart"></i> Pedidos
                </a>
                <a href="<?php echo $baseUrl; ?>index.php?page=admin&action=books" class="nav-link">
                    <i class="fas fa-book-open"></i> Libros
                </a>
                <a href="<?php echo $baseUrl; ?>index.php?page=admin&action=contact-info" class="nav-link">
                    <i class="fas fa-address-book"></i> Información de Contacto
                </a>
                <a href="<?php echo $baseUrl; ?>index.php?page=admin&action=profile" class="nav-link">
                    <i class="fas fa-user-cog"></i> Mi Perfil
                </a>
                <a href="<?php echo $baseUrl; ?>logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1>Información de Contacto</h1>
                <p>Administra la información de contacto y redes sociales que se muestran en el sitio web</p>
            </div>

            <div class="form-container">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-section">
                        <h3><i class="fas fa-phone"></i> Información de Teléfono</h3>
                        
                        <div class="form-group">
                            <label for="phone">Número de Teléfono</label>
                            <input 
                                type="text" 
                                id="phone" 
                                name="phone" 
                                value="<?php echo htmlspecialchars($contactInfo['phone'] ?? ''); ?>" 
                                placeholder="573123456789"
                                required
                            >
                            <small>Este número se mostrará en tu perfil</small>
                        </div>

                        <div class="form-group">
                            <label for="whatsapp_number">Número de WhatsApp Personal</label>
                            <input 
                                type="text" 
                                id="whatsapp_number" 
                                name="whatsapp_number" 
                                value="<?php echo htmlspecialchars($contactInfo['whatsapp_number'] ?? ''); ?>" 
                                placeholder="573123456789"
                                required
                            >
                            <small>Formato: código de país + número (ej: 573123456789)</small>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><i class="fas fa-share-alt"></i> Redes Sociales</h3>
                        
                        <div class="form-group">
                            <label for="instagram_url">Instagram</label>
                            <input 
                                type="url" 
                                id="instagram_url" 
                                name="instagram_url" 
                                value="<?php echo htmlspecialchars($contactInfo['instagram_url'] ?? ''); ?>" 
                                placeholder="https://instagram.com/tu_usuario"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="facebook_url">Facebook</label>
                            <input 
                                type="url" 
                                id="facebook_url" 
                                name="facebook_url" 
                                value="<?php echo htmlspecialchars($contactInfo['facebook_url'] ?? ''); ?>" 
                                placeholder="https://facebook.com/tu_pagina"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="youtube_url">YouTube</label>
                            <input 
                                type="url" 
                                id="youtube_url" 
                                name="youtube_url" 
                                value="<?php echo htmlspecialchars($contactInfo['youtube_url'] ?? ''); ?>" 
                                placeholder="https://youtube.com/@tu_canal"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="whatsapp_channel">Canal de WhatsApp</label>
                            <input 
                                type="url" 
                                id="whatsapp_channel" 
                                name="whatsapp_channel" 
                                value="<?php echo htmlspecialchars($contactInfo['whatsapp_channel'] ?? ''); ?>" 
                                placeholder="https://whatsapp.com/channel/..."
                            >
                            <small>URL del canal de WhatsApp (opcional)</small>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Auto-hide success messages after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>
