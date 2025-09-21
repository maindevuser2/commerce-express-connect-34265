<?php
namespace Controllers;

// Asegurar que la sesión esté iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Incluir las dependencias necesarias
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/VideoFile.php';
require_once __DIR__ . '/../models/Video.php';
require_once __DIR__ . '/../models/UserCourse.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../config/upload.php';

use Controllers\AuthController;
use Models\VideoFile;
use Models\Video;
use Models\UserCourse;
use Exception;

class VideoFileController {
    private $db;
    private $videoFileModel;
    private $videoModel;
    private $uploadDir;

    public function __construct() {
        try {
            // Cargar dependencias
            $database = new \Database();
            $this->db = $database->getConnection();
            $this->videoFileModel = new VideoFile($this->db);
            $this->videoModel = new Video($this->db);

            // Configurar directorio de subida
            $this->uploadDir = __DIR__ . '/../uploads/video_files/';

            // Crear directorio si no existe
            if (!file_exists($this->uploadDir)) {
                mkdir($this->uploadDir, 0755, true);
                // Crear archivo .htaccess para seguridad
                file_put_contents($this->uploadDir . '.htaccess', "Options -Indexes\nDeny from all\n");
            }
        } catch (Exception $e) {
            error_log("Error en VideoFileController constructor: " . $e->getMessage());
            throw $e;
        }
    }

    public function upload() {
        try {
            // Solo administradores pueden subir archivos
            if (!AuthController::isAdmin()) {
                $this->jsonResponse(['success' => false, 'error' => 'Acceso denegado.']);
                return;
            }

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->jsonResponse(['success' => false, 'error' => 'Método no permitido.']);
                return;
            }

            $video_id = intval($_POST['video_id'] ?? 0);
            
            if ($video_id <= 0) {
                $this->jsonResponse(['success' => false, 'error' => 'ID de video inválido.']);
                return;
            }

            // Verificar que el video existe
            $video = $this->videoModel->readOne($video_id);
            if (!$video) {
                $this->jsonResponse(['success' => false, 'error' => 'Video no encontrado.']);
                return;
            }

            // Verificar que se subió un archivo
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                $errorMsg = $this->getUploadErrorMessage($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE);
                $this->jsonResponse(['success' => false, 'error' => $errorMsg]);
                return;
            }

            // Usar UploadConfig para manejar la subida
            $fileData = \UploadConfig::handleFileUpload($_FILES['file'], 'video_files');

            // Preparar datos para la base de datos
            $this->videoFileModel->video_id = $video_id;
            $this->videoFileModel->filename = $fileData['file_name'];
            $this->videoFileModel->original_name = $fileData['original_name'];
            $this->videoFileModel->file_path = $fileData['file_path'];
            $this->videoFileModel->file_size = $fileData['file_size'];
            $this->videoFileModel->file_type = $fileData['file_type'];

