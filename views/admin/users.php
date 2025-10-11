<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir configuración de URL base
include __DIR__ . '/config.php';

// Verificar autenticación
require_once __DIR__ . '/../../controllers/AuthController.php';
use Controllers\AuthController;

if (!AuthController::isAdmin()) {
    header('Location: ../../login.php');
    exit();
}

// Obtener usuarios
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../models/User.php';
use Models\User;

$database = new \Database();
$db = $database->getConnection();

// --- INICIO DE DEPURACIÓN ---
echo "<!-- Debugging Database Connection and Users -->\n";
if ($db) {
    echo "<!-- Conexión a la base de datos establecida. -->\n";
    // Opcional: Probar la conexión explícitamente
    if ($database->testConnection()) {
        echo "<!-- Prueba de conexión a la base de datos exitosa. -->\n";
    } else {
        echo "<!-- FALLO: La prueba de conexión a la base de datos falló. Revisa las credenciales/servidor. -->\n";
    }
    $userModel = new User($db);
    $users = $userModel->readAll();
    echo "<!-- Número de usuarios obtenidos: " . count($users) . " -->\n";
    // Descomenta la siguiente línea para ver el contenido completo del array de usuarios:
    // echo "<pre>"; var_dump($users); echo "</pre>";
} else {
    echo "<!-- ERROR: La conexión a la base de datos es NULA. Revisa config/Database.php -->\n";
    $users = []; // Asegura que $users sea un array vacío si la conexión falla
}
echo "<!-- FIN DE DEPURACIÓN -->\n";
// --- FIN DE DEPURACIÓN ---

$currentUser = AuthController::getCurrentUser();

