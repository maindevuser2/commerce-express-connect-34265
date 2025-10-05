<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cargar dependencias
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../models/Playlist.php';
require_once __DIR__ . '/../../models/UserCourse.php';
require_once __DIR__ . '/../../controllers/CartController.php';
require_once __DIR__ . '/../../models/Book.php';

use Controllers\AuthController;
use Models\Playlist;
use Models\UserCourse;
use Controllers\CartController;
use Models\Book;

// Verificar autenticación
if (!AuthController::isAuthenticated()) {
    header('Location: ../../login.php'); // Redirigir a login si no está autenticado
    exit();
}

$database = new \Database();
$db = $database->getConnection();
$playlistModel = new Playlist($db);
$userCourseModel = new UserCourse($db);
$bookModel = new Book($db);

// Obtener usuario actual
$currentUser = AuthController::getCurrentUser();
$userId = $currentUser['id'] ?? 0;

// Verificar que el usuario existe y tiene datos válidos
if (!$currentUser || !isset($currentUser['id'])) {
    AuthController::logout(); // Cerrar sesión si los datos están corruptos
    exit();
}

// Obtener todos los cursos
$allPlaylists = $playlistModel->readAll();

// Obtener cursos ya comprados por el usuario
$purchasedCourses = $userCourseModel->readByUserId($userId);
$purchasedIds = array_column($purchasedCourses, 'playlist_id');

// Filtrar cursos disponibles (no comprados)
$availablePlaylists = array_filter($allPlaylists, function($playlist) use ($purchasedIds) {
    return !in_array($playlist['id'], $purchasedIds);
});

// Obtener las 3 playlists más vendidas (o simplemente las primeras 3 si no hay lógica de ventas)
// NOTA: Para una lógica real de "más vendidos", necesitarías un campo en la DB o un sistema de seguimiento.
// Por ahora, se toman los primeros 3 cursos disponibles o comprados.
$best_sellers = array_slice(array_merge($purchasedCourses, $availablePlaylists), 0, 3);
shuffle($best_sellers); // Mezclar para simular "más vendidos" si no hay datos reales

// Obtener playlists por nivel para la sección "Nuestros Cursos por Nivel"
$playlists_by_level = [];
foreach ($allPlaylists as $playlist) {
    $level = $playlist['level'] ?? 'Mixto'; // Usar el campo 'level' directamente
    $playlists_by_level[$level][] = $playlist;
}

// Ordenar los niveles para que A1, A2, B1, B2, C1 aparezcan primero
$ordered_levels = ['A1', 'A2', 'B1', 'B2', 'C1', 'Mixto'];
$sorted_playlists_by_level = [];
foreach ($ordered_levels as $level) {
    if (isset($playlists_by_level[$level])) {
        $sorted_playlists_by_level[$level] = $playlists_by_level[$level];
    }
}

// Obtener el conteo del carrito para el header
$cartController = new CartController();
require_once __DIR__ . '/../../controllers/SyncClassController.php';
$syncCartCount = \Controllers\SyncClassController::getCartCount();
$cart_count = $cartController->getCartCount() + $syncCartCount;

// Obtener mensaje flash si existe
$flashMessage = AuthController::getFlashMessage();

// Función helper para obtener el nombre del usuario de forma segura
function getUserDisplayName($user) {
    if (isset($user['name']) && !empty($user['name'])) {
        return $user['name'];
    } elseif (isset($user['first_name']) && isset($user['last_name'])) {
        return trim($user['first_name'] . ' ' . $user['last_name']);
    } elseif (isset($user['first_name'])) {
        return $user['first_name'];
    } elseif (isset($user['email'])) {
        return explode('@', $user['email'])[0]; // Usar la parte antes del @ del email
    } else {
        return 'Usuario';
    }
}