            if ($this->videoFileModel->create()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Archivo subido exitosamente.',
                    'file' => [
                        'id' => $this->videoFileModel->id,
                        'original_name' => $this->videoFileModel->original_name,
                        'file_size' => $this->videoFileModel->formatFileSize($this->videoFileModel->file_size),
                        'file_type' => $this->videoFileModel->file_type,
                        'icon' => $this->videoFileModel->getFileIcon($this->videoFileModel->file_type)
                    ]
                ]);
            } else {
                // Limpiar archivo si falla la BD
                $this->deletePhysicalFile($fileData['file_path']);
                $this->jsonResponse(['success' => false, 'error' => 'Error al guardar el archivo en la base de datos.']);
            }
        } catch (Exception $e) {
            error_log("Error en upload: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Error al subir el archivo: ' . $e->getMessage()]);
        }
    }

    public function delete($id) {
        try {
            // Solo administradores pueden eliminar archivos
            if (!AuthController::isAdmin()) {
                $this->jsonResponse(['success' => false, 'error' => 'Acceso denegado.']);
                return;
            }

            // Obtener información del archivo
            $file = $this->videoFileModel->readOne($id);
            if (!$file) {
                $this->jsonResponse(['success' => false, 'error' => 'Archivo no encontrado.']);
                return;
            }

            // Eliminar archivo físico
            $this->deletePhysicalFile($file['file_path']);

            // Eliminar registro de la base de datos
            if ($this->videoFileModel->delete($id)) {
                $this->jsonResponse(['success' => true, 'message' => 'Archivo eliminado exitosamente.']);
            } else {
                $this->jsonResponse(['success' => false, 'error' => 'Error al eliminar el archivo de la base de datos.']);
            }
        } catch (Exception $e) {
            error_log("Error en delete: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Error al eliminar el archivo: ' . $e->getMessage()]);
        }
    }

    public function download($id) {
        try {
            // Verificar que el usuario esté autenticado
            if (!AuthController::isAuthenticated()) {
                AuthController::setFlashMessage('error', 'Debes iniciar sesión para descargar archivos.');
                header('Location: ../../login.php');
                exit();
            }

            $file = $this->videoFileModel->readOne($id);
            if (!$file) {
                AuthController::setFlashMessage('error', 'Archivo no encontrado.');
                header('Location: ../../views/client/purchase-history.php');
                exit();
            }

            // Verificar que el usuario tenga acceso al video
            $video = $this->videoModel->readOne($file['video_id']);
            if (!$video) {
                AuthController::setFlashMessage('error', 'Video no encontrado.');
                header('Location: ../../views/client/purchase-history.php');
                exit();
            }

            // Para usuarios no admin, verificar acceso al curso
            if (!AuthController::isAdmin()) {
                $userCourseModel = new UserCourse($this->db);
                if (!$userCourseModel->hasAccess($_SESSION['user_id'], $video['playlist_id'])) {
                    AuthController::setFlashMessage('error', 'No tienes acceso a este archivo.');
                    header('Location: ../../views/client/purchase-history.php');
                    exit();
                }
            }

            $full_path = __DIR__ . '/../' . $file['file_path'];
            
            if (!file_exists($full_path)) {
                AuthController::setFlashMessage('error', 'El archivo no existe en el servidor.');
                header('Location: ../../views/client/purchase-history.php');
                exit();
            }

            // Configurar headers para descarga
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
            header('Content-Length: ' . filesize($full_path));
            header('Cache-Control: must-revalidate');
            header('Pragma: public');

            // Leer y enviar el archivo
            readfile($full_path);
            exit();
        } catch (Exception $e) {
            error_log("Error en download: " . $e->getMessage());
            AuthController::setFlashMessage('error', 'Error al descargar el archivo.');
            header('Location: ../../views/client/purchase-history.php');
            exit();
        }
    }

    public function getFilesByVideo($video_id) {
        try {
            $files = $this->videoFileModel->readByVideo($video_id);
            $formatted_files = [];

            foreach ($files as $file) {
                $formatted_files[] = [
                    'id' => $file['id'],
                    'original_name' => $file['original_name'],
                    'file_size' => $this->videoFileModel->formatFileSize($file['file_size']),
                    'file_type' => $file['file_type'],
                    'icon' => $this->videoFileModel->getFileIcon($file['file_type']),
                    'created_at' => $file['created_at']
                ];
            }

            return $formatted_files;
        } catch (Exception $e) {
            error_log("Error en getFilesByVideo: " . $e->getMessage());
            return [];
        }
    }

    private function deletePhysicalFile($file_path) {
        $full_path = __DIR__ . '/../' . $file_path;
        if (file_exists($full_path)) {
            if (!unlink($full_path)) {
                error_log("No se pudo eliminar el archivo: $full_path");
            }
        }
    }

    private function getUploadErrorMessage($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return 'El archivo excede el tamaño máximo permitido por PHP.';
            case UPLOAD_ERR_FORM_SIZE:
                return 'El archivo excede el tamaño máximo del formulario.';
            case UPLOAD_ERR_PARTIAL:
                return 'El archivo se subió parcialmente.';
            case UPLOAD_ERR_NO_FILE:
                return 'No se subió ningún archivo.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Falta la carpeta temporal.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Error al escribir el archivo en disco.';
            case UPLOAD_ERR_EXTENSION:
                return 'Una extensión de PHP detuvo la subida.';
            default:
                return 'Error desconocido en la subida del archivo.';
        }
    }

    private function jsonResponse($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
}

// Manejar las rutas si se accede directamente a este archivo
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    try {
        $action = $_GET['action'] ?? 'upload';
        $id = $_GET['id'] ?? null;
        $controller = new VideoFileController();

        switch ($action) {
            case 'upload':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $controller->upload();
                } else {
                    header('HTTP/1.0 405 Method Not Allowed');
                    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
                }
                break;
            case 'delete':
                if ($id) {
                    $controller->delete($id);
                } else {
                    header('HTTP/1.0 400 Bad Request');
                    echo json_encode(['success' => false, 'error' => 'ID requerido']);
                }
                break;
            case 'download':
                if ($id) {
                    $controller->download($id);
                } else {
                    header('HTTP/1.0 400 Bad Request');
                    echo 'ID requerido';
                }
                break;
            case 'get_files':
                $video_id = $_GET['video_id'] ?? null;
                if ($video_id) {
                    $files = $controller->getFilesByVideo($video_id);
                    echo json_encode(['success' => true, 'files' => $files]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Video ID requerido']);
                }
                break;
            default:
                header('HTTP/1.0 404 Not Found');
                echo json_encode(['success' => false, 'error' => 'Acción no encontrada']);
                break;
        }
    } catch (Exception $e) {
        error_log("Error en VideoFileController: " . $e->getMessage());
        header('HTTP/1.0 500 Internal Server Error');
        echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
    }
}
?>