// Calcular estadísticas
$totalUsers = count($users);
$activeUsers = count(array_filter($users, function($user) { return $user['is_active']; }));
$adminUsers = count(array_filter($users, function($user) { return $user['role'] === 'admin'; }));
$regularUsers = $totalUsers - $adminUsers;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios - Admin Panel</title>
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

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            background: white;
            min-height: 100vh;
        }

        .content-header {
            background: white;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #f1f5f9;
        }

        .content-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .content-header h1 i {
            color: #6366f1;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            font-weight: 500;
            background: #f8fafc;
            padding: 0.75rem 1.25rem;
            border-radius: 50px;
            border: 1px solid #e2e8f0;
        }

        .user-info i {
            color: #6366f1;
            font-size: 1.1rem;
        }

        /* Users Content */
        .users-content {
            padding: 2rem;
        }

        /* Users Overview */
        .users-overview {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            padding: 2.5rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);
            position: relative;
            overflow: hidden;
        }

        .users-overview::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .users-overview h2 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            z-index: 1;
        }

        .overview-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            position: relative;
            z-index: 1;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.2);
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .stat-label {
            font-size: 0.95rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        /* Search Section */
        .search-section {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid #f1f5f9;
        }

        .search-box {
            position: relative;
            max-width: 500px;
            margin: 0 auto;
        }

        .search-box input {
            width: 100%;
            padding: 1.25rem 1.5rem 1.25rem 3.5rem;
            border: 2px solid #e2e8f0;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: #f8fafc;
        }

        .search-box input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
            background: white;
        }

        .search-box i {
            position: absolute;
            left: 1.5rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 1.2rem;
        }

        /* Users Table */
        .users-table-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid #f1f5f9;
            overflow: hidden;
        }

        .table-header {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            padding: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h3 {
            font-size: 1.25rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-count {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .enhanced-table {
            width: 100%;
            border-collapse: collapse;
        }

        .enhanced-table th,
        .enhanced-table td {
            padding: 1.5rem 1.25rem;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }

        .enhanced-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #1e293b;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .enhanced-table th i {
            margin-right: 0.5rem;
            color: #6366f1;
        }

        .table-row {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .table-row:hover {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), rgba(139, 92, 246, 0.05));
            transform: translateY(-2px);
        }

        .user-cell {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 1rem;
        }

        .user-email {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 2px;
        }

        .role-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .role-badge.admin {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .role-badge.user {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .status-badge.active {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .status-badge.inactive {
            background: linear-gradient(135deg, #64748b, #475569);
            color: white;
        }

        .date-cell {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
            font-weight: 500;
        }

        .date-cell i {
            color: #6366f1;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }

        .empty-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.5rem;
            color: #64748b;
        }

        .empty-content i {
            color: #6366f1;
            opacity: 0.5;
            font-size: 4rem;
        }

        .empty-content h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
        }

        .empty-content p {
            font-size: 1rem;
            max-width: 400px;
            line-height: 1.6;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .content-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
                padding: 1.5rem;
            }

            .content-header h1 {
                font-size: 1.5rem;
            }

            .users-content {
                padding: 1rem;
            }

            .users-overview {
                padding: 2rem;
            }

            .overview-stats {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 1rem;
            }

            .stat-card {
                padding: 1.5rem;
            }

            .stat-number {
                font-size: 2rem;
            }

            .search-section {
                padding: 1.5rem;
            }

            .table-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
                padding: 1.5rem;
            }

            .enhanced-table th,
            .enhanced-table td {
                padding: 1rem 0.75rem;
                font-size: 0.9rem;
            }

            .user-cell {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }

            .user-avatar {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
        }

        /* Smooth Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
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
            <header class="content-header">
                <h1><i class="fas fa-users"></i> Gestión de Usuarios</h1>
                <div class="user-info">
                    <i class="fas fa-user-circle"></i>
                    Bienvenido, <?php echo htmlspecialchars($currentUser['first_name'] ?? 'Admin'); ?>
                </div>
            </header>

            <div class="users-content">
                <div class="users-overview">
                    <h2><i class="fas fa-chart-bar"></i> Resumen de Usuarios</h2>
                    <div class="overview-stats">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $totalUsers; ?></div>
                            <div class="stat-label">Total Usuarios</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $activeUsers; ?></div>
                            <div class="stat-label">Usuarios Activos</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $adminUsers; ?></div>
                            <div class="stat-label">Administradores</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $regularUsers; ?></div>
                            <div class="stat-label">Usuarios Regulares</div>
                        </div>
                    </div>
                </div>

                <div class="search-section">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="userSearch" placeholder="Buscar usuarios por nombre o email...">
                    </div>
                </div>

                <div class="users-table-container">
                    <div class="table-header">
                        <h3><i class="fas fa-table"></i> Lista de Usuarios</h3>
                        <span class="user-count"><?php echo $totalUsers; ?> usuarios registrados</span>
                    </div>
                    
                    <div class="table-wrapper">
                        <table class="enhanced-table users-table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-user"></i> Usuario</th>
                                    <th><i class="fas fa-shield-alt"></i> Rol</th>
                                    <th><i class="fas fa-toggle-on"></i> Estado</th>
                                    <th><i class="fas fa-calendar"></i> Fecha de Registro</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($users)): ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr class="table-row">
                                            <td>
                                                <div class="user-cell">
                                                    <div class="user-avatar">
                                                        <?php echo htmlspecialchars(strtoupper(substr($user['first_name'], 0, 1))); ?>
                                                    </div>
                                                    <div class="user-details">
                                                        <span class="user-name"><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></span>
                                                        <span class="user-email"><?php echo htmlspecialchars($user['email']); ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="role-badge <?php echo $user['role']; ?>">
                                                    <i class="fas fa-<?php echo $user['role'] === 'admin' ? 'crown' : 'user'; ?>"></i>
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                                    <i class="fas fa-<?php echo $user['is_active'] ? 'check-circle' : 'times-circle'; ?>"></i>
                                                    <?php echo $user['is_active'] ? 'Activo' : 'Inactivo'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="date-cell">
                                                    <i class="fas fa-calendar"></i>
                                                    <span><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="empty-state">
                                            <div class="empty-content">
                                                <i class="fas fa-users"></i>
                                                <h3>No hay usuarios registrados</h3>
                                                <p>Aún no se han registrado usuarios en el sistema. Los nuevos usuarios aparecerán aquí automáticamente.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Funcionalidad de búsqueda
        document.getElementById('userSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.table-row');

            rows.forEach(row => {
                const userName = row.querySelector('.user-name').textContent.toLowerCase();
                const userEmail = row.querySelector('.user-email').textContent.toLowerCase();
                
                if (userName.includes(searchTerm) || userEmail.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Animación de entrada para las filas de la tabla
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('.table-row');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    row.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Animación de conteo para las estadísticas
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(number => {
                const finalValue = parseInt(number.textContent);
                if (finalValue > 0) {
                    animateNumber(number, 0, finalValue, 2000);
                }
            });
        });

        function animateNumber(element, start, end, duration) {
            const range = end - start;
            const increment = range / (duration / 16);
            let current = start;
            
            const timer = setInterval(() => {
                current += increment;
                if (current >= end) {
                    current = end;
                    clearInterval(timer);
                }
                element.textContent = Math.floor(current);
            }, 16);
        }

        // Responsive sidebar toggle
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('active');
        }

        // Add mobile menu button if needed
        if (window.innerWidth <= 1024) {
            const header = document.querySelector('.content-header');
            const menuBtn = document.createElement('button');
            menuBtn.innerHTML = '<i class="fas fa-bars"></i>';
            menuBtn.className = 'mobile-menu-btn';
            menuBtn.onclick = toggleSidebar;
            header.insertBefore(menuBtn, header.firstChild);
        }
    </script>
</body>
</html>
