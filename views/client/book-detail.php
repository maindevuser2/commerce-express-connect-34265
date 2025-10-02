<?php
session_start();
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../models/Book.php';

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
    <header class="main-header">
        <div class="header-container">
            <div class="logo">
                <img src="../../public/img/logo-profe-hernan.png" alt="Logo Profe Hernán">
                <span>El Profe Hernán</span>
            </div>
            <nav class="main-nav">
                <a href="home.php">Inicio</a>
                <a href="all-courses.php">Cursos</a>
                <a href="all-books.php" class="active">Libros</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="profile.php">Mi Perfil</a>
                    <a href="purchase-history.php">Mis Compras</a>
                    <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
                <?php else: ?>
                    <a href="../../login.php" class="btn-login">Iniciar Sesión</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

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
                                 alt="<?php echo htmlspecialchars($book['title']); ?>"
                                 class="book-cover-image">
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
                        <a href="<?php echo htmlspecialchars($book['amazon_url']); ?>" 
                           target="_blank" 
                           rel="noopener noreferrer"
                           class="btn-amazon">
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
