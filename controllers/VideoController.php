<?php
namespace Controllers;

// Asegúrate de que la sesión esté iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Incluir las dependencias necesarias
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Video.php';
require_once __DIR__ . '/../models/Playlist.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

use Controllers\AuthController;
use Models\Video;
use Models\Playlist;
use Helpers\SecurityHelper;
use Exception;

class VideoController {
    private $db;
    private $videoModel;
    private $playlistModel;
    private $uploadDirs;

    public function __construct() {
        // Verificar autenticación
        if (!AuthController::isAuthenticated()) {
            AuthController::setFlashMessage('error', 'Debes iniciar sesión para acceder a esta función.');
            header('Location: ../../login.php');
            exit();
        }

        // Cargar dependencias
        $database = new \Database();
        $this->db = $database->getConnection();
        $this->videoModel = new Video($this->db);
        $this->playlistModel = new Playlist($this->db);

        // Configurar directorios de subida
        $this->uploadDirs = [
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

    public function upload() {
        // Solo administradores pueden subir videos
        if (!AuthController::isAdmin()) {
            AuthController::setFlashMessage('error', 'Acceso denegado.');
            header('Location: ../client/home.php');
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['video'])) {
    // Mostrar formulario de subida
    $playlists = $this->playlistModel->readAll();
    $csrfToken = SecurityHelper::generateCSRFToken();
    
    // Si se especifica un playlist_id en la URL, preseleccionarlo
    $selected_playlist_id = $_GET['playlist_id'] ?? null;
    
    require_once __DIR__ . '/../views/admin/upload_video.php';
    return;
}

        // Validar token CSRF
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!SecurityHelper::validateCSRFToken($csrfToken)) {
            AuthController::setFlashMessage('error', 'Token de seguridad inválido.');
            $this->redirect('video', 'upload');
            return;
        }

        // Sanitizar datos de entrada
        $data = [
            'title' => SecurityHelper::sanitizeInput($_POST['title'] ?? ''),
            'description' => SecurityHelper::sanitizeInput($_POST['description'] ?? ''),
            'playlist_id' => intval($_POST['playlist_id'] ?? 0),
            'file_path' => null,
            'thumbnail_image' => null
        ];

        // Validar datos requeridos
        if (empty($data['title']) || empty($data['playlist_id'])) {
            AuthController::setFlashMessage('error', 'El título y la playlist son requeridos.');
            $playlists = $this->playlistModel->readAll();
            $csrfToken = SecurityHelper::generateCSRFToken();
            require_once __DIR__ . '/../views/admin/upload_video.php';
            return;
        }

        try {
            // Subir video
            $data['file_path'] = $this->handleVideoUpload($_FILES['video']);

            // Subir miniatura si existe
            if (isset($_FILES['thumbnail_image']) && $_FILES['thumbnail_image']['error'] === UPLOAD_ERR_OK) {
                $data['thumbnail_image'] = $this->handleImageUpload($_FILES['thumbnail_image']);
            }

            if ($this->videoModel->create($data)) {
                AuthController::setFlashMessage('success', 'Video subido exitosamente.');
                $this->redirect('video', 'view_playlist', $data['playlist_id']);
            } else {
                // Limpiar archivos si falla la BD
                if ($data['file_path']) $this->deleteFile($data['file_path']);
                if ($data['thumbnail_image']) $this->deleteFile($data['thumbnail_image']);
                
                AuthController::setFlashMessage('error', 'Error al guardar el video en la base de datos.');
                $this->redirect('video', 'upload');
            }
        } catch (Exception $e) {
            // Limpiar archivos si hay error
            if (isset($data['file_path']) && $data['file_path']) $this->deleteFile($data['file_path']);
            if (isset($data['thumbnail_image']) && $data['thumbnail_image']) $this->deleteFile($data['thumbnail_image']);
            
            AuthController::setFlashMessage('error', 'Error al subir el video: ' . $e->getMessage());
            $this->redirect('video', 'upload');
        }
    }

