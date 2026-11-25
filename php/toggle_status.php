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
$status = trim($data['status'] ?? '');
$id_organizador = getCurrentUserId();

if ($event_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de evento inválido.']);
    exit;
}

if ($status !== 'activo' && $status !== 'cancelado') {
    echo json_encode(['success' => false, 'message' => 'Estado inválido. Solo se permite "activo" o "cancelado".']);
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
        echo json_encode(['success' => false, 'message' => 'No tienes permisos para modificar el estado de este evento.']);
        exit;
    }

    // 2. Modificar el estado del evento
    $stmt = $pdo->prepare("UPDATE Evento SET estado = ? WHERE id_evento = ?");
    $stmt->execute([$status, $event_id]);

    // S3: Registrar cambio de estado del evento en la Bitácora de Auditoría
    $stmtBitacora = $pdo->prepare("
        INSERT INTO Bitacora (accion, detalle, id_evento_afectado)
        VALUES ('CAMBIO_ESTADO_EVENTO', ?, ?)
    ");
    $stmtBitacora->execute([
        "Organizador ID $id_organizador cambió el estado del evento ID $event_id a '$status'",
        $event_id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'El estado del evento ha sido cambiado a ' . $status . ' exitosamente.'
    ]);

} catch (\PDOException $e) {
    error_log('[Agora S1][toggle_status.php] Error PDO: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Ocurrió un error interno al cambiar el estado del evento.'
    ]);
}
?>
