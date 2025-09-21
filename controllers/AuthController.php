<?php

namespace Controllers;

// Cargar configuración y dependencias
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/UserCourse.php';
require_once __DIR__ . '/EmailController.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

use Models\User;
use Helpers\SecurityHelper;

class AuthController {

    // Propiedad para la conexión a la base de datos
    private $db;

    public function __construct() {
        // Inicializar la conexión a la base de datos usando la clase global Database
        $database = new \Database();
        $this->db = $database->getConnection();
    }

    // Autenticar usuario
    public function login() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validar token CSRF
            $csrfToken = $_POST['csrf_token'] ?? '';
            if (!SecurityHelper::validateCSRFToken($csrfToken)) {
                self::setFlashMessage('error', 'Token de seguridad inválido. Intenta nuevamente.');
                header('Location: login.php');
                exit();
            }

            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';

            // Sanitizar entradas
            $email = SecurityHelper::sanitizeInput($email);
            $password = SecurityHelper::sanitizeInput($password);

            if (empty($email) || empty($password)) {
                self::setFlashMessage('error', 'Por favor, ingresa tu email y contraseña.');
                header('Location: login.php');
                exit();
            }

            // Validar formato de email
            if (!SecurityHelper::validateEmail($email)) {
                self::setFlashMessage('error', 'Formato de email inválido.');
                header('Location: login.php');
                exit();
            }

            // Verificar intentos de login fallidos
            if ($this->isAccountLocked($email)) {
                self::setFlashMessage('error', 'Cuenta temporalmente bloqueada por múltiples intentos fallidos. Intenta en 15 minutos.');
                header('Location: login.php');
                exit();
            }

            $userModel = new User($this->db);
            $userData = $userModel->findByEmail($email);
            
