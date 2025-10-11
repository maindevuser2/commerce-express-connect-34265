<?php
// Asegúrate de que la sesión esté iniciada
if (session_status() == PHP_SESSION_NONE) {
   session_start();
}

// Incluir todas las dependencias necesarias con rutas correctas
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../models/Order.php';
require_once __DIR__ . '/../../models/UserCourse.php';
require_once __DIR__ . '/../../models/Playlist.php';
require_once __DIR__ . '/../../models/UserSyncClass.php';
require_once __DIR__ . '/../../controllers/CartController.php';

// Usar los namespaces correctos
use Controllers\AuthController;
use Models\Order;
use Models\UserCourse;
use Models\Playlist;
use Models\UserSyncClass;
use Controllers\CartController;

// Verificar autenticación
if (!AuthController::isAuthenticated()) {
   AuthController::setFlashMessage('error', 'Debes iniciar sesión para ver tu historial de compras.');
   header('Location: ../../login.php');
   exit();
}

// Obtener usuario actual
$currentUser = AuthController::getCurrentUser();
$userId = $currentUser['id'];

// Conectar a la base de datos
$database = new \Database();
$pdo = $database->getConnection();

// Inicializar modelos
$orderModel = new Order($pdo);
$userCourseModel = new UserCourse($pdo);
$playlistModel = new Playlist($pdo);
$userSyncClassModel = new UserSyncClass($pdo);

// Obtener historial de pedidos del usuario
$orders = $orderModel->readByUserId($userId);

// Obtener cursos comprados del usuario con detalles completos
$purchasedCourses = $userCourseModel->readByUserId($userId);

// Obtener clases sincrónicas del usuario
$purchasedSyncClasses = $userSyncClassModel->readByUserId($userId);

// Obtener estadísticas del usuario
try {
    $userStats = [
        'total_courses' => count($purchasedCourses) + count($purchasedSyncClasses), // Incluir clases sincrónicas
        'total_spent' => 0,
        'different_levels' => 0,
        'first_purchase' => null
    ];

    if (!empty($orders)) {
        $totalSpent = 0;
        $levels = [];
        $firstPurchase = null;

        foreach ($orders as $order) {
            if (($order['status'] ?? '') === 'completed') {
                $totalSpent += floatval($order['amount'] ?? 0);
            }
            if ($firstPurchase === null || strtotime($order['created_at'] ?? 'now') < strtotime($firstPurchase)) {
                $firstPurchase = $order['created_at'];
            }
        }

        foreach ($purchasedCourses as $course) {
            if (!empty($course['level']) && !in_array($course['level'], $levels)) {
                $levels[] = $course['level'];
            }
        }

        $userStats['total_spent'] = $totalSpent;
        $userStats['different_levels'] = count($levels);
        $userStats['first_purchase'] = $firstPurchase;
    }
} catch (Exception $e) {
    error_log("Error obteniendo estadísticas de usuario: " . $e->getMessage());
    $userStats = [
        'total_courses' => 0,
        'total_spent' => 0,
        'different_levels' => 0,
        'first_purchase' => null
    ];
}

// Obtener conteo del carrito para el header
$cartController = new CartController();
$cart_count = $cartController->getCartCount();

// Obtener mensaje flash si existe
$flashMessage = AuthController::getFlashMessage();

// Funciones helper
function formatDate($date) {
   return date('d/m/Y H:i', strtotime($date));
}

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

function getOrderStatusBadge($status) {
   $badges = [
       'completed' => ['text' => 'Completado', 'class' => 'success'],
       'pending' => ['text' => 'Pendiente', 'class' => 'warning'],
       'failed' => ['text' => 'Fallido', 'class' => 'danger'],
       'cancelled' => ['text' => 'Cancelado', 'class' => 'secondary']
   ];

   $badge = $badges[$status] ?? ['text' => ucfirst($status), 'class' => 'secondary'];
   return '<span class="status-badge status-' . $badge['class'] . '">' . $badge['text'] . '</span>';
}

