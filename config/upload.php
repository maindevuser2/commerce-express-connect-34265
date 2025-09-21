<?php
// Configuración de uploads y verificación de tamaño de POST
ini_set('display_errors', 1);
error_reporting(E_ALL);

/**
 * Convierte un tamaño en formato PHP (e.g., '200M') a bytes.
 */
function convertToBytes(string $size): int {
    $unit = strtoupper(substr($size, -1));
    $value = (float) substr($size, 0, -1);
    switch ($unit) {
        case 'P': $value *= 1024;
        case 'T': $value *= 1024;
        case 'G': $value *= 1024;
        case 'M': $value *= 1024;
        case 'K': $value *= 1024;
    }
    return (int) $value;
}

// Obtener longitud de contenido de la petición
$contentLength = isset($_SERVER['CONTENT_LENGTH'])
    ? (int) $_SERVER['CONTENT_LENGTH']
    : 0;

// Límite configurado en PHP para POST
$postMax = ini_get('post_max_size');
$postMaxBytes = convertToBytes($postMax);

if ($contentLength > $postMaxBytes) {
    die('El archivo es demasiado grande para subirlo. Límite de POST: '
        . $postMax . ' (' . number_format($postMaxBytes / (1024 * 1024), 2) . ' MB)');
}

class UploadConfig {
    public static function createDirectories() {
        $directories = [
            __DIR__ . '/../uploads',
            __DIR__ . '/../uploads/thumbnails',
            __DIR__ . '/../uploads/video_thumbnails',
            __DIR__ . '/../uploads/videos',
            __DIR__ . '/../uploads/video_files'
        ];
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                if (mkdir($dir, 0755, true)) {
                    error_log("Directorio creado: " . $dir);
                    // Crear archivo .htaccess para seguridad
                    $htaccessContent = "Options -Indexes
Deny from all
<Files ~ \"\\.(jpg|jpeg|png|gif|pdf|doc|docx|xls|xlsx|ppt|pptx|txt|zip|rar|mp4|avi|mov|wmv)$\">
    Allow from all
</Files>";
                    file_put_contents($dir . '/.htaccess', $htaccessContent);
                } else {
                    error_log("Error al crear directorio: " . $dir);
                }
            }
        }
    }
    
    public static function handleImageUpload($file, $uploadDir = 'thumbnails') {
        self::createDirectories();
        
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No se subió ningún archivo o hubo un error en la subida');
        }
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Tipo de archivo no permitido. Solo se permiten: JPG, PNG, GIF');
        }
        if ($file['size'] > $maxSize) {
            throw new Exception('El archivo es demasiado grande. Tamaño máximo: 5MB');
        }
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            throw new Exception('El archivo no es una imagen válida');
        }
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = time() . '_' . uniqid() . '.' . $extension;
        $uploadPath = __DIR__ . '/../uploads/' . $uploadDir . '/';
        $targetFile = $uploadPath . $filename;
        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            return 'uploads/' . $uploadDir . '/' . $filename;
        } else {
            throw new Exception('Error al mover el archivo subido');
        }
    }
    
    public static function handleVideoUpload($file, $uploadDir = 'videos') {
        self::createDirectories();
        
        $allowedExtensions = ['mp4', 'avi', 'mov', 'wmv'];
        $maxSize = 250 * 1024 * 1024; // 250MB

        
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No se subió ningún archivo de video o hubo un error en la subida');
        }
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions)) {
            throw new Exception('Tipo de archivo no permitido. Solo se permiten: MP4, AVI, MOV, WMV');
        }
        if ($file['size'] > $maxSize) {
            throw new Exception('El archivo es demasiado grande. Tamaño máximo: 250MB');
        }
        $filename = time() . '_' . uniqid() . '.' . $extension;
        $uploadPath = __DIR__ . '/../uploads/' . $uploadDir . '/';
        $targetFile = $uploadPath . $filename;
        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            return 'uploads/' . $uploadDir . '/' . $filename;
        } else {
            throw new Exception('Error al mover el archivo de video');
        }
    }

    public static function handleFileUpload($file, $uploadDir = 'video_files') {
        self::createDirectories();
        
        // Tipos de archivo permitidos para descarga
        $allowedTypes = [
            'application/pdf',
            'image/jpeg',
            'image/jpg', 
            'image/png',
            'image/gif',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'application/zip',
            'application/x-rar-compressed'
        ];
        
        $maxSize = 50 * 1024 * 1024; // 50MB
        
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por PHP',
                UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo del formulario',
                UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
                UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
                UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal',
                UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en disco',
                UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida'
            ];
            $errorMsg = $errorMessages[$file['error']] ?? 'Error desconocido en la subida';
            throw new Exception($errorMsg);
        }
        
        // Verificar tipo MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        error_log("Archivo subido - MIME detectado: $mimeType, MIME reportado: {$file['type']}");
        
        if (!in_array($mimeType, $allowedTypes) && !in_array($file['type'], $allowedTypes)) {
            throw new Exception('Tipo de archivo no permitido. Tipos permitidos: PDF, Imágenes, Documentos de Office, TXT, ZIP, RAR');
        }
        
        if ($file['size'] > $maxSize) {
            throw new Exception('El archivo es demasiado grande. Tamaño máximo: 50MB');
        }
        
        // Sanitizar nombre del archivo
        $originalName = $file['name'];
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
        $filename = time() . '_' . uniqid() . '_' . $safeName . '.' . $extension;
        
        $uploadPath = __DIR__ . '/../uploads/' . $uploadDir . '/';
        $targetFile = $uploadPath . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            error_log("Archivo movido exitosamente a: $targetFile");
            return [
                'file_path' => 'uploads/' . $uploadDir . '/' . $filename,
                'file_name' => $filename,
                'original_name' => $originalName,
                'file_type' => $mimeType,
                'file_size' => $file['size']
            ];
        } else {
            throw new Exception('Error al mover el archivo subido');
        }
    }

    public static function getFileIcon($fileType) {
        $icons = [
            'application/pdf' => 'fas fa-file-pdf',
            'image/jpeg' => 'fas fa-file-image',
            'image/jpg' => 'fas fa-file-image',
            'image/png' => 'fas fa-file-image',
            'image/gif' => 'fas fa-file-image',
            'application/msword' => 'fas fa-file-word',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'fas fa-file-word',
            'application/vnd.ms-excel' => 'fas fa-file-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'fas fa-file-excel',
            'application/vnd.ms-powerpoint' => 'fas fa-file-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'fas fa-file-powerpoint',
            'text/plain' => 'fas fa-file-alt',
            'application/zip' => 'fas fa-file-archive',
            'application/x-rar-compressed' => 'fas fa-file-archive'
        ];
        
        return $icons[$fileType] ?? 'fas fa-file';
    }

    public static function formatFileSize($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}
?>
