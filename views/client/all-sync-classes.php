<?php
// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Incluir dependencias necesarias
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../models/SyncClass.php';
require_once __DIR__ . '/../../models/UserSyncClass.php';
require_once __DIR__ . '/../../models/SyncClassSchedule.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/SyncClassController.php';

use Controllers\AuthController;

// Inicializar conexión a la base de datos
$database = new \Database();
$db = $database->getConnection();
$syncClassModel = new \Models\SyncClass($db);
$userSyncClassModel = new \Models\UserSyncClass($db);
$scheduleModel = new \Models\SyncClassSchedule($db);

// Obtener todas las clases sincrónicas que NO estén finalizadas
$syncClasses = [];
try {
    $allSyncClasses = $syncClassModel->readAll();
    // Filtrar solo las que no estén finalizadas
    $syncClasses = array_filter($allSyncClasses, function($class) {
        return ($class['status'] ?? 'active') !== 'finished';
    });
} catch (Exception $e) {
    error_log("Error obteniendo clases sincrónicas: " . $e->getMessage());
    $syncClasses = [];
}

// Obtener usuario si está autenticado
$isAuthenticated = AuthController::isAuthenticated();
$currentUser = null;
$userId = 0;

if ($isAuthenticated) {
    $currentUser = AuthController::getCurrentUser();
    $userId = $currentUser['id'] ?? 0;
}

// Obtener contador del carrito
require_once __DIR__ . '/../../controllers/CartController.php';
$cartController = new \Controllers\CartController();
$syncCartCount = \Controllers\SyncClassController::getCartCount();
$cartCount = $cartController->getCartCount() + $syncCartCount;

