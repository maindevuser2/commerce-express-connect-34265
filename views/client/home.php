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
require_once __DIR__ . '/../../models/AdminContactInfo.php';

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

// Obtener información de contacto
$contactModel = new \Models\AdminContactInfo($db);
$contactInfo = $contactModel->get();

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
                    <li><a href="#inicio">Inicio</a></li>
                    <li><a href="#best-sellers">Cursos</a></li>
                    <li><a href="#clases-privadas">Clases Privadas</a></li>
                    <li><a href="#sobre-nosotros">Sobre Nosotros</a></li>
                    <li><a href="all-courses.php">Todos los Cursos</a></li>
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
    <section id="inicio" class="banner">
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
                            $status = $syncClass['status'] ?? 'active';
                            
                            // Obtener horarios semanales
                            require_once __DIR__ . '/../../models/SyncClassSchedule.php';
                            $scheduleModel = new \Models\SyncClassSchedule($db);
                            $schedules = $scheduleModel->readBySyncClass($syncClass['id']);
                            
                            // Mapeo de estados a badges
                            $statusBadges = [
                                'active' => ['text' => 'Activo', 'color' => '#28a745'],
                                'upcoming' => ['text' => 'Por Empezar', 'color' => '#17a2b8'],
                                'ending_soon' => ['text' => 'Por Terminar', 'color' => '#ff6b6b'],
                                'finished' => ['text' => 'Finalizado', 'color' => '#6c757d']
                            ];
                            $statusInfo = $statusBadges[$status] ?? ['text' => 'Activo', 'color' => '#28a745'];
                            $showPrecompra = ($status === 'upcoming');
                        ?>
                        <div class="product-card">
                            <div class="product-tumb">
                                <div style="background: linear-gradient(135deg, #8a56e2 0%, #56e2c6 100%); display: flex; align-items: center; justify-content: center; min-height: 200px; position: relative;">
                                    <div style="text-align: center; color: white;">
                                        <i class="fas fa-video" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                                        <p style="font-size: 1.2rem; font-weight: 600;">Clase en Vivo</p>
                                    </div>
                                    <div style="position: absolute; top: 10px; right: 10px; display: flex; gap: 6px; flex-direction: column; align-items: flex-end;">
                                        <div style="background: <?php echo $statusInfo['color']; ?>; color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
                                            <?php echo $statusInfo['text']; ?>
                                        </div>
                                        <?php if ($showPrecompra): ?>
                                        <div style="background: rgba(255, 255, 255, 0.9); color: #8a56e2; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">
                                            Disponible en Pre-compra
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($status !== 'finished' && $status !== 'ending_soon' && !$hasAccess): ?>
                                <div class="course-overlay">
                                    <button onclick="addSyncClassToCart(<?php echo $syncClass['id']; ?>)" class="btn-overlay">Agregar al Carrito</button>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="product-details">
                                <span class="product-catagory">
                                    <i class="fas fa-calendar"></i> <?php echo date('d M Y', strtotime($syncClass['start_date'])); ?> - <?php echo date('d M Y', strtotime($syncClass['end_date'])); ?>
                                </span>
                                <h4>
                                    <?php echo htmlspecialchars($syncClass['title']); ?>
                                </h4>
                                <p><?php echo htmlspecialchars(substr($syncClass['description'] ?: 'Clase sincrónica en vivo', 0, 100)); ?></p>
                                <?php if (!empty($schedules)): ?>
                                <div style="margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 8px; font-size: 0.85rem;">
                                    <strong style="display: block; margin-bottom: 5px;"><i class="fas fa-clock"></i> Horarios Semanales:</strong>
                                    <?php foreach ($schedules as $schedule): ?>
                                        <div style="padding: 3px 0;">
                                            <?php echo \Models\SyncClassSchedule::getDayName($schedule['day_of_week']); ?>: 
                                            <?php echo date('H:i', strtotime($schedule['start_time'])); ?> - 
                                            <?php echo date('H:i', strtotime($schedule['end_time'])); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                <div class="product-bottom-details" style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                                    <?php if (!$hasAccess && $status !== 'finished' && $status !== 'ending_soon'): ?>
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
                                            // Usar GoogleCalendarController para generar URL pre-llenada
                                            require_once __DIR__ . '/../../controllers/GoogleCalendarController.php';
                                            $googleCalController = new \Controllers\GoogleCalendarController();
                                            $calendarResult = $googleCalController->generateGoogleCalendarUrl($syncClass['id']);
                                            if (isset($calendarResult['url'])):
                                            ?>
                                            <a href="<?php echo htmlspecialchars($calendarResult['url']); ?>" target="_blank" class="btn-ics-download" style="background: #4285F4; color: white; font-size: 0.9rem; padding: 6px 12px; min-width: 120px; text-align: center;" title="Agregar a Google Calendar">
                                                <i class="fas fa-calendar-plus"></i> Google Calendar
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif ($status === 'finished'): ?>
                                        <button class="add-to-cart-btn" style="opacity: 0.5; cursor: not-allowed; font-size: 0.9rem; padding: 6px 12px; min-width: 120px;" disabled>Finalizada</button>
                                    <?php elseif ($status === 'ending_soon'): ?>
                                        <button class="add-to-cart-btn" style="background: #ff6b6b; opacity: 0.5; cursor: not-allowed; font-size: 0.9rem; padding: 6px 12px; min-width: 120px;" disabled>No Disponible</button>
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
            <div class="view-more" style="margin-top: 2rem;">
                <a href="all-sync-classes.php" style="display: inline-block; padding: 0.75rem 2rem; background: linear-gradient(135deg, #8a56e2 0%, #56e2c6 100%); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; transition: all 0.3s ease;">
                    Ver Todas las Clases Sincrónicas <i class="fas fa-arrow-right"></i>
                </a>
            </div>
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

    <!-- Private Classes Section -->
    <section id="clases-privadas" class="private-classes" style="background: #f8f9fa;">
        <div class="container">
            <div class="private-classes-content">
                <div class="private-classes-header">
                    <h2 style="color: #333;">Clases Privadas Personalizadas</h2>
                    <p class="section-subtitle" style="color: #666;">¿Necesitas un enfoque más personalizado? Agenda una clase privada conmigo y avanza a tu propio ritmo</p>
                </div>
                
                <div class="private-classes-grid">
                    <div class="private-class-card">
                        <div class="private-class-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <h3>Atención Personalizada</h3>
                        <p>Clases uno a uno diseñadas específicamente para tus necesidades y objetivos de aprendizaje</p>
                    </div>
                    
                    <div class="private-class-card">
                        <div class="private-class-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3>Horarios Flexibles</h3>
                        <p>Agenda tus clases cuando más te convenga, adaptándose a tu estilo de vida</p>
                    </div>
                    
                    <div class="private-class-card">
                        <div class="private-class-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3>Progreso Acelerado</h3>
                        <p>Avanza más rápido con un plan de estudios personalizado y retroalimentación constante</p>
                    </div>
                </div>
                
                <div class="private-classes-cta">
                    <h3>¿Listo para empezar?</h3>
                    <p>Contáctame por WhatsApp y conversemos sobre tus objetivos de aprendizaje</p>
                    <a href="https://wa.me/<?php echo htmlspecialchars($contactInfo['whatsapp_number'] ?? '573123456789'); ?>?text=Hola,%20estoy%20interesado%20en%20clases%20privadas%20de%20inglés" 
                       target="_blank" 
                       class="btn-whatsapp-large">
                        <i class="fab fa-whatsapp"></i> Contáctame por WhatsApp
                    </a>
                </div>
                
                <div class="social-media-section">
                    <h3>Sígueme en Redes Sociales</h3>
                    <p>Encuentra tips, consejos y contenido exclusivo en mis redes</p>
                    <div class="social-links">
                        <a href="<?php echo htmlspecialchars($contactInfo['instagram_url'] ?? 'https://instagram.com/profehernan'); ?>" target="_blank" class="social-link instagram">
                            <i class="fab fa-instagram"></i>
                            <span>Instagram</span>
                        </a>
                        <a href="<?php echo htmlspecialchars($contactInfo['facebook_url'] ?? 'https://facebook.com/profehernan'); ?>" target="_blank" class="social-link facebook">
                            <i class="fab fa-facebook-f"></i>
                            <span>Facebook</span>
                        </a>
                        <a href="<?php echo htmlspecialchars($contactInfo['youtube_url'] ?? 'https://youtube.com/@profehernan'); ?>" target="_blank" class="social-link youtube">
                            <i class="fab fa-youtube"></i>
                            <span>YouTube</span>
                        </a>
                        <a href="https://wa.me/<?php echo htmlspecialchars($contactInfo['whatsapp_number'] ?? '573123456789'); ?>" target="_blank" class="social-link whatsapp">
                            <i class="fab fa-whatsapp"></i>
                            <span>WhatsApp</span>
                        </a>
                        <?php if (!empty($contactInfo['whatsapp_channel'])): ?>
                        <a href="<?php echo htmlspecialchars($contactInfo['whatsapp_channel']); ?>" target="_blank" class="social-link whatsapp-channel" style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);">
                            <i class="fab fa-whatsapp"></i>
                            <span>Canal WhatsApp</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <style>
    .private-classes {
        padding: 80px 0;
        background: #f8f9fa;
        color: #333;
    }
    
    .private-classes-header {
        text-align: center;
        margin-bottom: 60px;
    }
    
    .private-classes-header h2 {
        font-size: 2.5rem;
        margin-bottom: 20px;
        color: #333;
    }
    
    .private-classes-header .section-subtitle {
        font-size: 1.2rem;
        max-width: 700px;
        margin: 0 auto;
        color: #666;
    }
    
    .private-classes-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 30px;
        margin-bottom: 60px;
    }
    
    .private-class-card {
        background: white;
        padding: 40px 30px;
        border-radius: 15px;
        text-align: center;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    
    .private-class-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }
    
    .private-class-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 20px;
        background: linear-gradient(135deg, #8a56e2 0%, #a256e2 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: white;
    }
    
    .private-class-card h3 {
        font-size: 1.3rem;
        margin-bottom: 15px;
        color: #333;
    }
    
    .private-class-card p {
        line-height: 1.6;
        color: #666;
    }
    
    .private-classes-cta {
        background: white;
        padding: 50px;
        border-radius: 20px;
        text-align: center;
        margin-bottom: 60px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    }
    
    .private-classes-cta h3 {
        font-size: 2rem;
        margin-bottom: 15px;
        color: #333;
    }
    
    .private-classes-cta p {
        font-size: 1.1rem;
        color: #666;
        margin-bottom: 30px;
    }
    
    .btn-whatsapp-large {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        padding: 18px 40px;
        background: #25D366;
        color: white;
        text-decoration: none;
        border-radius: 50px;
        font-size: 1.2rem;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 10px 30px rgba(37, 211, 102, 0.3);
    }
    
    .btn-whatsapp-large:hover {
        background: #128C7E;
        transform: scale(1.05);
        box-shadow: 0 15px 40px rgba(37, 211, 102, 0.4);
    }
    
    .btn-whatsapp-large i {
        font-size: 1.5rem;
    }
    
    .social-media-section {
        text-align: center;
    }
    
    .social-media-section h3 {
        font-size: 2rem;
        margin-bottom: 15px;
        color: #333;
    }
    
    .social-media-section p {
        font-size: 1.1rem;
        margin-bottom: 30px;
        color: #666;
    }
    
    .social-links {
        display: flex;
        justify-content: center;
        gap: 20px;
        flex-wrap: wrap;
    }
    
    .social-link {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        padding: 25px 30px;
        background: white;
        border-radius: 15px;
        text-decoration: none;
        color: #333;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        min-width: 140px;
    }
    
    .social-link i {
        font-size: 2.5rem;
    }
    
    .social-link span {
        font-size: 1rem;
        font-weight: 600;
    }
    
    .social-link:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }
    
    .social-link.instagram:hover {
        background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
        color: white;
    }
    
    .social-link.facebook:hover {
        background: #1877F2;
        color: white;
    }
    
    .social-link.youtube:hover {
        background: #FF0000;
        color: white;
    }
    
    .social-link.whatsapp:hover {
        background: #25D366;
        color: white;
    }
    
    .social-link.whatsapp-channel:hover {
        background: linear-gradient(135deg, #128C7E 0%, #075E54 100%);
        color: white;
    }
    
    @media (max-width: 768px) {
        .private-classes {
            padding: 60px 0;
        }
        
        .private-classes-header h2 {
            font-size: 2rem;
        }
        
        .private-classes-cta {
            padding: 30px 20px;
        }
        
        .social-links {
            gap: 15px;
        }
        
        .social-link {
            min-width: 120px;
            padding: 20px;
        }
    }
    </style>

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
    
    <!-- Smooth Scroll Script -->
    <script src="../../public/js/smooth-scroll.js"></script>
</body>
</html>