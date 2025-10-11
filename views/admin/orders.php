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

// Obtener pedidos
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../models/Order.php';
use Models\Order;

$database = new \Database();
$db = $database->getConnection();
$orderModel = new Order($db);
$orders = $orderModel->getAll() ?? [];

$currentUser = AuthController::getCurrentUser();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos - Admin Panel</title>
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

        /* Orders Content */
        .orders-content {
            padding: 2rem;
        }

        /* Filters Section */
        .filters-section {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 2rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 300px;
        }

        .search-box i {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 1.1rem;
        }

        .search-box input {
            width: 100%;
            padding: 1rem 1.25rem 1rem 3rem;
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

        .filter-controls {
            display: flex;
            gap: 1rem;
        }

        .filter-controls select {
            padding: 1rem 1.25rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 500;
            background: #f8fafc;
            color: #1e293b;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }

        .filter-controls select:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
            background: white;
        }

        .page-actions {
            display: flex;
            gap: 1rem;
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

        /* Orders Table */
        .orders-table-container {
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

        .order-count {
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

        .id-badge {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 700;
            display: inline-block;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
        }

        .customer-cell {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .customer-avatar {
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

        .customer-details {
            display: flex;
            flex-direction: column;
        }

        .customer-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 1rem;
        }

        .customer-id {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 2px;
        }

        .amount-cell {
            font-weight: 700;
            font-size: 1.2rem;
            color: #059669;
            display: flex;
            align-items: center;
            gap: 5px;
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

        .status-badge.pending {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
        }

        .status-badge.completed {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .status-badge.failed {
            background: linear-gradient(135deg, #ef4444, #dc2626);
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

            .orders-content {
                padding: 1rem;
            }

            .filters-section {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
                padding: 1.5rem;
            }

            .search-box {
                min-width: auto;
            }

            .filter-controls {
                flex-wrap: wrap;
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

            .customer-cell {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }

            .customer-avatar {
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
                <h1><i class="fas fa-shopping-cart"></i> Gestión de Pedidos</h1>
                <div class="user-info">
                    <i class="fas fa-user-circle"></i>
                    Bienvenido, <?php echo htmlspecialchars($currentUser['first_name'] ?? 'Admin'); ?>
                </div>
            </header>

            <div class="orders-content">
                <div class="filters-section">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="orderSearch" placeholder="Buscar por ID de pedido o nombre de cliente...">
                    </div>
                    <div class="filter-controls">
                        <select id="statusFilter">
                            <option value="">Todos los Estados</option>
                            <option value="pending">Pendiente</option>
                            <option value="completed">Completado</option>
                            <option value="failed">Fallido</option>
                        </select>
                    </div>
                </div>

                <div class="orders-table-container">
                    <div class="table-header">
                        <h3><i class="fas fa-table"></i> Lista de Pedidos</h3>
                        <span class="order-count"><?php echo count($orders); ?> pedidos registrados</span>
                    </div>
                    
                    <div class="table-wrapper">
                        <table class="enhanced-table orders-table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-hashtag"></i> ID</th>
                                    <th><i class="fas fa-user"></i> Cliente</th>
                                    <th><i class="fas fa-dollar-sign"></i> Monto</th>
                                    <th><i class="fas fa-info-circle"></i> Estado</th>
                                    <th><i class="fas fa-calendar"></i> Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($orders)): ?>
                                    <?php foreach ($orders as $order): ?>
                                        <tr class="table-row">
                                            <td>
                                                <span class="id-badge">#<?php echo htmlspecialchars($order['id']); ?></span>
                                            </td>
                                            <td>
                                                <div class="customer-cell">
                                                    <div class="customer-avatar">
                                                        <?php echo htmlspecialchars(strtoupper(substr($order['first_name'], 0, 1))); ?>
                                                    </div>
                                                    <div class="customer-details">
                                                        <span class="customer-name"><?php echo htmlspecialchars(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')); ?></span>
                                                        <span class="customer-id">Cliente ID: <?php echo htmlspecialchars($order['id']); ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="amount-cell">
                                                <i class="fas fa-dollar-sign"></i>
                                                <?php echo number_format($order['amount'], 2); ?>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo $order['status']; ?>">
                                                    <i class="fas fa-<?php 
                                                        echo $order['status'] === 'completed' ? 'check-circle' : 
                                                             ($order['status'] === 'pending' ? 'hourglass-half' : 'times-circle'); 
                                                    ?>"></i>
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="date-cell">
                                                    <i class="fas fa-calendar"></i>
                                                    <span><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="empty-state">
                                            <div class="empty-content">
                                                <i class="fas fa-shopping-cart"></i>
                                                <h3>No hay pedidos registrados</h3>
                                                <p>Aún no se han realizado pedidos en el sistema. Los nuevos pedidos aparecerán aquí automáticamente.</p>
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
        // Funcionalidad de búsqueda y filtros
        document.getElementById('orderSearch').addEventListener('input', filterOrders);
        document.getElementById('statusFilter').addEventListener('change', filterOrders);
        document.getElementById('dateFilter').addEventListener('change', filterOrders);

        function filterOrders() {
            const searchTerm = document.getElementById('orderSearch').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const dateFilter = document.getElementById('dateFilter').value;
            const rows = document.querySelectorAll('.table-row');

            rows.forEach(row => {
                const customerName = row.querySelector('.customer-name').textContent.toLowerCase();
                const orderId = row.querySelector('.id-badge').textContent.toLowerCase();
                const status = row.querySelector('.status-badge').className.includes('pending') ? 'pending' : 
                              row.querySelector('.status-badge').className.includes('completed') ? 'completed' : 'failed';
                
                const matchesSearch = customerName.includes(searchTerm) || orderId.includes(searchTerm);
                const matchesStatus = !statusFilter || status === statusFilter;
                
                if (matchesSearch && matchesStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function exportOrders() {
            // Implementar funcionalidad de exportación
            alert('Funcionalidad de exportación en desarrollo');
        }

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
        });

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