            if ($userData && $userData['is_active'] == 1) {
                // Poblar el objeto User con los datos encontrados
                $userModel->id = $userData['id'];
                $userModel->email = $userData['email'];
                $userModel->password = $userData['password'];
                $userModel->first_name = $userData['first_name'];
                $userModel->last_name = $userData['last_name'];
                $userModel->role = $userData['role'];
                $userModel->is_active = $userData['is_active'];
                
                if ($userModel->verifyPassword($password)) {
                    // Limpiar intentos fallidos
                    $this->clearFailedAttempts($email);
                    
                    $this->createSession($userModel);
                    $userModel->updateLastLogin(); // Actualizar la fecha del último login
                    
                    // Log de login exitoso
                    error_log("Login exitoso para usuario: " . $email . " desde IP: " . $this->getClientIP());
                    
                    self::setFlashMessage('success', '¡Bienvenido de nuevo!');
                    
                    // Redirigir según el rol
                    if ($userModel->role === 'admin') {
                        header('Location: views/admin/dashboard.php');
                    } else {
                        header('Location: views/client/home.php');
                    }
                    exit();
                } else {
                    // Registrar intento fallido
                    $this->recordFailedAttempt($email);
                    error_log("Intento de login fallido para: " . $email . " desde IP: " . $this->getClientIP());
                    self::setFlashMessage('error', 'Contraseña incorrecta.');
                }
            } else {
                // Registrar intento fallido
                $this->recordFailedAttempt($email);
                error_log("Intento de login con usuario inexistente: " . $email . " desde IP: " . $this->getClientIP());
                self::setFlashMessage('error', 'Usuario no encontrado o inactivo.');
            }
            header('Location: login.php');
            exit();
        }
    }

    /**
     * Recuperar contraseña - Sistema seguro con tokens
     */
    public function resetPassword() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validar token CSRF
            $csrfToken = $_POST['csrf_token'] ?? '';
            if (!SecurityHelper::validateCSRFToken($csrfToken)) {
                self::setFlashMessage('error', 'Token de seguridad inválido.');
                header('Location: login.php');
                exit();
            }

            $email = SecurityHelper::sanitizeInput($_POST['reset_email'] ?? '');

            if (empty($email) || !SecurityHelper::validateEmail($email)) {
                self::setFlashMessage('error', 'Por favor ingresa un email válido.');
                header('Location: login.php');
                exit();
            }

            // Verificar rate limiting
            if ($this->isResetRateLimited($email)) {
                self::setFlashMessage('error', 'Has solicitado demasiados resets. Espera 15 minutos antes de intentar nuevamente.');
                header('Location: login.php');
                exit();
            }

            $userModel = new User($this->db);
            $userData = $userModel->findByEmail($email);

            if (!$userData || $userData['is_active'] != 1) {
                // Por seguridad, no revelamos si el email existe o no
                self::setFlashMessage('success', 'Si el email está registrado, recibirás un enlace de recuperación.');
                header('Location: login.php');
                exit();
            }

            // Generar token seguro
            $token = SecurityHelper::generateRandomString(64);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Limpiar tokens antiguos del usuario
            $this->cleanupOldResetTokens($userData['id']);

            // Guardar token en la BD
            $stmt = $this->db->prepare("INSERT INTO password_resets (user_id, token, expires_at, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$userData['id'], hash('sha256', $token), $expiresAt, $this->getClientIP()]);

            // Enviar email con enlace seguro
            $this->sendPasswordResetEmail($email, $userData['first_name'], $token);

            // Registrar intento de reset
            $this->recordResetAttempt($email);

            self::setFlashMessage('success', 'Si el email está registrado, recibirás un enlace de recuperación.');
            header('Location: login.php');
            exit();
        }
    }

    /**
     * Procesar reset de contraseña con token
     */
    public function processPasswordReset() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = SecurityHelper::sanitizeInput($_POST['token'] ?? '');
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            $csrfToken = $_POST['csrf_token'] ?? '';

            // Validar token CSRF
            if (!SecurityHelper::validateCSRFToken($csrfToken)) {
                self::setFlashMessage('error', 'Token de seguridad inválido.');
                header('Location: login.php');
                exit();
            }

            // Validar que las contraseñas coincidan
            if ($newPassword !== $confirmPassword) {
                self::setFlashMessage('error', 'Las contraseñas no coinciden.');
                header('Location: reset-password.php?token=' . urlencode($token));
                exit();
            }

            // Validar fuerza de la contraseña
            $passwordErrors = SecurityHelper::validatePasswordStrength($newPassword);
            if (!empty($passwordErrors)) {
                self::setFlashMessage('error', implode('. ', $passwordErrors));
                header('Location: reset-password.php?token=' . urlencode($token));
                exit();
            }

            // Verificar token
            $resetData = $this->validateResetToken($token);
            if (!$resetData) {
                self::setFlashMessage('error', 'Token inválido o expirado.');
                header('Location: login.php');
                exit();
            }

            // Actualizar contraseña
            $hashedPassword = SecurityHelper::hashPassword($newPassword);
            $userModel = new User($this->db);
            
            if ($userModel->updatePassword($resetData['user_id'], $hashedPassword)) {
                // Eliminar token usado
                $this->deleteResetToken($resetData['id']);
                
                // Log de cambio de contraseña
                error_log("Contraseña cambiada exitosamente para usuario ID: " . $resetData['user_id']);
                
                self::setFlashMessage('success', 'Contraseña actualizada exitosamente. Ya puedes iniciar sesión.');
                header('Location: login.php');
                exit();
            } else {
                self::setFlashMessage('error', 'Error al actualizar la contraseña. Intenta nuevamente.');
                header('Location: reset-password.php?token=' . urlencode($token));
                exit();
            }
        }
    }

    //Registrar y verificar captcha
    public function register() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validar token CSRF
            $csrfToken = $_POST['csrf_token'] ?? '';
            if (!SecurityHelper::validateCSRFToken($csrfToken)) {
                self::setFlashMessage('error', 'Token de seguridad inválido.');
                header('Location: signup.php');
                exit();
            }

            // Obtener y sanitizar datos del formulario
            $first_name = SecurityHelper::sanitizeInput($_POST['first_name'] ?? '');
            $last_name = SecurityHelper::sanitizeInput($_POST['last_name'] ?? '');
            $email = SecurityHelper::sanitizeInput($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';

            error_log("AuthController::register - Datos recibidos: $first_name, $last_name, $email");

            // Validar reCAPTCHA usando la configuración
            $recaptchaSecret = RECAPTCHA_SECRET_KEY;
            
            error_log("AuthController::register - reCAPTCHA Secret Key: " . substr($recaptchaSecret, 0, 10) . "...");
            
            if (empty($recaptchaResponse)) {
                error_log("AuthController::register - reCAPTCHA no completado");
                self::setFlashMessage('error', 'Por favor confirma que no eres un robot.');
                header('Location: signup.php');
                exit();
            }

            // Verificar reCAPTCHA con Google
            $verifyURL = 'https://www.google.com/recaptcha/api/siteverify';
            $postData = http_build_query([
                'secret' => $recaptchaSecret,
                'response' => $recaptchaResponse,
                'remoteip' => $this->getClientIP()
            ]);

            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/x-www-form-urlencoded',
                    'content' => $postData
                ]
            ]);

            $verifyResponse = file_get_contents($verifyURL, false, $context);
            $responseData = json_decode($verifyResponse, true);

            error_log("AuthController::register - reCAPTCHA Response: " . json_encode($responseData));

            if (!$responseData || !$responseData['success']) {
                $errorCodes = $responseData['error-codes'] ?? [];
                error_log("AuthController::register - Falló la verificación reCAPTCHA. Errores: " . implode(', ', $errorCodes));
                
                $errorMessage = 'Error de verificación de reCAPTCHA.';
                if (in_array('invalid-input-secret', $errorCodes)) {
                    $errorMessage = 'Error de configuración del reCAPTCHA. Contacta al administrador.';
                } elseif (in_array('invalid-input-response', $errorCodes)) {
                    $errorMessage = 'reCAPTCHA inválido. Intenta nuevamente.';
                } elseif (in_array('timeout-or-duplicate', $errorCodes)) {
                    $errorMessage = 'reCAPTCHA expirado. Intenta nuevamente.';
                }
                
                self::setFlashMessage('error', $errorMessage);
                header('Location: signup.php');
                exit();
            }

            // Validaciones básicas
            if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($confirm_password)) {
                error_log("AuthController::register - Campos vacíos detectados");
                self::setFlashMessage('error', 'Todos los campos son requeridos.');
                header('Location: signup.php');
                exit();
            }

            if (!SecurityHelper::validateEmail($email)) {
                error_log("AuthController::register - Email inválido: $email");
                self::setFlashMessage('error', 'Formato de email inválido.');
                header('Location: signup.php');
                exit();
            }

            if ($password !== $confirm_password) {
                error_log("AuthController::register - Contraseñas no coinciden");
                self::setFlashMessage('error', 'Las contraseñas no coinciden.');
                header('Location: signup.php');
                exit();
            }

            // Validar fuerza de contraseña
            $passwordErrors = SecurityHelper::validatePasswordStrength($password);
            if (!empty($passwordErrors)) {
                error_log("AuthController::register - Contraseña débil");
                self::setFlashMessage('error', implode('. ', $passwordErrors));
                header('Location: signup.php');
                exit();
            }

            // Verificar rate limiting de registro
            if ($this->isRegistrationRateLimited()) {
                self::setFlashMessage('error', 'Demasiados registros desde esta IP. Intenta más tarde.');
                header('Location: signup.php');
                exit();
            }

            try {
                $userModel = new User($this->db);

                // Verificar si el email ya existe
                if ($userModel->emailExists($email)) {
                    error_log("AuthController::register - Email ya existe: $email");
                    self::setFlashMessage('error', 'Este email ya está registrado.');
                    header('Location: signup.php');
                    exit();
                }

                // Configurar datos del usuario
                $userModel->first_name = $first_name;
                $userModel->last_name = $last_name;
                $userModel->email = $email;
                $userModel->password = SecurityHelper::hashPassword($password);
                $userModel->role = 'user';

                error_log("AuthController::register - Intentando crear usuario...");

                // Intentar crear el usuario
                if ($userModel->create()) {
                    error_log("AuthController::register - Usuario creado exitosamente con ID: " . $userModel->id);

                    // Registrar intento de registro exitoso
                    $this->recordRegistrationAttempt($email, true);

                    // Crear sesión automáticamente
                    $this->createSession($userModel);
                    self::setFlashMessage('success', '¡Registro exitoso! Bienvenido.');
                    header('Location: views/client/home.php');
                    exit();
                } else {
                    error_log("AuthController::register - Fallo al crear usuario");
                    self::setFlashMessage('error', 'Error al registrar el usuario. Intenta de nuevo.');
                    header('Location: signup.php');
                    exit();
                }

            } catch (\Exception $e) {
                error_log("AuthController::register - Excepción: " . $e->getMessage());
                self::setFlashMessage('error', 'Error interno del servidor. Intenta de nuevo.');
                header('Location: signup.php');
                exit();
            }
        }
    }

    // Crear sesión segura
    public function createSession($user) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // Regenerar ID de sesión para prevenir session fixation
        session_regenerate_id(true);

        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_email'] = $user->email;
        $_SESSION['user_name'] = $user->getFullName();
        $_SESSION['user_first_name'] = $user->first_name;
        $_SESSION['user_last_name'] = $user->last_name;
        $_SESSION['user_role'] = $user->role;
        $_SESSION['login_time'] = time();
        $_SESSION['ip_address'] = $this->getClientIP();
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        error_log("AuthController::createSession - Sesión creada para usuario ID: " . $user->id);
    }

    // Cerrar sesión
    public static function logout() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // Log de logout
        if (isset($_SESSION['user_id'])) {
            error_log("Usuario ID " . $_SESSION['user_id'] . " cerró sesión");
        }

        $_SESSION = array();
        
        // Destruir cookie de sesión
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        header('Location: index.php');
        exit();
    }

    // Verificar si el usuario está autenticado
    public static function isAuthenticated() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            return false;
        }

        // Verificar integridad de sesión
        $currentIP = self::getClientIP();
        $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $currentIP) {
            error_log("Posible session hijacking detectado - IP cambió de " . $_SESSION['ip_address'] . " a " . $currentIP);
            self::logout();
            return false;
        }

        if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $currentUserAgent) {
            error_log("Posible session hijacking detectado - User Agent cambió");
            self::logout();
            return false;
        }

        return true;
    }

    // Alias para isAuthenticated() - para compatibilidad
    public static function isLoggedIn() {
        return self::isAuthenticated();
    }

    // Verificar si el usuario está logueado (método adicional)
    public static function checkAuth() {
        return self::isAuthenticated();
    }

    // Obtener usuario actual
    public static function getCurrentUser() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        if (self::isAuthenticated()) {
            return [
                'id' => $_SESSION['user_id'],
                'email' => $_SESSION['user_email'],
                'name' => $_SESSION['user_name'],
                'first_name' => $_SESSION['user_first_name'] ?? '',
                'last_name' => $_SESSION['user_last_name'] ?? '',
                'role' => $_SESSION['user_role']
            ];
        }
        return null;
    }

    // Verificar si el usuario es administrador
    public static function isAdmin() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        return self::isAuthenticated() && ($_SESSION['user_role'] ?? '') === 'admin';
    }

    // Verificar si el usuario es cliente
    public static function isUser() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        return self::isAuthenticated() && ($_SESSION['user_role'] ?? '') === 'user';
    }

    // Requerir autenticación - redirige si no está logueado
    public static function requireAuth($redirectTo = 'login.php') {
        if (!self::isAuthenticated()) {
            self::setFlashMessage('error', 'Debes iniciar sesión para acceder a esta página.');
            header('Location: ' . $redirectTo);
            exit();
        }
    }

    // Requerir rol de administrador
    public static function requireAdmin($redirectTo = 'login.php') {
        if (!self::isAdmin()) {
            self::setFlashMessage('error', 'No tienes permisos para acceder a esta página.');
            header('Location: ' . $redirectTo);
            exit();
        }
    }

    // Obtener ID del usuario actual
    public static function getCurrentUserId() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        return self::isAuthenticated() ? $_SESSION['user_id'] : null;
    }

    // Obtener rol del usuario actual
    public static function getCurrentUserRole() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        return self::isAuthenticated() ? ($_SESSION['user_role'] ?? 'user') : null;
    }

    // Establecer mensaje flash
    public static function setFlashMessage($type, $message) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['flash_message'] = ['type' => $type, 'message' => SecurityHelper::sanitizeInput($message)];
    }

    // Obtener y limpiar mensaje flash
    public static function getFlashMessage() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $message = $_SESSION['flash_message'] ?? null;
        unset($_SESSION['flash_message']);
        return $message;
    }

    // Verificar tiempo de sesión (opcional - para expirar sesiones)
    public static function checkSessionTimeout($timeout = 7200) { // 2 horas por defecto
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if (self::isAuthenticated()) {
            $loginTime = $_SESSION['login_time'] ?? 0;
            if (time() - $loginTime > $timeout) {
                error_log("Sesión expirada para usuario ID: " . ($_SESSION['user_id'] ?? 'desconocido'));
                self::logout();
                return false;
            }
            // Actualizar tiempo de actividad
            $_SESSION['login_time'] = time();
        }
        return true;
    }

    // Regenerar ID de sesión para seguridad
    public static function regenerateSession() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        session_regenerate_id(true);
    }

    // MÉTODOS PRIVADOS DE SEGURIDAD

    /**
     * Obtener IP del cliente de forma segura
     */
    private static function getClientIP() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
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

    /**
     * Verificar si una cuenta está bloqueada por intentos fallidos
     */
    private function isAccountLocked($email) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as attempts 
                FROM login_attempts 
                WHERE email = ? 
                AND success = 0 
                AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            ");
            $stmt->execute([$email]);
            $result = $stmt->fetch();
            
            return $result['attempts'] >= 5;
        } catch (\Exception $e) {
            error_log("Error verificando bloqueo de cuenta: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Registrar intento de login fallido
     */
    private function recordFailedAttempt($email) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO login_attempts (email, ip_address, user_agent, success, attempted_at) 
                VALUES (?, ?, ?, 0, NOW())
            ");
            $stmt->execute([
                $email,
                $this->getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (\Exception $e) {
            error_log("Error registrando intento fallido: " . $e->getMessage());
        }
    }

    /**
     * Limpiar intentos fallidos después de login exitoso
     */
    private function clearFailedAttempts($email) {
        try {
            $stmt = $this->db->prepare("DELETE FROM login_attempts WHERE email = ?");
            $stmt->execute([$email]);
        } catch (\Exception $e) {
            error_log("Error limpiando intentos fallidos: " . $e->getMessage());
        }
    }

    /**
     * Verificar rate limiting para reset de contraseña
     */
    private function isResetRateLimited($email) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as attempts 
                FROM password_reset_attempts 
                WHERE email = ? 
                AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            ");
            $stmt->execute([$email]);
            $result = $stmt->fetch();
            
            return $result['attempts'] >= 3;
        } catch (\Exception $e) {
            error_log("Error verificando rate limit de reset: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Registrar intento de reset de contraseña
     */
    private function recordResetAttempt($email) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO password_reset_attempts (email, ip_address, attempted_at) 
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$email, $this->getClientIP()]);
        } catch (\Exception $e) {
            error_log("Error registrando intento de reset: " . $e->getMessage());
        }
    }

    /**
     * Limpiar tokens de reset antiguos
     */
    private function cleanupOldResetTokens($userId) {
        try {
            $stmt = $this->db->prepare("DELETE FROM password_resets WHERE user_id = ? OR expires_at < NOW()");
            $stmt->execute([$userId]);
        } catch (\Exception $e) {
            error_log("Error limpiando tokens antiguos: " . $e->getMessage());
        }
    }

    /**
     * Enviar email de reset de contraseña
     */
    private function sendPasswordResetEmail($email, $firstName, $token) {
        try {
            require_once __DIR__ . '/EmailController.php';
            
            $baseUrl = APP_URL;
            $resetLink = $baseUrl . '/reset-password.php?token=' . urlencode($token);
            
            $subject = "Recuperación de Contraseña - English Learning Platform";
            $body = "
                <h2>Recuperación de Contraseña</h2>
                <p>Hola {$firstName},</p>
                <p>Has solicitado restablecer tu contraseña. Haz clic en el siguiente enlace para crear una nueva contraseña:</p>
                <p><a href='{$resetLink}' style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Restablecer Contraseña</a></p>
                <p>Si no puedes hacer clic en el botón, copia y pega este enlace en tu navegador:</p>
                <p>{$resetLink}</p>
                <p><strong>Este enlace expira en 1 hora.</strong></p>
                <p>Si no solicitaste este cambio, puedes ignorar este email.</p>
                <hr>
                <p><small>English Learning Platform - Sistema de Seguridad</small></p>
            ";

            $emailController = new EmailController();
            return $emailController->sendEmail($email, $subject, $body);
        } catch (\Exception $e) {
            error_log("Error enviando email de reset: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validar token de reset
     */
    private function validateResetToken($token) {
        try {
            $hashedToken = hash('sha256', $token);
            $stmt = $this->db->prepare("
                SELECT id, user_id, expires_at 
                FROM password_resets 
                WHERE token = ? AND expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute([$hashedToken]);
            
            return $stmt->fetch();
        } catch (\Exception $e) {
            error_log("Error validando token de reset: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar token de reset usado
     */
    private function deleteResetToken($tokenId) {
        try {
            $stmt = $this->db->prepare("DELETE FROM password_resets WHERE id = ?");
            $stmt->execute([$tokenId]);
        } catch (\Exception $e) {
            error_log("Error eliminando token de reset: " . $e->getMessage());
        }
    }

    /**
     * Verificar rate limiting de registro
     */
    private function isRegistrationRateLimited() {
        try {
            $ip = $this->getClientIP();
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as attempts 
                FROM registration_attempts 
                WHERE ip_address = ? 
                AND attempted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$ip]);
            $result = $stmt->fetch();
            
            return $result['attempts'] >= 5;
        } catch (\Exception $e) {
            error_log("Error verificando rate limit de registro: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Registrar intento de registro
     */
    private function recordRegistrationAttempt($email, $success = false) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO registration_attempts (email, ip_address, user_agent, success, attempted_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $email,
                $this->getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $success ? 1 : 0
            ]);
        } catch (\Exception $e) {
            error_log("Error registrando intento de registro: " . $e->getMessage());
        }
    }
}
?>
