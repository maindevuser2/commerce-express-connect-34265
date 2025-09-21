<?php
// Incluir el controlador de autenticación
require_once 'controllers/AuthController.php';

use Controllers\AuthController; // Usar namespace

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Llamar al método estático logout
AuthController::logout();
?>