$userDisplayName = getUserDisplayName($currentUser);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>El Profesor Hernán - Cursos de Inglés</title>
    <link rel="stylesheet" href="../../public/css/styles.css">
        <link rel="stylesheet" href="../../public/css/client/book-section.css">
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
                    <li><a href="home.php" class="active">Inicio</a></li>
                    <li><a href="all-courses.php">Cursos</a></li>
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
                <a href="purchase-history.php" class="btn-history active">Mis Cursos</a>
                <a href="profile.php" class="btn-profile">Mi Perfil</a>
                <a href="../../logout.php" class="btn-logout">Cerrar Sesión</a>
            </div>
        </div>
    </header>

    <!-- Banner Section -->
    <section class="banner">
        <div class="container">
            <div class="banner-content">
                <div class="banner-text">
                    <h1>Domina el Inglés con Nuestros Cursos Online</h1>
                    <p>Aprende a tu propio ritmo con lecciones interactivas, profesores expertos y una comunidad de apoyo. ¡Tu fluidez comienza aquí!</p>
                    <div class="banner-buttons">
                        <a href="#best-sellers" class="btn-primary">Explorar Cursos</a>
                        <a href="#about-section" class="btn-secondary">Conocer al Profesor</a>
                    </div>
                </div>
                <div class="banner-image">
                    <div class="image-container">
                        <img src="../../public/img/hero-image.png" alt="Profesor Hernán enseñando inglés">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Best Sellers Section -->
    <section class="best-sellers" id="best-sellers">
        <div class="container">
            <h2>Nuestros Cursos Más Populares</h2>
            <p class="section-subtitle">Descubre los cursos que nuestros estudiantes aman y que te ayudarán a alcanzar tus metas.</p>
            <div class="products-grid">
                <?php foreach ($best_sellers as $playlist): ?>
                    <?php 
                        $hasAccess = $userCourseModel->hasAccess($userId, $playlist['id']);
                        $imageUrl = 'https://via.placeholder.com/300x200/8a56e2/ffffff?text=Curso';
                        if (!empty($playlist['thumbnail'])) {
                            $imageUrl = '../../' . $playlist['thumbnail'];
                        } elseif (!empty($playlist['cover_image'])) {
                            $imageUrl = '../../' . $playlist['cover_image'];
                        }
                    ?>
                    <div class="product-card">
                        <div class="product-tumb">
                            <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                                alt="<?php echo htmlspecialchars($playlist['title'] ?? $playlist['name'] ?? 'Curso'); ?>">
                            <div class="course-overlay">
                                <?php if ($hasAccess): ?>
                                    <a href="course-detail.php?id=<?php echo htmlspecialchars($playlist['id']); ?>" class="btn-overlay">Acceder al Curso</a>
                                <?php else: ?>
                                    <a href="course-detail.php?id=<?php echo htmlspecialchars($playlist['id']); ?>" class="btn-overlay">Ver Detalles</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="product-details">
                            <span class="product-catagory">
                                <?php echo htmlspecialchars($playlist['level'] ?? 'General'); ?>
                            </span>
                            <h4>
                                <a href="course-detail.php?id=<?php echo htmlspecialchars($playlist['id']); ?>">
                                    <?php echo htmlspecialchars($playlist['title'] ?? $playlist['name'] ?? 'Curso sin título'); ?>
                                </a>
                            </h4>
                            <p><?php echo htmlspecialchars($playlist['description'] ?: 'Curso completo de inglés para todos los niveles.'); ?></p>
                            <div class="product-bottom-details">
                                <div class="product-price">
                                    $<?php echo htmlspecialchars(number_format($playlist['price'] ?? 0, 2)); ?>
                                </div>
                                <?php if ($hasAccess): ?>
                                    <a href="course-detail.php?id=<?php echo htmlspecialchars($playlist['id']); ?>" class="add-to-cart-btn">Acceder</a>
                                <?php else: ?>
                                    <button onclick="addToCart(<?php echo $playlist['id']; ?>)" class="add-to-cart-btn">Añadir al Carrito</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="view-more">
                <a href="all-courses.php">Ver todos los cursos <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </section>

    <!-- Sync Classes Section -->
    <?php
    // Obtener clases sincrónicas activas
    try {
        require_once __DIR__ . '/../../models/SyncClass.php';
        require_once __DIR__ . '/../../models/UserSyncClass.php';
        $syncClassModel = new \Models\SyncClass($db);
        $userSyncClassModel = new \Models\UserSyncClass($db);
        $activeSyncClasses = $syncClassModel->readActive();
    } catch (Exception $e) {
        $activeSyncClasses = [];
        error_log("Error cargando clases sincrónicas: " . $e->getMessage());
    }
    ?>
    
    <section class="best-sellers" style="background: #f8f9fa;">
        <div class="container">
            <h2>Clases Sincrónicas</h2>
            <p class="section-subtitle">Únete a nuestras clases en vivo y aprende en tiempo real con el profesor</p>
                <?php if (!empty($activeSyncClasses)): ?>
                <div class="products-grid">
                    <?php foreach ($activeSyncClasses as $syncClass): ?>
                        <?php 
                            $hasAccess = $userSyncClassModel->hasAccess($userId, $syncClass['id']);
                            $isPast = strtotime($syncClass['end_date']) < time();
                        ?>
                        <div class="product-card">
                            <div class="product-tumb">
                                <div style="background: linear-gradient(135deg, #8a56e2 0%, #56e2c6 100%); display: flex; align-items: center; justify-content: center; min-height: 200px;">
                                    <div style="text-align: center; color: white;">
                                        <i class="fas fa-video" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                                        <p style="font-size: 1.2rem; font-weight: 600;">Clase en Vivo</p>
                                    </div>
                                </div>
                                <?php if (!$isPast && !$hasAccess): ?>
                                <div class="course-overlay">
                                    <button onclick="addSyncClassToCart(<?php echo $syncClass['id']; ?>)" class="btn-overlay">Agregar al Carrito</button>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="product-details">
                                <span class="product-catagory">
                                    <?php if ($isPast): ?>
                                        <i class="fas fa-clock"></i> Finalizada
                                    <?php else: ?>
                                        <i class="fas fa-calendar"></i> <?php echo date('d M Y', strtotime($syncClass['start_date'])); ?>
                                    <?php endif; ?>
                                </span>
                                <h4>
                                    <?php echo htmlspecialchars($syncClass['title']); ?>
                                </h4>
                                <p><?php echo htmlspecialchars(substr($syncClass['description'] ?: 'Clase sincrónica en vivo', 0, 100)); ?></p>
                                <div class="product-bottom-details" style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                                    <?php if (!$hasAccess && !$isPast): ?>
                                        <div class="product-price">
                                            $<?php echo number_format($syncClass['price'], 2); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($hasAccess): ?>
                                        <div style="display: flex; gap: 8px; flex-direction: column; align-items: center; width: 100%;">
                                            <a href="<?php echo htmlspecialchars($syncClass['meeting_link']); ?>" target="_blank" class="add-to-cart-btn" style="font-size: 0.9rem; padding: 6px 12px; min-width: 120px; text-align: center;">Unirse a la Clase</a>
                                            <?php if (!empty($syncClass['whatsapp_group_link'])): ?>
                                            <a href="<?php echo htmlspecialchars($syncClass['whatsapp_group_link']); ?>" target="_blank" class="btn-ics-download" style="background: #25D366; color: white; font-size: 0.9rem; padding: 6px 12px; min-width: 120px; text-align: center;" title="Unirse al grupo de WhatsApp">
                                                <i class="fab fa-whatsapp"></i> Unirse al Grupo
                                            </a>
                                            <?php endif; ?>
                                            <?php
                                            // Google Calendar logic replicada de purchase-history.php
                                            $title = urlencode($syncClass['title']);
                                            $details = urlencode($syncClass['description']);
                                            $location = urlencode($syncClass['meeting_link']);
                                            $start = date('Ymd\THis', strtotime($syncClass['start_date']));
                                            $end = date('Ymd\THis', strtotime($syncClass['end_date']));
                                            $googleCalendarUrl = "https://www.google.com/calendar/render?action=TEMPLATE&text={$title}&dates={$start}/{$end}&details={$details}&location={$location}";
                                            ?>
                                            <a href="<?php echo $googleCalendarUrl; ?>" target="_blank" class="btn-ics-download" style="background: #4285F4; color: white; font-size: 0.9rem; padding: 6px 12px; min-width: 120px; text-align: center;" title="Agregar a Google Calendar">
                                                <i class="fas fa-calendar-plus"></i> Google Calendar
                                            </a>
                                        </div>
                                    <?php elseif ($isPast): ?>
                                        <button class="add-to-cart-btn" style="opacity: 0.5; cursor: not-allowed; font-size: 0.9rem; padding: 6px 12px; min-width: 120px;" disabled>Finalizada</button>
                                    <?php else: ?>
                                        <button onclick="addSyncClassToCart(<?php echo $syncClass['id']; ?>)" class="add-to-cart-btn" style="font-size: 0.9rem; padding: 6px 12px; min-width: 120px;">Agregar al Carrito</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem; background: white; border-radius: 12px;">
                    <i class="fas fa-calendar-times" style="font-size: 4rem; color: #ccc; margin-bottom: 1rem;"></i>
                    <h3>No hay clases sincrónicas programadas</h3>
                    <p>Pronto anunciaremos nuevas clases en vivo. ¡Mantente atento!</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Call to action for all courses -->
    <section class="view-all-courses">
        <div class="container">
            <div class="view-all-content">
                <h2>¿Listo para llevar tu inglés al siguiente nivel?</h2>
                <p>Explora nuestra biblioteca completa de cursos y encuentra el camino perfecto hacia la fluidez.</p>
                <a href="all-courses.php" class="btn-large">
                    <i class="fas fa-book-open"></i> Ver Todos los Cursos
                </a>
            </div>
        </div>
    </section>

    <!-- Books Section -->
    <section class="books-showcase">
        <div class="container">
            <div class="section-header">
                <h2>Libros Recomendados</h2>
                <p>Complementa tu aprendizaje con estos libros especializados disponibles en Amazon</p>
            </div>
            
            <?php
            // Obtener libros destacados
            try {
                $database = new \Database();
                $db = $database->getConnection();
                $bookModel = new Book($db);
                $featuredBooks = $bookModel->getFeatured(6); // Obtener máximo 6 libros destacados
            } catch (Exception $e) {
                $featuredBooks = [];
                error_log("Error cargando libros en index.php: " . $e->getMessage());
            }
            ?>
            
            <?php if (!empty($featuredBooks)): ?>
                <div class="books-showcase-grid">
                    <?php foreach ($featuredBooks as $book): ?>
                        <div class="book-showcase-card" data-book-id="<?php echo $book['id']; ?>">
                            <div class="book-showcase-cover">
                                <?php if (!empty($book['cover_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($book['cover_image']); ?>" 
                                         alt="<?php echo htmlspecialchars($book['title']); ?>" 
                                         loading="lazy">
                                <?php else: ?>
                                    <div class="book-showcase-placeholder">
                                        <i class="fas fa-book"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="book-showcase-info">
                                <div class="book-showcase-category"><?php echo htmlspecialchars($book['category']); ?></div>
                                <h3 class="book-showcase-title"><?php echo htmlspecialchars($book['title']); ?></h3>
                                <p class="book-showcase-author">por <?php echo htmlspecialchars($book['author']); ?></p>
                                <div class="book-showcase-price">
                                    <span class="showcase-current-price">$<?php echo number_format($book['price'], 2); ?></span>
                                </div>
                                <a href="book-detail.php?id=<?php echo $book['id']; ?>" class="btn-showcase-details">
                                    <i class="fas fa-info-circle"></i> Ver Detalles
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="section-footer">
                    <a href="all-books.php" class="btn-primary">Ver Más Libros</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- About Section -->
    <section class="about-section" id="about-section">
        <div class="container">
            <div class="about-content">
                <div class="about-text">
                    <h2>Conoce al Profesor Hernán</h2>
                    <p>Con más de 15 años de experiencia, el Profesor Hernán ha ayudado a miles de estudiantes a alcanzar sus metas en inglés. Su metodología se centra en la práctica constante, la inmersión cultural y un enfoque personalizado para cada alumno.</p>
                    <div class="about-features">
                        <div class="feature-item">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <div>
                                <h4>Metodología Comprobada</h4>
                                <p>Clases dinámicas y efectivas diseñadas para el aprendizaje rápido.</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-users"></i>
                            <div>
                                <h4>Comunidad de Apoyo</h4>
                                <p>Únete a una red de estudiantes y practica con hablantes nativos.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="about-image">
                    <img src="../../public/img/logo-profe-hernan.png" alt="Profesor Hernán">
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2024 El Profesor Hernán. Todos los derechos reservados.</p>
            <div class="footer-links">
                <a href="home.php">Inicio</a>
                <a href="all-courses.php">Cursos</a>
                <a href="cart.php">Carrito</a>
            </div>
            <p>Aprende inglés con los mejores cursos online</p>
        </div>
    </footer>

    <!-- Book Details Modal -->
    <div id="bookModal" class="book-modal">
        <div class="book-modal-content">
            <div class="book-modal-header">
                <button class="book-modal-close" onclick="closeBookModal()">&times;</button>
                <h2 id="modalBookTitle">Cargando...</h2>
            </div>
            <div class="book-modal-body">
                <div class="book-details-grid">
                    <div class="book-details-cover">
                        <img id="modalBookCover" src="/placeholder.svg" alt="Book Cover">
                    </div>
                    <div class="book-details-info">
                        <h2 id="modalBookTitleFull">Título del Libro</h2>
                        <p class="book-details-author" id="modalBookAuthor">Autor</p>
                        <div class="book-details-price">
                            <span class="book-details-current-price" id="modalBookPrice">$0.00</span>
                            <span class="book-details-original-price" id="modalBookOriginalPrice" style="display: none;">$0.00</span>
                        </div>
                        <div class="book-details-meta" id="modalBookMeta">
                            <!-- Meta information will be populated here -->
                        </div>
                    </div>
                </div>
                <div class="book-description">
                    <h3>Descripción</h3>
                    <p id="modalBookDescription">Descripción del libro...</p>
                </div>
                <div class="book-modal-actions">
                    <a id="modalAmazonLink" href="#" target="_blank" class="btn-buy-amazon">
                        <i class="fab fa-amazon"></i> Comprar en Amazon
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-auth.js"></script>
    <script src="../../public/js/smooth-scroll.js"></script>
    <script>
function addToCart(courseId) {
    // Obtener el botón que se clickeó
    const button = event.target;
    const originalHTML = button.innerHTML;
    
    // Mostrar estado de carga
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agregando...';
    button.disabled = true;

    // Crear FormData para enviar la solicitud
    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('id', courseId);

    fetch('../../controllers/CartController.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text();
    })
    .then(text => {
        console.log('Raw response:', text);
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Error parsing JSON:', e);
            throw new Error('Respuesta inválida del servidor');
        }
        
        if (data.status === 'success') {
            showNotification(data.message || 'Curso agregado al carrito exitosamente', 'success');
            updateCartCount();
            
            // Cambiar el botón a "Agregado"
            button.innerHTML = '<i class="fas fa-check"></i> Agregado';
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.disabled = false;
            }, 2000);
        } else {
            showNotification(data.message || 'Error al agregar al carrito', 'error');
            button.innerHTML = originalHTML;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error al agregar al carrito. Inténtalo de nuevo.', 'error');
        
        button.innerHTML = originalHTML;
        button.disabled = false;
    });
}

