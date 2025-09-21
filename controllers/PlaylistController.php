<?php
namespace Controllers;

// Asegúrate de que la sesión esté iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Incluir las dependencias necesarias
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Playlist.php';
require_once __DIR__ . '/../models/Video.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

use Controllers\AuthController;
use Models\Playlist;
use Models\Video;
use Helpers\SecurityHelper;

class PlaylistController {
    private $db;
    private $playlistModel;
    private $videoModel;
    private $uploadDirs;

    public function __construct() {
        // Verificar autenticación para acciones administrativas
        if (!AuthController::isAuthenticated()) {
            AuthController::setFlashMessage('error', 'Debes iniciar sesión para acceder a esta función.');
            header('Location: ../../login.php');
            exit();
        }

        // Cargar dependencias
        $database = new \Database();
        $this->db = $database->getConnection();
        $this->playlistModel = new Playlist($this->db);
        $this->videoModel = new Video($this->db);

        // Configurar directorios de subida
        $this->uploadDirs = [
            'images' => __DIR__ . '/../uploads/images/',
            'videos' => __DIR__ . '/../uploads/videos/',
            'thumbnails' => __DIR__ . '/../uploads/thumbnails/'
        ];

        // Crear directorios si no existen
        foreach ($this->uploadDirs as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    public function index() {
        // Verificar permisos de administrador para la vista de administración
        if (AuthController::isAdmin()) {
            $playlists = $this->playlistModel->readAll();
            require_once __DIR__ . '/../views/admin/courses.php';
        } else {
            // Redirigir a la vista del cliente
            header('Location: ../client/home.php');
            exit();
        }
    }

    public function create() {
        // Solo administradores pueden crear playlists
        if (!AuthController::isAdmin()) {
            AuthController::setFlashMessage('error', 'Acceso denegado.');
            header('Location: ../client/home.php');
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            require_once __DIR__ . '/../views/admin/create_playlist.php';
            return;
        }

        // Validar token CSRF
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!SecurityHelper::validateCSRFToken($csrfToken)) {
            AuthController::setFlashMessage('error', 'Token de seguridad inválido.');
            header('Location: index.php?controller=playlist&action=index');
            exit();
        }

        // Sanitizar datos de entrada
        $data = [
            'name' => SecurityHelper::sanitizeInput($_POST['name'] ?? ''),
            'description' => SecurityHelper::sanitizeInput($_POST['description'] ?? ''),
            'level' => SecurityHelper::sanitizeInput($_POST['level'] ?? 'A1'),
            'price' => floatval($_POST['price'] ?? 0.00),
            'cover_image' => null
        ];

        // Validar datos requeridos
        if (empty($data['name']) || empty($data['description'])) {
            AuthController::setFlashMessage('error', 'El nombre y la descripción son requeridos.');
            require_once __DIR__ . '/../views/admin/create_playlist.php';
            return;
        }

        // Manejar subida de imagen
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            try {
                $data['cover_image'] = $this->handleImageUpload($_FILES['cover_image'], 'images');
            } catch (\Exception $e) {
                AuthController::setFlashMessage('error', 'Error al subir la imagen: ' . $e->getMessage());
                require_once __DIR__ . '/../views/admin/create_playlist.php';
                return;
            }
        }

        if ($this->playlistModel->create($data)) {
            AuthController::setFlashMessage('success', 'Curso creado exitosamente.');
            $this->redirect('playlist', 'index', null, true);
        } else {
            // Limpiar imagen subida si falla la creación
            if ($data['cover_image']) {
                $this->deleteFile($data['cover_image']);
            }
            AuthController::setFlashMessage('error', 'Error al crear el curso.');
            require_once __DIR__ . '/../views/admin/create_playlist.php';
        }
    }

    public function edit($id) {
        // Solo administradores pueden editar playlists
        if (!AuthController::isAdmin()) {
            AuthController::setFlashMessage('error', 'Acceso denegado.');
            header('Location: ../client/home.php');
            exit();
        }

        $playlist = $this->playlistModel->readOne($id);
        if (!$playlist) {
            AuthController::setFlashMessage('error', 'Curso no encontrado.');
            $this->redirect('playlist', 'index', null, true);
            return;
        }

        // Generar token CSRF
        $csrfToken = SecurityHelper::generateCSRFToken();
        
        require_once __DIR__ . '/../views/admin/edit_playlist.php';
    }

    public function update() {
        // Solo administradores pueden actualizar playlists
        if (!AuthController::isAdmin()) {
            AuthController::setFlashMessage('error', 'Acceso denegado.');
            header('Location: ../client/home.php');
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        // Validar token CSRF
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!SecurityHelper::validateCSRFToken($csrfToken)) {
            AuthController::setFlashMessage('error', 'Token de seguridad inválido.');
            $this->redirect('playlist', 'index', null, true);
            return;
        }

        $id = intval($_POST['id'] ?? 0);
        $current_playlist = $this->playlistModel->readOne($id);
        
        if (!$current_playlist) {
            AuthController::setFlashMessage('error', 'Curso no encontrado.');
            $this->redirect('playlist', 'index', null, true);
            return;
        }

        // Sanitizar datos de entrada
        $data = [
            'name' => SecurityHelper::sanitizeInput($_POST['name'] ?? ''),
            'description' => SecurityHelper::sanitizeInput($_POST['description'] ?? ''),
            'level' => SecurityHelper::sanitizeInput($_POST['level'] ?? 'A1'),
            'price' => floatval($_POST['price'] ?? 0.00),
            'cover_image' => $current_playlist['cover_image']
        ];

        // Validar datos requeridos
        if (empty($data['name']) || empty($data['description'])) {
            AuthController::setFlashMessage('error', 'El nombre y la descripción son requeridos.');
            $this->redirect('playlist', 'edit', $id, true);
            return;
        }

        // Manejar nueva imagen si se subió
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            try {
                // Eliminar imagen anterior
                if ($current_playlist['cover_image']) {
                    $this->deleteFile($current_playlist['cover_image']);
                }
                $data['cover_image'] = $this->handleImageUpload($_FILES['cover_image'], 'images');
            } catch (\Exception $e) {
                AuthController::setFlashMessage('error', 'Error al subir la imagen: ' . $e->getMessage());
                $this->redirect('playlist', 'edit', $id, true);
                return;
            }
        }

