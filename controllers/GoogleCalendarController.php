<?php
namespace Controllers;

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/SyncClass.php';
require_once __DIR__ . '/../models/SyncClassSchedule.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/UserSyncClass.php';

use Models\SyncClass;
use Models\SyncClassSchedule;
use Models\UserSyncClass;
use Controllers\AuthController;
use Exception;

class GoogleCalendarController {
    private $db;
    private $syncClassModel;
    private $scheduleModel;
    private $userSyncClassModel;
    
    public function __construct() {
        try {
            $database = new \Database();
            $this->db = $database->getConnection();
            $this->syncClassModel = new SyncClass($this->db);
            $this->scheduleModel = new SyncClassSchedule($this->db);
            $this->userSyncClassModel = new UserSyncClass($this->db);
        } catch (Exception $e) {
            error_log("Error en GoogleCalendarController constructor: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Genera URL de Google Calendar para una clase sincrónica
     */
    public function generateGoogleCalendarUrl($sync_class_id) {
        try {
            // Verificar que el usuario esté autenticado
            if (!AuthController::isAuthenticated()) {
                return ['error' => 'Debes iniciar sesión para agregar al calendario.'];
            }
            
            $currentUser = AuthController::getCurrentUser();
            
            // Verificar que el usuario tenga acceso a esta clase
            if (!$this->userSyncClassModel->hasAccess($currentUser['id'], $sync_class_id)) {
                return ['error' => 'No tienes acceso a esta clase.'];
            }
            
            // Obtener datos de la clase
            $syncClass = $this->syncClassModel->readOne($sync_class_id);
            
            if (!$syncClass) {
                return ['error' => 'Clase no encontrada.'];
            }
            
            // Obtener horarios
            $schedules = $this->scheduleModel->readBySyncClass($sync_class_id);
            
            // Generar URL de Google Calendar
            $url = $this->createGoogleCalendarUrl($syncClass, $schedules);
            
            return ['url' => $url];
            
        } catch (Exception $e) {
            error_log("Error generando URL de Google Calendar: " . $e->getMessage());
            return ['error' => 'Error al generar el enlace de calendario.'];
        }
    }
    
    /**
     * Crea la URL de Google Calendar
     */
    private function createGoogleCalendarUrl($syncClass, $schedules) {
        $title = urlencode($syncClass['title']);
        
        // Crear descripción con horarios
        $description = $syncClass['description'] ?? '';
        if (!empty($schedules)) {
            $description .= "\n\nHorarios semanales:\n";
            foreach ($schedules as $schedule) {
                $dayName = SyncClassSchedule::getDayName($schedule['day_of_week']);
                $description .= "• {$dayName}: " . substr($schedule['start_time'], 0, 5) . " - " . substr($schedule['end_time'], 0, 5) . "\n";
            }
        }
        $description .= "\n\nEnlace de reunión: " . $syncClass['meeting_link'];
        $description = urlencode($description);
        
        // Calcular fecha y hora del primer evento
        if (!empty($schedules)) {
            // Ordenar horarios por día de semana
            usort($schedules, function($a, $b) {
                return $a['day_of_week'] <=> $b['day_of_week'];
            });
            
            $firstSchedule = $schedules[0];
            $startDateBase = strtotime($syncClass['start_date']);
            
            // Encontrar el primer día que coincide con el horario programado
            $targetDayOfWeek = $firstSchedule['day_of_week'];
            $currentDayOfWeek = date('w', $startDateBase);
            
            // Calcular cuántos días faltan para llegar al primer día de clase
            $daysUntilFirst = ($targetDayOfWeek - $currentDayOfWeek + 7) % 7;
            $firstClassDate = strtotime("+{$daysUntilFirst} days", $startDateBase);
            
            // Crear DateTime para el primer evento con la hora específica
            $startDateTime = date('Y-m-d', $firstClassDate) . ' ' . $firstSchedule['start_time'];
            $endDateTime = date('Y-m-d', $firstClassDate) . ' ' . $firstSchedule['end_time'];
            
            $startDate = $this->formatDateForGoogle($startDateTime);
            $endDate = $this->formatDateForGoogle($endDateTime);
            
            // Crear regla de recurrencia para los días seleccionados
            $daysWithClasses = [];
            foreach ($schedules as $schedule) {
                $daysWithClasses[] = $this->getGoogleDayAbbreviation($schedule['day_of_week']);
            }
            $daysStr = implode(',', array_unique($daysWithClasses));
            
            // Calcular fecha final para recurrencia (formato YYYYMMDD sin horas)
            $untilDate = date('Ymd\T235959\Z', strtotime($syncClass['end_date']));
            $recurrence = urlencode("RRULE:FREQ=WEEKLY;BYDAY={$daysStr};UNTIL={$untilDate}");
        } else {
            // Si no hay horarios, usar fechas generales
            $startDate = $this->formatDateForGoogle($syncClass['start_date']);
            $endDate = $this->formatDateForGoogle($syncClass['end_date']);
            $recurrence = '';
        }
        
        $location = urlencode($syncClass['meeting_link']);
        
        // Construir URL
        $url = "https://calendar.google.com/calendar/render?action=TEMPLATE";
        $url .= "&text=" . $title;
        $url .= "&dates=" . $startDate . "/" . $endDate;
        $url .= "&details=" . $description;
        $url .= "&location=" . $location;
        if (!empty($recurrence)) {
            $url .= "&recur=" . $recurrence;
        }
        
        return $url;
    }
    
    /**
     * Formatea una fecha para Google Calendar (YYYYMMDDTHHMMSSZ)
     */
    private function formatDateForGoogle($dateString) {
        $timestamp = strtotime($dateString);
        return gmdate('Ymd\THis\Z', $timestamp);
    }
    
    /**
     * Convierte día de semana a abreviación de Google Calendar
     */
    private function getGoogleDayAbbreviation($day_of_week) {
        $abbreviations = [
            0 => 'SU', // Domingo
            1 => 'MO', // Lunes
            2 => 'TU', // Martes
            3 => 'WE', // Miércoles
            4 => 'TH', // Jueves
            5 => 'FR', // Viernes
            6 => 'SA'  // Sábado
        ];
        return $abbreviations[$day_of_week] ?? 'MO';
    }
}

// Manejo de solicitudes directas
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    session_start();
    
    try {
        $controller = new GoogleCalendarController();
        $action = $_GET['action'] ?? 'add';
        
        if ($action === 'add') {
            $classId = intval($_GET['class_id'] ?? 0);
            if ($classId > 0) {
                $result = $controller->generateGoogleCalendarUrl($classId);
                if (isset($result['error'])) {
                    http_response_code(403);
                    echo json_encode($result);
                } else {
                    // Redirigir a Google Calendar
                    header('Location: ' . $result['url']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'ID de clase inválido.']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida.']);
        }
    } catch (Exception $e) {
        error_log("Error en GoogleCalendarController: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Error interno del servidor.']);
    }
    exit();
}
?>
