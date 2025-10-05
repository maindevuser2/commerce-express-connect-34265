<?php
include __DIR__ . '/config.php';

require_once __DIR__ . '/../../controllers/AuthController.php';
use Controllers\AuthController;

if (!AuthController::isAdmin()) {
    header('Location: ../../login.php');
    exit();
}

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../models/SyncClass.php';
use Models\SyncClass;

$currentUser = AuthController::getCurrentUser();
$database = new \Database();
$db = $database->getConnection();
$syncClassModel = new SyncClass($db);

$action = $_GET['sub_action'] ?? '';
$classId = $_GET['class_id'] ?? '';

// Manejar creación
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $meeting_link = trim($_POST['meeting_link'] ?? '');
    $whatsapp_group_link = trim($_POST['whatsapp_group_link'] ?? '');
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    
    if (!empty($title) && !empty($meeting_link) && !empty($start_date) && !empty($end_date)) {
        $syncClassModel->title = $title;
        $syncClassModel->description = $description;
        $syncClassModel->price = $price;
        $syncClassModel->meeting_link = $meeting_link;
        $syncClassModel->whatsapp_group_link = $whatsapp_group_link;
        $syncClassModel->start_date = $start_date;
        $syncClassModel->end_date = $end_date;
        $syncClassModel->is_active = 1;
        
        if ($syncClassModel->create()) {
            $success_message = "Clase sincrónica creada exitosamente";
        } else {
            $error_message = "Error al crear la clase";
        }
    } else {
        $error_message = "Todos los campos requeridos deben ser completados";
    }
}

// Manejar actualización
if ($action === 'edit' && $classId && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $meeting_link = trim($_POST['meeting_link'] ?? '');
    $whatsapp_group_link = trim($_POST['whatsapp_group_link'] ?? '');
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $is_active = intval($_POST['is_active'] ?? 1);
    
    if (!empty($title) && !empty($meeting_link) && !empty($start_date) && !empty($end_date)) {
        $syncClassModel->id = $classId;
        $syncClassModel->title = $title;
        $syncClassModel->description = $description;
        $syncClassModel->price = $price;
        $syncClassModel->meeting_link = $meeting_link;
        $syncClassModel->whatsapp_group_link = $whatsapp_group_link;
        $syncClassModel->start_date = $start_date;
        $syncClassModel->end_date = $end_date;
        $syncClassModel->is_active = $is_active;
        
        if ($syncClassModel->update()) {
            $success_message = "Clase sincrónica actualizada exitosamente";
        } else {
            $error_message = "Error al actualizar la clase";
        }
    } else {
        $error_message = "Todos los campos requeridos deben ser completados";
    }
}

// Manejar eliminación
if ($action === 'delete' && $classId) {
    if ($syncClassModel->delete($classId)) {
        $success_message = "Clase sincrónica eliminada exitosamente";
    } else {
        $error_message = "Error al eliminar la clase";
    }
}

// Obtener todas las clases
$syncClasses = $syncClassModel->readAll();

