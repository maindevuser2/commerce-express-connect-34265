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

// Obtener datos básicos
require_once __DIR__ . '/../../config/Database.php';

$database = new \Database();
$db = $database->getConnection();

// Estadísticas básicas
$stats = [
    'total_users' => 0,
    'total_courses' => 0,
    'total_orders' => 0,
    'total_revenue' => 0,
    'total_books' => 0
];

try {
    // Total usuarios
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
    $stmt->execute();
    $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total cursos
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM playlists");
    $stmt->execute();
    $stats['total_courses'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total pedidos
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM orders");
    $stmt->execute();
    $stats['total_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Ingresos totales
    $stmt = $db->prepare("SELECT SUM(amount) as total FROM orders WHERE status = 'completed'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_revenue'] = $result['total'] ?? 0;
    
    // Total libros
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM books WHERE is_active = 1");
    $stmt->execute();
    $stats['total_books'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
} catch (Exception $e) {
    error_log("Error obteniendo estadísticas: " . $e->getMessage());
}

$currentUser = AuthController::getCurrentUser();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin Panel</title>
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

        /* Dashboard Stats */
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            padding: 2rem;
            margin-bottom: 1rem;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid #f1f5f9;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(99, 102, 241, 0.15);
        }

        .stat-card:nth-child(1) .stat-icon {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }

        .stat-card:nth-child(2) .stat-icon {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .stat-card:nth-child(3) .stat-icon {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .stat-card:nth-child(4) .stat-icon {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .stat-card:nth-child(5) .stat-icon {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            margin-bottom: 1.5rem;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .stat-info h3 {
            font-size: 2.5rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 0.5rem;
            line-height: 1;
        }

        .stat-info p {
            color: #64748b;
            font-size: 1rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Dashboard Content */
        .dashboard-content {
            padding: 0 2rem 2rem;
        }

        .content-section {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid #f1f5f9;
            margin-bottom: 2rem;
        }

        .content-section h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .content-section h2 i {
            color: #6366f1;
        }

        .content-section p {
            color: #64748b;
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.4);
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

            .dashboard-stats {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                padding: 1rem;
                gap: 1.5rem;
            }

            .stat-card {
                padding: 1.5rem;
            }

            .stat-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }

            .stat-info h3 {
                font-size: 2rem;
            }

            .dashboard-content {
                padding: 0 1rem 1rem;
            }

            .content-section {
                padding: 2rem;
            }

            .quick-actions {
                flex-direction: column;
            }

            .btn {
                justify-content: center;
            }
        }

        /* Loading Animation */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .loading {
            animation: pulse 2s infinite;
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
                <h1><i class="fas fa-chart-line"></i> Dashboard</h1>
                <div class="user-info">
                    <i class="fas fa-user-circle"></i>
                    Bienvenido, <?php echo htmlspecialchars($currentUser['first_name'] ?? 'Admin'); ?>
                </div>
            </header>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['total_users']); ?></h3>
                        <p>Usuarios Totales</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['total_courses']); ?></h3>
                        <p>Cursos Totales</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['total_orders']); ?></h3>
                        <p>Pedidos Totales</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3>$<?php echo number_format($stats['total_revenue'], 0); ?></h3>
                        <p>Ingresos Totales</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['total_books'] ?? 0); ?></h3>
                        <p>Libros Publicados</p>
                    </div>
                </div>
            </div>

            <div class="dashboard-content">
                <div class="content-section">
                    <h2><i class="fas fa-info-circle"></i> Resumen del Sistema</h2>
                    <p>Bienvenido al panel de administración moderno. Aquí puedes gestionar usuarios, cursos y pedidos de manera eficiente con una interfaz intuitiva y amigable.</p>
                    
                    <div class="quick-actions">
                        <a href="<?php echo $baseUrl; ?>index.php?page=admin&action=users" class="btn btn-primary">
                            <i class="fas fa-users"></i> Gestionar Usuarios
                        </a>
                        <a href="<?php echo $baseUrl; ?>index.php?page=admin&action=courses" class="btn btn-secondary">
                            <i class="fas fa-book"></i> Gestionar Cursos
                        </a>
                        <a href="<?php echo $baseUrl; ?>index.php?page=admin&action=orders" class="btn btn-success">
                            <i class="fas fa-shopping-cart"></i> Ver Pedidos
                        </a>
                        <a href="<?php echo $baseUrl; ?>index.php?page=admin&action=books" class="btn btn-success">
                            <i class="fas fa-book-open"></i> Gestionar Libros
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Animación de entrada para las tarjetas de estadísticas
        document.addEventListener('DOMContentLoaded', function() {
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 150);
            });

            // Efecto de conteo animado para los números
            const numbers = document.querySelectorAll('.stat-info h3');
            numbers.forEach(number => {
                const finalValue = parseInt(number.textContent.replace(/[^0-9]/g, ''));
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
                
                const prefix = element.textContent.includes('$') ? '$' : '';
                element.textContent = prefix + Math.floor(current).toLocaleString();
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
