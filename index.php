<?php
// Inicializar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Incluir archivos de configuración y dependencias
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/helpers/SecurityHelper.php';

use Controllers\AuthController;
use Controllers\BookController;
// Obtener parámetros de la URL
$page = $_GET['page'] ?? '';
$action = $_GET['action'] ?? '';

// Manejar rutas de administración
if ($page === 'admin') {
    // Verificar autenticación de administrador
    if (!AuthController::isAdmin()) {
        header('Location: login.php');
        exit();
    }
    
    switch ($action) {
        case 'dashboard':
            include __DIR__ . '/views/admin/dashboard.php';
            break;
            
        case 'users':
            include __DIR__ . '/views/admin/users.php';
            break;
            
        case 'courses':
            include __DIR__ . '/views/admin/courses.php';
            break;
            
        case 'orders':
            include __DIR__ . '/views/admin/orders.php';
            break;
            
        case 'books':
            // Manejar sub-acciones de libros
            $bookAction = $_GET['book_action'] ?? 'index';
            
            switch ($bookAction) {
                case 'create':
                    include __DIR__ . '/views/admin/book-form.php';
                    break;
                    
                case 'edit':
                    if (!isset($_GET['id'])) {
                        header('Location: index.php?page=admin&action=books');
                        exit();
                    }
                    include __DIR__ . '/views/admin/book-form.php';
                    break;
                    
                case 'delete':
                    if (isset($_GET['id'])) {
                        require_once __DIR__ . '/controllers/BookController.php';
                        $controller = new BookController();
                        $controller->delete($_GET['id']);
                    }
                    header('Location: index.php?page=admin&action=books');
                    exit();                    
                case 'store':
                    require_once __DIR__ . '/controllers/BookController.php';
                    $controller = new BookController();
                    break;
                    
                case 'update':
                    if (isset($_GET['id'])) {
                        require_once __DIR__ . '/controllers/BookController.php';
                        $controller = new BookController();
                    }
                    break;
                    
                case 'details':
                    if (isset($_GET['id'])) {
                        require_once __DIR__ . '/controllers/BookController.php';
                        $controller = new BookController();
  }
                    break;
                    
                default:
                    include __DIR__ . '/views/admin/books.php';
                    break;
            }
            break;
            
        case 'sync-classes':
            include __DIR__ . '/views/admin/sync-classes.php';
            break;
            
        case 'contact-info':
            include __DIR__ . '/views/admin/contact-info.php';
            break;
            
        case 'profile':
            include __DIR__ . '/views/admin/profile.php';
            break;
            
        default:
            include __DIR__ . '/views/admin/dashboard.php';
            break;
    }
    exit();
}
// Si el usuario está logueado, redirigir según su rol
if (AuthController::isAuthenticated()) {
    if (AuthController::isAdmin()) {
        header('Location: index.php?page=admin&action=dashboard');
        exit();
    } else {
        header('Location: views/client/home.php');
        exit();
    }
}

// Si no está logueado, mostrar la página de inicio pública
require_once __DIR__ . '/models/Playlist.php';
require_once __DIR__ . '/models/AdminContactInfo.php';
use Models\Playlist;

$database = new Database();
$db = $database->getConnection();
$playlistModel = new Playlist($db);

// Obtener información de contacto
$contactModel = new \Models\AdminContactInfo($db);
$contactInfo = $contactModel->get();

