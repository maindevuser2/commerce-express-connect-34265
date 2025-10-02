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
                <span>Hola, <?php echo htmlspecialchars($userDisplayName); ?></span>
                <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                    <a href="../admin/index.php?controller=admin&action=dashboard" class="btn-admin">Panel Admin</a>
                <?php endif; ?>
                <a href="purchase-history.php" class="btn-history">Mis Cursos</a>
                <a href="profile.php" class="btn-profile">Mi Perfil</a>
                <a href="../../logout.php" class="btn-logout">Cerrar Sesión</a>
            </div>
        </div>
    </header>

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
                                    <button class="btn-details" onclick="showBookDetails(<?php echo $book['id']; ?>)">
                                        <i class="fas fa-info-circle"></i> Ver Detalles
                                    </button>
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

    <!-- Book Details Modal -->
    <div id="bookModal" class="book-modal">
        <div class="book-modal-content">
            <div class="book-modal-header">
                <h2 id="modalBookTitle">Detalles del Libro</h2>
                <button class="book-modal-close" onclick="closeBookModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="book-modal-body" id="modalBookContent">
                <!-- El contenido se carga dinámicamente -->
            </div>
        </div>
    </div>

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

    <script>
        // Función para mostrar detalles del libro en modal
        function showBookDetails(bookId) {
            document.getElementById('modalBookTitle').textContent = 'Cargando...';
            document.getElementById('modalBookContent').innerHTML = '<div class="loading">Cargando detalles del libro...</div>';
            document.getElementById('bookModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            // Hacer petición AJAX para obtener detalles
            fetch(`../../controllers/BookController.php?action=details&id=${bookId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        populateBookModal(data.book);
                    } else {
                        alert('Error al cargar los detalles del libro');
                        closeBookModal();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar los detalles del libro');
                    closeBookModal();
                });
        }

        // Función para poblar el modal con datos del libro
        function populateBookModal(book) {
            document.getElementById('modalBookTitle').textContent = book.title;
            
            const modalContent = document.getElementById('modalBookContent');
            modalContent.innerHTML = `
                <div class="book-details-grid">
                    <div class="book-details-cover">
                        <img src="${book.cover_image}" alt="${book.title}">
                    </div>
                    <div class="book-details-info">
                        <h2>${book.title}</h2>
                        <p class="book-details-author">por ${book.author}</p>
                        <div class="book-details-price">
                            <span class="book-details-current-price">$${parseFloat(book.price).toFixed(2)}</span>
                        </div>
                        <div class="book-details-meta">
                            ${book.category ? `<div class="book-meta-item"><div class="book-meta-label">Categoría</div><div class="book-meta-value">${book.category}</div></div>` : ''}
                            ${book.publication_date ? `<div class="book-meta-item"><div class="book-meta-label">Publicación</div><div class="book-meta-value">${new Date(book.publication_date).getFullYear()}</div></div>` : ''}
                        </div>
                    </div>
                </div>
                <div class="book-description">
                    <h3>Descripción</h3>
                    <p>${book.description || 'Sin descripción disponible.'}</p>
                </div>
                <div class="book-modal-actions">
                    <a href="${book.amazon_url}" target="_blank" class="btn-buy-amazon">
                        <i class="fab fa-amazon"></i>
                        Comprar en Amazon
                    </a>
                </div>
            `;
        }

        // Función para cerrar el modal
        function closeBookModal() {
            document.getElementById('bookModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Cerrar modal al hacer clic fuera de él
        window.onclick = function(event) {
            const modal = document.getElementById('bookModal');
            if (event.target === modal) {
                closeBookModal();
            }
        }

        // Cerrar modal con la tecla Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeBookModal();
            }
        });
    </script>
</body>
</html>