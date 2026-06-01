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
$id_usuario = getCurrentUserId();

if ($event_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de evento inválido.']);
    exit;
}

try {
    // 1. Encontrar la zona del evento
    $stmt = $pdo->prepare("SELECT id_zona FROM Zona_Boleto WHERE id_evento = ? LIMIT 1");
    $stmt->execute([$event_id]);
    $zona = $stmt->fetch();

    if (!$zona) {
        echo json_encode(['success' => false, 'message' => 'El evento seleccionado no cuenta con zonas de boletaje activas.']);
        exit;
    }

    $id_zona = $zona['id_zona'];

    // 2. Buscar la reservación activa ('Confirmado') para este usuario y zona
    $stmtRes = $pdo->prepare("SELECT id_reservacion FROM Reservacion WHERE id_usuario = ? AND id_zona = ? AND estado_reservacion = 'Confirmado' LIMIT 1");
    $stmtRes->execute([$id_usuario, $id_zona]);
    $res = $stmtRes->fetch();

    if (!$res) {
        echo json_encode(['success' => false, 'message' => 'No se encontró ninguna reservación activa para este evento.']);
        exit;
    }

    $id_reservacion = $res['id_reservacion'];

    // 3. Cancelar la reservación
    // El trigger trg_restaurar_lugares_cancelacion en MySQL se encargará de:
    // a. Devolver los asientos reservados al stock disponible en Zona_Boleto.
    // b. Cambiar el estado de los tickets individuales en la tabla Boleto a 'cancelado'.
    // c. Registrar la bitácora de auditoría.
    $stmtCancel = $pdo->prepare("UPDATE Reservacion SET estado_reservacion = 'Cancelado' WHERE id_reservacion = ?");
    $stmtCancel->execute([$id_reservacion]);

    echo json_encode([
        'success' => true,
        'message' => 'Reservación cancelada exitosamente y cupos liberados.'
    ]);

} catch (\PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al cancelar la reservación en el servidor: ' . $e->getMessage()
    ]);
}
?>
