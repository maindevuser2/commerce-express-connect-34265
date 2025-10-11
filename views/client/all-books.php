<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cargar dependencias
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../controllers/CartController.php';
require_once __DIR__ . '/../../models/Book.php';

use Controllers\AuthController;
use Controllers\CartController;
use Models\Book;

// Verificar autenticación
if (!AuthController::isAuthenticated()) {
    header('Location: ../../login.php');
    exit();
}

$database = new \Database();
$db = $database->getConnection();
$bookModel = new Book($db);

// Obtener usuario actual
$currentUser = AuthController::getCurrentUser();

// Obtener el conteo del carrito para el header
$cartController = new CartController();
$cart_count = $cartController->getCartCount();

// Función helper para obtener el nombre del usuario de forma segura
function getUserDisplayName($user) {
    if (isset($user['name']) && !empty($user['name'])) {
        return $user['name'];
    } elseif (isset($user['first_name']) && isset($user['last_name'])) {
        return trim($user['first_name'] . ' ' . $user['last_name']);
    } elseif (isset($user['first_name'])) {
        return $user['first_name'];
    } elseif (isset($user['email'])) {
        return explode('@', $user['email'])[0];
    } else {
        return 'Usuario';
    }
}

$userDisplayName = getUserDisplayName($currentUser);

// Obtener parámetros de filtrado y búsqueda
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 12; // Libros por página
$offset = ($page - 1) * $per_page;

// Obtener libros según los filtros
try {
    if (!empty($search)) {
        $books = $bookModel->search($search, $per_page);
        $totalBooks = count($bookModel->search($search)); // Para paginación exacta
    } elseif (!empty($category)) {
        $books = $bookModel->getByCategory($category, $per_page);
        $totalBooks = count($bookModel->getByCategory($category));
    } else {
        $books = $bookModel->getActive($per_page);
        $totalBooks = $bookModel->countActive();
    }
    
    $categories = $bookModel->getCategories();
} catch (Exception $e) {
    $books = [];
    $categories = [];
    $totalBooks = 0;
    error_log("Error cargando libros en all-books.php: " . $e->getMessage());
}

