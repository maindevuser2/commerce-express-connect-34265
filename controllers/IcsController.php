<?php
namespace Controllers;

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/SyncClass.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/UserSyncClass.php';

use Models\SyncClass;
use Models\UserSyncClass;
use Controllers\AuthController;
use Exception;

class IcsController {
    private $db;
    private $syncClassModel;
    private $userSyncClassModel;
    
    public function __construct() {
        try {
            $database = new \Database();
            $this->db = $database->getConnection();
            $this->syncClassModel = new SyncClass($this->db);
            $this->userSyncClassModel = new UserSyncClass($this->db);
        } catch (Exception $e) {
            error_log("Error en IcsController constructor: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Genera un archivo .ics para una clase sincrónica
     */
    public function generateIcs($sync_class_id) {
        try {
            // Verificar que el usuario esté autenticado
            if (!AuthController::isAuthenticated()) {
                http_response_code(401);
                echo json_encode(['error' => 'Debes iniciar sesión para descargar el calendario.']);
                exit();
            }
            
            $currentUser = AuthController::getCurrentUser();
            
            // Verificar que el usuario tenga acceso a esta clase
            if (!$this->userSyncClassModel->hasAccess($currentUser['id'], $sync_class_id)) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes acceso a esta clase.']);
                exit();
            }
            
            // Obtener datos de la clase
            $syncClass = $this->syncClassModel->readOne($sync_class_id);
            
            if (!$syncClass) {
                http_response_code(404);
                echo json_encode(['error' => 'Clase no encontrada.']);
                exit();
            }
            
            // Generar contenido ICS
            $icsContent = $this->createIcsContent($syncClass, $currentUser);
            
            // Configurar headers para descarga
            header('Content-Type: text/calendar; charset=utf-8');
            header('Content-Disposition: attachment; filename="clase-' . $sync_class_id . '.ics"');
            header('Content-Length: ' . strlen($icsContent));
            
            echo $icsContent;
            exit();
            
        } catch (Exception $e) {
            error_log("Error generando ICS: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Error al generar el archivo de calendario.']);
            exit();
        }
    }
    
    /**
     * Genera archivo .ics para todas las clases de un usuario
     */
    public function generateAllUserClassesIcs() {
        try {
            if (!AuthController::isAuthenticated()) {
                http_response_code(401);
                echo json_encode(['error' => 'Debes iniciar sesión.']);
                exit();
            }
            
            $currentUser = AuthController::getCurrentUser();
            $userClasses = $this->userSyncClassModel->readByUserId($currentUser['id']);
            
            if (empty($userClasses)) {
                http_response_code(404);
                echo json_encode(['error' => 'No tienes clases sincrónicas registradas.']);
                exit();
            }
            
            // Generar contenido ICS con múltiples eventos
            $icsContent = $this->createMultipleEventsIcs($userClasses, $currentUser);
            
            header('Content-Type: text/calendar; charset=utf-8');
            header('Content-Disposition: attachment; filename="mis-clases.ics"');
            header('Content-Length: ' . strlen($icsContent));
            
            echo $icsContent;
            exit();
            
        } catch (Exception $e) {
            error_log("Error generando ICS múltiple: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Error al generar el archivo de calendario.']);
            exit();
        }
    }
    
    /**
     * Crea el contenido del archivo .ics para un evento
     */
    private function createIcsContent($syncClass, $user) {
        $startDate = $this->formatDateForIcs($syncClass['start_date']);
        $endDate = $this->formatDateForIcs($syncClass['end_date']);
        $now = $this->formatDateForIcs(date('Y-m-d H:i:s'));
        
        // Generar UID único
        $uid = 'syncclass-' . $syncClass['id'] . '-' . time() . '@profehernan.com';
        
        // Escapar texto para ICS
        $title = $this->escapeIcsText($syncClass['title']);
        $description = $this->escapeIcsText($syncClass['description'] ?? '');
        $meetingLink = $syncClass['meeting_link'];
        
        // Crear descripción completa
        $fullDescription = $description . "\\n\\nEnlace de reunión: " . $meetingLink;
        
        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//Profe Hernán//Clases Sincrónicas//ES\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";
        $ics .= "X-WR-CALNAME:Clases Sincrónicas - Profe Hernán\r\n";
        $ics .= "X-WR-TIMEZONE:America/Bogota\r\n";
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:" . $uid . "\r\n";
        $ics .= "DTSTAMP:" . $now . "\r\n";
        $ics .= "DTSTART:" . $startDate . "\r\n";
        $ics .= "DTEND:" . $endDate . "\r\n";
        $ics .= "SUMMARY:" . $title . "\r\n";
        $ics .= "DESCRIPTION:" . $fullDescription . "\r\n";
        $ics .= "URL:" . $meetingLink . "\r\n";
        $ics .= "LOCATION:" . $meetingLink . "\r\n";
        $ics .= "STATUS:CONFIRMED\r\n";
        $ics .= "SEQUENCE:0\r\n";
        $ics .= "BEGIN:VALARM\r\n";
        $ics .= "TRIGGER:-PT15M\r\n";
        $ics .= "ACTION:DISPLAY\r\n";
        $ics .= "DESCRIPTION:Recordatorio: " . $title . " en 15 minutos\r\n";
        $ics .= "END:VALARM\r\n";
        $ics .= "END:VEVENT\r\n";
        $ics .= "END:VCALENDAR\r\n";
        
        return $ics;
    }
    
    /**
     * Crea un archivo ICS con múltiples eventos
     */
    private function createMultipleEventsIcs($classes, $user) {
        $now = $this->formatDateForIcs(date('Y-m-d H:i:s'));
        
        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//Profe Hernán//Clases Sincrónicas//ES\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";
        $ics .= "X-WR-CALNAME:Mis Clases Sincrónicas - Profe Hernán\r\n";
        $ics .= "X-WR-TIMEZONE:America/Bogota\r\n";
        
        foreach ($classes as $class) {
            $startDate = $this->formatDateForIcs($class['start_date']);
            $endDate = $this->formatDateForIcs($class['end_date']);
            $uid = 'syncclass-' . $class['id'] . '-' . time() . '@profehernan.com';
            
            $title = $this->escapeIcsText($class['title']);
            $description = $this->escapeIcsText($class['description'] ?? '');
            $meetingLink = $class['meeting_link'];
            
            $fullDescription = $description . "\\n\\nEnlace de reunión: " . $meetingLink;
            
            $ics .= "BEGIN:VEVENT\r\n";
            $ics .= "UID:" . $uid . "\r\n";
            $ics .= "DTSTAMP:" . $now . "\r\n";
            $ics .= "DTSTART:" . $startDate . "\r\n";
            $ics .= "DTEND:" . $endDate . "\r\n";
            $ics .= "SUMMARY:" . $title . "\r\n";
            $ics .= "DESCRIPTION:" . $fullDescription . "\r\n";
            $ics .= "URL:" . $meetingLink . "\r\n";
            $ics .= "LOCATION:" . $meetingLink . "\r\n";
            $ics .= "STATUS:CONFIRMED\r\n";
            $ics .= "SEQUENCE:0\r\n";
            $ics .= "BEGIN:VALARM\r\n";
            $ics .= "TRIGGER:-PT15M\r\n";
            $ics .= "ACTION:DISPLAY\r\n";
            $ics .= "DESCRIPTION:Recordatorio: " . $title . " en 15 minutos\r\n";
            $ics .= "END:VALARM\r\n";
            $ics .= "END:VEVENT\r\n";
        }
        
        $ics .= "END:VCALENDAR\r\n";
        
        return $ics;
    }
    
    /**
     * Formatea una fecha para el formato ICS (YYYYMMDDTHHMMSSZ)
     */
    private function formatDateForIcs($dateString) {
        $timestamp = strtotime($dateString);
        return gmdate('Ymd\THis\Z', $timestamp);
    }
    
    /**
     * Escapa texto para formato ICS
     */
    private function escapeIcsText($text) {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(',', '\\,', $text);
        $text = str_replace(';', '\\;', $text);
        $text = str_replace("\n", '\\n', $text);
        $text = str_replace("\r", '', $text);
        return $text;
    }
}

// Manejo de solicitudes directas
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    session_start();
    
    try {
        $controller = new IcsController();
        $action = $_GET['action'] ?? 'download';
        
        switch ($action) {
            case 'download':
                $classId = intval($_GET['class_id'] ?? 0);
                if ($classId > 0) {
                    $controller->generateIcs($classId);
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de clase inválido.']);
                }
                break;
                
            case 'download_all':
                $controller->generateAllUserClassesIcs();
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Acción no válida.']);
                break;
        }
    } catch (Exception $e) {
        error_log("Error en IcsController: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Error interno del servidor.']);
    }
    exit();
}
?>