// Función helper para obtener el nombre del usuario de forma segura
function getUserDisplayName($user) {
    if (empty($user) || !is_array($user)) {
        return 'Usuario';
    }

    if (!empty($user['name'])) {
        return htmlspecialchars($user['name']);
    }

    if (!empty($user['first_name'])) {
        $name = $user['first_name'];
        if (!empty($user['last_name'])) {
            $name .= ' ' . $user['last_name'];
        }
        return htmlspecialchars($name);
    }

    if (!empty($user['email'])) {
        $emailParts = explode('@', $user['email']);
        return htmlspecialchars(ucfirst($emailParts[0]));
    }

    return 'Usuario';
}


// Cálculo adicional: total invertido
$totalSpent = 0;

foreach ($orders as $order) {
    if (($order['status'] ?? '') === 'completed') {
        $totalSpent += floatval($order['amount'] ?? 0);
    }
}

// Niveles distintos
$levels = [];
foreach ($purchasedCourses as $course) {
    if (!empty($course['level'])) {
        $levels[] = $course['level'];
    }
}
$differentLevels = count(array_unique($levels));

// Días desde la primera compra
$firstAccessDate = $userCourseModel->getFirstAccessDate($userId);
$daysLearning = 0;
if ($firstAccessDate) {
    $timestamp = strtotime($firstAccessDate);
    $daysLearning = floor((time() - $timestamp) / (60 * 60 * 24));
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Mi Historial de Compras - El Profesor Hernán</title>
   <link rel="stylesheet" href="../../public/css/styles.css">
    <link rel="stylesheet" href="../../public/css/client/purchase-history.css">
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
                    <li><a href="all-sync-classes.php">Clases</a></li>
                    <li><a href="all-books.php">Libros</a></li>
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

   <!-- Purchase History Section -->
   <section class="purchase-history-section">
       <div class="container">
           <!-- Page Header -->
           <div class="page-header">
               <h1><i class="fas fa-graduation-cap"></i> Mi Historial de Aprendizaje</h1>
               <p>Gestiona tus cursos y revisa tu progreso académico</p>
           </div>

           <?php if ($flashMessage): ?>
               <div class="alert alert-<?php echo $flashMessage['type']; ?>">
                   <i class="fas fa-<?php echo $flashMessage['type'] === 'error' ? 'exclamation-triangle' : 'check-circle'; ?>"></i>
                   <?php echo $flashMessage['message']; ?>
               </div>
           <?php endif; ?>

           <!-- Statistics -->
           <?php if (!empty($userStats)): ?>
               <div class="stats-grid">
                    <div class="stat-card">
                        <div class="icon courses">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="number"><?php echo $userStats['total_courses'] ?? 0; ?></div>
                        <div class="label">Cursos y Clases</div>
                    </div>

                    <div class="stat-card">
                        <div class="icon spent">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="number">$<?php echo number_format($totalSpent, 2); ?></div>
                        <div class="label">Total Invertido</div>
                    </div>

                    <div class="stat-card">
                        <div class="icon levels">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div class="number"><?php echo $differentLevels; ?></div>
                        <div class="label">Niveles Diferentes</div>
                    </div>

                    <div class="stat-card">
                        <div class="icon time">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="number"><?php echo $daysLearning; ?></div>
                        <div class="label">Días de Trayectoria</div>
                    </div>

               </div>
           <?php endif; ?>

           <!-- Content Tabs -->
           <div class="content-tabs">
               <button class="tab-button active" onclick="switchTab('courses')">
                   <i class="fas fa-play-circle"></i> Mis Cursos
               </button>
               <button class="tab-button" onclick="switchTab('orders')">
                   <i class="fas fa-receipt"></i> Historial de Compras
               </button>
           </div>

           <!-- Courses Tab -->
           <div id="courses-tab" class="tab-content active">
               <?php if (!empty($purchasedCourses)): ?>
                   <div class="courses-grid">
                       <?php foreach ($purchasedCourses as $course): ?>
                           <div class="course-card">
                               <div class="course-image">
                                   <?php 
                                        $courseImageUrl = 'https://via.placeholder.com/300x200/8a56e2/ffffff?text=Curso'; // Imagen por defecto

                                        if (!empty($course['cover_image'])) {
                                            $courseImageUrl = '../../' . ltrim($course['cover_image'], '/');
                                        } elseif (!empty($course['thumbnail'])) {
                                            $courseImageUrl = '../../' . ltrim($course['thumbnail'], '/');
                                        }
                                   ?>
                                   <img src="<?php echo htmlspecialchars($courseImageUrl); ?>" 
                                        alt="<?php echo htmlspecialchars($course['name'] ?? 'Curso'); ?>">
                                   <div class="course-level" style="background-color: <?php echo getLevelColor($course['level'] ?? 'Mixto'); ?>">
                                       <?php echo htmlspecialchars($course['level'] ?? 'Todos los niveles'); ?>
                                   </div>
                               </div>
                               <div class="course-content">
                                   <h3 class="course-title"><?php echo htmlspecialchars($course['name'] ?? 'Curso sin nombre'); ?></h3>
                                   <p class="course-description">
                                       <?php echo htmlspecialchars($course['description'] ?: 'Curso completo de inglés diseñado para mejorar tus habilidades lingüísticas.'); ?>
                                   </p>
                                   <div class="course-meta">
                                       <span><i class="fas fa-calendar"></i> Adquirido: <?php echo htmlspecialchars(formatDate($course['access_granted_at'])); ?></span>
                                   </div>
                                   <div class="course-meta">
                                        <span class="course-price">$<?php echo number_format($course['price'] ?? 0, 2); ?></span>
                                        <span><i class="fas fa-check-circle" style="color: var(--teal-color);"></i> Acceso Completo</span>
                                   </div>
                                    <div class="course-actions">
                                        <a href="playlist_videos.php?id=<?php echo $course['playlist_id']; ?>" class="btn-access">
                                            <i class="fas fa-play"></i> Acceder al Curso
                                        </a>
                                    </div>
                               </div>
                           </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (!empty($purchasedSyncClasses)): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 2rem; margin-bottom: 1rem;">
                        <h3 style="margin: 0;"><i class="fas fa-video"></i> Mis Clases Sincrónicas</h3>
<!--                         <a href="../../controllers/IcsController.php?action=download_all" class="btn-access btn-download-all" style="background: var(--primary-color);">
                            <i class="fas fa-download"></i> Descargar Todas (.ics)
                        </a> -->
                    </div>
                    <div class="courses-grid">
                        <?php foreach ($purchasedSyncClasses as $syncClass): ?>
                            <?php 
                                $status = $syncClass['status'] ?? 'active';
                                
                                // Obtener horarios semanales
                                require_once __DIR__ . '/../../models/SyncClassSchedule.php';
                                $scheduleModel = new \Models\SyncClassSchedule($pdo);
                                $schedules = $scheduleModel->readBySyncClass($syncClass['id']);
                                
                                // Mapeo de estados a badges
                                $statusBadges = [
                                    'active' => ['text' => 'Activo', 'color' => '#28a745'],
                                    'inactive' => ['text' => 'Inactivo', 'color' => '#ffc107'],
                                    'finished' => ['text' => 'Finalizado', 'color' => '#6c757d']
                                ];
                                $statusInfo = $statusBadges[$status] ?? ['text' => 'Activo', 'color' => '#28a745'];
                            ?>
                            <div class="course-card sync-class-card" style="border-left: 4px solid #667eea;">
                                <div class="course-image" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; min-height: 200px; position: relative;">
                                    <div style="text-align: center; color: white;">
                                        <i class="fas fa-video" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                                        <p style="font-size: 1.2rem; font-weight: 600;">Clase en Vivo</p>
                                    </div>
                                    <div class="course-level" style="background-color: <?php echo $statusInfo['color']; ?>; position: absolute; top: 10px; right: 10px;">
                                        <?php echo $statusInfo['text']; ?>
                                    </div>
                                </div>
                                <div class="course-content">
                                    <h3 class="course-title"><?php echo htmlspecialchars($syncClass['title'] ?? 'Clase sin nombre'); ?></h3>
                                    <p class="course-description">
                                        <?php echo htmlspecialchars($syncClass['description'] ?: 'Clase sincrónica en vivo.'); ?>
                                    </p>
                                    <div class="course-meta">
                                        <span><i class="fas fa-calendar"></i> Inicio: <?php echo date('d M Y', strtotime($syncClass['start_date'])); ?></span>
                                    </div>
                                    <div class="course-meta">
                                        <span><i class="fas fa-clock"></i> Fin: <?php echo date('d M Y', strtotime($syncClass['end_date'])); ?></span>
                                    </div>
                                    <?php if (!empty($schedules)): ?>
                                    <div style="margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 8px; font-size: 0.85rem;">
                                        <strong style="display: block; margin-bottom: 5px;"><i class="fas fa-clock"></i> Horarios Semanales:</strong>
                                        <?php foreach ($schedules as $schedule): ?>
                                            <div style="padding: 3px 0; color: #555;">
                                                <strong><?php echo \Models\SyncClassSchedule::getDayName($schedule['day_of_week']); ?>:</strong> 
                                                <?php echo date('H:i', strtotime($schedule['start_time'])); ?> - 
                                                <?php echo date('H:i', strtotime($schedule['end_time'])); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="course-meta">
                                         <span class="course-price">$<?php echo number_format($syncClass['price'] ?? 0, 2); ?></span>
                                         <span><i class="fas fa-check-circle" style="color: var(--teal-color);"></i> Acceso Completo</span>
                                    </div>
                                     <div class="course-actions">
                                         <?php if ($status === 'finished'): ?>
                                         <button class="btn-access" style="background: #6c757d; cursor: not-allowed;" disabled>
                                             <i class="fas fa-clock"></i> Clase Finalizada
                                         </button>
                                         <?php else: ?>
                                         <a href="<?php echo htmlspecialchars($syncClass['meeting_link']); ?>" target="_blank" class="btn-access" style="background: #46b6ff; margin-bottom: 8px;">
                                             <i class="fas fa-video"></i> Unirse a la Clase
                                         </a>
                                         <?php if (!empty($syncClass['whatsapp_group_link'])): ?>
                                         <a href="<?php echo htmlspecialchars($syncClass['whatsapp_group_link']); ?>" target="_blank" class="btn-access" style="background: #25D366; color: white; margin-bottom: 8px;">
                                             <i class="fab fa-whatsapp"></i> Unirse al Grupo de WhatsApp
                                         </a>
                                         <?php endif; ?>
                                         <?php
                                         // Usar GoogleCalendarController para generar URL
                                         require_once __DIR__ . '/../../controllers/GoogleCalendarController.php';
                                         $googleCalController = new \Controllers\GoogleCalendarController();
                                         $calendarResult = $googleCalController->generateGoogleCalendarUrl($syncClass['id']);
                                         if (isset($calendarResult['url'])):
                                         ?>
                                         <a href="<?php echo htmlspecialchars($calendarResult['url']); ?>" target="_blank" class="btn-access btn-ics-small" style="background: #4285f4; color: white; border: 2px solid #4285f4;">
                                             <i class="fab fa-google"></i> Agregar a Google Calendar
                                         </a>
                                         <?php endif; ?>
                                         <?php endif; ?>
                                     </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-book-open"></i>
                        <h3>Aún no tienes cursos</h3>
                        <p>¡Comienza tu viaje de aprendizaje hoy! Explora nuestros cursos de inglés y encuentra el perfecto para ti.</p>
                        <a href="cart.php" class="btn-browse">
                            <i class="fas fa-search"></i> Explorar Cursos
                        </a>
                    </div>
                <?php endif; ?>
            </div>

           <!-- Orders Tab -->
           <div id="orders-tab" class="tab-content">
               <?php if (!empty($orders)): ?>
                   <div class="orders-table">
                        <div class="table-header table-row">
                            <div>Pedido #</div>
                            <div>Contenido</div>
                            <div>Total</div>
                            <div>Estado</div>
                            <div>Fecha</div>
                        </div>
                        <?php foreach ($orders as $order): ?>
                            <div class="table-row">
                                <div>
                                    <strong>#<?php echo htmlspecialchars($order['id'] ?? ''); ?></strong>
                                </div>
                                <div>
                                    <?php 
                                    // Obtener cursos y clases de esta orden
                                    $orderCourses = $userCourseModel->getCoursesByOrderId($order['id']);
                                    $orderSyncClasses = $userSyncClassModel->getSyncClassesByOrderId($order['id']);
                                    $totalItems = count($orderCourses) + count($orderSyncClasses);
                                    
                                    $itemsText = [];
                                    if (count($orderCourses) > 0) {
                                        $itemsText[] = count($orderCourses) . ' curso(s)';
                                    }
                                    if (count($orderSyncClasses) > 0) {
                                        $itemsText[] = count($orderSyncClasses) . ' clase(s)';
                                    }
                                    
                                    // Si no hay items actuales pero la orden existe, mostrar mensaje informativo
                                    if (empty($itemsText)) {
                                        echo '<span style="color: #6c757d; font-style: italic;">Contenido eliminado</span>';
                                    } else {
                                        echo implode(' + ', $itemsText);
                                    }
                                    ?>
                                </div>
                               <div>
                                   <strong>$<?php echo number_format($order['amount'] ?? 0, 2); ?></strong>
                               </div>
                               <div>
                                   <?php echo getOrderStatusBadge($order['status'] ?? 'unknown'); ?>
                               </div>
                               <div>
                                   <?php echo formatDate($order['created_at'] ?? 'now'); ?>
                               </div>
                           </div>
                       <?php endforeach; ?>
                   </div>
               <?php else: ?>
                   <div class="empty-state">
                       <i class="fas fa-receipt"></i>
                       <h3>No tienes pedidos aún</h3>
                       <p>Cuando realices tu primera compra, aparecerá aquí tu historial de Compras.</p>
                       <a href="cart.php" class="btn-browse">
                           <i class="fas fa-shopping-cart"></i> Hacer Primera Compra
                       </a>
                   </div>
               <?php endif; ?>
           </div>

           <!-- Quick Actions -->
           <div style="text-align: center; margin-top: 3rem;">
               <a href="home.php" class="btn-browse" style="margin-right: 1rem;">
                   <i class="fas fa-home"></i> Volver al Inicio
               </a>
               <a href="all-courses.php" class="btn-browse">
                   <i class="fas fa-plus"></i> Explorar Más Cursos
               </a>
           </div>
       </div>
   </section>

   <!-- Footer -->
   <footer class="footer">
       <div class="container">
           <p>&copy; 2024 El Profesor Hernán. Todos los derechos reservados.</p>
           <div class="footer-links">
               <a href="home.php">Inicio</a>
               <a href="home.php">Cursos</a>
               <a href="cart.php">Carrito</a>
               <a href="purchase-history.php">Mis Cursos</a>
           </div>
           <p>Aprende inglés con los mejores cursos online</p>
       </div>
   </footer>

   <!-- Scripts -->
   <script>
       function switchTab(tabName) {
           // Ocultar todas las pestañas
           document.querySelectorAll('.tab-content').forEach(tab => {
               tab.classList.remove('active');
           });
           
           // Remover clase active de todos los botones
           document.querySelectorAll('.tab-button').forEach(button => {
               button.classList.remove('active');
           });
           
           // Mostrar la pestaña seleccionada
           document.getElementById(tabName + '-tab').classList.add('active');
           
           // Activar el botón correspondiente
           event.target.classList.add('active');
       }

       // Animaciones al cargar la página
       document.addEventListener('DOMContentLoaded', function() {
           // Animar las tarjetas de estadísticas
           const statCards = document.querySelectorAll('.stat-card');
           statCards.forEach((card, index) => {
               setTimeout(() => {
                   card.style.opacity = '0';
                   card.style.transform = 'translateY(20px)';
                   card.style.transition = 'all 0.5s ease';
                   
                   setTimeout(() => {
                       card.style.opacity = '1';
                       card.style.transform = 'translateY(0)';
                   }, 100);
               }, index * 100);
           });

           // Animar las tarjetas de cursos
           const courseCards = document.querySelectorAll('.course-card');
           courseCards.forEach((card, index) => {
               setTimeout(() => {
                   card.style.opacity = '0';
                   card.style.transform = 'translateY(20px)';
                   card.style.transition = 'all 0.5s ease';
                   
                   setTimeout(() => {
                       card.style.opacity = '1';
                       card.style.transform = 'translateY(0)';
                   }, 100);
               }, index * 150);
           });
       });
   </script>
</body>
</html>
