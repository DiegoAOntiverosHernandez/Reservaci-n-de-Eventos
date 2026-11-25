<?php
/**
 * Sistema Agora - Gestión de Reservación de Eventos
 *
 * @category   Controllers
 * @package    Agora_Core
 * @author     Equipo de Desarrollo Agora
 * @description API que procesa la solicitud de inicio de sesión de usuarios de manera asíncrona.
 */

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

$email = trim($data['email'] ?? '');
$password = trim($data['password'] ?? '');

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'El correo y la contraseña son obligatorios.']);
    exit;
}

try {
    // Buscar el usuario por correo electrónico (sentencia preparada con parámetro anónimo)
    $stmt = $pdo->prepare("SELECT id_usuario, nombre, correo_electronico, contrasena, rol FROM Usuario WHERE correo_electronico = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['contrasena'])) {
        // S4 (ISO/IEC 27001 A.9): Regenerar el identificador de sesión para prevenir fijación de sesión
        session_regenerate_id(true);

        // Inicializar variables de sesión seguras
        $_SESSION['usuario_id']     = $user['id_usuario'];
        $_SESSION['usuario_nombre'] = $user['nombre'];
        $_SESSION['usuario_email']  = $user['correo_electronico'];
        $_SESSION['usuario_rol']    = $user['rol'];

        echo json_encode([
            'success' => true,
            'message' => 'Inicio de sesión exitoso.',
            'user' => [
                'id'     => $user['id_usuario'],
                'nombre' => $user['nombre'],
                'email'  => $user['correo_electronico'],
                'rol'    => $user['rol']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Correo electrónico o contraseña incorrectos.'
        ]);
    }
} catch (\PDOException $e) {
    error_log('[Agora S1][login.php] Error PDO: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error interno en el servidor al procesar el inicio de sesión. Inténtalo de nuevo más tarde.'
    ]);
}
?>
