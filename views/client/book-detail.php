<?php
session_start();
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../models/Book.php';

use Controllers\AuthController;

$database = new Database();
$db = $database->getConnection();
$bookModel = new Models\Book($db);

// Obtener ID del libro
$bookId = $_GET['id'] ?? 0;
$book = $bookModel->getById($bookId);

if (!$book) {
    header('Location: all-books.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($book['title']); ?> - Detalles del Libro</title>
    <link rel="stylesheet" href="../../public/css/styles.css">
    <link rel="stylesheet" href="../../public/css/client/book-detail.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="auth-links">
                <?php if (isset($_SESSION['user_id'])): ?>
                <?php 
                    require_once __DIR__ . '/../../controllers/AuthController.php';
                    $currentUser = AuthController::getCurrentUser();
                    $userDisplayName = $currentUser['name'] ?? 'Usuario';
                    ?>
                <div class="user-menu" style="position: relative;">
                    <button class="user-button"
                        style="display: flex; align-items: center; gap: 0.5rem; background: transparent; border: 1px solid #ddd; padding: 0.5rem 1rem; border-radius: 8px; color: #333; cursor: pointer; transition: all 0.3s ease;"
                        onclick="toggleUserMenu()">
                        <i class="fas fa-bars" style="font-size: 1.2rem;"></i>
                        <i class="fas fa-chevron-down" style="font-size: 0.8rem; transition: transform 0.3s;"></i>
                    </button>
                    <div class="user-dropdown" id="userDropdown"
                        style="display: none; position: absolute; top: calc(100% + 10px); right: 0; background: white; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); min-width: 200px; z-index: 1000; overflow: hidden;">
                        <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                        <a href="../admin/index.php?controller=admin&action=dashboard" class="dropdown-item"
                            style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: #333; text-decoration: none; transition: background 0.2s;">
                            <i class="fas fa-cog"></i> Panel Admin
                        </a>
                        <?php endif; ?>
                        <a href="purchase-history.php" class="dropdown-item"
                            style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: #333; text-decoration: none; transition: background 0.2s;">
                            <i class="fas fa-graduation-cap"></i> Mis Cursos
                        </a>
                        <a href="profile.php" class="dropdown-item"
                            style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: #333; text-decoration: none; transition: background 0.2s;">
                            <i class="fas fa-user"></i> Mi Perfil
                        </a>
                        <a href="../../logout.php" class="dropdown-item"
                            style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: #dc3545; text-decoration: none; transition: background 0.2s; border-top: 1px solid #eee;">
                            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                        </a>
                    </div>
                </div>
                <?php else: ?>
                    <a. href="login.php" class="btn-login">Iniciar Sesión</a>
                <?php endif; ?>
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

    <!-- Breadcrumbs -->
    <div class="breadcrumbs">
        <div class="container">
            <a href="home.php">Inicio</a>
            <span class="separator">/</span>
            <a href="all-books.php">Libros</a>
            <span class="separator">/</span>
            <span class="current"><?php echo htmlspecialchars($book['title']); ?></span>
        </div>
    </div>

    <!-- Book Detail -->
    <main class="book-detail-container">
        <div class="container">
            <div class="book-detail-grid">
                <!-- Book Cover -->
                <div class="book-cover-section">
                    <div class="book-cover-wrapper">
                        <?php if (!empty($book['cover_image'])): ?>
                        <img src="<?php echo htmlspecialchars($book['cover_image']); ?>"
                            alt="<?php echo htmlspecialchars($book['title']); ?>" class="book-cover-image">
                        <?php else: ?>
                        <div class="book-cover-placeholder">
                            <i class="fas fa-book"></i>
                            <p>Sin imagen</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Book Information -->
                <div class="book-info-section">
                    <h1 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h1>
                    <p class="book-author">
                        <i class="fas fa-user"></i>
                        por <?php echo htmlspecialchars($book['author']); ?>
                    </p>

                    <?php if (!empty($book['category'])): ?>
                    <div class="book-category">
                        <i class="fas fa-tag"></i>
                        <span><?php echo htmlspecialchars($book['category']); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($book['publication_date'])): ?>
                    <div class="book-publication">
                        <i class="fas fa-calendar"></i>
                        <span>Publicado: <?php echo date('Y', strtotime($book['publication_date'])); ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="book-price-section">
                        <span class="price-label">Precio:</span>
                        <span class="book-price">$<?php echo number_format($book['price'], 2); ?></span>
                    </div>

                    <div class="book-actions">
                        <a href="<?php echo htmlspecialchars($book['amazon_url']); ?>" target="_blank"
                            rel="noopener noreferrer" class="btn-amazon">
                            <i class="fab fa-amazon"></i>
                            Comprar en Amazon
                        </a>
                        <a href="all-books.php" class="btn-back">
                            <i class="fas fa-arrow-left"></i>
                            Volver a Libros
                        </a>
                    </div>
                </div>
            </div>

            <!-- Book Description -->
            <?php if (!empty($book['description'])): ?>
            <div class="book-description-section">
                <h2>Descripción</h2>
                <p><?php echo nl2br(htmlspecialchars($book['description'])); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="main-footer">
        <div class="footer-container">
            <p>&copy; <?php echo date('Y'); ?> Profe Hernán. Todos los derechos reservados.</p>
        </div>
    </footer>
</body>
</html>