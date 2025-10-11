<?php
// Incluir configuración de URL base
include __DIR__ . '/config.php';

// Verificar autenticación
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../config/upload.php';
require_once __DIR__ . '/../../helpers/SecurityHelper.php';
use Controllers\AuthController;

if (!AuthController::isAdmin()) {
    header('Location: ../../login.php');
    exit();
}

// Obtener cursos
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../models/Playlist.php';
require_once __DIR__ . '/../../models/Video.php';
require_once __DIR__ . '/../../models/VideoFile.php';
use Models\Playlist;
use Models\Video;
use Models\VideoFile;

$database = new \Database();
$db = $database->getConnection();
$playlistModel = new Playlist($db);
$videoModel = new Video($db);
$videoFileModel = new VideoFile($db);

// Crear directorios de upload
UploadConfig::createDirectories();

// Manejar acciones
$action = $_GET['sub_action'] ?? '';
$courseId = $_GET['course_id'] ?? '';

// Manejar solicitud AJAX para obtener datos del curso
if ($action === 'get_course' && $courseId) {
    header('Content-Type: application/json');
    $course = $playlistModel->findById($courseId);
    if ($course) {
        echo json_encode($course);
    } else {
        echo json_encode(['error' => 'Curso no encontrado']);
    }
    exit();
}

// Manejar solicitud AJAX para obtener datos del video
if ($action === 'get_video' && isset($_GET['video_id'])) {
    header('Content-Type: application/json');
    $videoId = $_GET['video_id'];
    $video = $videoModel->readOne($videoId);
    if ($video) {
        echo json_encode($video);
    } else {
        echo json_encode(['error' => 'Video no encontrado']);
    }
    exit();
}

// Manejar solicitud AJAX para obtener archivos del video
if ($action === 'get_video_files' && isset($_GET['video_id'])) {
    header('Content-Type: application/json');
    $videoId = $_GET['video_id'];
    $files = $videoFileModel->readByVideo($videoId);
    
    $formattedFiles = [];
    foreach ($files as $file) {
        $formattedFiles[] = [
            'id' => $file['id'],
            'original_name' => $file['original_name'],
            'file_size' => $videoFileModel->formatFileSize($file['file_size']),
            'file_type' => $file['file_type'],
            'icon' => $videoFileModel->getFileIcon($file['file_type']),
            'created_at' => $file['created_at']
        ];
    }
    
    echo json_encode(['success' => true, 'files' => $formattedFiles]);
    exit();
}

// Manejar subida de archivo para video
if ($action === 'upload_file' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $videoId = $_POST['video_id'] ?? '';
    $description = trim($_POST['file_description'] ?? '');
    
    error_log("=== SUBIENDO ARCHIVO PARA VIDEO ===");
    error_log("Video ID: $videoId, Description: $description");
    error_log("FILES: " . print_r($_FILES, true));
    
    if (!empty($videoId) && isset($_FILES['video_file_upload'])) {
        try {
            // Verificar que el video existe
            $video = $videoModel->readOne($videoId);
            if (!$video) {
                throw new Exception("Video no encontrado");
            }
            
            // Manejar subida del archivo
            $fileData = UploadConfig::handleFileUpload($_FILES['video_file_upload'], 'video_files');
            error_log("Archivo subido: " . print_r($fileData, true));
            
            // Guardar en base de datos
            $videoFileModel->video_id = $videoId;
            $videoFileModel->filename = $fileData['file_name'];
            $videoFileModel->original_name = $fileData['original_name'];
            $videoFileModel->file_path = $fileData['file_path'];
            $videoFileModel->file_type = $fileData['file_type'];
            $videoFileModel->file_size = $fileData['file_size'];
            
            if ($videoFileModel->create()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Archivo agregado exitosamente al video',
                    'file' => [
                        'id' => $videoFileModel->id,
                        'original_name' => $videoFileModel->original_name,
                        'file_size' => $videoFileModel->formatFileSize($videoFileModel->file_size),
                        'file_type' => $videoFileModel->file_type,
                        'icon' => $videoFileModel->getFileIcon($videoFileModel->file_type)
                    ]
                ]);
                error_log("SUCCESS: Archivo creado con ID " . $videoFileModel->id);
            } else {
                // Limpiar archivo si falla la BD
                if (file_exists(__DIR__ . '/../../' . $fileData['file_path'])) {
                    unlink(__DIR__ . '/../../' . $fileData['file_path']);
                }
                echo json_encode(['success' => false, 'error' => 'Error al agregar el archivo a la base de datos']);
                error_log("ERROR: No se pudo crear el archivo en BD");
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            error_log("EXCEPTION: " . $e->getMessage());
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'El archivo es requerido']);
        error_log("ERROR: Archivo faltante");
    }
    exit();
}

