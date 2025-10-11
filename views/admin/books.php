<?php
// Incluir configuración de URL base
include __DIR__ . '/config.php';

// Verificar autenticación
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../models/Book.php';
require_once __DIR__ . '/../../config/Database.php';

use Controllers\AuthController;
use Models\Book;

if (!AuthController::isAdmin()) {
    header('Location: ../../login.php');
    exit();
}

$currentUser = AuthController::getCurrentUser();
$flashMessage = AuthController::getFlashMessage();

// Inicializar conexión a la base de datos
$database = new \Database();
$db = $database->getConnection();
$bookModel = new Book($db);

// Manejar acciones de eliminación
if (isset($_GET['book_action']) && $_GET['book_action'] === 'delete' && isset($_GET['id'])) {
    $bookId = intval($_GET['id']);
    if ($bookModel->delete($bookId)) {
        AuthController::setFlashMessage('Libro eliminado exitosamente', 'success');
    } else {
        AuthController::setFlashMessage('Error al eliminar el libro', 'error');
    }
    header('Location: index.php?page=admin&action=books');
    exit();
}

// Obtener todos los libros y estadísticas
try {
    $books = $bookModel->getAll(); // Corregido: usar getAll() en lugar de getAllBooks()
    $stats = [
        'total_books' => $bookModel->count(),
        'featured_books' => $bookModel->countFeatured(),
        'total_categories' => count($bookModel->getCategories())
    ];
} catch (Exception $e) {
    $books = [];
    $stats = ['total_books' => 0, 'featured_books' => 0, 'total_categories' => 0];
    error_log("Error cargando libros: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Libros - Admin Panel</title>
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

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.75rem 1.5rem;
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

        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            padding: 2rem;
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

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
        }

        .stat-info h3 {
            font-size: 2rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .stat-info p {
            color: #64748b;
            font-weight: 500;
        }

        /* Books Table */
        .books-section {
            padding: 0 2rem 2rem;
        }

        .books-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid #f1f5f9;
            overflow: hidden;
        }

        .books-header {
            padding: 2rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .books-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .books-table {
            width: 100%;
            border-collapse: collapse;
        }

        .books-table th,
        .books-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }

        .books-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .books-table tr:hover {
            background: #f8fafc;
        }

        .book-cover {
            width: 50px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .book-cover-placeholder {
            width: 50px;
            height: 60px;
            background: #f3f4f6;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
        }

        .book-title {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .book-author {
            color: #64748b;
            font-size: 0.9rem;
        }

        .book-price {
            font-weight: 600;
            color: #059669;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0.125rem;
        }

        .badge-featured {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
        }

        .badge-active {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .badge-inactive {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .book-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }

        .btn-edit {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }

        .btn-delete {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-edit:hover,
        .btn-delete:hover {
            transform: translateY(-2px);
        }

        /* Flash Messages */
        .flash-message {
            padding: 1rem 2rem;
            margin: 0;
            border: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .flash-success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .flash-error {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #d1d5db;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: #374151;
        }

        /* Responsive */
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

            .books-table {
                font-size: 0.9rem;
            }

            .books-table th,
            .books-table td {
                padding: 0.75rem;
            }
        }

        @media (max-width: 768px) {
            .content-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
                padding: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                padding: 1rem;
                gap: 1rem;
            }

            .books-section {
                padding: 0 1rem 1rem;
            }

            .books-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .books-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
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
            <!-- Flash Messages -->
            <?php if ($flashMessage): ?>
                <div class="flash-message flash-<?php echo htmlspecialchars($flashMessage['type']); ?>">
                    <i class="fas fa-<?php echo $flashMessage['type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <?php echo htmlspecialchars($flashMessage['message']); ?>
                </div>
            <?php endif; ?>

            <header class="content-header">
                <h1><i class="fas fa-book-open"></i> Gestión de Libros</h1>
                <a href="index.php?page=admin&action=books&book_action=create" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Agregar Libro
                </a>
            </header>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['total_books']); ?></h3>
                        <p>Total de Libros</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['featured_books']); ?></h3>
                        <p>Libros Destacados</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-tags"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['total_categories']); ?></h3>
                        <p>Categorías</p>
                    </div>
                </div>
            </div>

            <!-- Books List -->
            <div class="books-section">
                <div class="books-container">
                    <div class="books-header">
                        <h2><i class="fas fa-list"></i> Lista de Libros</h2>
                    </div>

                    <?php if (empty($books)): ?>
                        <div class="empty-state">
                            <i class="fas fa-book-open"></i>
                            <h3>No hay libros registrados</h3>
                            <p>Comienza agregando tu primer libro de Amazon</p>
                            <a href="index.php?page=admin&action=books&book_action=create" class="btn btn-primary" style="margin-top: 1rem;">
                                <i class="fas fa-plus"></i> Agregar Primer Libro
                            </a>
                        </div>
                    <?php else: ?>
                        <table class="books-table">
                            <thead>
                                <tr>
                                    <th>Portada</th>
                                    <th>Libro</th>
                                    <th>Categoría</th>
                                    <th>Precio</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($books as $book): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($book['cover_image'])): ?>
                                                <img src="<?php echo htmlspecialchars($book['cover_image']); ?>" 
                                                     alt="<?php echo htmlspecialchars($book['title']); ?>" 
                                                     class="book-cover">
                                            <?php else: ?>
                                                <div class="book-cover-placeholder">
                                                    <i class="fas fa-book"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="book-title"><?php echo htmlspecialchars($book['title']); ?></div>
                                            <div class="book-author">por <?php echo htmlspecialchars($book['author']); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($book['category']); ?></td>
                                        <td>
                                            <span class="book-price">$<?php echo number_format($book['price'], 2); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($book['is_featured']): ?>
                                                <span class="badge badge-featured">Destacado</span>
                                            <?php endif; ?>
                                            <span class="badge <?php echo $book['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                                <?php echo $book['is_active'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="book-actions">
                                                <a href="index.php?page=admin&action=books&book_action=edit&id=<?php echo $book['id']; ?>" 
                                                   class="btn btn-edit btn-sm">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="index.php?page=admin&action=books&book_action=delete&id=<?php echo $book['id']; ?>" 
                                                   class="btn btn-delete btn-sm"
                                                   onclick="return confirm('¿Estás seguro de que quieres eliminar este libro?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Animación de entrada para las tarjetas
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

            // Animación para la tabla
            const table = document.querySelector('.books-container');
            if (table) {
                table.style.opacity = '0';
                table.style.transform = 'translateY(30px)';
                
                setTimeout(() => {
                    table.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                    table.style.opacity = '1';
                    table.style.transform = 'translateY(0)';
                }, 300);
            }
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
            menuBtn.className = 'btn btn-primary';
            menuBtn.onclick = toggleSidebar;
            menuBtn.style.position = 'absolute';
            menuBtn.style.left = '1rem';
            menuBtn.style.top = '50%';
            menuBtn.style.transform = 'translateY(-50%)';
            header.style.position = 'relative';
            header.appendChild(menuBtn);
        }
    </script>
</body>
</html>
