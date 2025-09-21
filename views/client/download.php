<?php
session_start();

require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../models/VideoFile.php';
require_once __DIR__ . '/../../models/UserCourse.php';
require_once __DIR__ . '/../../models/Video.php';

use Controllers\AuthController;
use Models\VideoFile;
use Models\UserCourse;
use Models\Video;

// Verificar autenticación
if (!AuthController::isAuthenticated()) {
    http_response_code(401);
    die('Acceso no autorizado');
}

$file_id = $_GET['id'] ?? null;
if (!$file_id) {
    http_response_code(400);
    die('ID de archivo no especificado');
}

try {
    $database = new \Database();
    $db = $database->getConnection();
    $videoFileModel = new VideoFile($db);
    $userCourseModel = new UserCourse($db);
    $videoModel = new Video($db);

    // Obtener información del archivo
    $file = $videoFileModel->readOne($file_id);
    if (!$file) {
        http_response_code(404);
        die('Archivo no encontrado');
    }

    $user_id = $_SESSION['user_id'];

    // Obtener información del video para conseguir el playlist_id
    $video = $videoModel->readOne($file['video_id']);
    if (!$video) {
        http_response_code(404);
        die('Video asociado no encontrado');
    }

    $playlist_id = $video['playlist_id'];

    // Verificar que el usuario tenga acceso al curso (excepto administradores)
    if (!AuthController::isAdmin() && !$userCourseModel->hasAccess($user_id, $playlist_id)) {
        http_response_code(403);
        die('No tienes acceso a este archivo');
    }

    // Verificar que el archivo existe físicamente - ajustar ruta desde views/client/
    $filePath = __DIR__ . '/../../' . $file['file_path'];
    if (!file_exists($filePath)) {
        http_response_code(404);
        die('Archivo no encontrado en el servidor');
    }

    // Registrar descarga (opcional - para estadísticas)
    error_log("Usuario {$user_id} descargó archivo: {$file['original_name']} (ID: {$file_id})");

    // Configurar headers para descarga
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');

    // Limpiar buffer de salida
    ob_clean();
    flush();

    // Enviar archivo
    readfile($filePath);
    exit();

} catch (Exception $e) {
    error_log("Error en descarga de archivo: " . $e->getMessage());
    http_response_code(500);
    die('Error interno del servidor');
}
?>
