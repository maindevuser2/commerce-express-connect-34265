<?php
// Asegúrate de que la sesión esté iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Incluir todas las dependencias necesarias
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../models/Playlist.php';
require_once __DIR__ . '/../../models/UserCourse.php';

// Usar los namespaces correctos
use Controllers\AuthController;
use Models\Playlist;
use Models\UserCourse;

// Verificar autenticación
if (!AuthController::isAuthenticated()) {
    AuthController::setFlashMessage('error', 'Debes iniciar sesión para ver los detalles del curso.');
    header('Location: ../../login.php');
    exit();
}

// Conectar a la base de datos y cargar modelo de cursos
$database = new \Database();
$db = $database->getConnection();
$playlistModel   = new Playlist($db);
$userCourseModel = new UserCourse($db);

// Manejo del formulario “Añadir al Carrito”
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) && $_POST['action'] === 'add' &&
    isset($_POST['id']) && is_numeric($_POST['id'])
) {
    $courseIdToAdd = intval($_POST['id']);
    // Obtener detalles del curso
    $courseToAdd = $playlistModel->findById($courseIdToAdd);
    if ($courseToAdd) {
        // Agregar al carrito en sesión
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        if (!isset($_SESSION['cart'][$courseIdToAdd])) {
            $_SESSION['cart'][$courseIdToAdd] = [
                'id' => $courseToAdd['id'],
                'name' => $courseToAdd['title'] ?? $courseToAdd['name'] ?? 'Curso sin nombre',
                'price' => floatval($courseToAdd['price'] ?? 0),
                'cover_image' => $courseToAdd['cover_image'] ?? $courseToAdd['thumbnail'] ?? '',
                'level' => $courseToAdd['level'] ?? '',
                'description' => $courseToAdd['description'] ?? '',
                'quantity' => 1
            ];
        }
    }
    // Redirigir al carrito
    header('Location: cart.php');
    exit();
}

// Obtener ID del curso desde GET
$courseId = $_GET['id'] ?? null;
if (!$courseId || !is_numeric($courseId)) {
    AuthController::setFlashMessage('error', 'Curso no encontrado.');
    header('Location: home.php');
    exit();
}
$courseId = intval($courseId);

// Obtener detalles del curso usando findById
$course = $playlistModel->findById($courseId);
if (!$course) {
    echo "<h1>Curso no encontrado para el ID: " . htmlspecialchars($courseId) . "</h1>";
    echo "<p>Por favor, verifica que el ID del curso sea correcto.</p>";
    echo "<a href='home.php'>Volver al inicio</a>";
    exit();
}

// Obtener usuario actual y estado de acceso
$currentUser = AuthController::getCurrentUser();
$userId      = $currentUser['id'] ?? null;
$hasAccess   = $userCourseModel->hasAccess($userId, $courseId);

// Obtener conteo del carrito para el header
$cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;

// Obtener mensaje flash si existe
$flashMessage = AuthController::getFlashMessage();

