<?php
// Configuración principal de la aplicación

// Cargar variables de entorno desde .env
function loadEnv($path) {
    if (!file_exists($path)) {
        error_log("Archivo .env no encontrado en: $path");
        return false;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        error_log("No se pudo leer el archivo .env");
        return false;
    }
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Saltar líneas vacías y comentarios
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        // Buscar el signo igual
        $equalPos = strpos($line, '=');
        if ($equalPos === false) {
            continue;
        }
        
        $name = trim(substr($line, 0, $equalPos));
        $value = trim(substr($line, $equalPos + 1));
        
        // Remover comillas si existen
        if (preg_match('/^"(.*)"$/', $value, $matches)) {
            $value = $matches[1];
        } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
            $value = $matches[1];
        }
        
        // Solo establecer si no existe ya
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
    
    return true;
}

// Cargar variables de entorno
$envLoaded = loadEnv(__DIR__ . '/../.env');
if (!$envLoaded) {
    error_log("Advertencia: No se pudo cargar el archivo .env, usando valores por defecto");
}

// Configuración de la aplicación
define('APP_NAME', $_ENV['APP_NAME'] ?? 'English Learning Platform');
define('APP_ENV', $_ENV['APP_ENV'] ?? 'development');
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost:3000');
define('APP_DEBUG', filter_var($_ENV['APP_DEBUG'] ?? 'true', FILTER_VALIDATE_BOOLEAN));

// Configuración de base de datos
define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'ecommerce_cursos');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? '');

// Configuración de reCAPTCHA
define('RECAPTCHA_SITE_KEY', $_ENV['RECAPTCHA_SITE_KEY'] ?? '6LdvMtArAAAAAOnDELXScRcyI9obm3nheBp9rblI');
define('RECAPTCHA_SECRET_KEY', $_ENV['RECAPTCHA_SECRET_KEY'] ?? '6LdvMtArAAAAAESsm4pHyg3zQQGoSZtNVFhardp7');

// Configuración de email
define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
define('SMTP_PORT', $_ENV['SMTP_PORT'] ?? '587');
define('SMTP_USERNAME', $_ENV['SMTP_USERNAME'] ?? '');
define('SMTP_PASSWORD', $_ENV['SMTP_PASSWORD'] ?? 'pesadzfrnnozxztc');
define('FROM_EMAIL', $_ENV['FROM_EMAIL'] ?? '');
define('FROM_NAME', $_ENV['FROM_NAME'] ?? APP_NAME);

// Configuración de seguridad
define('SESSION_LIFETIME', (int)($_ENV['SESSION_LIFETIME'] ?? 7200));
define('MAX_LOGIN_ATTEMPTS', (int)($_ENV['MAX_LOGIN_ATTEMPTS'] ?? 5));
define('LOCKOUT_DURATION', (int)($_ENV['LOCKOUT_DURATION'] ?? 900));
define('PASSWORD_MIN_LENGTH', (int)($_ENV['PASSWORD_MIN_LENGTH'] ?? 8));

// Configuración de archivos
define('MAX_FILE_SIZE', (int)($_ENV['MAX_FILE_SIZE'] ?? 10485760));
define('ALLOWED_FILE_TYPES', $_ENV['ALLOWED_FILE_TYPES'] ?? 'pdf,doc,docx,txt,jpg,jpeg,png');

// Configuración de sesión segura
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
}

// Headers de seguridad
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    if (isset($_SERVER['HTTPS'])) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// Función helper para obtener configuración
function config($key, $default = null) {
    return $_ENV[$key] ?? $default;
}

// Función helper para verificar si estamos en desarrollo
function isDevelopment() {
    return APP_ENV === 'development';
}

// Función helper para verificar si estamos en producción
function isProduction() {
    return APP_ENV === 'production';
}

// Función helper para debug logging
function debugLog($message, $context = []) {
    if (APP_DEBUG) {
        $logMessage = is_string($message) ? $message : print_r($message, true);
        if (!empty($context)) {
            $logMessage .= ' | Context: ' . print_r($context, true);
        }
        error_log("[DEBUG] " . $logMessage);
    }
}

// Función helper para obtener IP del cliente
function getClientIP() {
    $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) {
                $ip = explode(',', $ip)[0];
            }
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Función helper para sanitizar entrada
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Función helper para validar email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Función helper para generar token seguro
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Función helper para hash de contraseña
function hashPassword($password) {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 3
    ]);
}

// Función helper para verificar contraseña
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Configurar zona horaria
date_default_timezone_set($_ENV['TIMEZONE'] ?? 'America/Mexico_City');

// Configurar manejo de errores
if (isProduction()) {
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
}

// Log de inicialización
debugLog("Configuración cargada", [
    'env' => APP_ENV,
    'debug' => APP_DEBUG,
    'db_host' => DB_HOST,
    'db_name' => DB_NAME,
    'recaptcha_configured' => !empty(RECAPTCHA_SITE_KEY) && !empty(RECAPTCHA_SECRET_KEY)
]);
?>