// Manejar eliminación de archivo
if ($action === 'delete_file' && isset($_GET['file_id'])) {
    header('Content-Type: application/json');
    
    $fileId = $_GET['file_id'];
    try {
        // Obtener archivo para eliminar físicamente
        $file = $videoFileModel->readOne($fileId);
        if ($file) {
            // Eliminar archivo físico
            if ($file['file_path'] && file_exists(__DIR__ . '/../../' . $file['file_path'])) {
                unlink(__DIR__ . '/../../' . $file['file_path']);
            }
            
            if ($videoFileModel->delete($fileId)) {
                echo json_encode(['success' => true, 'message' => 'Archivo eliminado exitosamente']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al eliminar el archivo']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Archivo no encontrado']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// Manejar subida de video
if ($action === 'upload_video' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $playlistId = $_POST['playlist_id'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    error_log("=== SUBIENDO VIDEO ===");
    error_log("Playlist ID: $playlistId, Title: $title");
    error_log("FILES: " . print_r($_FILES, true));
    
    if (!empty($playlistId) && !empty($title) && isset($_FILES['video_file'])) {
        try {
            // Manejar subida del archivo de video
            $videoFile = '';
            if ($_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
                error_log("Procesando archivo de video...");
                $videoFile = UploadConfig::handleVideoUpload($_FILES['video_file'], 'videos');
                error_log("Video subido: $videoFile");
            } else {
                throw new Exception("Error al subir el archivo de video. Código de error: " . $_FILES['video_file']['error']);
            }
            
            // Manejar subida de miniatura del video (opcional)
            $thumbnailImage = '';
            if (isset($_FILES['thumbnail_image']) && $_FILES['thumbnail_image']['error'] === UPLOAD_ERR_OK) {
                error_log("Procesando miniatura del video...");
                $thumbnailImage = UploadConfig::handleImageUpload($_FILES['thumbnail_image'], 'video_thumbnails');
                error_log("Miniatura subida: $thumbnailImage");
            }
            
            // Asignar valores al modelo de video
            $videoModel->playlist_id = $playlistId;
            $videoModel->title = $title;
            $videoModel->description = $description;
            $videoModel->file_path = $videoFile;
            $videoModel->thumbnail_image = $thumbnailImage;
            
            error_log("Creando video en BD...");
            if ($videoModel->create()) {
                $success_message = "Video agregado exitosamente al curso";
                error_log("SUCCESS: Video creado con ID " . $videoModel->id);
            } else {
                $error_message = "Error al agregar el video a la base de datos";
                error_log("ERROR: No se pudo crear el video en BD");
                // Limpiar archivos si falla la BD
                if ($videoFile && file_exists(__DIR__ . '/../../' . $videoFile)) {
                    unlink(__DIR__ . '/../../' . $videoFile);
                }
                if ($thumbnailImage && file_exists(__DIR__ . '/../../' . $thumbnailImage)) {
                    unlink(__DIR__ . '/../../' . $thumbnailImage);
                }
            }
        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
            error_log("EXCEPTION: " . $e->getMessage());
            // Limpiar archivos si hay error
            if (isset($videoFile) && $videoFile && file_exists(__DIR__ . '/../../' . $videoFile)) {
                unlink(__DIR__ . '/../../' . $videoFile);
            }
            if (isset($thumbnailImage) && $thumbnailImage && file_exists(__DIR__ . '/../../' . $thumbnailImage)) {
                unlink(__DIR__ . '/../../' . $thumbnailImage);
            }
        }
    } else {
        $error_message = "El título y el archivo de video son requeridos";
        error_log("ERROR: Campos requeridos faltantes");
    }
}

// Manejar actualización de video
if ($action === 'update_video' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $videoId = $_POST['video_id'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    error_log("=== ACTUALIZANDO VIDEO ===");
    error_log("Video ID: $videoId, Title: $title");
    
    if (!empty($videoId) && !empty($title)) {
        try {
            // Obtener video actual
            $currentVideo = $videoModel->readOne($videoId);
            if (!$currentVideo) {
                throw new Exception("Video no encontrado");
            }
            
            $thumbnailImage = $currentVideo['thumbnail_image'];
            
            // Manejar nueva miniatura si se subió
            if (isset($_FILES['thumbnail_image']) && $_FILES['thumbnail_image']['error'] === UPLOAD_ERR_OK) {
                error_log("Procesando nueva miniatura del video...");
                
                // Eliminar miniatura anterior si existe
                if ($thumbnailImage && file_exists(__DIR__ . '/../../' . $thumbnailImage)) {
                    unlink(__DIR__ . '/../../' . $thumbnailImage);
                }
                
                $thumbnailImage = UploadConfig::handleImageUpload($_FILES['thumbnail_image'], 'video_thumbnails');
                error_log("Nueva miniatura subida: $thumbnailImage");
            }
            
            // Preparar datos para actualizar
            $updateData = [
                'title' => $title,
                'description' => $description,
                'file_path' => $currentVideo['file_path'],
                'thumbnail_image' => $thumbnailImage,
                'playlist_id' => $currentVideo['playlist_id']
            ];
            
            error_log("Actualizando video en BD...");
            if ($videoModel->update($videoId, $updateData)) {
                $success_message = "Video actualizado exitosamente";
                error_log("SUCCESS: Video actualizado con ID " . $videoId);
            } else {
                $error_message = "Error al actualizar el video en la base de datos";
                error_log("ERROR: No se pudo actualizar el video en BD");
            }
        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
            error_log("EXCEPTION: " . $e->getMessage());
        }
    } else {
        $error_message = "El título del video es requerido";
        error_log("ERROR: Título del video faltante");
    }
}

// Manejar eliminación de video
if ($action === 'delete_video' && isset($_GET['video_id'])) {
    $videoId = $_GET['video_id'];
    try {
        // Obtener video para eliminar archivos
        $video = $videoModel->readOne($videoId);
        if ($video) {
            // Eliminar archivos asociados al video
            $videoFiles = $videoFileModel->readByVideo($videoId);
            foreach ($videoFiles as $file) {
                if ($file['file_path'] && file_exists(__DIR__ . '/../../' . $file['file_path'])) {
                    unlink(__DIR__ . '/../../' . $file['file_path']);
                }
            }
            $videoFileModel->deleteByVideo($videoId);
            
            // Eliminar archivo de video
            if ($video['file_path'] && file_exists(__DIR__ . '/../../' . $video['file_path'])) {
                unlink(__DIR__ . '/../../' . $video['file_path']);
            }
            // Eliminar miniatura
            if ($video['thumbnail_image'] && file_exists(__DIR__ . '/../../' . $video['thumbnail_image'])) {
                unlink(__DIR__ . '/../../' . $video['thumbnail_image']);
            }
            
            if ($videoModel->delete($videoId)) {
                $success_message = "Video eliminado exitosamente";
            } else {
                $error_message = "Error al eliminar el video";
            }
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'upload_video' && $action !== 'upload_file' && $action !== 'update_video') {
    if ($action === 'create') {
        $title = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $level = $_POST['level'] ?? 'A1';
        $price = floatval($_POST['price'] ?? 0);
        
        if (!empty($title) && !empty($description)) {
            try {
                // Manejar subida de imagen
                $thumbnail = '';
                if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                    $thumbnail = UploadConfig::handleImageUpload($_FILES['thumbnail'], 'thumbnails');
                }
                
                // Asignar valores al modelo
                $playlistModel->title = $title;
                $playlistModel->description = $description;
                $playlistModel->level = $level;
                $playlistModel->price = $price;
                $playlistModel->thumbnail = $thumbnail;
                
                if ($playlistModel->create()) {
                    $success_message = "Curso creado exitosamente";
                } else {
                    $error_message = "Error al crear el curso en la base de datos";
                }
            } catch (Exception $e) {
                $error_message = "Error: " . $e->getMessage();
            }
        } else {
            $error_message = "El nombre y la descripción son requeridos";
        }
    } elseif ($action === 'edit' && $courseId) {
        $title = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $level = $_POST['level'] ?? 'A1';
        $price = floatval($_POST['price'] ?? 0);
        
        if (!empty($title) && !empty($description)) {
            try {
                // Obtener curso actual
                $currentCourse = $playlistModel->findById($courseId);
                $thumbnail = $currentCourse['thumbnail'] ?? '';
                
                // Manejar nueva imagen si se subió
                if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                    // Eliminar imagen anterior si existe
                    if ($thumbnail && file_exists(__DIR__ . '/../../' . $thumbnail)) {
                        unlink(__DIR__ . '/../../' . $thumbnail);
                    }
                    $thumbnail = UploadConfig::handleImageUpload($_FILES['thumbnail'], 'thumbnails');
                }
                
                // Asignar valores al modelo
                $playlistModel->id = $courseId;
                $playlistModel->title = $title;
                $playlistModel->description = $description;
                $playlistModel->level = $level;
                $playlistModel->price = $price;
                $playlistModel->thumbnail = $thumbnail;
                
                if ($playlistModel->update()) {
                    $success_message = "Curso actualizado exitosamente";
                } else {
                    $error_message = "Error al actualizar el curso";
                }
            } catch (Exception $e) {
                $error_message = "Error: " . $e->getMessage();
            }
        } else {
            $error_message = "El nombre y la descripción son requeridos";
        }
    }
}

// Manejar eliminación de curso
if ($action === 'delete' && $courseId) {
    try {
        // Obtener curso para eliminar imagen
        $course = $playlistModel->findById($courseId);
        if ($course && $course['thumbnail'] && file_exists(__DIR__ . '/../../' . $course['thumbnail'])) {
            unlink(__DIR__ . '/../../' . $course['thumbnail']);
        }
        
        // Eliminar videos del curso y sus archivos asociados
        $videos = $videoModel->readByPlaylist($courseId);
        foreach ($videos as $video) {
            // Eliminar archivos asociados al video
            $videoFiles = $videoFileModel->readByVideo($video['id']);
            foreach ($videoFiles as $file) {
                if ($file['file_path'] && file_exists(__DIR__ . '/../../' . $file['file_path'])) {
                    unlink(__DIR__ . '/../../' . $file['file_path']);
                }
            }
            $videoFileModel->deleteByVideo($video['id']);
            
            // Eliminar archivos del video
            if ($video['file_path'] && file_exists(__DIR__ . '/../../' . $video['file_path'])) {
                unlink(__DIR__ . '/../../' . $video['file_path']);
            }
            if ($video['thumbnail_image'] && file_exists(__DIR__ . '/../../' . $video['thumbnail_image'])) {
                unlink(__DIR__ . '/../../' . $video['thumbnail_image']);
            }
        }
        $videoModel->deleteByPlaylist($courseId);
        
        $playlistModel->id = $courseId;
        if ($playlistModel->delete()) {
            $success_message = "Curso eliminado exitosamente";
        } else {
            $error_message = "Error al eliminar el curso";
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

$courses = $playlistModel->readAll();
$currentUser = AuthController::getCurrentUser();

// Obtener videos para vista de playlist
$playlistVideos = [];
if ($action === 'view_videos' && $courseId) {
    $playlistVideos = $videoModel->readByPlaylist($courseId);
    $viewPlaylist = $playlistModel->findById($courseId);
}

function getFileIconClass($fileType) {
    if ($fileType === 'application/pdf') return 'pdf';
    if (strpos($fileType, 'image/') === 0) return 'image';
    if (strpos($fileType, 'word') !== false) return 'word';
    if (strpos($fileType, 'excel') !== false || strpos($fileType, 'sheet') !== false) return 'excel';
    if (strpos($fileType, 'powerpoint') !== false || strpos($fileType, 'presentation') !== false) return 'powerpoint';
    if ($fileType === 'text/plain') return 'text';
    if (strpos($fileType, 'zip') !== false || strpos($fileType, 'rar') !== false) return 'archive';
    return 'default';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cursos - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
:root {
    --primary-color: #6366f1;
    --secondary-color: #8b5cf6;
    --accent-color: #06b6d4;
    --success-color: #10b981;
    --warning-color: #f59e0b;
    --danger-color: #ef4444;
    --info-color: #3b82f6;
    --light-purple: #f3f4f6;
    --medium-purple: #e5e7eb;
    --dark-purple: #374151;
    --text-color: #1f2937;
    --border-color: #e5e7eb;
    --white: #ffffff;
    --background-color: #f8fafc;
    --shadow-light: 0 1px 3px rgba(0, 0, 0, 0.1);
    --shadow-medium: 0 4px 6px rgba(0, 0, 0, 0.1);
    --shadow-heavy: 0 10px 25px rgba(0, 0, 0, 0.15);
    --border-radius: 12px;
    --border-radius-lg: 20px;
    --border-radius-xl: 24px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Reset y estilos base */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    color: var(--text-color);
}

/* Layout principal */
.admin-layout {
    display: flex;
    min-height: 100vh;
}

/* Sidebar mejorado */
.sidebar {
    width: 280px;
    background: var(--white);
    box-shadow: var(--shadow-medium);
    border-radius: 0 var(--border-radius-lg) var(--border-radius-lg) 0;
    margin: 20px 0 20px 20px;
    overflow: hidden;
    position: fixed;
    height: calc(100vh - 40px);
    z-index: 100;
}

.sidebar-header {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: var(--white);
    padding: 30px 25px;
    text-align: center;
}

.sidebar-header h2 {
    font-size: 1.5rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
}

.sidebar-nav {
    padding: 20px 0;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px 25px;
    color: var(--text-color);
    text-decoration: none;
    font-weight: 500;
    transition: var(--transition);
    border-left: 4px solid transparent;
}

.nav-link:hover {
    background: var(--light-purple);
    border-left-color: var(--primary-color);
    transform: translateX(5px);
}

.nav-link.active {
    background: linear-gradient(90deg, rgba(99, 102, 241, 0.1), transparent);
    border-left-color: var(--primary-color);
    color: var(--primary-color);
    font-weight: 600;
}

/* Contenido principal */
.main-content {
    flex: 1;
    padding: 20px;
    margin-left: 320px; /* 280px sidebar + 40px spacing */
    min-height: 100vh;
}

.content-header {
    background: var(--white);
    padding: 25px 30px;
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-light);
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.content-header h1 {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-color);
    display: flex;
    align-items: center;
    gap: 15px;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--dark-purple);
    font-weight: 500;
    background: var(--light-purple);
    padding: 12px 20px;
    border-radius: var(--border-radius);
}

/* Contenido de cursos */
.courses-content {
    background: var(--white);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-light);
    padding: 40px;
    min-height: calc(100vh - 200px);
    margin-bottom: 30px;
}

/* Header de cursos */
.courses-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 40px;
    padding-bottom: 25px;
    border-bottom: 2px solid var(--light-purple);
}

.courses-actions {
    display: flex;
    gap: 15px;
}

/* Grid de cursos */
.courses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 30px;
    margin-top: 20px;
}

.course-card {
    background: var(--white);
    border-radius: var(--border-radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-light);
    transition: var(--transition);
    position: relative;
    border: 2px solid var(--light-purple);
}

.course-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-heavy);
    border-color: var(--primary-color);
}

.course-image {
    height: 200px;
    overflow: hidden;
    position: relative;
    background: linear-gradient(135deg, var(--light-purple), var(--medium-purple));
}

.course-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: var(--transition);
}

.course-card:hover .course-image img {
    transform: scale(1.1);
}

.no-image {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: var(--white);
    font-size: 3rem;
}

.course-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(99, 102, 241, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: var(--transition);
}

.course-card:hover .course-overlay {
    opacity: 1;
}

.course-overlay-actions {
    display: flex;
    gap: 15px;
}

.overlay-btn {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    border: none;
    color: var(--white);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition);
    font-size: 1.4rem;
    box-shadow: var(--shadow-medium);
}

.overlay-btn:hover {
    transform: scale(1.2);
}

.overlay-btn.edit {
    background: var(--warning-color);
}

.overlay-btn.view {
    background: var(--info-color);
}

.overlay-btn.delete {
    background: var(--danger-color);
}

.course-info {
    padding: 25px;
}

.course-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
    gap: 15px;
}

