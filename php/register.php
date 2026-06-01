<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

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
$password = trim($data['password'] ?? '');

// Validaciones básicas
if (strlen($nombre) < 3) {
    echo json_encode(['success' => false, 'message' => 'El nombre debe tener al menos 3 caracteres.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Ingresa un correo electrónico válido.']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres.']);
    exit;
}

try {
    // Verificar si el correo ya está registrado
    $stmt = $pdo->prepare("SELECT id_usuario FROM Usuario WHERE correo_electronico = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'El correo electrónico ya está registrado.']);
        exit;
    }

    // Cifrar la contraseña
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Insertar el nuevo usuario (rol por defecto 'cliente')
    // Nota: Si deseamos permitir registrarse como 'organizador' directamente, se podría añadir un campo, pero
    // por defecto se crea como 'cliente' según la interfaz de registro normal.
    $stmt = $pdo->prepare("INSERT INTO Usuario (nombre, correo_electronico, contrasena, rol) VALUES (?, ?, ?, 'cliente')");
    $stmt->execute([$nombre, $email, $passwordHash]);

    echo json_encode([
        'success' => true,
        'message' => '¡Cuenta creada exitosamente! Ahora puedes iniciar sesión.'
    ]);
} catch (\PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar el usuario en el servidor: ' . $e->getMessage()
    ]);
}
?>
