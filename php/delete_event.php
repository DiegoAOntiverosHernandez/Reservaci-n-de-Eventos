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

$event_id = (int)($data['event_id'] ?? 0);
$id_organizador = getCurrentUserId();

if ($event_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de evento inválido.']);
    exit;
}

try {
    // 1. Verificar si el evento existe y pertenece al organizador logueado
    $stmt = $pdo->prepare("SELECT id_evento, id_organizador FROM Evento WHERE id_evento = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'El evento solicitado no existe.']);
        exit;
    }

    if ((int)$event['id_organizador'] !== $id_organizador) {
        echo json_encode(['success' => false, 'message' => 'No tienes permisos para eliminar este evento.']);
        exit;
    }

    // 2. Eliminar el evento
    // Nota: El trigger y las claves foráneas en la base de datos se encargarán de eliminar en cascada
    // las zonas, itinerarios, etc. (ON DELETE CASCADE) y de controlar si hay reservaciones críticas.
    $stmt = $pdo->prepare("DELETE FROM Evento WHERE id_evento = ?");
    $stmt->execute([$event_id]);

    echo json_encode([
        'success' => true,
        'message' => 'El evento ha sido eliminado exitosamente.'
    ]);

} catch (\PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al intentar eliminar el evento en el servidor: ' . $e->getMessage()
    ]);
}
?>
