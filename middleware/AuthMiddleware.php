<?php

namespace Middleware;

require_once __DIR__ . '/../controllers/AuthController.php';

use Controllers\AuthController;

class AuthMiddleware {
    
    // Verificar autenticación básica
    public static function requireAuth($redirectTo = '../login.php') {
        if (!AuthController::isAuthenticated()) {
            AuthController::setFlashMessage('error', 'Debes iniciar sesión para acceder a esta página.');
            header('Location: ' . $redirectTo);
            exit();
        }
    }
    
    // Verificar rol de administrador
    public static function requireAdmin($redirectTo = '../login.php') {
        if (!AuthController::isAdmin()) {
            AuthController::setFlashMessage('error', 'No tienes permisos de administrador para acceder a esta página.');
            header('Location: ' . $redirectTo);
            exit();
        }
    }
    
    // Verificar rol de usuario
    public static function requireUser($redirectTo = '../login.php') {
        if (!AuthController::isUser()) {
            AuthController::setFlashMessage('error', 'No tienes permisos de usuario para acceder a esta página.');
            header('Location: ' . $redirectTo);
            exit();
        }
    }
    
    // Verificar que NO esté autenticado (para páginas como login/register)
    public static function requireGuest($redirectTo = 'views/client/home.php') {
        if (AuthController::isAuthenticated()) {
            if (AuthController::isAdmin()) {
                header('Location: views/admin/dashboard.php');
            } else {
                header('Location: ' . $redirectTo);
            }
            exit();
        }
    }
    
    // Verificar timeout de sesión
    public static function checkSessionTimeout($timeout = 3600) {
        return AuthController::checkSessionTimeout($timeout);
    }
    
    // Regenerar ID de sesión
    public static function regenerateSession() {
        AuthController::regenerateSession();
    }
}
?>