        if ($this->playlistModel->update($id, $data)) {
            AuthController::setFlashMessage('success', 'Curso actualizado exitosamente.');
            $this->redirect('playlist', 'index', null, true);
        } else {
            AuthController::setFlashMessage('error', 'Error al actualizar el curso.');
            $this->redirect('playlist', 'edit', $id, true);
        }
    }

    public function delete($id) {
        // Solo administradores pueden eliminar playlists
        if (!AuthController::isAdmin()) {
            AuthController::setFlashMessage('error', 'Acceso denegado.');
            header('Location: ../client/home.php');
            exit();
        }

        $playlist = $this->playlistModel->readOne($id);
        if (!$playlist) {
            AuthController::setFlashMessage('error', 'Curso no encontrado.');
            $this->redirect('playlist', 'index', null, true);
            return;
        }

        try {
            // Eliminar archivos asociados
            if ($playlist['cover_image']) {
                $this->deleteFile($playlist['cover_image']);
            }

            // Eliminar videos de la playlist
            $videos = $this->videoModel->readByPlaylist($id);
            foreach ($videos as $video) {
                if ($video['file_path']) {
                    $this->deleteFile($video['file_path']);
                }
                if ($video['thumbnail_image']) {
                    $this->deleteFile($video['thumbnail_image']);
                }
            }

            // Eliminar videos de la base de datos
            $this->videoModel->deleteByPlaylist($id);

            // Eliminar la playlist
            if ($this->playlistModel->delete($id)) {
                AuthController::setFlashMessage('success', 'Curso eliminado exitosamente.');
            } else {
                AuthController::setFlashMessage('error', 'Error al eliminar el curso de la base de datos.');
            }
        } catch (\Exception $e) {
            error_log("Error eliminando playlist $id: " . $e->getMessage());
            AuthController::setFlashMessage('error', 'Error al eliminar el curso.');
        }

        $this->redirect('playlist', 'index', null, true);
    }

    // Método para la vista de detalles del cliente
    public function viewClientDetail($id) {
        $playlist = $this->playlistModel->readOne($id);
        if (!$playlist) {
            AuthController::setFlashMessage('error', 'Curso no encontrado.');
            header('Location: home.php');
            exit();
        }
        require_once __DIR__ . '/../views/client/course-detail.php';
    }

    private function handleImageUpload($file, $type) {
        $allowedTypes = ['jpeg', 'jpg', 'png'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        // Validar tipo de archivo
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedTypes)) {
            throw new \Exception("Tipo de archivo no permitido. Solo se permiten: " . implode(', ', $allowedTypes));
        }

        // Validar tamaño
        if ($file['size'] > $maxSize) {
            throw new \Exception("El archivo es demasiado grande. Tamaño máximo: 5MB");
        }

        // Validar que sea una imagen real
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            throw new \Exception("El archivo no es una imagen válida.");
        }

        $uploadDir = $this->uploadDirs[$type];
        
        // Generar nombre único
        $filename = time() . '_' . uniqid() . '.' . $extension;
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return "uploads/{$type}/" . $filename;
        } else {
            throw new \Exception("Error al mover el archivo subido.");
        }
    }

    private function deleteFile($filePath) {
        $fullPath = __DIR__ . '/../' . $filePath;
        if (file_exists($fullPath)) {
            if (!unlink($fullPath)) {
                error_log("No se pudo eliminar el archivo: $fullPath");
            }
        }
    }

    private function redirect($controller, $action, $id = null, $isAdmin = false) {
        if ($isAdmin) {
            $base_url = "../admin/index.php";
        } else {
            $base_url = "index.php";
        }
        
        $url = "{$base_url}?controller={$controller}&action={$action}";
        if ($id) $url .= "&id={$id}";
        
        header("Location: {$url}");
        exit();
    }
}

// Manejar las rutas si se accede directamente a este archivo
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    $action = $_GET['action'] ?? 'index';
    $id = $_GET['id'] ?? null;
    $controller = new PlaylistController();
    
    switch ($action) {
        case 'index':
            $controller->index();
            break;
        case 'create':
            $controller->create();
            break;
        case 'edit':
            if ($id) {
                $controller->edit($id);
            } else {
                $controller->index();
            }
            break;
        case 'update':
            $controller->update();
            break;
        case 'delete':
            if ($id) {
                $controller->delete($id);
            } else {
                $controller->index();
            }
            break;
        case 'view_client_detail':
            if ($id) {
                $controller->viewClientDetail($id);
            } else {
                header('Location: ../client/home.php');
                exit();
            }
            break;
        default:
            $controller->index();
            break;
    }
}
?>