    public function viewPlaylist($id) {
        // Verificar que la playlist existe
        $playlist = $this->playlistModel->findById($id);
        if (!$playlist) {
            AuthController::setFlashMessage('error', 'Playlist no encontrada.');
            $this->redirect('playlist', 'index', null, true);
            return;
        }

        $videos = $this->videoModel->readByPlaylist($id);
        
        if (AuthController::isAdmin()) {
            require_once __DIR__ . '/../views/admin/view_playlist.php';
        } else {
            // Vista del cliente - verificar acceso al curso
            require_once __DIR__ . '/../views/client/playlist_videos.php';
        }
    }

    public function viewVideo($id) {
        $video = $this->videoModel->readOne($id);
        if (!$video) {
            AuthController::setFlashMessage('error', 'Video no encontrado.');
            $this->redirect('playlist', 'index', null, AuthController::isAdmin());
            return;
        }
        
        $playlist = $this->playlistModel->findById($video['playlist_id']);
        $related_videos = $this->videoModel->readByPlaylist($video['playlist_id'], $id);
        
        if (AuthController::isAdmin()) {
            require_once __DIR__ . '/../views/admin/view_video.php';
        } else {
            // Vista del cliente - verificar acceso al curso
            require_once __DIR__ . '/../views/client/view_video.php';
        }
    }

    public function editVideo($id) {
        // Solo administradores pueden editar videos
        if (!AuthController::isAdmin()) {
            AuthController::setFlashMessage('error', 'Acceso denegado.');
            header('Location: ../client/home.php');
            exit();
        }

        $video = $this->videoModel->readOne($id);
        if (!$video) {
            AuthController::setFlashMessage('error', 'Video no encontrado.');
            $this->redirect('playlist', 'index', null, true);
            return;
        }

        $playlists = $this->playlistModel->readAll();
        $csrfToken = SecurityHelper::generateCSRFToken();
        require_once __DIR__ . '/../views/admin/edit_video.php';
    }

    public function updateVideo() {
        // Solo administradores pueden actualizar videos
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
        $current_video = $this->videoModel->readOne($id);
        
        if (!$current_video) {
            AuthController::setFlashMessage('error', 'Video no encontrado.');
            $this->redirect('playlist', 'index', null, true);
            return;
        }

        // Sanitizar datos de entrada
        $data = [
            'title' => SecurityHelper::sanitizeInput($_POST['title'] ?? ''),
            'description' => SecurityHelper::sanitizeInput($_POST['description'] ?? ''),
            'playlist_id' => intval($_POST['playlist_id'] ?? 0),
            'file_path' => $current_video['file_path'],
            'thumbnail_image' => $current_video['thumbnail_image']
        ];

        // Validar datos requeridos
        if (empty($data['title']) || empty($data['playlist_id'])) {
            AuthController::setFlashMessage('error', 'El título y la playlist son requeridos.');
            $this->redirect('video', 'edit_video', $id);
            return;
        }

        try {
            // Actualizar miniatura si se subió nueva
            if (isset($_FILES['thumbnail_image']) && $_FILES['thumbnail_image']['error'] === UPLOAD_ERR_OK) {
                if ($current_video['thumbnail_image']) {
                    $this->deleteFile($current_video['thumbnail_image']);
                }
                $data['thumbnail_image'] = $this->handleImageUpload($_FILES['thumbnail_image']);
            }

            if ($this->videoModel->update($id, $data)) {
                AuthController::setFlashMessage('success', 'Video actualizado exitosamente.');
                $this->redirect('video', 'view_playlist', $data['playlist_id']);
            } else {
                AuthController::setFlashMessage('error', 'Error al actualizar el video.');
                $this->redirect('video', 'edit_video', $id);
            }
        } catch (Exception $e) {
            AuthController::setFlashMessage('error', 'Error al actualizar el video: ' . $e->getMessage());
            $this->redirect('video', 'edit_video', $id);
        }
    }