.course-title {
    margin: 0;
    font-size: 1.4rem;
    color: var(--text-color);
    font-weight: 600;
    line-height: 1.3;
    flex: 1;
}

.course-level {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: var(--white);
    padding: 8px 16px;
    border-radius: var(--border-radius);
    font-size: 0.85rem;
    font-weight: 600;
    white-space: nowrap;
    box-shadow: var(--shadow-light);
}

.course-description {
    color: var(--dark-purple);
    margin-bottom: 20px;
    line-height: 1.6;
    font-size: 0.95rem;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

.course-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-top: 15px;
    border-top: 2px solid var(--light-purple);
}

.course-price {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--success-color);
}

.course-stats {
    display: flex;
    gap: 15px;
    font-size: 0.9rem;
    color: var(--dark-purple);
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 6px;
    background: var(--light-purple);
    padding: 6px 12px;
    border-radius: var(--border-radius);
}

.course-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
}

/* Estado vacío */
.no-courses {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 30px;
    color: var(--dark-purple);
    background: linear-gradient(135deg, var(--light-purple), var(--white));
    border-radius: var(--border-radius-lg);
    border: 2px dashed var(--medium-purple);
}

.no-courses i {
    font-size: 4rem;
    margin-bottom: 20px;
    color: var(--primary-color);
    opacity: 0.7;
}

.no-courses h3 {
    margin-bottom: 10px;
    color: var(--text-color);
    font-size: 1.5rem;
}

/* Estilos de modales */
.modal {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(10px);
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: var(--white);
    padding: 0;
    border-radius: var(--border-radius-xl);
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow: hidden;
    position: relative;
    box-shadow: var(--shadow-heavy);
    animation: slideIn 0.3s ease;
    border: 3px solid var(--light-purple);
}

@keyframes slideIn {
    from { transform: translateY(-50px) scale(0.9); opacity: 0; }
    to { transform: translateY(0) scale(1); opacity: 1; }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 25px 30px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: var(--white);
}

.modal-title {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
}

.modal-close {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    font-size: 1.8rem;
    cursor: pointer;
    color: var(--white);
    padding: 8px;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: var(--transition);
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
}

.modal-body {
    padding: 30px;
    max-height: calc(90vh - 120px);
    overflow-y: auto;
}

/* Estilos de formularios */
.form-group {
    margin-bottom: 25px;
}

.form-label {
    display: block;
    margin-bottom: 10px;
    font-weight: 600;
    color: var(--text-color);
    font-size: 1rem;
}

.form-input,
.form-textarea,
.form-select {
    width: 100%;
    padding: 16px 20px;
    border: 2px solid var(--border-color);
    border-radius: var(--border-radius);
    font-size: 1rem;
    color: var(--text-color);
    background: var(--white);
    transition: var(--transition);
    box-sizing: border-box;
    font-family: 'Poppins', sans-serif;
}

.form-input:focus,
.form-textarea:focus,
.form-select:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
    transform: translateY(-2px);
}