// Obtener algunos cursos para mostrar (máximo 6)
try {
    $playlists = $playlistModel->readAll();
    $featured_playlists = array_slice($playlists, 0, 6);
} catch (Exception $e) {
    error_log("Error obteniendo playlists: " . $e->getMessage());
    $featured_playlists = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>El Profesor Hernán - Aprende Inglés Online</title>
    <link rel="stylesheet" href="public/css/styles.css">
    <link rel="stylesheet" href="public/css/client/book-section.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="public/img/logo-profe-hernan.png" alt="El Profesor Hernán" style="height: 40px;">
                <span>El Profesor Hernán</span>
            </div>
            
            <nav class="nav">
                <ul>
                    <li><a href="#inicio">Inicio</a></li>
                    <li><a href="#cursos">Cursos</a></li>
                    <li><a href="#clases-privadas">Clases</a></li>
                    <li><a href="#books-showcase">Libros</a></li>
                    <li><a href="#sobre-nosotros">Sobre Nosotros</a></li>
                </ul>
            </nav>
            
            <div class="auth-links">
                <a href="login.php" class="btn-login">Iniciar Sesión</a>
                <a href="signup.php" class="btn-signup">Registrarse</a>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section id="inicio" class="banner">
        <div class="container">
            <div class="banner-content">
                <div class="banner-text">
                    <h1>Aprende Inglés con el Profesor Hernán</h1>
                    <p>Domina el inglés con nuestros cursos. Desde nivel básico hasta avanzado, te acompañamos en tu camino hacia la fluidez.</p>
                    <div class="banner-buttons">
                        <a href="login.php" class="btn-primary">COMENZAR AHORA</a>
                        <a href="#cursos" class="btn-secondary">VER CURSOS</a>
                    </div>
                </div>
                <div class="banner-image">
                    <div class="image-container">
                        <img src="public/img/hero-image.png?height=400&width=400" alt="Profesor enseñando inglés">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Courses Section -->
    <section id="cursos" class="best-sellers">
        <div class="container">
            <h2>Cursos Destacados</h2>
            <p class="section-subtitle">Descubre nuestros cursos más populares y comienza tu viaje de aprendizaje</p>

            <div class="products-grid">
                <?php if (!empty($featured_playlists)): ?>
                    <?php foreach ($featured_playlists as $playlist): ?>
                        <div class="product-card">
                            <div class="product-tumb">
                                <?php if (!empty($playlist['thumbnail'])): ?>
                                    <img src="<?php echo htmlspecialchars($playlist['thumbnail']); ?>" alt="<?php echo htmlspecialchars($playlist['title']); ?>">
                                <?php else: ?>
                                    <img src="https://hebbkx1anhila5yf.public.blob.vercel-storage.com/design-mode-images/loading-icon-614x460%281%29%281%29-nVqTbUacUFIJgSymTaqvpqoo7jkWBS.png" alt="Imagen por defecto">
                                <?php endif; ?>
                                <div class="course-overlay">
                                    <a href="login.php" class="btn-overlay">Ver Curso</a>
                                </div>
                            </div>
                            <div class="product-details">
                                <span class="product-catagory">Nivel <?php echo htmlspecialchars($playlist['level'] ?? 'General'); ?></span>
                                <h4><?php echo htmlspecialchars($playlist['title']); ?></h4>
                                <p><?php echo htmlspecialchars($playlist['description'] ?: 'Curso completo de inglés'); ?></p>
                                <div class="product-bottom-details">
                                    <div class="product-price">$<?php echo htmlspecialchars(number_format($playlist['price'], 2)); ?></div>
                                    <a href="login.php" class="add-to-cart-btn">Acceder</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-products">
                        <p>Próximamente nuevos cursos disponibles.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="view-more">
                <a href="login.php">Ver Todos los Cursos <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </section>

    <!-- Sync Classes Section -->
    <?php
    // Obtener clases sincrónicas activas
    try {
        require_once __DIR__ . '/models/SyncClass.php';
        $syncClassModel = new \Models\SyncClass($db);
        $activeSyncClasses = $syncClassModel->readActive();
        $featuredSyncClasses = array_slice($activeSyncClasses, 0, 3);
    } catch (Exception $e) {
        $featuredSyncClasses = [];
        error_log("Error cargando clases sincrónicas: " . $e->getMessage());
    }
    ?>
    
    <?php if (!empty($featuredSyncClasses)): ?>
    <section class="best-sellers" style="background: #f8f9fa;">
        <div class="container">
            <h2>Clases Sincrónicas</h2>
            <p class="section-subtitle">Únete a nuestras clases en vivo y aprende en tiempo real con el profesor</p>

            <div class="products-grid">
                <?php foreach ($featuredSyncClasses as $syncClass): ?>
                    <div class="product-card">
                        <div class="product-tumb" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; min-height: 200px;">
                            <div style="text-align: center; color: white;">
                                <i class="fas fa-video" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                                <p style="font-size: 1.2rem; font-weight: 600;">Clase en Vivo</p>
                            </div>
                        </div>
                        <div class="product-details">
                            <span class="product-catagory">
                                <i class="fas fa-calendar"></i> <?php echo date('d M Y', strtotime($syncClass['start_date'])); ?>
                            </span>
                            <h4><?php echo htmlspecialchars($syncClass['title']); ?></h4>
                            <p><?php echo htmlspecialchars(substr($syncClass['description'] ?: 'Clase sincrónica en vivo', 0, 100)); ?></p>
                            <div class="product-bottom-details">
                                <div class="product-price">$<?php echo htmlspecialchars(number_format($syncClass['price'], 2)); ?></div>
                                <a href="login.php" class="add-to-cart-btn">Acceder</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="view-more">
                <a href="login.php">Ver Todas las Clases <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Private Classes Section -->
    <section id="clases-privadas" class="private-classes" style="background: #f8f9fa;">
        <div class="container">
            <div class="private-classes-content">
                <div class="private-classes-header">
                    <h2 style="color: #333;">Clases Privadas Personalizadas</h2>
                    <p class="section-subtitle" style="color: #666;">¿Necesitas un enfoque más personalizado? Agenda una clase privada conmigo y avanza a tu propio ritmo</p>
                </div>
                
                <div class="private-classes-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px; margin-bottom: 60px;">
                    <div class="private-class-card" style="background: white; padding: 40px 30px; border-radius: 15px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: all 0.3s ease;">
                        <div class="private-class-icon" style="background: linear-gradient(135deg, #8a56e2 0%, #a256e2 100%); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 2rem; color: white;">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <h3 style="color: #333; margin-bottom: 15px; font-size: 1.3rem;">Atención Personalizada</h3>
                        <p style="color: #666; line-height: 1.6;">Clases uno a uno diseñadas específicamente para tus necesidades y objetivos de aprendizaje</p>
                    </div>
                    
                    <div class="private-class-card" style="background: white; padding: 40px 30px; border-radius: 15px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: all 0.3s ease;">
                        <div class="private-class-icon" style="background: linear-gradient(135deg, #8a56e2 0%, #a256e2 100%); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 2rem; color: white;">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3 style="color: #333; margin-bottom: 15px; font-size: 1.3rem;">Horarios Flexibles</h3>
                        <p style="color: #666; line-height: 1.6;">Agenda tus clases cuando más te convenga, adaptándose a tu estilo de vida</p>
                    </div>
                    
                    <div class="private-class-card" style="background: white; padding: 40px 30px; border-radius: 15px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: all 0.3s ease;">
                        <div class="private-class-icon" style="background: linear-gradient(135deg, #8a56e2 0%, #a256e2 100%); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 2rem; color: white;">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3 style="color: #333; margin-bottom: 15px; font-size: 1.3rem;">Progreso Acelerado</h3>
                        <p style="color: #666; line-height: 1.6;">Avanza más rápido con un plan de estudios personalizado y retroalimentación constante</p>
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

    <!-- Books Section -->
    <section class="books-showcase" id="books-showcase">
        <div class="container">
            <div class="section-header">
                <h2>Libros Recomendados</h2>
                <p>Complementa tu aprendizaje con estos libros especializados disponibles en Amazon</p>
            </div>
            
            <?php
            // Obtener libros destacados
            try {
                require_once __DIR__ . '/models/Book.php';
                require_once __DIR__ . '/config/Database.php';
                $database = new \Database();
                $db = $database->getConnection();
                $bookModel = new \Models\Book($db);
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
                                <button class="btn-showcase-details" onclick="showBookDetails(<?php echo $book['id']; ?>)">
                                    <i class="fas fa-info-circle"></i> Ver Detalles
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="section-footer">
                    <a href="signup.php" class="btn-primary">Ver Más Libros</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- About Section -->
    <section id="sobre-nosotros" class="about-section">
        <div class="container">
            <div class="about-content">
                <div class="about-text">
                    <h2>Sobre el Profesor Hernán</h2>
                    <p>Con más de 10 años de experiencia enseñando inglés, el Profesor Hernán ha ayudado a miles de estudiantes a alcanzar sus metas lingüísticas.</p>
                    <div class="about-features">
                        <div class="feature-item">
                            <i class="fas fa-users"></i>
                            <div>
                                <h4>Experiencia Comprobada</h4>
                                <p>Multiples estudiantes exitosos</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-heart"></i>
                            <div>
                                <h4>Método Personalizado</h4>
                                <p>Adaptado a tu ritmo de aprendizaje</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="about-image">
                    <img src="public/img/logo-profe-hernan.png" alt="Profesor Hernán">
                </div>
            </div>
        </div>
    </section>


    
    <style>
    .private-classes {
        padding: 80px 0;
        background: linear-gradient(135deg, #f3effaff 0%, #a256e2 100%);
        color: white;
    }
    
    .private-classes-header {
        text-align: center;
        margin-bottom: 60px;
    }
    
    .private-classes-header h2 {
        font-size: 2.5rem;
        margin-bottom: 20px;
        color: white;
    }
    
    .private-classes-header .section-subtitle {
        font-size: 1.2rem;
        opacity: 0.95;
        max-width: 700px;
        margin: 0 auto;
        color: white;
    }
    
    .private-classes-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 30px;
        margin-bottom: 60px;
    }
    
    .private-class-card {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        padding: 40px 30px;
        border-radius: 15px;
        text-align: center;
        transition: transform 0.3s ease, background 0.3s ease;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .private-class-card:hover {
        transform: translateY(-10px);
        background: rgba(255, 255, 255, 0.15);
    }
    
    .private-class-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 20px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
    }
    
    .private-class-card h3 {
        font-size: 1.5rem;
        margin-bottom: 15px;
        color: white;
    }
    
    .private-class-card p {
        opacity: 0.9;
        line-height: 1.6;
        color: white;
    }
    
    .private-classes-cta {
        background: rgba(255, 255, 255, 0.95);
        padding: 50px;
        border-radius: 20px;
        text-align: center;
        margin-bottom: 60px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
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
        color: black
    }
    
    .social-media-section p {
        font-size: 1.1rem;
        margin-bottom: 30px;
        opacity: 0.9;
        color: black;
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
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border-radius: 15px;
        text-decoration: none;
        color: white;
        transition: all 0.3s ease;
        border: 1px solid rgba(255, 255, 255, 0.2);
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
    }
    
    .social-link.facebook:hover {
        background: #1877F2;
    }
    
    .social-link.youtube:hover {
        background: #FF0000;
    }
    
    .social-link.whatsapp:hover {
        background: #25D366;
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
            <div class="footer-content">
                <div class="footer-section">
                    <div class="logo">
                        <img src="public/img/logo-profe-hernan.png" alt="El Profesor Hernán" style="height: 40px;">
                        <span>El Profesor Hernán</span>
                    </div>
                    <p>Tu mejor opción para aprender inglés online. Cursos diseñados para todos los niveles.</p>
                </div>
                <div class="footer-section">
                    <h4>Enlaces Rápidos</h4>
                    <ul>
                        <li><a href="#inicio">Inicio</a></li>
                        <li><a href="#cursos">Cursos</a></li>
                        <li><a href="#clases-privadas">Clases Privadas</a></li>
                        <li><a href="#sobre-nosotros">Sobre Nosotros</a></li>
                        <li><a href="#contacto">Contacto</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Cursos</h4>
                    <ul>
                        <li><a href="login.php">Nivel Básico (A1)</a></li>
                        <li><a href="login.php">Pre Intermedio (A2)</a></li>
                        <li><a href="login.php">Intermedio (B1)</a></li>
                        <li><a href="login.php">Avanzado (C1)</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Síguenos</h4>
                    <div class="social-links">
                        <a href="https://www.facebook.com/elprofesorhernantena"><i class="fab fa-facebook"></i></a>
                        <a href="https://www.instagram.com/elprofesorhernan/"><i class="fab fa-instagram"></i></a>
                        <a href="https://www.youtube.com/channel/UC88P2sLx2IIWOK-wjDmy-0g"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 El Profesor Hernán. Todos los derechos reservados.</p>
            </div>
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
                    <a href="signup.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Registrarse para Ver Más
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="public/js/smooth-scroll.js"></script>
    <script>
            // Animación de números en estadísticas
        function animateNumbers() {
            const stats = document.querySelectorAll('.stat-number');
            stats.forEach(stat => {
                const target = parseInt(stat.textContent.replace(/\D/g, ''));
                const suffix = stat.textContent.replace(/[0-9]/g, '');
                let current = 0;
                const increment = target / 50;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    stat.textContent = Math.floor(current) + suffix;
                }, 50);
            });
        }

        // Intersection Observer para animaciones
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    if (entry.target.classList.contains('stats')) {
                        animateNumbers();
                    }
                    entry.target.classList.add('animate');
                }
            });
        });

        // Observar elementos para animación
        document.querySelectorAll('.stats, .course-card, .testimonial-card, .book-showcase-card').forEach(el => {
            observer.observe(el);
        });

        // Cerrar mensaje flash automáticamente después de 5 segundos
        const flashMessage = document.querySelector('.flash-message');
        if (flashMessage) {
            setTimeout(() => {
                flashMessage.style.opacity = '0';
                setTimeout(() => {
                    flashMessage.style.display = 'none';
                }, 300);
            }, 5000);
        }

        // Función para mostrar detalles del libro
        function showBookDetails(bookId) {
            const modal = document.getElementById('bookModal');
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            // Mostrar estado de carga
            document.getElementById('modalBookTitle').textContent = 'Cargando...';
            document.getElementById('modalBookTitleFull').textContent = 'Cargando...';
            
            // Hacer petición AJAX para obtener detalles
            fetch(`controllers/BookController.php?action=details&id=${bookId}`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.status === 'success') {
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
            document.getElementById('modalBookTitleFull').textContent = book.title;
            document.getElementById('modalBookAuthor').textContent = `por ${book.author}`;
            document.getElementById('modalBookPrice').textContent = `$${parseFloat(book.price).toFixed(2)}`;
            document.getElementById('modalBookDescription').textContent = book.description || 'Sin descripción disponible.';
            
            // Enlace de Amazon con target="_blank"
            const amazonLink = document.getElementById('modalAmazonLink');
            amazonLink.href = book.amazon_url;
            amazonLink.target = '_blank';
            amazonLink.rel = 'noopener noreferrer';
            
            // Imagen de portada del modal
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
        
        // Función para cerrar el modal
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
    </script>
    
    <!-- Smooth Scroll Script -->
    <script src="public/js/smooth-scroll.js"></script>
</body>
</html>