// Obtener clase para editar
$editClass = null;
if ($action === 'edit' && $classId && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $editClass = $syncClassModel->readOne($classId);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clases Sincrónicas - Admin</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/public/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: #2d3748;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 1rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 500;
            border-left: 3px solid transparent;
            position: relative;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 0;
            background: rgba(255, 255, 255, 0.1);
            transition: width 0.3s ease;
        }

        .nav-link:hover::before {
            width: 100%;
        }

        .nav-link:hover {
            color: white;
            transform: translateX(5px);
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border-left-color: #fbbf24;
            backdrop-filter: blur(10px);
        }

        .nav-link i {
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            background: white;
            min-height: 100vh;
        }

        .content-header {
            background: white;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #f1f5f9;
        }

        .content-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .content-header h1 i {
            color: #6366f1;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            font-weight: 500;
            background: #f8fafc;
            padding: 0.75rem 1.25rem;
            border-radius: 50px;
            border: 1px solid #e2e8f0;
        }

        .user-info i {
            color: #6366f1;
            font-size: 1.1rem;
        }

        /* Flash Messages */
        .flash-message {
            padding: 1rem 2rem;
            margin: 0;
            border: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .flash-success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .flash-error {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        /* Content Section */
        .content-section {
            padding: 2rem;
        }

        .section-header {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid #f1f5f9;
        }

        .section-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .section-header p {
            color: #64748b;
        }

        /* Form Styles */
        .class-form {
            background: white;
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid #f1f5f9;
            margin-bottom: 2rem;
        }

        .class-form h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .class-form h2 i {
            color: #6366f1;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #374151;
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #64748b, #475569);
            color: white;
            box-shadow: 0 4px 15px rgba(100, 116, 139, 0.3);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(100, 116, 139, 0.4);
        }

        /* Table Styles */
        .classes-table-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid #f1f5f9;
            overflow: hidden;
        }

        .table-header {
            padding: 2rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .table-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-header h2 i {
            color: #6366f1;
        }

        .classes-table {
            width: 100%;
            border-collapse: collapse;
        }

        .classes-table th,
        .classes-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }

        .classes-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .classes-table tr:hover {
            background: #f8fafc;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .status-inactive {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }

        .btn-edit {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }

        .btn-delete {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-edit:hover,
        .btn-delete:hover {
            transform: translateY(-2px);
        }

        .btn-back {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #d1d5db;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: #374151;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .content-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
                padding: 1.5rem;
            }

            .content-header h1 {
                font-size: 1.5rem;
            }

            .content-section {
                padding: 1rem;
            }

            .class-form {
                padding: 1.5rem;
            }

            .classes-table {
                font-size: 0.85rem;
            }

            .classes-table th,
            .classes-table td {
                padding: 0.75rem 0.5rem;
            }
        }

        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-graduation-cap"></i> Admin Panel</h2>
            </div>
            <nav class="sidebar-nav">
                <a href="<?php echo $baseUrl; ?>index.php?page=admin&action=dashboard" class="nav-link active">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="<?php echo $baseUrl; ?>index.php?page=admin&action=users" class="nav-link">
                    <i class="fas fa-users"></i> Usuarios
                </a>
                <a href="<?php echo $baseUrl; ?>index.php?page=admin&action=courses" class="nav-link">
                    <i class="fas fa-book"></i> Cursos
                </a>
                <a href="<?php echo $baseUrl; ?>index.php?page=admin&action=sync-classes" class="nav-link">
                    <i class="fas fa-video"></i> Clases Sincrónicas
                </a>
                <a href="<?php echo $baseUrl; ?>index.php?page=admin&action=orders" class="nav-link">
                    <i class="fas fa-shopping-cart"></i> Pedidos
                </a>
                <a href="<?php echo $baseUrl; ?>index.php?page=admin&action=books" class="nav-link">
                    <i class="fas fa-book-open"></i> Libros
                </a>
                <a href="<?php echo $baseUrl; ?>index.php?page=admin&action=profile" class="nav-link">
                    <i class="fas fa-user-cog"></i> Mi Perfil
                </a>
                <a href="<?php echo $baseUrl; ?>logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="content-header">
                <h1><i class="fas fa-video"></i> Gestión de Clases Sincrónicas</h1>
                <div class="user-info">
                    <i class="fas fa-user-circle"></i>
                    <?php echo htmlspecialchars($currentUser['first_name'] ?? 'Admin'); ?>
                </div>
            </header>

            <?php if (isset($success_message)): ?>
                <div class="flash-message flash-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="flash-message flash-error">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="content-section">
                <!-- Info Box sobre archivos ICS -->
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);">
                    <p style="margin-top: 1rem; padding: 1rem; background: rgba(255,255,255,0.2); border-radius: 8px; margin-bottom: 0;">
                        <i class="fas fa-lightbulb"></i> <strong>Tip:</strong> Asegúrate de incluir enlaces de reunión válidos (Zoom, Google Meet, etc.) para que los estudiantes puedan unirse fácilmente desde su calendario.
                    </p>
                </div>
                
                <div class="class-form">
                    <h2>
                        <i class="fas fa-<?php echo $editClass ? 'edit' : 'plus-circle'; ?>"></i>
                        <?php echo $editClass ? 'Editar' : 'Nueva'; ?> Clase Sincrónica
                    </h2>
            <form method="POST" action="?page=admin&action=sync-classes&sub_action=<?php echo $editClass ? 'edit&class_id=' . $editClass['id'] : 'create'; ?>">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Título *</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($editClass['title'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Descripción</label>
                        <textarea name="description"><?php echo htmlspecialchars($editClass['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Precio (USD) *</label>
                        <input type="number" name="price" step="0.01" min="0" value="<?php echo htmlspecialchars($editClass['price'] ?? '0'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Link de Reunión *</label>
                        <input type="url" name="meeting_link" value="<?php echo htmlspecialchars($editClass['meeting_link'] ?? ''); ?>" placeholder="https://zoom.us/..." required>
                    </div>
                    
                    <div class="form-group">
                        <label>Link de Grupo de WhatsApp (Opcional)</label>
                        <input type="url" name="whatsapp_group_link" value="<?php echo htmlspecialchars($editClass['whatsapp_group_link'] ?? ''); ?>" placeholder="https://chat.whatsapp.com/...">
                    </div>
                    
                    <div class="form-group">
                        <label>Fecha de Inicio *</label>
                        <input type="datetime-local" name="start_date" value="<?php echo $editClass ? date('Y-m-d\TH:i', strtotime($editClass['start_date'])) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Fecha de Finalización *</label>
                        <input type="datetime-local" name="end_date" value="<?php echo $editClass ? date('Y-m-d\TH:i', strtotime($editClass['end_date'])) : ''; ?>" required>
                    </div>
                    
                    <?php if ($editClass): ?>
                    <div class="form-group">
                        <label>Estado</label>
                        <select name="is_active">
                            <option value="1" <?php echo ($editClass['is_active'] == 1) ? 'selected' : ''; ?>>Activa</option>
                            <option value="0" <?php echo ($editClass['is_active'] == 0) ? 'selected' : ''; ?>>Inactiva</option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                    <!-- Horarios Semanales -->
                    <div class="form-group full-width">
                        <label style="font-size: 1.1rem; color: #1e293b; margin-bottom: 1rem; display: block;">
                            <i class="fas fa-calendar-week"></i> Horarios Semanales
                        </label>
                        <div id="schedules-container" style="border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; background: #f8fafc;">
                            <?php 
                            $schedulesData = !empty($editSchedules) ? $editSchedules : [];
                            if (empty($schedulesData)) {
                                $schedulesData = [['day_of_week' => '', 'start_time' => '', 'end_time' => '']];
                            }
                            foreach ($schedulesData as $index => $schedule): 
                            ?>
                            <div class="schedule-row" style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 1rem; margin-bottom: 1rem; align-items: center;">
                                <select name="schedules[<?php echo $index; ?>][day]" class="form-control" style="padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 10px; font-family: 'Poppins', sans-serif;">
                                    <option value="">Seleccionar día</option>
                                    <option value="1" <?php echo (isset($schedule['day_of_week']) && $schedule['day_of_week'] == 1) ? 'selected' : ''; ?>>Lunes</option>
                                    <option value="2" <?php echo (isset($schedule['day_of_week']) && $schedule['day_of_week'] == 2) ? 'selected' : ''; ?>>Martes</option>
                                    <option value="3" <?php echo (isset($schedule['day_of_week']) && $schedule['day_of_week'] == 3) ? 'selected' : ''; ?>>Miércoles</option>
                                    <option value="4" <?php echo (isset($schedule['day_of_week']) && $schedule['day_of_week'] == 4) ? 'selected' : ''; ?>>Jueves</option>
                                    <option value="5" <?php echo (isset($schedule['day_of_week']) && $schedule['day_of_week'] == 5) ? 'selected' : ''; ?>>Viernes</option>
                                    <option value="6" <?php echo (isset($schedule['day_of_week']) && $schedule['day_of_week'] == 6) ? 'selected' : ''; ?>>Sábado</option>
                                    <option value="0" <?php echo (isset($schedule['day_of_week']) && $schedule['day_of_week'] == 0) ? 'selected' : ''; ?>>Domingo</option>
                                </select>
                                <input type="time" name="schedules[<?php echo $index; ?>][start_time]" 
                                       value="<?php echo isset($schedule['start_time']) ? substr($schedule['start_time'], 0, 5) : ''; ?>" 
                                       placeholder="Hora inicio"
                                       style="padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 10px; font-family: 'Poppins', sans-serif;">
                                <input type="time" name="schedules[<?php echo $index; ?>][end_time]" 
                                       value="<?php echo isset($schedule['end_time']) ? substr($schedule['end_time'], 0, 5) : ''; ?>" 
                                       placeholder="Hora fin"
                                       style="padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 10px; font-family: 'Poppins', sans-serif;">
                                <button type="button" onclick="removeSchedule(this)" class="btn btn-sm btn-delete" style="padding: 0.5rem 0.75rem;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" onclick="addSchedule()" class="btn btn-secondary" style="margin-top: 1rem;">
                            <i class="fas fa-plus"></i> Agregar Horario
                        </button>
                    </div>
                </div>
                
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $editClass ? 'Actualizar' : 'Crear'; ?> Clase
                        </button>
                        <?php if ($editClass): ?>
                        <a href="?page=admin&action=sync-classes" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                        <?php else: ?>
                        <a href="?page=admin&action=dashboard" class="btn btn-back">
                            <i class="fas fa-arrow-left"></i> Volver al Dashboard
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <div class="classes-table-container">
                <div class="table-header">
                    <h2><i class="fas fa-list"></i> Clases Registradas</h2>
                </div>
                <table class="classes-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Título</th>
                        <th>Precio</th>
                        <th>Fecha Inicio</th>
                        <th>Fecha Fin</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($syncClasses)): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <i class="fas fa-video-slash"></i>
                                <h3>No hay clases sincrónicas</h3>
                                <p>Crea tu primera clase sincrónica usando el formulario anterior</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($syncClasses as $class): ?>
                    <tr>
                        <td><?php echo $class['id']; ?></td>
                        <td><?php echo htmlspecialchars($class['title']); ?></td>
                        <td>$<?php echo number_format($class['price'], 2); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($class['start_date'])); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($class['end_date'])); ?></td>
                        <td>
                            <span class="status-badge <?php echo $class['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $class['is_active'] ? 'Activa' : 'Inactiva'; ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="?page=admin&action=sync-classes&sub_action=edit&class_id=<?php echo $class['id']; ?>" class="btn btn-sm btn-edit">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                <a href="?page=admin&action=sync-classes&sub_action=delete&class_id=<?php echo $class['id']; ?>" 
                                   class="btn btn-sm btn-delete" 
                                   onclick="return confirm('¿Estás seguro de eliminar esta clase?')">
                                    <i class="fas fa-trash"></i> Eliminar
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                </table>
            </div>
            </div>
        </main>
    </div>

    <script>
        let scheduleIndex = <?php echo count($schedulesData); ?>;
        
        function addSchedule() {
            const container = document.getElementById('schedules-container');
            const newRow = document.createElement('div');
            newRow.className = 'schedule-row';
            newRow.style.cssText = 'display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 1rem; margin-bottom: 1rem; align-items: center;';
            
            newRow.innerHTML = `
                <select name="schedules[${scheduleIndex}][day]" class="form-control" style="padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 10px; font-family: 'Poppins', sans-serif;">
                    <option value="">Seleccionar día</option>
                    <option value="1">Lunes</option>
                    <option value="2">Martes</option>
                    <option value="3">Miércoles</option>
                    <option value="4">Jueves</option>
                    <option value="5">Viernes</option>
                    <option value="6">Sábado</option>
                    <option value="0">Domingo</option>
                </select>
                <input type="time" name="schedules[${scheduleIndex}][start_time]" placeholder="Hora inicio" style="padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 10px; font-family: 'Poppins', sans-serif;">
                <input type="time" name="schedules[${scheduleIndex}][end_time]" placeholder="Hora fin" style="padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 10px; font-family: 'Poppins', sans-serif;">
                <button type="button" onclick="removeSchedule(this)" class="btn btn-sm btn-delete" style="padding: 0.5rem 0.75rem;">
                    <i class="fas fa-trash"></i>
                </button>
            `;
            
            container.appendChild(newRow);
            scheduleIndex++;
        }
        
        function removeSchedule(button) {
            const row = button.closest('.schedule-row');
            const container = document.getElementById('schedules-container');
            if (container.querySelectorAll('.schedule-row').length > 1) {
                row.remove();
            } else {
                alert('Debe haber al menos un horario');
            }
        }
    
        // Animación de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.class-form');
            const table = document.querySelector('.classes-table-container');
            
            if (form) {
                form.style.opacity = '0';
                form.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    form.style.transition = 'all 0.6s ease';
                    form.style.opacity = '1';
                    form.style.transform = 'translateY(0)';
                }, 100);
            }
            
            if (table) {
                table.style.opacity = '0';
                table.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    table.style.transition = 'all 0.6s ease';
                    table.style.opacity = '1';
                    table.style.transform = 'translateY(0)';
                }, 300);
            }
        });

        function addToGoogleCalendar(classId) {
            window.open('/controllers/GoogleCalendarController.php?action=add&class_id=' + classId, '_blank');
        }

        // Responsive sidebar
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('active');
        }

        if (window.innerWidth <= 1024) {
            const header = document.querySelector('.content-header');
            const menuBtn = document.createElement('button');
            menuBtn.innerHTML = '<i class="fas fa-bars"></i>';
            menuBtn.className = 'mobile-menu-btn';
            menuBtn.style.cssText = 'background: #6366f1; color: white; border: none; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; margin-right: 1rem;';
            menuBtn.onclick = toggleSidebar;
            header.insertBefore(menuBtn, header.firstChild);
        }
    </script>
</body>
</html>