.form-textarea {
    resize: vertical;
    min-height: 120px;
    line-height: 1.6;
}

.form-help {
    display: block;
    font-size: 0.85rem;
    color: var(--dark-purple);
    margin-top: 8px;
    font-style: italic;
}

.form-actions {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    margin-top: 30px;
    padding-top: 25px;
    border-top: 2px solid var(--light-purple);
}

/* Estilos de botones */
.btn {
    padding: 14px 28px;
    border: none;
    border-radius: var(--border-radius);
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    box-shadow: var(--shadow-light);
    font-family: 'Poppins', sans-serif;
    position: relative;
    overflow: hidden;
}

.btn:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-medium);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: var(--white);
}

.btn-primary:hover {
    background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
}

.btn-secondary {
    background: linear-gradient(135deg, var(--medium-purple), var(--dark-purple));
    color: var(--white);
}

.btn-success {
    background: linear-gradient(135deg, var(--success-color), #059669);
    color: var(--white);
}

.btn-warning {
    background: linear-gradient(135deg, var(--warning-color), #d97706);
    color: var(--white);
}

.btn-danger {
    background: linear-gradient(135deg, var(--danger-color), #dc2626);
    color: var(--white);
}

.btn-info {
    background: linear-gradient(135deg, var(--info-color), #2563eb);
    color: var(--white);
}

.btn-sm {
    padding: 10px 20px;
    font-size: 0.85rem;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

/* Efectos de botón */
.btn::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.btn:active::before {
    width: 300px;
    height: 300px;
}

/* Estilos de subida de archivos */
.file-upload-section {
    margin-bottom: 30px;
    padding: 25px;
    background: linear-gradient(135deg, var(--light-purple), var(--white));
    border-radius: var(--border-radius-lg);
    border: 2px dashed var(--border-color);
}

.file-upload-section h4 {
    margin-bottom: 20px;
    color: var(--text-color);
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
}

.file-upload-area {
    text-align: center;
    padding: 40px 25px;
    border: 2px dashed var(--medium-purple);
    border-radius: var(--border-radius);
    background: var(--white);
    transition: var(--transition);
    cursor: pointer;
}

.file-upload-area:hover {
    border-color: var(--primary-color);
    background: rgba(99, 102, 241, 0.05);
    transform: translateY(-3px);
}

.file-upload-area.dragover {
    border-color: var(--success-color);
    background: rgba(16, 185, 129, 0.1);
    transform: scale(1.02);
}

.form-input[type="file"] {
    padding: 20px;
    border: 2px dashed var(--medium-purple);
    background: linear-gradient(135deg, var(--light-purple), var(--white));
    cursor: pointer;
    transition: var(--transition);
    text-align: center;
}

.form-input[type="file"]:hover {
    border-color: var(--primary-color);
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), var(--white));
    transform: translateY(-2px);
}

/* Preview de archivos */
.thumbnail-preview {
    margin-top: 20px;
    text-align: center;
    padding: 20px;
    border: 2px solid var(--border-color);
    border-radius: var(--border-radius);
    background: linear-gradient(135deg, var(--light-purple), var(--white));
    min-height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.thumbnail-preview img {
    max-width: 100%;
    max-height: 200px;
    border-radius: var(--border-radius);
    object-fit: contain;
    box-shadow: var(--shadow-light);
}

/* Lista de archivos */
.existing-files-section {
    margin-top: 30px;
}

.existing-files-section h4 {
    margin-bottom: 20px;
    color: var(--text-color);
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
}

.files-list {
    min-height: 120px;
}

.loading-files {
    text-align: center;
    color: var(--dark-purple);
    padding: 40px;
    font-style: italic;
}

.file-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    margin-bottom: 15px;
    background: var(--white);
    border-radius: var(--border-radius);
    border: 2px solid var(--light-purple);
    transition: var(--transition);
    box-shadow: var(--shadow-light);
}

.file-item:hover {
    box-shadow: var(--shadow-medium);
    transform: translateY(-3px);
    border-color: var(--primary-color);
}

.file-info {
    display: flex;
    align-items: center;
    gap: 20px;
    flex: 1;
}

.file-icon {
    font-size: 2rem;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--border-radius);
    background: var(--light-purple);
}

.file-icon.pdf { 
    color: var(--danger-color); 
    background: rgba(239, 68, 68, 0.1);
}

.file-icon.doc { 
    color: var(--info-color); 
    background: rgba(59, 130, 246, 0.1);
}

.file-icon.xls { 
    color: var(--success-color); 
    background: rgba(16, 185, 129, 0.1);
}

.file-icon.ppt { 
    color: var(--warning-color); 
    background: rgba(245, 158, 11, 0.1);
}

.file-icon.image { 
    color: var(--secondary-color); 
    background: rgba(139, 92, 246, 0.1);
}

.file-icon.archive { 
    color: var(--dark-purple); 
    background: rgba(55, 65, 81, 0.1);
}

.file-icon.default { 
    color: var(--primary-color); 
    background: rgba(99, 102, 241, 0.1);
}

.file-details h5 {
    margin: 0 0 8px 0;
    font-size: 1.1rem;
    color: var(--text-color);
    font-weight: 600;
}

.file-meta {
    font-size: 0.9rem;
    color: var(--dark-purple);
}

.file-actions {
    display: flex;
    gap: 10px;
}

.no-files {
    text-align: center;
    color: var(--dark-purple);
    padding: 50px 25px;
    font-style: italic;
    background: linear-gradient(135deg, var(--light-purple), var(--white));
    border-radius: var(--border-radius);
    border: 2px dashed var(--medium-purple);
}

.no-files i {
    font-size: 3rem;
    margin-bottom: 20px;
    opacity: 0.6;
    display: block;
    color: var(--primary-color);
}

/* Alertas */
.alert {
    padding: 20px 25px;
    margin-bottom: 25px;
    border-radius: var(--border-radius);
    display: flex;
    align-items: center;
    gap: 15px;
    font-weight: 500;
    box-shadow: var(--shadow-light);
    animation: slideDown 0.3s ease;
    border: 2px solid;
}

@keyframes slideDown {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.alert-success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05));
    color: var(--success-color);
    border-color: var(--success-color);
}

.alert-error {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
    color: var(--danger-color);
    border-color: var(--danger-color);
}

/* Progreso de subida */
.upload-progress {
    display: none;
    margin-top: 20px;
    padding: 20px;
    background: var(--light-purple);
    border-radius: var(--border-radius);
    border: 2px solid var(--border-color);
}

.progress-bar {
    width: 100%;
    height: 28px;
    background-color: var(--medium-purple);
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--success-color), #059669);
    width: 0%;
    transition: width 0.4s ease;
    border-radius: var(--border-radius);
    position: relative;
}

.progress-fill::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, transparent 35%, rgba(255,255,255,0.3) 35%, rgba(255,255,255,0.3) 65%, transparent 65%);
    background-size: 25px 25px;
    animation: progress-animation 1s linear infinite;
}

@keyframes progress-animation {
    0% { background-position: 0 0; }
    100% { background-position: 25px 0; }
}

/* Información de archivos */
.file-info {
    margin-top: 20px;
    padding: 20px;
    background: linear-gradient(135deg, var(--light-purple), var(--white));
    border-radius: var(--border-radius);
    font-size: 0.95rem;
    border: 2px solid var(--border-color);
    box-shadow: var(--shadow-light);
}

.video-preview {
    margin-top: 20px;
    max-width: 100%;
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: var(--shadow-medium);
}

.video-preview video {
    width: 100%;
    height: auto;
    border-radius: var(--border-radius);
}

.current-thumbnail {
    margin-top: 15px;
    padding: 15px;
    background: var(--light-purple);
    border-radius: var(--border-radius);
    border: 2px solid var(--border-color);
}

.current-thumbnail img {
    max-width: 200px;
    height: auto;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-light);
}