// Mapeo de estados a badges
$statusBadges = [
    'active' => ['text' => 'Activo', 'color' => '#28a745'],
    'upcoming' => ['text' => 'Por Empezar', 'color' => '#17a2b8'],
    'ending_soon' => ['text' => 'Por Terminar', 'color' => '#ff6b6b'],
    'finished' => ['text' => 'Finalizado', 'color' => '#6c757d']
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clases Sincrónicas - El Profesor Hernán</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../public/css/client/all-courses.css">
    <style>
        .sync-class-card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }
        
        .sync-class-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }
        
        .sync-class-header {
            background: linear-gradient(135deg, #8a56e2 0%, #56e2c6 100%);
            padding: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: white;
            position: relative;
            min-height: 200px;
        }
        
        .sync-class-header i {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .sync-class-header h3 {
            font-size: 1.5rem;
            font-weight: 600;
            text-align: center;
        }
        
        .status-badges-container {
            position: absolute;
            top: 10px;
            right: 10px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: flex-end;
            max-width: 200px;
        }
        
        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
            white-space: nowrap;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        
        .precompra-badge {
            background: rgba(255, 255, 255, 0.95);
            color: #8a56e2;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            white-space: nowrap;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .sync-class-content {
            padding: 1.5rem;
        }
        
        .date-range {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        
        .sync-class-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 1rem;
        }
        
        .sync-class-description {
            color: var(--text-muted);
            margin-bottom: 1rem;
            line-height: 1.6;
        }
        
        .schedule-box {
            margin: 1rem 0;
            padding: 1rem;
            background: var(--light-gray);
            border-radius: var(--border-radius-md);
            font-size: 0.9rem;
        }
        
        .schedule-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .schedule-item {
            padding: 0.3rem 0;
            color: var(--text-color);
        }
        
        .sync-class-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1.5rem;
        }
        
        .price-tag {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .owned-label {
            background: var(--teal-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius-sm);
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-container">
            <a href="home.php" class="logo">
                <i class="fas fa-graduation-cap"></i>
                El Profesor Hernán
            </a>
            
            <nav class="nav-menu">
                <a href="home.php" class="nav-link">Inicio</a>
                <a href="all-courses.php" class="nav-link">Cursos</a>
                <?php if ($isAuthenticated): ?>
                    <a href="purchase-history.php" class="nav-link">Mis Compras</a>
                <?php endif; ?>
            </nav>
            
            <div class="nav-actions">
                <a href="cart.php" class="cart-link">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-count" id="cart-count"><?php echo $cartCount; ?></span>
                </a>
                
                <?php if ($isAuthenticated): ?>
                    <div class="user-menu">
                        <button class="user-button">
                            <i class="fas fa-user-circle"></i>
                            <span><?php echo htmlspecialchars($currentUser['name'] ?? 'Usuario'); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="user-dropdown">
                            <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                                <a href="../admin/dashboard.php" class="dropdown-item">
                                    <i class="fas fa-cog"></i> Panel Admin
                                </a>
                            <?php endif; ?>
                            <a href="../../logout.php" class="dropdown-item">
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

    <main class="main-content">
        <div class="container">
            <!-- Header de la página -->
            <div class="page-header">
                <h1>Clases Sincrónicas en Vivo</h1>
                <p>Aprende en tiempo real con el Profesor Hernán y otros estudiantes</p>
            </div>

            <!-- Resultados -->
            <div class="courses-results">
                <div class="results-info">
                    <span><?php echo count($syncClasses); ?> clase(s) disponible(s)</span>
                </div>

                <!-- Grid de clases -->
                <div class="courses-grid">
                    <?php if (empty($syncClasses)): ?>
                        <div class="no-results">
                            <i class="fas fa-calendar-times"></i>
                            <h3>No hay clases sincrónicas disponibles</h3>
                            <p>Pronto anunciaremos nuevas clases en vivo. ¡Mantente atento!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($syncClasses as $syncClass): ?>
                            <?php 
                            $hasAccess = $isAuthenticated ? $userSyncClassModel->hasAccess($userId, $syncClass['id']) : false;
                            $status = $syncClass['status'] ?? 'active';
                            $statusInfo = $statusBadges[$status] ?? ['text' => 'Activo', 'color' => '#28a745'];
                            $showPrecompra = ($status === 'upcoming');
                            
                            // Obtener horarios semanales
                            $schedules = $scheduleModel->readBySyncClass($syncClass['id']);
                            ?>
                            <div class="sync-class-card">
                                <div class="sync-class-header">
                                    <i class="fas fa-video"></i>
                                    <h3>Clase en Vivo</h3>
                                    <div class="status-badges-container">
                                        <div class="status-badge" style="background: <?php echo $statusInfo['color']; ?>;">
                                            <?php echo $statusInfo['text']; ?>
                                        </div>
                                        <?php if ($showPrecompra): ?>
                                        <div class="precompra-badge">
                                            Pre-compra
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="sync-class-content">
                                    <div class="date-range">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date('d M Y', strtotime($syncClass['start_date'])); ?> - 
                                        <?php echo date('d M Y', strtotime($syncClass['end_date'])); ?>
                                    </div>
                                    
                                    <h3 class="sync-class-title">
                                        <?php echo htmlspecialchars($syncClass['title']); ?>
                                    </h3>
                                    
                                    <p class="sync-class-description">
                                        <?php echo htmlspecialchars($syncClass['description'] ?: 'Clase sincrónica en vivo'); ?>
                                    </p>
                                    
                                    <?php if (!empty($schedules)): ?>
                                    <div class="schedule-box">
                                        <div class="schedule-title">
                                            <i class="fas fa-clock"></i>
                                            Horarios Semanales:
                                        </div>
                                        <?php foreach ($schedules as $schedule): ?>
                                            <div class="schedule-item">
                                                <strong><?php echo \Models\SyncClassSchedule::getDayName($schedule['day_of_week']); ?>:</strong>
                                                <?php echo date('H:i', strtotime($schedule['start_time'])); ?> - 
                                                <?php echo date('H:i', strtotime($schedule['end_time'])); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="sync-class-footer">
                                        <?php if ($hasAccess): ?>
                                            <span class="owned-label">
                                                <i class="fas fa-check-circle"></i> Inscrito
                                            </span>
                                            <div class="action-buttons">
                                                <a href="<?php echo htmlspecialchars($syncClass['meeting_link']); ?>" 
                                                   target="_blank" 
                                                   class="btn btn-primary">
                                                    <i class="fas fa-video"></i> Unirse a la Clase
                                                </a>
                                                <?php if (!empty($syncClass['whatsapp_group_link'])): ?>
                                                <a href="<?php echo htmlspecialchars($syncClass['whatsapp_group_link']); ?>" 
                                                   target="_blank" 
                                                   class="btn" 
                                                   style="background: #25D366; color: white;">
                                                    <i class="fab fa-whatsapp"></i> Grupo
                                                </a>
                                                <?php endif; ?>
                                                <?php
                                                require_once __DIR__ . '/../../controllers/GoogleCalendarController.php';
                                                $googleCalController = new \Controllers\GoogleCalendarController();
                                                $calendarResult = $googleCalController->generateGoogleCalendarUrl($syncClass['id']);
                                                if (isset($calendarResult['url'])):
                                                ?>
                                                <a href="<?php echo htmlspecialchars($calendarResult['url']); ?>" 
                                                   target="_blank" 
                                                   class="btn" 
                                                   style="background: #4285F4; color: white;">
                                                    <i class="fas fa-calendar-plus"></i> Calendario
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php elseif ($status === 'ending_soon'): ?>
                                            <span class="price-tag" style="text-decoration: line-through; opacity: 0.6;">
                                                $<?php echo number_format($syncClass['price'], 2); ?>
                                            </span>
                                            <button class="btn btn-outline" disabled style="opacity: 0.5; cursor: not-allowed;">
                                                No Disponible
                                            </button>
                                        <?php else: ?>
                                            <span class="price-tag">
                                                $<?php echo number_format($syncClass['price'], 2); ?>
                                            </span>
                                            <?php if ($isAuthenticated): ?>
                                                <button onclick="addSyncClassToCart(<?php echo $syncClass['id']; ?>)" 
                                                        class="btn btn-primary add-to-cart-btn">
                                                    <i class="fas fa-cart-plus"></i> Agregar al Carrito
                                                </button>
                                            <?php else: ?>
                                                <a href="../../login.php" class="btn btn-primary">
                                                    Iniciar Sesión
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
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
    function addSyncClassToCart(syncClassId) {
        const button = event.target.closest('.add-to-cart-btn');
        const originalHTML = button.innerHTML;
        
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agregando...';
        button.disabled = true;
        button.classList.add('loading');

        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('sync_class_id', syncClassId);

        fetch('../../controllers/SyncClassController.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showNotification(data.message || 'Clase agregada al carrito exitosamente', 'success');
                updateCartCount();
                
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
            
            button.innerHTML = originalHTML;
            button.disabled = false;
            button.classList.remove('loading');
        });
    }

    function showNotification(message, type) {
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

    document.addEventListener('DOMContentLoaded', function() {
        updateCartCount();
    });
    </script>
</body>
</html>
