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
use Models\Playlist;

$database = new Database();
$db = $database->getConnection();
$playlistModel = new Playlist($db);

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
                    <li><a href="#sobre-nosotros">Sobre Nosotros</a></li>
                    <li><a href="#contacto">Contacto</a></li>
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

    <!-- Levels Section -->
    <section class="courses">
        <div class="container">
            <h2>Niveles de Inglés</h2>
            <p class="section-subtitle">Encuentra el nivel perfecto para ti</p>

            <div class="courses-grid">
                <div class="course-card">
                    <div class="level-badge neon-glow" style="background-color: #ff6b35; color: white;">A1</div>
                    <div class="course-icon"><i class="fas fa-seedling"></i></div>
                    <h3 class="course-title">BÁSICO</h3>
                    <p class="course-subtitle">Primeros pasos en inglés</p>
                    <ul class="course-features">
                        <li>Vocabulario básico</li>
                        <li>Gramática fundamental</li>
                        <li>Conversaciones simples</li>
                    </ul>
                </div>
                
                <div class="course-card">
                    <div class="level-badge neon-glow" style="background-color: #e74c3c; color: white;">A2</div>
                    <div class="course-icon"><i class="fas fa-comments"></i></div>
                    <h3 class="course-title">PRE INTERMEDIO</h3>
                    <p class="course-subtitle">Construye tu base</p>
                    <ul class="course-features">
                        <li>Expresiones cotidianas</li>
                        <li>Tiempos verbales</li>
                        <li>Comprensión auditiva</li>
                    </ul>
                </div>
                
                <div class="course-card">
                    <div class="level-badge neon-glow" style="background-color: #3498db; color: white;">B1</div>
                    <div class="course-icon"><i class="fas fa-graduation-cap"></i></div>
                    <h3 class="course-title">INTERMEDIO</h3>
                    <p class="course-subtitle">Desarrolla fluidez</p>
                    <ul class="course-features">
                        <li>Conversaciones fluidas</li>
                        <li>Escritura estructurada</li>
                        <li>Comprensión de textos</li>
                    </ul>
                </div>
                
                <div class="course-card">
                    <div class="level-badge neon-glow" style="background-color: #1abc9c; color: white;">B2</div>
                    <div class="course-icon"><i class="fas fa-trophy"></i></div>
                    <h3 class="course-title">INTERMEDIO ALTO</h3>
                    <p class="course-subtitle">Perfecciona tu inglés</p>
                    <ul class="course-features">
                        <li>Debates y discusiones</li>
                        <li>Escritura avanzada</li>
                        <li>Comprensión compleja</li>
                    </ul>
                </div>
                
                <div class="course-card">
                    <div class="level-badge neon-glow" style="background-color: #9b59b6; color: white;">C1</div>
                    <div class="course-icon"><i class="fas fa-crown"></i></div>
                    <h3 class="course-title">AVANZADO</h3>
                    <p class="course-subtitle">Dominio del idioma</p>
                    <ul class="course-features">
                        <li>Inglés profesional</li>
                        <li>Literatura y cultura</li>
                        <li>Expresión sofisticada</li>
                    </ul>
                </div>
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

    <!-- CTA Section -->
    <section class="promo-box">
        <div class="container">
            <h2 class="promo-title">¿Listo para Comenzar?</h2>
            <p style="font-size: 1.2rem; margin-bottom: 30px; opacity: 0.9;">Únete a nuestra comunidad de estudiantes y comienza tu viaje hacia la fluidez en inglés</p>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contacto" class="contact">
        <div class="container">
            <div class="contact-form">
                <h2>¿Tienes Preguntas?</h2>
                <p>Contáctanos y te ayudaremos a elegir el curso perfecto para ti</p>
                <form>
                    <div class="form-group">
                        <label for="name">Tu nombre</label>
                        <input type="text" id="name" placeholder="Nombre completo" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Tu correo electrónico</label>
                        <input type="email" id="email" placeholder="correo@ejemplo.com" required>
                    </div>
                    <div class="form-group">
                        <label for="message">Tu mensaje</label>
                        <textarea id="message" placeholder="¿En qué podemos ayudarte?" required></textarea>
                    </div>
                    <button type="submit" class="btn-primary">Enviar Mensaje</button>
                </form>
            </div>
            <div class="contact-info">
                <div class="info-item">
                    <div class="icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="text">
                        <h3>CORREO ELECTRÓNICO</h3>
                        <p>elprofesorhernan@gmail.com</p>
                    </div>
                </div>
                <div class="info-item">
                    <div class="icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div class="text">
                        <h3>TELÉFONO</h3>
                        <p>+57 123 456 789</p>
                    </div>
                </div>
                <div class="info-item">
                    <div class="icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="text">
                        <h3>HORARIO</h3>
                        <p>Lun - Vie: 8:00 AM - 6:00 PM</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

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
</body>
</html>