// Función para obtener el color del nivel
function getLevelColor($level) {
    $colors = [
        'A1' => '#56e2c6',
        'A2' => '#4dabf7',
        'B1' => '#ffa726',
        'B2' => '#ff5a5a',
        'C1' => '#8a56e2',
        'Mixto' => '#6c757d'
    ];
    return $colors[$level] ?? '#6c757d';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['title'] ?? 'Curso no encontrado'); ?> - El Profesor Hernán</title>
    <link rel="stylesheet" href="../../public/css/styles.css">
    <link rel="stylesheet" href="../../public/css/client/course-detail.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        
    </style>
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
                    <li><a href="cart.php">
                        <i class="fas fa-shopping-cart"></i>
                        Carrito
                        <?php if ($cartCount > 0): ?>
                            <span class="cart-count"><?php echo $cartCount; ?></span>
                        <?php endif; ?>
                    </a></li>
                </ul>
            </nav>
            
            <div class="auth-links">
                <span>Hola, <?php echo htmlspecialchars($currentUser['name'] ?? 'Usuario'); ?></span>
                <?php if ($currentUser['role'] === 'admin'): ?>
                    <a href="../admin/index.php?controller=admin&action=dashboard" class="btn-admin">Panel Admin</a>
                <?php endif; ?>
                <a href="purchase-history.php" class="btn-history">Mis Cursos</a>
                <a href="../../logout.php" class="btn-logout">Cerrar Sesión</a>
            </div>
        </div>
    </header>

    <!-- Course Header -->
    <section class="course-header">
        <div class="container">
            <div class="course-header-content">
                <div class="course-info">
                    <h1><?php echo htmlspecialchars($course['title'] ?? 'Curso no encontrado'); ?></h1>
                    <div class="course-meta">
                        <div class="meta-item">
                            <i class="fas fa-layer-group"></i>
                            Nivel <?php echo htmlspecialchars($course['level'] ?? 'Todos los niveles'); ?>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-clock"></i>
                            Acceso de por vida
                        </div>

                    </div>
                    <p class="course-description">
                        <?php echo htmlspecialchars($course['description'] ?? 'Curso completo de inglés diseñado para mejorar tus habilidades lingüísticas de manera efectiva y práctica.'); ?>
                    </p>
                </div>
                <div class="course-image">
                    <?php if (!empty($course['cover_image'])): ?>
                        <img src="../../<?php echo htmlspecialchars($course['cover_image']); ?>" 
                             alt="<?php echo htmlspecialchars($course['title'] ?? 'Curso'); ?>">
                    <?php else: ?>
                        <img src="" alt="Imagen por defecto">
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Course Detail Section -->
    <section class="course-detail-section">
        <div class="container">
            <?php if ($flashMessage): ?>
                <div class="alert alert-<?php echo $flashMessage['type']; ?>">
                    <i class="fas fa-<?php echo $flashMessage['type'] === 'error' ? 'exclamation-triangle' : 'check-circle'; ?>"></i>
                    <?php echo $flashMessage['message']; ?>
                </div>
            <?php endif; ?>

            <div class="course-content">
                <!-- Main Content -->
                <div class="main-content">
                    <h2 class="section-title">¿Qué aprenderás?</h2>
                    <ul class="features-list">
                        <li><i class="fas fa-check"></i> Vocabulario esencial para comunicación diaria</li>
                        <li><i class="fas fa-check"></i> Gramática práctica y fácil de entender</li>
                        <li><i class="fas fa-check"></i> Pronunciación correcta con ejercicios de audio</li>
                        <li><i class="fas fa-check"></i> Conversaciones reales y situaciones cotidianas</li>
                        <li><i class="fas fa-check"></i> Comprensión auditiva con material auténtico</li>
                        <li><i class="fas fa-check"></i> Escritura efectiva para diferentes contextos</li>
                        <li><i class="fas fa-check"></i> Estrategias para mejorar la fluidez</li>
                    </ul>
                </div>

                <!-- Sidebar -->
                <div class="sidebar">
                    <div class="price-section">
                        <div class="price">$<?php echo number_format($course['price'] ?? 0, 2); ?></div>
                        <div class="price-label">Acceso completo de por vida</div>
                    </div>

                    <?php if ($hasAccess): ?>
                        <p style="text-align: center; color: var(--teal-color); font-weight: 500;">
                            <i class="fas fa-check-circle"></i> Ya tienes acceso a este curso
                        </p>
                    <?php else: ?>
    <form action="" method="post" style="display: inline;">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($course['id']); ?>">
        <button type="submit" class="access-button btn-add-cart">
            <i class="fas fa-shopping-cart"></i> Añadir al Carrito
        </button>
    </form>
                    <?php endif; ?>

                    <h3 style="margin-top: 2rem; margin-bottom: 1rem;">Este curso incluye:</h3>
                    <ul class="features-list">
                        <li><i class="fas fa-video"></i> Lecciones en video HD</li>
                        <li><i class="fas fa-headphones"></i> Ejercicios de audio</li>
                        <li><i class="fas fa-infinity"></i> Acceso de por vida</li>
                    </ul>
                </div>
            </div>

            <!-- Navigation -->
            <div style="text-align: center; margin-top: 3rem;">
                <a href="home.php" style="margin-right: 1rem; color: var(--primary-color); text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Volver al Inicio
                </a>
                <a href="purchase-history.php" style="color: var(--primary-color); text-decoration: none;">
                    <i class="fas fa-graduation-cap"></i> Ver Mis Cursos
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>© 2024 El Profesor Hernán. Todos los derechos reservados.</p>
            <div class="footer-links">
                <a href="home.php">Inicio</a>
                <a href="home.php">Cursos</a>
                <a href="cart.php">Carrito</a>
                <a href="purchase-history.php">Mis Cursos</a>
            </div>
            <p>Aprende inglés con los mejores cursos online</p>
        </div>
    </footer>
</body>
</html>