function showNotification(message, type) {
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(n => n.remove());

    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 2rem;
        right: 2rem;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        color: white;
        font-weight: 600;
        z-index: 1000;
        animation: slideIn 0.3s ease;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        ${type === 'success' ? 'background: #56e2c6;' : 'background: #ff5a5a;'}
    `;
    notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 4000);
}

function updateCartCount() {
    fetch('../../controllers/CartController.php?action=count')
        .then(response => response.json())
        .then(data => {
            const cartCounts = document.querySelectorAll('.cart-count');
            cartCounts.forEach(cartCount => {
                if (data.count !== undefined) {
                    cartCount.textContent = data.count;
                    cartCount.style.transform = 'scale(1.3)';
                    setTimeout(() => {
                        cartCount.style.transform = 'scale(1)';
                    }, 200);
                }
            });
        })
        .catch(error => {
            console.error('Error updating cart count:', error);
        });
}

function showBookDetails(bookId) {
    const modal = document.getElementById('bookModal');
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // Mostrar estado de carga
    document.getElementById('modalBookTitle').textContent = 'Cargando...';
    document.getElementById('modalBookTitleFull').textContent = 'Cargando...';
    
    // Hacer petición AJAX para obtener detalles
    fetch(`../../controllers/BookController.php?action=details&id=${bookId}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                populateBookModal(data.book);
            } else {
                showNotification('Error al cargar los detalles del libro', 'error');
                closeBookModal();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error al cargar los detalles del libro', 'error');
            closeBookModal();
        });
}

