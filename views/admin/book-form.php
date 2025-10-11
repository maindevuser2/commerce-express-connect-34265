<?php
// Incluir configuración de URL base
include __DIR__ . '/config.php';

// Verificar autenticación
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../models/Book.php';
require_once __DIR__ . '/../../helpers/SecurityHelper.php';
require_once __DIR__ . '/../../config/Database.php';

use Controllers\AuthController;
use Models\Book;

if (!AuthController::isAdmin()) {
    header('Location: ../../login.php');
    exit();
}

$currentUser = AuthController::getCurrentUser();
$flashMessage = AuthController::getFlashMessage();

// Inicializar variables
$book = null;
$isEdit = false;
$pageTitle = 'Agregar Libro';
$submitText = 'Crear Libro';

// Verificar si es edición
if (isset($_GET['book_action']) && $_GET['book_action'] === 'edit' && isset($_GET['id'])) {
    $isEdit = true;
    $pageTitle = 'Editar Libro';
    $submitText = 'Actualizar Libro';
    
    $database = new \Database();
    $db = $database->getConnection();
    $bookModel = new Book($db);
    
    $book = $bookModel->getById(intval($_GET['id']));
    if (!$book) {
        AuthController::setFlashMessage('Libro no encontrado', 'error');
        header('Location: index.php?page=admin&action=books');
        exit();
    }
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new \Database();
    $db = $database->getConnection();
    $bookModel = new Book($db);
    
    $data = [
        'title' => trim($_POST['title'] ?? ''),
        'author' => trim($_POST['author'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'price' => floatval($_POST['price'] ?? 0),
        'amazon_url' => trim($_POST['amazon_url'] ?? ''),
        'cover_image' => trim($_POST['cover_image'] ?? ''),
        'category' => trim($_POST['category'] ?? 'English Learning'),
        'publication_date' => !empty($_POST['publication_date']) ? $_POST['publication_date'] : null,
        'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'display_order' => intval($_POST['display_order'] ?? 0)
    ];
    
    // Validaciones
    $errors = [];
    if (empty($data['title'])) $errors[] = 'El título es obligatorio';
    if (empty($data['author'])) $errors[] = 'El autor es obligatorio';
    if ($data['price'] <= 0) $errors[] = 'El precio debe ser mayor a 0';
    if (empty($data['amazon_url'])) $errors[] = 'La URL de Amazon es obligatoria';
    if (!filter_var($data['amazon_url'], FILTER_VALIDATE_URL)) $errors[] = 'La URL de Amazon no es válida';
    
    if (empty($errors)) {
        if ($isEdit) {
            $success = $bookModel->update(intval($_GET['id']), $data);
            $message = $success ? 'Libro actualizado exitosamente' : 'Error al actualizar el libro';
        } else {
            $success = $bookModel->create($data);
            $message = $success ? 'Libro creado exitosamente' : 'Error al crear el libro';
        }
        
        AuthController::setFlashMessage($message, $success ? 'success' : 'error');
        header('Location: index.php?page=admin&action=books');
        exit();
    } else {
        AuthController::setFlashMessage(implode(', ', $errors), 'error');
    }
}

// Obtener categorías existentes
$database = new Database();
$db = $database->getConnection();
$bookModel = new Book($db);
$categories = $bookModel->getCategories();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Admin Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        .nav-link:hover {
            color: white;
            transform: translateX(5px);
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border-left-color: #fbbf24;
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
        }

        .btn-secondary {
            background: linear-gradient(135deg, #64748b, #475569);
            color: white;
            box-shadow: 0 4px 15px rgba(100, 116, 139, 0.3);
        }

        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        /* Form Styles */
        .form-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .form-card {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid #f1f5f9;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .form-input,
        .form-textarea,
        .form-select {
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-input:focus,
        .form-textarea:focus,
        .form-select:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 120px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .form-checkbox {
            width: 18px;
            height: 18px;
            accent-color: #6366f1;
        }

        .checkbox-label {
            font-weight: 500;
            color: #374151;
            cursor: pointer;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #f1f5f9;
        }

        .image-preview {
            max-width: 200px;
            max-height: 250px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-top: 1rem;
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
                gap: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .content-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
                padding: 1.5rem;
            }

            .form-container {
                padding: 0 1rem;
            }

            .form-card {
                padding: 2rem;
            }

            .form-actions {
                flex-direction: column;
            }
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
                <a href="<?php echo $baseUrl; ?>index.php?page=admin&action=contact-info" class="nav-link">
                    <i class="fas fa-address-book"></i> Información de Contacto
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
            <!-- Flash Messages -->
            <?php if ($flashMessage): ?>
                <div class="flash-message flash-<?php echo htmlspecialchars($flashMessage['type']); ?>">
                    <i class="fas fa-<?php echo $flashMessage['type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <?php echo htmlspecialchars($flashMessage['message']); ?>
                </div>
            <?php endif; ?>

            <header class="content-header">
                <h1><i class="fas fa-<?php echo $isEdit ? 'edit' : 'plus'; ?>"></i> <?php echo $pageTitle; ?></h1>
                <a href="index.php?page=admin&action=books" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver a Libros
                </a>
            </header>

            <!-- Form -->
            <div class="form-container">
                <div class="form-card">
                    <form method="POST" action="">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="title" class="form-label">Título *</label>
                                <input type="text" id="title" name="title" class="form-input" 
                                       value="<?php echo htmlspecialchars($book['title'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="author" class="form-label">Autor *</label>
                                <input type="text" id="author" name="author" class="form-input" 
                                       value="<?php echo htmlspecialchars($book['author'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group full-width">
                                <label for="description" class="form-label">Descripción</label>
                                <textarea id="description" name="description" class="form-textarea" 
                                          placeholder="Descripción detallada del libro..."><?php echo htmlspecialchars($book['description'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="price" class="form-label">Precio *</label>
                                <input type="number" id="price" name="price" class="form-input" 
                                       step="0.01" min="0" value="<?php echo $book['price'] ?? ''; ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="category" class="form-label">Categoría</label>
                                <select id="category" name="category" class="form-select">
                                    <option value="English Learning" <?php echo (!$book || $book['category'] === 'English Learning') ? 'selected' : ''; ?>>English Learning</option>
                                    <option value="Grammar" <?php echo ($book && $book['category'] === 'Grammar') ? 'selected' : ''; ?>>Grammar</option>
                                    <option value="Vocabulary" <?php echo ($book && $book['category'] === 'Vocabulary') ? 'selected' : ''; ?>>Vocabulary</option>
                                    <option value="Business English" <?php echo ($book && $book['category'] === 'Business English') ? 'selected' : ''; ?>>Business English</option>
                                    <option value="Pronunciation" <?php echo ($book && $book['category'] === 'Pronunciation') ? 'selected' : ''; ?>>Pronunciation</option>
                                    <option value="Writing" <?php echo ($book && $book['category'] === 'Writing') ? 'selected' : ''; ?>>Writing</option>
                                    <option value="Reading" <?php echo ($book && $book['category'] === 'Reading') ? 'selected' : ''; ?>>Reading</option>
                                    <option value="Dictionary" <?php echo ($book && $book['category'] === 'Dictionary') ? 'selected' : ''; ?>>Dictionary</option>
                                    <?php foreach ($categories as $category): ?>
                                        <?php if (!in_array($category, ['English Learning', 'Grammar', 'Vocabulary', 'Business English', 'Pronunciation', 'Writing', 'Reading', 'Dictionary'])): ?>
                                            <option value="<?php echo htmlspecialchars($category); ?>" 
                                                    <?php echo ($book && $book['category'] === $category) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category); ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group full-width">
                                <label for="amazon_url" class="form-label">URL de Amazon *</label>
                                <input type="url" id="amazon_url" name="amazon_url" class="form-input" 
                                       value="<?php echo htmlspecialchars($book['amazon_url'] ?? ''); ?>" 
                                       placeholder="https://www.amazon.com/..." required>
                            </div>

                            <div class="form-group full-width">
                                <label for="cover_image" class="form-label">URL de Portada</label>
                                <input type="url" id="cover_image" name="cover_image" class="form-input" 
                                       value="<?php echo htmlspecialchars($book['cover_image'] ?? ''); ?>" 
                                       placeholder="https://..." onchange="previewImage(this.value)">
                                <img id="image-preview" class="image-preview" style="display: none;" alt="Vista previa">
                            </div>

                            <div class="form-group">
                                <label for="publication_date" class="form-label">Fecha de Publicación</label>
                                <input type="date" id="publication_date" name="publication_date" class="form-input" 
                                       value="<?php echo $book['publication_date'] ?? ''; ?>">
                            </div>

                            <div class="form-group">
                                <label for="display_order" class="form-label">Orden de Visualización</label>
                                <input type="number" id="display_order" name="display_order" class="form-input" 
                                       value="<?php echo $book['display_order'] ?? 0; ?>" min="0">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Estado</label>
                                <div class="checkbox-group">
                                    <input type="checkbox" id="is_featured" name="is_featured" class="form-checkbox" 
                                           <?php echo ($book && $book['is_featured']) ? 'checked' : ''; ?>>
                                    <label for="is_featured" class="checkbox-label">Libro Destacado</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" id="is_active" name="is_active" class="form-checkbox" 
                                           <?php echo (!$book || $book['is_active']) ? 'checked' : ''; ?>>
                                    <label for="is_active" class="checkbox-label">Activo</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <a href="index.php?page=admin&action=books" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-<?php echo $isEdit ? 'save' : 'plus'; ?>"></i> <?php echo $submitText; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Preview de imagen
        function previewImage(url) {
            const preview = document.getElementById('image-preview');
            if (url && url.trim() !== '') {
                preview.src = url;
                preview.style.display = 'block';
                preview.onerror = function() {
                    this.style.display = 'none';
                };
            } else {
                preview.style.display = 'none';
            }
        }

        // Cargar preview inicial si existe
        document.addEventListener('DOMContentLoaded', function() {
            const coverImageInput = document.getElementById('cover_image');
            if (coverImageInput.value) {
                previewImage(coverImageInput.value);
            }
        });

        // Responsive sidebar toggle
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('active');
        }

        // Add mobile menu button if needed
        if (window.innerWidth <= 1024) {
            const header = document.querySelector('.content-header');
            const menuBtn = document.createElement('button');
            menuBtn.innerHTML = '<i class="fas fa-bars"></i>';
            menuBtn.className = 'btn btn-secondary';
            menuBtn.onclick = toggleSidebar;
            menuBtn.style.position = 'absolute';
            menuBtn.style.left = '1rem';
            menuBtn.style.top = '50%';
            menuBtn.style.transform = 'translateY(-50%)';
            header.style.position = 'relative';
            header.appendChild(menuBtn);
        }
    </script>
</body>
</html>
