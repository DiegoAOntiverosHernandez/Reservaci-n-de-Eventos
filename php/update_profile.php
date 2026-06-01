<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/auth.php';

// Exigir inicio de sesión
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método de solicitud no permitido.']);
    exit;
}

// Obtener datos del cuerpo de la petición (JSON)
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'No se recibieron datos válidos.']);
    exit;
}

$nombre = trim($data['nombre'] ?? '');
$email = trim($data['email'] ?? '');
$newPassword = trim($data['new_password'] ?? '');
$currentUserId = getCurrentUserId();

// Validaciones
if (strlen($nombre) < 3) {
    echo json_encode(['success' => false, 'message' => 'El nombre debe tener al menos 3 caracteres.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Ingresa un correo electrónico válido.']);
    exit;
}

if (!empty($newPassword) && strlen($newPassword) < 6) {
    echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres.']);
    exit;
}

try {
    // Verificar si el correo ya está registrado por otro usuario
    $stmt = $pdo->prepare("SELECT id_usuario FROM Usuario WHERE correo_electronico = ? AND id_usuario != ?");
    $stmt->execute([$email, $currentUserId]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'El correo electrónico ya está en uso por otra cuenta.']);
        exit;
    }

    if (!empty($newPassword)) {
        // Cifrar la nueva contraseña
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE Usuario SET nombre = ?, correo_electronico = ?, contrasena = ? WHERE id_usuario = ?");
        $stmt->execute([$nombre, $email, $passwordHash, $currentUserId]);
    } else {
        // Actualizar sin cambiar la contraseña
        $stmt = $pdo->prepare("UPDATE Usuario SET nombre = ?, correo_electronico = ? WHERE id_usuario = ?");
        $stmt->execute([$nombre, $email, $currentUserId]);
    }

    // Actualizar datos en sesión activa
    $_SESSION['usuario_nombre'] = $nombre;
    $_SESSION['usuario_email']  = $email;

    echo json_encode([
        'success' => true,
        'message' => 'Información de perfil actualizada exitosamente.'
    ]);
} catch (\PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar el perfil en el servidor: ' . $e->getMessage()
    ]);
}
?>