function populateBookModal(book) {
    document.getElementById('modalBookTitle').textContent = book.title;
    document.getElementById('modalBookTitleFull').textContent = book.title;
    document.getElementById('modalBookAuthor').textContent = `por ${book.author}`;
    document.getElementById('modalBookPrice').textContent = `$${parseFloat(book.price).toFixed(2)}`;
    document.getElementById('modalBookDescription').textContent = book.description || 'Sin descripción disponible.';
    
    // Enlace de Amazon con target="_blank"
    const amazonLink = document.getElementById('modalAmazonLink');
    amazonLink.href = book.amazon_url;
    amazonLink.target = '_blank';
    amazonLink.rel = 'noopener noreferrer';
    
    // Imagen de portada
    const coverImg = document.getElementById('modalBookCover');
    if (book.cover_image) {
        coverImg.src = book.cover_image;
        coverImg.alt = book.title;
    } else {
        coverImg.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjI1MCIgdmlld0JveD0iMCAwIDIwMCAyNTAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMjUwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0xMDAgMTI1QzEwNS41MjMgMTI1IDExMCAxMjAuNTIzIDExMCAxMTVDMTEwIDEwOS40NzcgMTA1LjUyMyAxMDUgMTAwIDEwNUM5NC40NzcgMTA1IDkwIDEwOS40NzcgOTAgMTE1QzkwIDEyMC41MjMgOTQuNDc3IDEyNSAxMDAgMTI1WiIgZmlsbD0iIzlDQTNBRiIvPgo8L3N2Zz4K';
        coverImg.alt = 'Sin imagen';
    }
    
    // Precio original
    const originalPriceEl = document.getElementById('modalBookOriginalPrice');
    if (book.original_price && parseFloat(book.original_price) > parseFloat(book.price)) {
        originalPriceEl.style.display = 'none';
    } else {
        originalPriceEl.style.display = 'none';
    }
    
    // Meta información
    const metaContainer = document.getElementById('modalBookMeta');
    let metaHTML = '';
    
    if (book.category) {
        metaHTML += `
            <div class="book-meta-item">
                <div class="book-meta-label">Categoría</div>
                <div class="book-meta-value">${book.category}</div>
            </div>
        `;
    }
    
    if (book.publication_date) {
        const date = new Date(book.publication_date);
        metaHTML += `
            <div class="book-meta-item">
                <div class="book-meta-label">Publicación</div>
                <div class="book-meta-value">${date.getFullYear()}</div>
            </div>
        `;
    }
    
    metaContainer.innerHTML = metaHTML;
}

