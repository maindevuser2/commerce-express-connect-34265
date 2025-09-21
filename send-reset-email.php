<?php
require_once 'vendor/autoload.php';
require_once 'config/bootstrap.php';

use Controllers\EmailController;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$email = $_POST['reset_email'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email inválido']);
    exit;
}

try {
    $pdo = new PDO("mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']}", $_ENV['DB_USER'], $_ENV['DB_PASSWORD']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verificar si el usuario existe
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'No existe una cuenta con ese correo.']);
        exit;
    }

    $userId = $user['id'];
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hora

    // Eliminar tokens antiguos del mismo usuario
    $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$userId]);

    // Insertar nuevo token
    $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $token, $expiresAt]);

    // Crear link
    $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost';
    $resetLink = $baseUrl . "/reset_password.php?token=" . $token; //TODO: ajustar si está en hosting


    $htmlMessage = "
        <p>Hola,</p>
        <p>Has solicitado restablecer tu contraseña. Haz clic en el siguiente enlace:</p>
        <p><a href='$resetLink'>$resetLink</a></p>
        <p>Este enlace expirará en 1 hora.</p>
    ";

    $emailController = new EmailController();
    $sent = $emailController->sendEmail($email, '🔐 Restablecer tu contraseña', $htmlMessage);

    if ($sent) {
        echo json_encode(['success' => 'Correo de recuperación enviado correctamente.']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'No se pudo enviar el correo.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor.', 'details' => $e->getMessage()]);
}