/* Responsive */
@media (max-width: 768px) {
    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
        margin: 10px;
        border-radius: var(--border-radius-lg);
    }
    
    .main-content {
        margin-left: 0;
        padding: 10px;
    }
    
    .courses-grid {
        grid-template-columns: 1fr;
    }
    
    .courses-header {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }
    
    .courses-actions {
        flex-direction: column;
    }
    
    .course-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .course-level {
        align-self: flex-start;
    }
    
    .course-meta {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .modal-content {
        width: 95%;
        margin: 10px;
    }
    
    .modal-header {
        padding: 20px 25px;
    }
    
    .modal-body {
        padding: 25px;
    }
    
    .form-actions {
        flex-direction: column;
        gap: 12px;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
    
    .file-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 20px;
    }
    
    .file-actions {
        width: 100%;
        justify-content: flex-end;
    }
}

/* Animaciones adicionales */
@keyframes bounce {
    0%, 20%, 53%, 80%, 100% {
        transform: translate3d(0,0,0);
    }
    40%, 43% {
        transform: translate3d(0, -30px, 0);
    }
    70% {
        transform: translate3d(0, -15px, 0);
    }
    90% {
        transform: translate3d(0, -4px, 0);
    }
}

.btn:active {
    animation: bounce 0.6s;
}

/* Mejoras en selected file info */
#selectedFileInfo {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(255, 255, 255, 0.9));
    border: 2px solid rgba(99, 102, 241, 0.3);
    border-radius: var(--border-radius);
    padding: 20px;
    margin-top: 20px;
}

#selectedFileInfo strong {
    color: var(--text-color);
    display: block;
    margin-bottom: 10px;
    font-size: 1.1rem;
}

#selectedFileInfo i {
    margin-right: 10px;
    color: var(--primary-color);
}

/* Progreso de texto */
#fileProgressText,
#progressText {
    margin-top: 15px;
    text-align: center;
    font-weight: 600;
    color: var(--text-color);
    font-size: 1rem;
}

/* Estilos para el área de drag and drop */
.file-upload-area p {
    margin: 15px 0;
    color: var(--dark-purple);
    font-size: 1.1rem;
    font-weight: 500;
}

