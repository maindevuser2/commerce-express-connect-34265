
<?php
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../models/UserCourse.php';
require_once __DIR__ . '/../../models/Playlist.php';
require_once __DIR__ . '/../../models/Video.php';
require_once __DIR__ . '/../../config/Database.php';

use Controllers\AuthController;
use Models\UserCourse;
use Models\Playlist;
use Models\Video;

// Verificar autenticación
if (!AuthController::isAuthenticated()) {
    AuthController::setFlashMessage('error', 'Debes iniciar sesión para acceder a esta función.');
    header('Location: ../../login.php');
    exit();
}

$playlist_id = isset($_GET['id']) ? intval($_GET['id']) : null;
if (!$playlist_id) {
    AuthController::setFlashMessage('error', 'Curso no especificado.');
    header('Location: purchase-history.php');
    exit();
}

$database = new \Database();
$db = $database->getConnection();
$userCourseModel = new UserCourse($db);
$playlistModel = new Playlist($db);
$videoModel = new Video($db);

$user_id = $_SESSION['user_id'];

error_log("Comprobando acceso para user_id=$user_id y playlist_id=$playlist_id");

// Verificar que el usuario tenga acceso al curso
if (!$userCourseModel->hasAccess($user_id, $playlist_id)) {
    AuthController::setFlashMessage('error', 'No tienes acceso a este curso.');
    header('Location: purchase-history.php');
    exit();
}

// Obtener información del curso
$playlist = $playlistModel->readOne($playlist_id);
if (!$playlist) {
    AuthController::setFlashMessage('error', 'Curso no encontrado.');
    header('Location: purchase-history.php');
    exit();
}

// Obtener videos del curso
$videos = $videoModel->readByPlaylist($playlist_id);

$pageTitle = $playlist['title'] ?? $playlist['name'];

//DEBBUG
error_log("Playlist ID recibido: " . $playlist_id);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Videos</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../public/css/client/playlist-videos.css">
    <link rel="stylesheet" href="../../public/css/styles.css">

</head>
<body>
    <!-- Course Hero Section -->
    <section class="course-hero">
        <div class="container">
            <div class="course-hero-content">
                <div class="course-hero-text">
                    <div class="level-badge">Nivel: <?php echo htmlspecialchars($playlist['level'] ?? 'N/A'); ?></div>
                    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <p><?php echo htmlspecialchars($playlist['description'] ?? 'Explora todo el contenido de este curso y mejora tus habilidades paso a paso.'); ?></p>
                    
                    <div class="course-stats">
                        <div class="stat-item">
                            <span class="number"><?php echo count($videos); ?></span>
                            <span class="label">Videos</span>
                        </div>
                        <div class="stat-item">
                            <span class="number">∞</span>
                            <span class="label">Acceso</span>
                        </div>
                        <div class="stat-item">
                            <span class="number">HD</span>
                            <span class="label">Calidad</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Breadcrumb Navigation -->
    <nav class="breadcrumb">
        <div class="container">
            <div class="breadcrumb-nav">
                <a href="purchase-history.php">
                    <i class="fas fa-arrow-left"></i> Mis Cursos
                </a>
                <span>/</span>
                <span><?php echo htmlspecialchars($pageTitle); ?></span>
            </div>
        </div>
    </nav>

    <!-- Videos Section -->
    <section class="videos-section">
        <div class="container">
            <div class="section-header">
                <h2>Contenido del Curso</h2>
                <p>Selecciona cualquier video para comenzar tu aprendizaje</p>
            </div>

            <?php if (empty($videos)): ?>
                <div class="empty-state">
                    <i class="fas fa-video"></i>
                    <h3>No hay videos disponibles</h3>
                    <p>Los videos se agregarán próximamente. Mantente atento a las actualizaciones.</p>
                </div>
            <?php else: ?>
                <div class="videos-grid">
                    <?php foreach ($videos as $index => $video): ?>
                        <article class="video-card">
                            <a href="view_video.php?id=<?php echo $video['id']; ?>" class="video-link">
                                <div class="video-thumbnail">
                                    <?php if ($video['thumbnail_image']): ?>
                                        <img src="../../<?php echo htmlspecialchars($video['thumbnail_image']); ?>" 
                                             alt="<?php echo htmlspecialchars($video['title']); ?>"
                                             loading="lazy">
                                    <?php else: ?>
                                        <div class="no-thumbnail">
                                            <i class="fas fa-play-circle"></i>
                                            <span>Video <?php echo $index + 1; ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="play-overlay">
                                        <div class="play-button">
                                            <i class="fas fa-play"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="video-content">
                                    <h3 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h3>
                                    
                                    <?php if ($video['description']): ?>
                                        <p class="video-description"><?php echo htmlspecialchars($video['description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="video-meta">
                                        <span class="video-duration">
                                            <i class="fas fa-clock"></i> Video <?php echo $index + 1; ?>
                                        </span>
                                        <span class="watch-btn">
                                            <i class="fas fa-play"></i> Ver Video
                                        </span>
                                    </div>
                                </div>
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <script>
        // Animaciones suaves al cargar
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.video-card');
            
            // Animación escalonada para las tarjetas
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
            
            // Efecto de hover mejorado
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });
        });
        
        // Lazy loading para imágenes
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.classList.add('loaded');
                        observer.unobserve(img);
                    }
                });
            });
            
            document.querySelectorAll('img[loading="lazy"]').forEach(img => {
                imageObserver.observe(img);
            });
        }
    </script>
</body>
</html>
