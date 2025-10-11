<?php
// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
   session_start();
}

// Incluir dependencias necesarias
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../models/Playlist.php';
require_once __DIR__ . '/../../models/UserCourse.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/CartController.php';

use Models\Playlist;
use Models\UserCourse;
use Controllers\AuthController;

// Inicializar conexión a la base de datos
$database = new \Database();
$db = $database->getConnection();
$playlistModel = new Playlist($db);
$userCourseModel = new UserCourse($db);

// Obtener todos los cursos
$playlists = [];
try {
   $playlistsResult = $playlistModel->readAll();
   $playlists = is_array($playlistsResult) ? $playlistsResult : [];
} catch (Exception $e) {
   error_log("Error obteniendo playlists: " . $e->getMessage());
   $playlists = [];
}

// Obtener cursos del usuario si está autenticado
$userCourses = [];
$userCourseIds = [];
$isAuthenticated = AuthController::isAuthenticated();
$currentUser = null;

if ($isAuthenticated) {
   try {
       $currentUser = AuthController::getCurrentUser();
       $userCoursesResult = $userCourseModel->readByUserId($currentUser['id']);
       $userCourses = is_array($userCoursesResult) ? $userCoursesResult : [];
       $userCourseIds = array_column($userCourses, 'playlist_id');
   } catch (Exception $e) {
       error_log("Error obteniendo cursos del usuario: " . $e->getMessage());
       $userCourseIds = [];
   }
}

// Obtener contador del carrito
$cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;