    public function deleteVideo($id) {
        // Solo administradores pueden eliminar videos
        if (!AuthController::isAdmin()) {
            AuthController::setFlashMessage('error', 'Acceso denegado.');
            header('Location: ../client/home.php');
            exit();
        }

        $video = $this->videoModel->readOne($id);
        if (!$video) {
            AuthController::setFlashMessage('error', 'Video no encontrado.');
            $this->redirect('playlist', 'index', null, true);
            return;
        }

        try {
            // Eliminar archivos
            if ($video['file_path']) $this->deleteFile($video['file_path']);
            if ($video['thumbnail_image']) $this->deleteFile($video['thumbnail_image']);

            if ($this->videoModel->delete($id)) {
                AuthController::setFlashMessage('success', 'Video eliminado exitosamente.');
            } else {
                AuthController::setFlashMessage('error', 'Error al eliminar el video de la base de datos.');
            }
        } catch (Exception $e) {
            error_log("Error eliminando video $id: " . $e->getMessage());
            AuthController::setFlashMessage('error', 'Error al eliminar el video.');
        }

        $this->redirect('video', 'view_playlist', $video['playlist_id']);
    }

    private function handleVideoUpload($file) {
        $allowedTypes = ['mp4', 'avi', 'mov', 'wmv'];
        $maxSize = 250 * 1024 * 1024; // 250MB

        // Validar tipo de archivo
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedTypes)) {
            throw new Exception("Tipo de archivo no permitido. Solo se permiten: " . implode(', ', $allowedTypes));
        }

        // Validar tamaño
        if ($file['size'] > $maxSize) {
            throw new Exception("El archivo es demasiado grande. Tamaño máximo: 250MB");
        }

        $uploadDir = $this->uploadDirs['videos'];
        
        // Generar nombre único
        $filename = time() . '_' . uniqid() . '.' . $extension;
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return 'uploads/videos/' . $filename;
        } else {
            throw new Exception("Error al mover el archivo de video.");
        }
    }

    private function handleImageUpload($file) {
        $allowedTypes = ['jpeg', 'jpg', 'png'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        // Validar tipo de archivo
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedTypes)) {
            throw new Exception("Tipo de archivo no permitido para miniatura. Solo se permiten: " . implode(', ', $allowedTypes));
        }

        // Validar tamaño
        if ($file['size'] > $maxSize) {
            throw new Exception("La imagen es demasiado grande. Tamaño máximo: 5MB");
        }

        // Validar que sea una imagen real
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            throw new Exception("El archivo no es una imagen válida.");
        }

        $uploadDir = $this->uploadDirs['thumbnails'];
        
        // Generar nombre único
        $filename = time() . '_' . uniqid() . '.' . $extension;
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return 'uploads/thumbnails/' . $filename;
        } else {
            throw new Exception("Error al mover la imagen de miniatura.");
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

    private function redirect($controller, $action, $id = null) {
        $base_url = AuthController::isAdmin() ? "../admin/index.php" : "index.php";
        $url = "{$base_url}?controller={$controller}&action={$action}";
        if ($id) $url .= "&id={$id}";
        header("Location: {$url}");
        exit();
    }
}

// Manejar las rutas si se accede directamente a este archivo
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    $action = $_GET['action'] ?? 'upload';
    $id = $_GET['id'] ?? null;
    $controller = new VideoController();
    
    switch ($action) {
        case 'upload':
            $controller->upload();
            break;
        case 'view_playlist':
            if ($id) {
                $controller->viewPlaylist($id);
            } else {
                header('Location: ../admin/index.php?controller=playlist&action=index');
                exit();
            }
            break;
        case 'view_video':
            if ($id) {
                $controller->viewVideo($id);
            } else {
                header('Location: ../admin/index.php?controller=playlist&action=index');
                exit();
            }
            break;
        case 'edit_video':
            if ($id) {
                $controller->editVideo($id);
            } else {
                header('Location: ../admin/index.php?controller=playlist&action=index');
                exit();
            }
            break;
        case 'update_video':
            $controller->updateVideo();
            break;
        case 'delete_video':
            if ($id) {
                $controller->deleteVideo($id);
            } else {
                header('Location: ../admin/index.php?controller=playlist&action=index');
                exit();
            }
            break;
        default:
            $controller->upload();
            break;
    }
}
?>