function closeBookModal() {
    const modal = document.getElementById('bookModal');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Cerrar modal al hacer clic fuera de él
window.onclick = function(event) {
    const modal = document.getElementById('bookModal');
    if (event.target === modal) {
        closeBookModal();
    }
}

// Cerrar modal con tecla Escape
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeBookModal();
    }
});

// Función para agregar clases sincrónicas al carrito
function addSyncClassToCart(classId) {
    // Obtener el botón que se clickeó
    const button = event.target;
    const originalHTML = button.innerHTML;
    
    // Mostrar estado de carga
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agregando...';
    button.disabled = true;

    fetch('../../controllers/SyncClassController.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=add&id=${classId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showNotification(data.message || 'Clase agregada al carrito exitosamente', 'success');
            updateCartCount();
            
            // Cambiar el botón a "Agregado"
            button.innerHTML = '<i class="fas fa-check"></i> Agregado';
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.disabled = false;
            }, 2000);
        } else if (data.status === 'info') {
            showNotification(data.message, 'success');
            button.innerHTML = originalHTML;
            button.disabled = false;
        } else {
            showNotification(data.message || 'Error al agregar la clase', 'error');
            button.innerHTML = originalHTML;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error al agregar la clase al carrito', 'error');
        button.innerHTML = originalHTML;
        button.disabled = false;
    });
}
    </script>
</body>
</html>