<?php
namespace Helpers;

class ValidationHelper {
    
    /**
     * Validar datos de checkout
     */
    public static function validateCheckoutData($data) {
        $errors = [];
        
        // Validar token de Stripe
        if (empty($data['stripeToken'])) {
            $errors[] = 'Token de pago requerido';
        }
        
        // Validar email si está presente
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email inválido';
        }
        
        return $errors;
    }
    
    /**
     * Validar datos de usuario
     */
    public static function validateUserData($data) {
        $errors = [];
        
        if (empty($data['first_name'])) {
            $errors[] = 'Nombre es requerido';
        }
        
        if (empty($data['last_name'])) {
            $errors[] = 'Apellido es requerido';
        }
        
        if (empty($data['email'])) {
            $errors[] = 'Email es requerido';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email inválido';
        }
        
        if (empty($data['password'])) {
            $errors[] = 'Contraseña es requerida';
        } elseif (strlen($data['password']) < 6) {
            $errors[] = 'La contraseña debe tener al menos 6 caracteres';
        }
        
        return $errors;
    }
    
    /**
     * Validar datos de playlist
     */
    public static function validatePlaylistData($data) {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors[] = 'Nombre del curso es requerido';
        }
        
        if (empty($data['description'])) {
            $errors[] = 'Descripción es requerida';
        }
        
        if (empty($data['level'])) {
            $errors[] = 'Nivel es requerido';
        }
        
        if (!isset($data['price']) || $data['price'] < 0) {
            $errors[] = 'Precio debe ser mayor o igual a 0';
        }
        
        return $errors;
    }
    
    /**
     * Validar datos de video
     */
    public static function validateVideoData($data) {
        $errors = [];
        
        if (empty($data['title'])) {
            $errors[] = 'Título del video es requerido';
        }
        
        if (empty($data['playlist_id'])) {
            $errors[] = 'Playlist es requerida';
        }
        
        return $errors;
    }
    
    /**
     * Validar archivo subido
     */
    public static function validateUploadedFile($file, $allowedTypes, $maxSize) {
        $errors = [];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Error al subir el archivo';
            return $errors;
        }
        
        // Validar tipo
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedTypes)) {
            $errors[] = 'Tipo de archivo no permitido. Permitidos: ' . implode(', ', $allowedTypes);
        }
        
        // Validar tamaño
        if ($file['size'] > $maxSize) {
            $errors[] = 'El archivo es demasiado grande. Máximo: ' . self::formatBytes($maxSize);
        }
        
        return $errors;
    }
    
    /**
     * Formatear bytes a formato legible
     */
    public static function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Validar número de teléfono
     */
    public static function validatePhone($phone) {
        // Remover espacios y caracteres especiales
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Validar longitud básica
        return strlen($phone) >= 10 && strlen($phone) <= 15;
    }
    
    /**
     * Validar URL
     */
    public static function validateUrl($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Validar fecha
     */
    public static function validateDate($date, $format = 'Y-m-d') {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}
?>