.file-upload-area i {
    display: block;
    margin-bottom: 20px;
    opacity: 0.8;
    color: var(--primary-color);
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
            <header class="content-header">
                <h1><i class="fas fa-book"></i> Gestión de Cursos</h1>
                <div class="user-info">
                    <i class="fas fa-user"></i> Bienvenido, <?php echo htmlspecialchars($currentUser['first_name'] ?? 'Admin'); ?>
                </div>
            </header>

            <div class="courses-content">
                <!-- Mensajes -->
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Header con acciones -->
                <div class="courses-header">
                    <div class="courses-actions">
                        <button class="btn btn-primary" onclick="openCreateModal()">
                            <i class="fas fa-plus"></i> Crear Curso
                        </button>
                    </div>
                </div>

                <!-- Vista de videos de playlist -->
                <?php if ($action === 'view_videos' && isset($viewPlaylist)): ?>
                    <div class="admin-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                            <div>
                                <h2><i class="fas fa-video"></i> Videos del Curso: <?php echo htmlspecialchars($viewPlaylist['title']); ?></h2>
                                <p style="color: var(--dark-purple); margin: 0.5rem 0;">Nivel: <?php echo htmlspecialchars($viewPlaylist['level'] ?? 'General'); ?> | Precio: $<?php echo number_format($viewPlaylist['price'], 2); ?></p>
                            </div>
                            <div>
                                <button class="btn btn-primary" onclick="openVideoModal(<?php echo $courseId; ?>)" style="margin-right: 1rem;">
                                    <i class="fas fa-plus"></i> Agregar Video
                                </button>
                                <a href="<?php echo $baseUrl; ?>index.php?page=admin&action=courses" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Volver a Cursos
                                </a>
                            </div>
                        </div>
                        
                        <?php if (!empty($playlistVideos)): ?>
                            <div class="videos-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
                                <?php foreach ($playlistVideos as $video): ?>
                                    <div class="video-card" style="background: white; border-radius: var(--border-radius-lg); overflow: hidden; box-shadow: var(--shadow-light); border: 2px solid var(--light-purple);">
                                        <div class="video-thumbnail" style="height: 180px; background: linear-gradient(135deg, var(--light-purple), var(--medium-purple)); display: flex; align-items: center; justify-content: center; color: var(--dark-purple); position: relative;">
                                            <?php if (!empty($video['thumbnail_image'])): ?>
                                                <img src="<?php echo htmlspecialchars($video['thumbnail_image']); ?>" alt="<?php echo htmlspecialchars($video['title']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                            <?php else: ?>
                                                <i class="fas fa-play-circle" style="font-size: 3rem; color: var(--primary-color);"></i>
                                            <?php endif; ?>
                                            <div style="position: absolute; bottom: 8px; right: 8px; background: rgba(99, 102, 241, 0.9); color: white; padding: 4px 8px; border-radius: 6px; font-size: 0.8em; font-weight: 600;">
                                                <i class="fas fa-video"></i> MP4
                                            </div>
                                        </div>
                                        <div style="padding: 1.5rem;">
                                            <h4 style="margin: 0 0 0.5rem; color: var(--text-color); font-weight: 600;"><?php echo htmlspecialchars($video['title']); ?></h4>
                                            <p style="color: var(--dark-purple); font-size: 0.9rem; margin: 0 0 1rem; line-height: 1.5;"><?php echo htmlspecialchars($video['description'] ?: 'Sin descripción'); ?></p>
                                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                                <button class="btn btn-sm btn-info" onclick="playVideo('<?php echo htmlspecialchars($video['file_path']); ?>', '<?php echo htmlspecialchars($video['title']); ?>')">
                                                    <i class="fas fa-play"></i> Reproducir
                                                </button>
                                                <button class="btn btn-sm btn-warning" onclick="openEditVideoModal(<?php echo $video['id']; ?>)">
                                                    <i class="fas fa-edit"></i> Editar
                                                </button>
                                                <button class="btn btn-sm btn-secondary" onclick="openFilesModal(<?php echo $video['id']; ?>, '<?php echo htmlspecialchars($video['title']); ?>')">
                                                    <i class="fas fa-file"></i> Archivos
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="deleteVideo(<?php echo $video['id']; ?>, '<?php echo htmlspecialchars($video['title']); ?>')">
                                                    <i class="fas fa-trash"></i> Eliminar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 3rem; color: var(--dark-purple); background: linear-gradient(135deg, var(--light-purple), var(--white)); border-radius: var(--border-radius-lg); border: 2px dashed var(--medium-purple);">
                                <i class="fas fa-video" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.7; color: var(--primary-color);"></i>
                                <h3>No hay videos en este curso</h3>
                                <p>Comienza agregando el primer video a este curso.</p>
                                <button class="btn btn-primary" onclick="openVideoModal(<?php echo $courseId; ?>)">
                                    <i class="fas fa-plus"></i> Agregar Video
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Grid de cursos -->
                    <div class="courses-grid">
                        <?php if (!empty($courses)): ?>
                            <?php foreach ($courses as $course): ?>
                                <div class="course-card">
                                    <div class="course-image">
                                        <?php if (!empty($course['thumbnail'])): ?>
                                            <img src="<?php echo htmlspecialchars($course['thumbnail']); ?>" alt="<?php echo htmlspecialchars($course['title']); ?>">
                                        <?php else: ?>
                                            <div class="no-image">
                                                <i class="fas fa-book"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="course-overlay">
                                            <div class="course-overlay-actions">
                                                <button class="overlay-btn edit" onclick="openEditModal(<?php echo $course['id']; ?>)" title="Editar Curso">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="overlay-btn view" onclick="viewVideos(<?php echo $course['id']; ?>)" title="Ver Videos">
                                                    <i class="fas fa-video"></i>
                                                </button>
                                                <button class="overlay-btn delete" onclick="deleteCourse(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['title']); ?>')" title="Eliminar Curso">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="course-info">
                                        <div class="course-header">
                                            <h3 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h3>
                                            <span class="course-level"><?php echo htmlspecialchars($course['level'] ?? 'General'); ?></span>
                                        </div>
                                        <p class="course-description"><?php echo htmlspecialchars($course['description'] ?: 'Sin descripción'); ?></p>
                                        <div class="course-meta">
                                            <div class="course-price">$<?php echo number_format($course['price'], 2); ?></div>
                                            <div class="course-stats">
                                                <span class="stat-item">
                                                    <i class="fas fa-video"></i>
                                                    <?php 
                                                    $videoCount = count($videoModel->readByPlaylist($course['id']));
                                                    echo $videoCount;
                                                    ?> videos
                                                </span>
                                                <span class="stat-item">
                                                    <i class="fas fa-calendar"></i>
                                                    <?php echo date('d/m/Y', strtotime($course['created_at'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="course-actions">
                                            <button class="btn btn-sm btn-warning" onclick="openEditModal(<?php echo $course['id']; ?>)">
                                                <i class="fas fa-edit"></i> Editar
                                            </button>
                                            <button class="btn btn-sm btn-primary" onclick="viewVideos(<?php echo $course['id']; ?>)">
                                                <i class="fas fa-video"></i> Videos
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-courses">
                                <i class="fas fa-book"></i>
                                <h3>No hay cursos disponibles</h3>
                                <p>Comienza creando tu primer curso</p>
                                <button class="btn btn-primary" onclick="openCreateModal()">
                                    Crear Primer Curso
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal para crear/editar curso -->
    <div id="courseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Crear Curso</h3>
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="courseForm" method="POST" enctype="multipart/form-data" action="<?php echo $baseUrl; ?>index.php?page=admin&action=courses&sub_action=create">
                    <input type="hidden" id="courseId" name="course_id">
                    <input type="hidden" id="subAction" name="sub_action" value="create">
                    
                    <div class="form-group">
                        <label for="courseName" class="form-label">Nombre del Curso *</label>
                        <input type="text" id="courseName" name="name" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="courseDescription" class="form-label">Descripción *</label>
                        <textarea id="courseDescription" name="description" class="form-textarea" rows="4" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="courseLevel" class="form-label">Nivel</label>
                        <select id="courseLevel" name="level" class="form-select">
                            <option value="A1">A1 - Principiante</option>
                            <option value="A2">A2 - Básico</option>
                            <option value="B1">B1 - Intermedio</option>
                            <option value="B2">B2 - Intermedio Alto</option>
                            <option value="C1">C1 - Avanzado</option>
                            <option value="C2">C2 - Experto</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="coursePrice" class="form-label">Precio (USD) *</label>
                        <input type="number" id="coursePrice" name="price" class="form-input" min="0" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="courseThumbnail" class="form-label">Miniatura del Curso</label>
                        <input type="file" id="courseThumbnail" name="thumbnail" class="form-input" accept="image/*">
                        <small class="form-help">Formatos permitidos: JPG, PNG, GIF. Tamaño máximo: 5MB</small>
                        <div id="thumbnailPreview" class="thumbnail-preview"></div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <span id="submitText">Crear Curso</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para agregar video -->
    <div id="videoModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3 class="modal-title">Agregar Video al Curso</h3>
                <button class="modal-close" onclick="closeVideoModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="videoForm" method="POST" enctype="multipart/form-data" action="<?php echo $baseUrl; ?>index.php?page=admin&action=courses&sub_action=upload_video">
                    <input type="hidden" id="videoPlaylistId" name="playlist_id">
                    
                    <div class="form-group">
                        <label for="videoTitle" class="form-label">Título del Video *</label>
                        <input type="text" id="videoTitle" name="title" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="videoDescription" class="form-label">Descripción</label>
                        <textarea id="videoDescription" name="description" class="form-textarea" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="videoFile" class="form-label">Archivo de Video * (MP4, AVI, MOV, WMV)</label>
                        <input type="file" id="videoFile" name="video_file" class="form-input" accept="video/mp4,video/avi,video/quicktime,video/x-msvideo" required>
                        <small class="form-help">Tamaño máximo: 250MB. Formatos soportados: MP4, AVI, MOV, WMV</small>
                        <div id="videoFileInfo" class="file-info" style="display: none;"></div>
                        <div id="videoPreview" class="video-preview" style="display: none;"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="videoThumbnail" class="form-label">Miniatura del Video (Opcional)</label>
                        <input type="file" id="videoThumbnail" name="thumbnail_image" class="form-input" accept="image/*">
                        <small class="form-help">Si no se proporciona, se usará una miniatura por defecto</small>
                        <div id="videoThumbnailPreview" class="thumbnail-preview"></div>
                    </div>
                    
                    <div class="upload-progress" id="uploadProgress">
                        <div class="progress-bar">
                            <div class="progress-fill" id="progressFill"></div>
                        </div>
                        <div id="progressText">Subiendo video...</div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeVideoModal()">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="submitVideoBtn">
                            <i class="fas fa-upload"></i> Subir Video
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para editar video -->
    <div id="editVideoModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3 class="modal-title">Editar Video</h3>
                <button class="modal-close" onclick="closeEditVideoModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="editVideoForm" method="POST" enctype="multipart/form-data" action="<?php echo $baseUrl; ?>index.php?page=admin&action=courses&sub_action=update_video">
                    <input type="hidden" id="editVideoId" name="video_id">
                    
                    <div class="form-group">
                        <label for="editVideoTitle" class="form-label">Título del Video *</label>
                        <input type="text" id="editVideoTitle" name="title" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editVideoDescription" class="form-label">Descripción</label>
                        <textarea id="editVideoDescription" name="description" class="form-textarea" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="editVideoThumbnail" class="form-label">Nueva Miniatura del Video (Opcional)</label>
                        <div id="currentThumbnail" class="current-thumbnail"></div>
                        <input type="file" id="editVideoThumbnail" name="thumbnail_image" class="form-input" accept="image/*">
                        <small class="form-help">Deja vacío para mantener la miniatura actual</small>
                        <div id="editVideoThumbnailPreview" class="thumbnail-preview"></div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeEditVideoModal()">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Actualizar Video
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para gestionar archivos del video -->
    <div id="filesModal" class="modal">
        <div class="modal-content" style="max-width: 900px;">
            <div class="modal-header">
                <h3 class="modal-title" id="filesModalTitle">Archivos Descargables</h3>
                <button class="modal-close" onclick="closeFilesModal()">×</button>
            </div>
            
            <div class="modal-body">
                <!-- Formulario para subir archivo -->
                <div class="file-upload-section">
                    <h4><i class="fas fa-upload"></i> Subir Nuevo Archivo</h4>
                    <form id="fileUploadForm" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="sub_action" value="upload_file">
                        <input type="hidden" name="video_id" id="fileVideoId" value="">
                        <input type="hidden" name="course_id" value="<?php echo $courseId; ?>">
                        
                        <div class="file-upload-area" id="fileUploadArea">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 2.5rem; color: var(--primary-color); margin-bottom: 1rem;"></i>
                            <p style="margin: 0 0 1rem;">Arrastra archivos aquí o haz clic para seleccionar</p>
                            <input type="file" id="videoFileUpload" name="video_file_upload" style="display: none;" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar,.jpg,.jpeg,.png,.gif">
                            <button type="button" class="btn btn-primary" onclick="document.getElementById('videoFileUpload').click()">
                                <i class="fas fa-folder-open"></i> Seleccionar Archivo
                            </button>
                        </div>
                        
                        <div class="form-group" style="margin-top: 1rem;">
                            <label for="fileDescription" class="form-label">Descripción del Archivo</label>
                            <input type="text" id="fileDescription" name="file_description" class="form-input" placeholder="Ej: Material de apoyo, ejercicios, etc.">
                        </div>
                        
                        <div class="file-info" id="selectedFileInfo" style="display: none;"></div>
                        
                        <div class="upload-progress" id="fileUploadProgress">
                            <div class="progress-bar">
                                <div class="progress-fill" id="fileProgressFill"></div>
                            </div>
                            <div id="fileProgressText">Subiendo...</div>
                        </div>
                        
                        <button type="submit" class="btn btn-success" id="uploadFileBtn" style="display: none; margin-top: 1rem;">
                            <i class="fas fa-upload"></i> Subir Archivo
                        </button>
                    </form>
                </div>
                
                <!-- Lista de archivos existentes -->
                <div class="existing-files-section">
                    <h4><i class="fas fa-files"></i> Archivos Existentes</h4>
                    <div class="files-list" id="modalFilesList">
                        <!-- Se carga dinámicamente -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para reproducir video -->
    <div id="playVideoModal" class="modal">
        <div class="modal-content" style="max-width: 900px;">
            <div class="modal-header">
                <h3 class="modal-title" id="playVideoTitle">Reproducir Video</h3>
                <button class="modal-close" onclick="closePlayVideoModal()">×</button>
            </div>
            <div class="modal-body">
                <video id="videoPlayer" controls style="width: 100%; height: auto; border-radius: var(--border-radius);">
                    Tu navegador no soporta la reproducción de video.
                </video>
            </div>
        </div>
    </div>

    <script>
        // Funciones del modal de curso
        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Crear Curso';
            document.getElementById('subAction').value = 'create';
            document.getElementById('submitText').textContent = 'Crear Curso';
            document.getElementById('courseForm').action = '<?php echo $baseUrl; ?>index.php?page=admin&action=courses&sub_action=create';
            document.getElementById('courseForm').reset();
            document.getElementById('thumbnailPreview').innerHTML = '';
            document.getElementById('courseModal').classList.add('active');
        }

        function openEditModal(courseId) {
            fetch('<?php echo $baseUrl; ?>index.php?page=admin&action=courses&sub_action=get_course&course_id=' + courseId)
                .then(response => response.json())
                .then(course => {
                    if (course.error) {
                        alert('Error al cargar los datos del curso: ' + course.error);
                        return;
                    }
                    
                    document.getElementById('modalTitle').textContent = 'Editar Curso';
                    document.getElementById('subAction').value = 'edit';
                    document.getElementById('courseId').value = courseId;
                    document.getElementById('courseName').value = course.title || '';
                    document.getElementById('courseDescription').value = course.description || '';
                    document.getElementById('courseLevel').value = course.level || 'A1';
                    document.getElementById('coursePrice').value = course.price || 0;
                    document.getElementById('submitText').textContent = 'Actualizar Curso';
                    document.getElementById('courseForm').action = '<?php echo $baseUrl; ?>index.php?page=admin&action=courses&sub_action=edit&course_id=' + courseId;
                    
                    const preview = document.getElementById('thumbnailPreview');
                    if (course.thumbnail) {
                        preview.innerHTML = `<img src="${course.thumbnail}" alt="Miniatura actual" style="max-width: 200px; max-height: 150px; border-radius: var(--border-radius);">`;
                    } else {
                        preview.innerHTML = '';
                    }
                    
                    document.getElementById('courseModal').classList.add('active');
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar los datos del curso');
                });
        }

        function closeModal() {
            document.getElementById('courseModal').classList.remove('active');
        }

        // Funciones del modal de video
        function openVideoModal(courseId) {
            document.getElementById('videoPlaylistId').value = courseId;
            document.getElementById('videoForm').reset();
            document.getElementById('videoThumbnailPreview').innerHTML = '';
            document.getElementById('videoFileInfo').style.display = 'none';
            document.getElementById('videoPreview').style.display = 'none';
            document.getElementById('uploadProgress').style.display = 'none';
            document.getElementById('videoModal').classList.add('active');
        }

        function closeVideoModal() {
            document.getElementById('videoModal').classList.remove('active');
        }

        // Funciones del modal de edición de video
        function openEditVideoModal(videoId) {
            fetch('<?php echo $baseUrl; ?>index.php?page=admin&action=courses&sub_action=get_video&video_id=' + videoId)
                .then(response => response.json())
                .then(video => {
                    if (video.error) {
                        alert('Error al cargar los datos del video: ' + video.error);
                        return;
                    }
                    
                    document.getElementById('editVideoId').value = videoId;
                    document.getElementById('editVideoTitle').value = video.title || '';
                    document.getElementById('editVideoDescription').value = video.description || '';
                    
                    const currentThumbnail = document.getElementById('currentThumbnail');
                    if (video.thumbnail_image) {
                        currentThumbnail.innerHTML = `
                            <p><strong>Miniatura actual:</strong></p>
                            <img src="${video.thumbnail_image}" alt="Miniatura actual" style="max-width: 200px; max-height: 150px; border-radius: var(--border-radius);">
                        `;
                    } else {
                        currentThumbnail.innerHTML = '<p><em>Sin miniatura actual</em></p>';
                    }
                    
                    document.getElementById('editVideoThumbnailPreview').innerHTML = '';
                    document.getElementById('editVideoModal').classList.add('active');
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar los datos del video');
                });
        }

        function closeEditVideoModal() {
            document.getElementById('editVideoModal').classList.remove('active');
        }

        // Función para reproducir video
        function playVideo(filePath, title) {
            document.getElementById('playVideoTitle').textContent = title;
            document.getElementById('videoPlayer').src = filePath;
            document.getElementById('playVideoModal').classList.add('active');
        }

        function closePlayVideoModal() {
            document.getElementById('playVideoModal').classList.remove('active');
            document.getElementById('videoPlayer').pause();
            document.getElementById('videoPlayer').src = '';
        }

        // Preview de imagen para curso
        document.getElementById('courseThumbnail').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('thumbnailPreview');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="max-width: 200px; max-height: 150px; border-radius: var(--border-radius);">`;
                };
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '';
            }
        });

        // Preview de archivo de video
        document.getElementById('videoFile').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const fileInfo = document.getElementById('videoFileInfo');
            const videoPreview = document.getElementById('videoPreview');
            
            if (file) {
                const fileSize = (file.size / (1024 * 1024)).toFixed(2);
                fileInfo.innerHTML = `
                    <strong>Archivo seleccionado:</strong><br>
                    <i class="fas fa-video" style="color: var(--primary-color);"></i> ${file.name}<br>
                    <small>Tamaño: ${fileSize} MB | Tipo: ${file.type}</small>
                `;
                fileInfo.style.display = 'block';
                
                const videoURL = URL.createObjectURL(file);
                videoPreview.innerHTML = `<video controls style="border-radius: var(--border-radius);"><source src="${videoURL}" type="${file.type}">Tu navegador no soporta la reproducción de video.</video>`;
                videoPreview.style.display = 'block';
            } else {
                fileInfo.style.display = 'none';
                videoPreview.style.display = 'none';
            }
        });

        // Preview de imagen para video
        document.getElementById('videoThumbnail').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('videoThumbnailPreview');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="max-width: 200px; max-height: 150px; border-radius: var(--border-radius);">`;
                };
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '';
            }
        });

        // Preview de imagen para edición de video
        document.getElementById('editVideoThumbnail').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('editVideoThumbnailPreview');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `
                        <p><strong>Nueva miniatura:</strong></p>
                        <img src="${e.target.result}" alt="Preview" style="max-width: 200px; max-height: 150px; border-radius: var(--border-radius);">
                    `;
                };
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '';
            }
        });

        // Manejar envío del formulario de video con progreso
        document.getElementById('videoForm').addEventListener('submit', function(e) {
            const fileInput = document.getElementById('videoFile');
            if (fileInput.files.length > 0) {
                const fileSize = fileInput.files[0].size;
                const maxSize = 250 * 1024 * 1024; // 250MB
                
                if (fileSize > maxSize) {
                    e.preventDefault();
                    alert('El archivo es demasiado grande. El tamaño máximo permitido es 250MB.');
                    return;
                }
                
                document.getElementById('uploadProgress').style.display = 'block';
                document.getElementById('submitVideoBtn').disabled = true;
                document.getElementById('submitVideoBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subiendo...';
            }
        });

        // Funciones de gestión
        function viewVideos(courseId) {
            window.location.href = '<?php echo $baseUrl; ?>index.php?page=admin&action=courses&sub_action=view_videos&course_id=' + courseId;
        }

        function deleteCourse(courseId, courseName) {
            if (confirm(`¿Estás seguro de que deseas eliminar el curso "${courseName}"? Esta acción eliminará también todos los videos del curso y no se puede deshacer.`)) {
                window.location.href = '<?php echo $baseUrl; ?>index.php?page=admin&action=courses&sub_action=delete&course_id=' + courseId;
            }
        }

        function deleteVideo(videoId, videoTitle) {
            if (confirm(`¿Estás seguro de eliminar el video "${videoTitle}"? Esta acción no se puede deshacer.`)) {
                window.location.href = '<?php echo $baseUrl; ?>index.php?page=admin&action=courses&sub_action=delete_video&video_id=' + videoId;
            }
        }

        // Funciones específicas para archivos
        function openFilesModal(videoId, videoTitle) {
            document.getElementById('filesModalTitle').textContent = `Archivos - ${videoTitle}`;
            document.getElementById('fileVideoId').value = videoId;
            document.getElementById('filesModal').classList.add('active');
            loadVideoFiles(videoId);
        }

        function closeFilesModal() {
            document.getElementById('filesModal').classList.remove('active');
            document.getElementById('fileUploadForm').reset();
            document.getElementById('selectedFileInfo').style.display = 'none';
            document.getElementById('uploadFileBtn').style.display = 'none';
        }

        function loadVideoFiles(videoId) {
            const filesList = document.getElementById('modalFilesList');
            filesList.innerHTML = '<div class="loading-files"><i class="fas fa-spinner fa-spin"></i> Cargando archivos...</div>';
            
            fetch(`<?php echo $baseUrl; ?>index.php?page=admin&action=courses&sub_action=get_video_files&video_id=${videoId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.files && data.files.length > 0) {
                        filesList.innerHTML = data.files.map(file => `
                            <div class="file-item">
                                <div class="file-info">
                                    <div class="file-icon ${getFileIconClass(file.file_type)}">
                                        <i class="${file.icon}"></i>
                                    </div>
                                    <div class="file-details">
                                        <h5>${file.original_name}</h5>
                                        <div class="file-meta">${file.file_size}</div>
                                    </div>
                                </div>
                                <div class="file-actions">
                                    <button class="btn btn-sm btn-danger" onclick="deleteFile(${file.id}, '${file.original_name}', ${videoId})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        `).join('');
                    } else {
                        filesList.innerHTML = `
                            <div class="no-files">
                                <i class="fas fa-file"></i>
                                <p>No hay archivos descargables</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error al cargar archivos:', error);
                    filesList.innerHTML = `
                        <div class="no-files">
                            <p>Error al cargar los archivos</p>
                        </div>
                    `;
                });
        }

        function deleteFile(fileId, fileName, videoId) {
            if (confirm(`¿Estás seguro de que quieres eliminar el archivo "${fileName}"?`)) {
                fetch(`<?php echo $baseUrl; ?>index.php?page=admin&action=courses&sub_action=delete_file&file_id=${fileId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            loadVideoFiles(videoId);
                            showAlert('success', data.message);
                        } else {
                            showAlert('error', data.error || 'Error al eliminar el archivo');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showAlert('error', 'Error de conexión');
                    });
            }
        }

        // Funciones auxiliares
        function getFileIcon(fileType) {
            const icons = {
                'application/pdf': 'fas fa-file-pdf',
                'image/jpeg': 'fas fa-file-image',
                'image/jpg': 'fas fa-file-image',
                'image/png': 'fas fa-file-image',
                'image/gif': 'fas fa-file-image',
                'application/msword': 'fas fa-file-word',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'fas fa-file-word',
                'application/vnd.ms-excel': 'fas fa-file-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'fas fa-file-excel',
                'application/vnd.ms-powerpoint': 'fas fa-file-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'fas fa-file-powerpoint',
                'text/plain': 'fas fa-file-alt',
                'application/zip': 'fas fa-file-archive',
                'application/x-rar-compressed': 'fas fa-file-archive'
            };
            return icons[fileType] || 'fas fa-file';
        }

        function getFileIconClass(fileType) {
            if (fileType === 'application/pdf') return 'pdf';
            if (fileType.startsWith('image/')) return 'image';
            if (fileType.includes('word')) return 'word';
            if (fileType.includes('excel') || fileType.includes('sheet')) return 'excel';
            if (fileType.includes('powerpoint') || fileType.includes('presentation')) return 'powerpoint';
            if (fileType === 'text/plain') return 'text';
            if (fileType.includes('zip') || fileType.includes('rar')) return 'archive';
            return 'default';
        }

        function formatFileSize(bytes) {
            if (bytes >= 1073741824) {
                return (bytes / 1073741824).toFixed(2) + ' GB';
            } else if (bytes >= 1048576) {
                return (bytes / 1048576).toFixed(2) + ' MB';
            } else if (bytes >= 1024) {
                return (bytes / 1024).toFixed(2) + ' KB';
            } else {
                return bytes + ' bytes';
            }
        }

        function showAlert(type, message) {
            // Crear elemento de alerta
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
            
            // Insertar al inicio del contenido
            const content = document.querySelector('.courses-content');
            content.insertBefore(alert, content.firstChild);
            
            // Remover después de 5 segundos
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }

        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const courseModal = document.getElementById('courseModal');
            const videoModal = document.getElementById('videoModal');
            const editVideoModal = document.getElementById('editVideoModal');
            const filesModal = document.getElementById('filesModal');
            const playVideoModal = document.getElementById('playVideoModal');
            
            if (event.target === courseModal) {
                closeModal();
            } else if (event.target === videoModal) {
                closeVideoModal();
            } else if (event.target === editVideoModal) {
                closeEditVideoModal();
            } else if (event.target === filesModal) {
                closeFilesModal();
            } else if (event.target === playVideoModal) {
                closePlayVideoModal();
            }
        }

        // Manejar envío del formulario de archivos
        document.getElementById('fileUploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const videoId = document.getElementById('fileVideoId').value;
            
            // Mostrar progreso
            document.getElementById('fileUploadProgress').style.display = 'block';
            document.getElementById('uploadFileBtn').disabled = true;
            document.getElementById('uploadFileBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subiendo...';
            
            fetch(`<?php echo $baseUrl; ?>index.php?page=admin&action=courses&sub_action=upload_file`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Resetear formulario
                    this.reset();
                    document.getElementById('selectedFileInfo').style.display = 'none';
                    document.getElementById('uploadFileBtn').style.display = 'none';
                    
                    // Recargar lista de archivos
                    loadVideoFiles(videoId);
                    
                    showAlert('success', data.message);
                } else {
                    showAlert('error', data.error || 'Error al subir el archivo');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', 'Error de conexión al subir el archivo');
            })
            .finally(() => {
                // Restaurar botón
                document.getElementById('fileUploadProgress').style.display = 'none';
                document.getElementById('uploadFileBtn').disabled = false;
                document.getElementById('uploadFileBtn').innerHTML = '<i class="fas fa-upload"></i> Subir Archivo';
            });
        });

        // Manejo de selección de archivos
        document.getElementById('videoFileUpload').addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const fileInfo = document.getElementById('selectedFileInfo');
                const uploadBtn = document.getElementById('uploadFileBtn');
                
                fileInfo.innerHTML = `
                    <strong>Archivo seleccionado:</strong><br>
                    <i class="${getFileIcon(file.type)}" style="color: var(--primary-color);"></i> ${file.name}<br>
                    <small>Tamaño: ${formatFileSize(file.size)}</small>
                `;
                fileInfo.style.display = 'block';
                uploadBtn.style.display = 'inline-block';
            }
        });
    </script>
</body>
</html>