$totalPages = ceil($totalBooks / $per_page);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Todos los Libros - El Profesor Hernán</title>
    <link rel="stylesheet" href="../../public/css/styles.css">
    <link rel="stylesheet" href="../../public/css/client/all-books.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../public/img/logo-profe-hernan.png" alt="El Profesor Hernán" style="height: 40px;">
                <span>El Profesor Hernán</span>
            </div>
            
            <nav class="nav">
                <ul>
                    <li><a href="home.php">Inicio</a></li>
                    <li><a href="all-courses.php">Cursos</a></li>
                    <li><a href="all-sync-classes.php">Clases</a></li>
                    <li><a href="all-books.php" class="active">Libros</a></li>
                    <li><a href="cart.php">
                        <i class="fas fa-shopping-cart"></i>
                        Carrito
                        <?php if ($cart_count > 0): ?>
                            <span class="cart-count"><?php echo $cart_count; ?></span>
                        <?php endif; ?>
                    </a></li>
                </ul>
            </nav>
            
            <div class="auth-links">
                <div class="user-menu" style="position: relative;">
                    <button class="user-button" style="display: flex; align-items: center; gap: 0.5rem; background: transparent; border: 1px solid #ddd; padding: 0.5rem 1rem; border-radius: 8px; color: #333; cursor: pointer; transition: all 0.3s ease;" onclick="toggleUserMenu()">
                        <i class="fas fa-bars" style="font-size: 1.2rem;"></i>
                        <i class="fas fa-chevron-down" style="font-size: 0.8rem; transition: transform 0.3s;"></i>
                    </button>
                    <div class="user-dropdown" id="userDropdown" style="display: none; position: absolute; top: calc(100% + 10px); right: 0; background: white; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); min-width: 200px; z-index: 1000; overflow: hidden;">
                        <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                            <a href="../admin/index.php?controller=admin&action=dashboard" class="dropdown-item" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: #333; text-decoration: none; transition: background 0.2s;">
                                <i class="fas fa-cog"></i> Panel Admin
                            </a>
                        <?php endif; ?>
                        <a href="purchase-history.php" class="dropdown-item" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: #333; text-decoration: none; transition: background 0.2s;">
                            <i class="fas fa-graduation-cap"></i> Mis Cursos
                        </a>
                        <a href="profile.php" class="dropdown-item" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: #333; text-decoration: none; transition: background 0.2s;">
                            <i class="fas fa-user"></i> Mi Perfil
                        </a>
                        <a href="../../logout.php" class="dropdown-item" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: #dc3545; text-decoration: none; transition: background 0.2s; border-top: 1px solid #eee;">
                            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <style>
        .user-button:hover {
            background: #f8f9fa !important;
            border-color: #8a56e2 !important;
        }
        .user-button:hover .fa-chevron-down {
            transform: rotate(180deg);
        }
        .dropdown-item:hover {
            background: #f8f9fa !important;
        }
    </style>
    <script>
        function toggleUserMenu() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        }
        
        document.addEventListener('click', function(event) {
            const userMenu = document.querySelector('.user-menu');
            if (userMenu && !userMenu.contains(event.target)) {
                const dropdown = document.getElementById('userDropdown');
                if (dropdown) dropdown.style.display = 'none';
            }
        });
    </script>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <div class="page-header-content">
                <h1>Biblioteca de Libros</h1>
                <p>Descubre nuestra colección completa de libros especializados en el aprendizaje del inglés</p>
                <nav class="breadcrumb">
                    <a href="home.php">Inicio</a>
                    <span><i class="fas fa-chevron-right"></i></span>
                    <span>Libros</span>
                </nav>
            </div>
        </div>
    </section>

    <!-- Filters Section -->
    <section class="filters-section">
        <div class="container">
            <form method="GET" class="filters-form">
                <div class="search-box">
                    <input type="text" name="search" placeholder="Buscar por título, autor o descripción..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </div>
                
                <div class="category-filters">
                    <select name="category" onchange="this.form.submit()">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" 
                                    <?php echo $category === $cat ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (!empty($search) || !empty($category)): ?>
                    <a href="all-books.php" class="clear-filters">
                        <i class="fas fa-times"></i> Limpiar filtros
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </section>

    <!-- Books Grid -->
    <section class="books-grid-section">
        <div class="container">
            <?php if (!empty($search) || !empty($category)): ?>
                <div class="results-info">
                    <p>
                        <?php if (!empty($search)): ?>
                            Resultados para: "<strong><?php echo htmlspecialchars($search); ?></strong>"
                        <?php endif; ?>
                        <?php if (!empty($category)): ?>
                            Categoría: <strong><?php echo htmlspecialchars($category); ?></strong>
                        <?php endif; ?>
                        (<?php echo $totalBooks; ?> libro<?php echo $totalBooks !== 1 ? 's' : ''; ?> encontrado<?php echo $totalBooks !== 1 ? 's' : ''; ?>)
                    </p>
                </div>
            <?php endif; ?>

            <?php if (!empty($books)): ?>
                <div class="books-grid">
                    <?php foreach ($books as $book): ?>
                        <div class="book-card" data-book-id="<?php echo $book['id']; ?>">
                            <div class="book-cover">
                                <?php if (!empty($book['cover_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($book['cover_image']); ?>" 
                                         alt="<?php echo htmlspecialchars($book['title']); ?>" 
                                         loading="lazy">
                                <?php else: ?>
                                    <div class="book-placeholder">
                                        <i class="fas fa-book"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($book['is_featured']): ?>
                                    <div class="featured-badge">
                                        <i class="fas fa-star"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="book-info">
                                <div class="book-category"><?php echo htmlspecialchars($book['category']); ?></div>
                                <h3 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h3>
                                <p class="book-author">por <?php echo htmlspecialchars($book['author']); ?></p>
                                
                                <p class="book-description">
                                    <?php echo htmlspecialchars(substr($book['description'], 0, 120)) . (strlen($book['description']) > 120 ? '...' : ''); ?>
                                </p>
                                
                                <div class="book-price">
                                    <span class="current-price">$<?php echo number_format($book['price'], 2); ?></span>
                                </div>
                                
                                <div class="book-actions">
                                    <a href="book-detail.php?id=<?php echo $book['id']; ?>" class="btn-details">
                                        <i class="fas fa-info-circle"></i> Ver Detalles
                                    </a>
                                    <?php if (!empty($book['amazon_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($book['amazon_url']); ?>" 
                                           target="_blank" rel="noopener noreferrer" class="btn-amazon">
                                            <i class="fab fa-amazon"></i> Comprar
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Paginación -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>" 
                               class="pagination-btn">
                                <i class="fas fa-chevron-left"></i> Anterior
                            </a>
                        <?php endif; ?>

                        <div class="pagination-numbers">
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($totalPages, $page + 2);
                            
                            for ($i = $start; $i <= $end; $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>" 
                                   class="pagination-number <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </div>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>" 
                               class="pagination-btn">
                                Siguiente <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-results">
                    <div class="no-results-icon">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <h3>No se encontraron libros</h3>
                    <p>
                        <?php if (!empty($search) || !empty($category)): ?>
                            No hay libros que coincidan con los filtros seleccionados. 
                            <a href="all-books.php">Ver todos los libros</a>
                        <?php else: ?>
                            Actualmente no hay libros disponibles en nuestra biblioteca.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>El Profesor Hernán</h3>
                    <p>Transformando vidas a través del aprendizaje del inglés. Únete a miles de estudiantes que ya han mejorado su fluidez con nuestros cursos.</p>
                </div>
                <div class="footer-section">
                    <h3>Enlaces Rápidos</h3>
                    <ul>
                        <li><a href="home.php">Inicio</a></li>
                        <li><a href="all-courses.php">Cursos</a></li>
                        <li><a href="all-books.php">Libros</a></li>
                        <li><a href="profile.php">Mi Perfil</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Contacto</h3>
                    <p><i class="fas fa-envelope"></i> info@profesorhernan.com</p>
                    <p><i class="fas fa-phone"></i> +1 (555) 123-4567</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 El Profesor Hernán. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>
</body>
</html>