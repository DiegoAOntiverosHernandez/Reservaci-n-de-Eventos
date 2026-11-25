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
    // Buscar la reservación activa ('Confirmado') para este usuario y evento en cualquiera de sus zonas
    $stmtRes = $pdo->prepare("
        SELECT r.id_reservacion 
        FROM Reservacion r
        JOIN Zona_Boleto z ON r.id_zona = z.id_zona
        WHERE r.id_usuario = ? 
          AND z.id_evento = ? 
          AND r.estado_reservacion = 'Confirmado'
        LIMIT 1
    ");
    $stmtRes->execute([$id_usuario, $event_id]);
    $res = $stmtRes->fetch();

    if (!$res) {
        echo json_encode(['success' => false, 'message' => 'No se encontró ninguna reservación activa para este evento.']);
        exit;
    }

    $id_reservacion = $res['id_reservacion'];

    // Iniciar transacción de base de datos para consistencia
    $pdo->beginTransaction();

    // Obtener los datos de la reservación (zona y cantidad de espacios)
    $stmtResOld = $pdo->prepare("SELECT id_zona, espacios_reservados FROM Reservacion WHERE id_reservacion = ?");
    $stmtResOld->execute([$id_reservacion]);
    $resOld = $stmtResOld->fetch();

    if ($resOld) {
        // 3. Cancelar la reservación
        $stmtCancel = $pdo->prepare("UPDATE Reservacion SET estado_reservacion = 'Cancelado' WHERE id_reservacion = ?");
        $stmtCancel->execute([$id_reservacion]);

        // Lógica Manual de Trigger:
        // a. Cambiar el estado de los tickets individuales en la tabla Boleto a 'cancelado'
        $stmtCancelBoletos = $pdo->prepare("UPDATE Boleto SET estado_boleto = 'cancelado' WHERE id_reservacion = ?");
        $stmtCancelBoletos->execute([$id_reservacion]);

        // b. Liberar los asientos ocupados cambiándolos a 'disponible' en la tabla Asiento
        $stmtGetAsientos = $pdo->prepare("SELECT id_asiento FROM Boleto WHERE id_reservacion = ? AND id_asiento IS NOT NULL");
        $stmtGetAsientos->execute([$id_reservacion]);
        $oldAsientos = $stmtGetAsientos->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($oldAsientos)) {
            $inQuery = implode(',', array_fill(0, count($oldAsientos), '?'));
            $stmtFreeAsientos = $pdo->prepare("UPDATE Asiento SET estado = 'disponible' WHERE id_asiento IN ($inQuery)");
            $stmtFreeAsientos->execute($oldAsientos);
        }

        // c. Devolver los asientos reservados al stock disponible en Zona_Boleto
        $stmtInc = $pdo->prepare("UPDATE Zona_Boleto SET lugares_disponibles = lugares_disponibles + ? WHERE id_zona = ?");
        $stmtInc->execute([$resOld['espacios_reservados'], $resOld['id_zona']]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Reservación cancelada exitosamente y cupos liberados.'
    ]);

} catch (\PDOException $e) {
    error_log('[Agora S1][cancel_reservation.php] Error PDO: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Ocurrió un error interno al cancelar la reservación. Inténtalo nuevamente.'
    ]);
}
?>