// Filtros
$levelFilter = $_GET['level'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Aplicar filtros
if ($levelFilter || $searchQuery) {
   $playlists = array_filter($playlists, function($playlist) use ($levelFilter, $searchQuery) {
       $matchesLevel = !$levelFilter || ($playlist['level'] ?? '') === $levelFilter;
       $matchesSearch = !$searchQuery || 
           stripos($playlist['name'] ?? '', $searchQuery) !== false || 
           stripos($playlist['description'] ?? '', $searchQuery) !== false;
       return $matchesLevel && $matchesSearch;
   });
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Todos los Cursos - El Profesor Hernán</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../public/css/client/all-courses.css">
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
                    <li><a href="all-courses.php" class="active">Cursos</a></li>
                    <li><a href="all-sync-classes.php">Clases</a></li>
                    <li><a href="all-books.php">Libros</a></li>
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
                <?php if ($isAuthenticated): ?>
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
                <?php else: ?>
                    <a href="../../login.php" class="btn btn-primary">Iniciar Sesión</a>
                    <a href="../../signup.php" class="btn btn-outline">Registrarse</a>
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

   <main class="main-content">
       <div class="container">
           <!-- Header de la página -->
           <div class="page-header">
               <h1>Todos los Cursos</h1>
               <p>Descubre nuestra colección completa de cursos de inglés</p>
           </div>

           <!-- Filtros y búsqueda -->
           <div class="filters-section">
               <form method="GET" class="filters-form">
                   <div class="search-box">
                       <input type="text" 
                              name="search" 
                              placeholder="Buscar cursos..." 
                              value="<?php echo htmlspecialchars($searchQuery); ?>"
                              class="search-input">
                       <button type="submit" class="search-btn">
                           <i class="fas fa-search"></i>
                       </button>
                   </div>
                   
                   <div class="filter-group">
                       <select name="level" class="filter-select">
                           <option value="">Todos los niveles</option>
                           <option value="A1" <?php echo $levelFilter === 'A1' ? 'selected' : ''; ?>>A1 - Principiante</option>
                           <option value="A2" <?php echo $levelFilter === 'A2' ? 'selected' : ''; ?>>A2 - Básico</option>
                           <option value="B1" <?php echo $levelFilter === 'B1' ? 'selected' : ''; ?>>B1 - Intermedio</option>
                           <option value="B2" <?php echo $levelFilter === 'B2' ? 'selected' : ''; ?>>B2 - Intermedio Alto</option>
                           <option value="C1" <?php echo $levelFilter === 'C1' ? 'selected' : ''; ?>>C1 - Avanzado</option>
                           <option value="C2" <?php echo $levelFilter === 'C2' ? 'selected' : ''; ?>>C2 - Experto</option>
                       </select>
                       <button type="submit" class="filter-btn">Filtrar</button>
                   </div>
               </form>
           </div>

           <!-- Resultados -->
           <div class="courses-results">
               <div class="results-info">
                   <span><?php echo count($playlists); ?> curso(s) encontrado(s)</span>
               </div>

               <!-- Grid de cursos -->
                <div class="courses-grid">
                    <?php if (empty($playlists)): ?>
                        <div class="no-results">
                            <i class="fas fa-search"></i>
                            <h3>No se encontraron cursos</h3>
                            <p>Intenta ajustar tus filtros de búsqueda</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($playlists as $playlist): ?>
                            <?php 
                            $isOwned = in_array($playlist['id'], $userCourseIds);
                            $imageUrl = 'https://via.placeholder.com/300x200/8a56e2/ffffff?text=Curso'; // Default

                            // Usar thumbnail con prefijo ../../ como en home.php
                            if (!empty($playlist['thumbnail'])) {
                                $imageUrl = '../../' . $playlist['thumbnail'];
                            } elseif (!empty($playlist['cover_image'])) {
                                // Ajustar cover_image con prefijo ../../ si es necesario
                                $imageUrl = '../../' . $playlist['cover_image'];
                            }
                            ?>
                            <div class="course-card">
                                <div class="course-image">
                                    <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                                        alt="<?php echo htmlspecialchars($playlist['name'] ?? ''); ?>">
                                    <?php if (!empty($playlist['level'])): ?>
                                        <div class="course-level"><?php echo htmlspecialchars($playlist['level']); ?></div>
                                    <?php endif; ?>
                                    <?php if ($isOwned): ?>
                                        <div class="owned-badge">
                                            <i class="fas fa-check"></i> Adquirido
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="course-content">
                                    <h3 class="course-title">
                                        <a href="course-detail.php?id=<?php echo $playlist['id']; ?>">
                                            <?php echo htmlspecialchars($playlist['name'] ?? ''); ?>
                                        </a>
                                    </h3>
                                    
                                    <p class="course-description">
                                        <?php 
                                        $description = $playlist['description'] ?? '';
                                        echo htmlspecialchars(substr($description, 0, 100));
                                        if (strlen($description) > 100) echo '...';
                                        ?>
                                    </p>
                                    
                                    <div class="course-footer">
                                        <div class="course-price">
                                            <?php if (($playlist['price'] ?? 0) > 0): ?>
                                                <span class="price">$<?php echo number_format($playlist['price'], 2); ?></span>
                                            <?php else: ?>
                                                <span class="price free">Gratis</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="course-actions">
                                            <?php if ($isOwned): ?>
                                                <a href="purchase-history.php" 
                                                class="btn btn-primary">
                                                    <i class="fas fa-play"></i> Continuar
                                                </a>
                                            <?php else: ?>
                                                <a href="course-detail.php?id=<?php echo $playlist['id']; ?>" class="btn btn-outline">Ver Detalles</a>
                                                <?php if ($isAuthenticated): ?>
                                                    <button onclick="addToCart(<?php echo $playlist['id']; ?>)" 
                                                            class="btn btn-primary add-to-cart-btn">
                                                        <i class="fas fa-cart-plus"></i> Agregar
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
           </div>
       </div>
   </main>

   <script>
   function addToCart(courseId) {
       // Obtener el botón que se clickeó
       const button = event.target.closest('.add-to-cart-btn');
       const originalHTML = button.innerHTML;
       
       // Mostrar estado de carga
       button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agregando...';
       button.disabled = true;
       button.classList.add('loading');

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
           console.log('Raw response:', text); // Para debugging
           
           // Intentar parsear como JSON
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
                   button.classList.remove('loading');
               }, 2000);
           } else {
               showNotification(data.message || 'Error al agregar al carrito', 'error');
               button.innerHTML = originalHTML;
               button.disabled = false;
               button.classList.remove('loading');
           }
       })
       .catch(error => {
           console.error('Error:', error);
           showNotification('Error al agregar al carrito. Inténtalo de nuevo.', 'error');
           
           // Restaurar botón
           button.innerHTML = originalHTML;
           button.disabled = false;
           button.classList.remove('loading');
       });
   }

   function showNotification(message, type) {
       // Remover notificaciones existentes
       const existingNotifications = document.querySelectorAll('.notification');
       existingNotifications.forEach(n => n.remove());

       const notification = document.createElement('div');
       notification.className = `notification ${type}`;
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
               const cartCount = document.getElementById('cart-count');
               if (cartCount && data.count !== undefined) {
                   cartCount.textContent = data.count;
                   
                   // Animación del contador
                   cartCount.style.transform = 'scale(1.3)';
                   setTimeout(() => {
                       cartCount.style.transform = 'scale(1)';
                   }, 200);
               }
           })
           .catch(error => {
               console.error('Error updating cart count:', error);
           });
   }

   // Actualizar contador del carrito al cargar la página
   document.addEventListener('DOMContentLoaded', function() {
       updateCartCount();
   });
   </script>
</body>
</html>
