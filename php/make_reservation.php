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
$espacios = (int)($data['espacios'] ?? 0);
$current_slots = (int)($data['current_slots'] ?? 0);
$id_usuario = getCurrentUserId();

if ($event_id <= 0 || $espacios <= 0) {
    echo json_encode(['success' => false, 'message' => 'Parámetros de reservación inválidos.']);
    exit;
}

try {
    // 1. Obtener la zona de boletos del evento (típicamente General)
    $stmt = $pdo->prepare("SELECT id_zona, precio, lugares_disponibles FROM Zona_Boleto WHERE id_evento = ? LIMIT 1");
    $stmt->execute([$event_id]);
    $zona = $stmt->fetch();

    if (!$zona) {
        echo json_encode(['success' => false, 'message' => 'El evento seleccionado no cuenta con zonas de boletaje activas.']);
        exit;
    }

    $id_zona = $zona['id_zona'];
    $precio_unitario = (float)$zona['precio'];

    // Iniciar transacción de base de datos para consistencia
    $pdo->beginTransaction();

    // 2. Si es una actualización (current_slots > 0), cancelar la reservación previa primero
    if ($current_slots > 0) {
        $stmtPrev = $pdo->prepare("SELECT id_reservacion FROM Reservacion WHERE id_usuario = ? AND id_zona = ? AND estado_reservacion = 'Confirmado' LIMIT 1");
        $stmtPrev->execute([$id_usuario, $id_zona]);
        $prevRes = $stmtPrev->fetch();

        if ($prevRes) {
            // Actualizar la reserva vieja a 'Cancelado'.
            // Esto activará el Trigger trg_restaurar_lugares_cancelacion en MySQL que sumará
            // los lugares previos devueltos al stock disponible.
            $stmtCancel = $pdo->prepare("UPDATE Reservacion SET estado_reservacion = 'Cancelado' WHERE id_reservacion = ?");
            $stmtCancel->execute([$prevRes['id_reservacion']]);
        }
    }

    // 3. Volver a consultar la zona para verificar stock disponible (luego de la potencial restauración)
    $stmtStock = $pdo->prepare("SELECT lugares_disponibles FROM Zona_Boleto WHERE id_zona = ?");
    $stmtStock->execute([$id_zona]);
    $zonaActualizada = $stmtStock->fetch();
    $disponibles = (int)$zonaActualizada['lugares_disponibles'];

    if ($espacios > $disponibles) {
        // Cancelamos la transacción si no hay suficiente espacio
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => "No hay suficientes cupos disponibles en el evento. Cupos libres actuales: $disponibles."
        ]);
        exit;
    }

    // 4. Crear Transacción (en este mockup los eventos son gratuitos, pero simulamos la pasarela)
    $monto_total = $precio_unitario * $espacios;
    $metodo_pago = $monto_total > 0 ? 'tarjeta' : 'gratis';
    
    $stmtTrans = $pdo->prepare("INSERT INTO Transaccion (id_usuario, id_evento, monto_total, metodo_pago, referencia_pago, digitos_tarjeta, estado_pago) VALUES (?, ?, ?, ?, ?, ?, 'completado')");
    $referencia = $monto_total > 0 ? 'REF-' . uniqid() : NULL;
    $digitos = $monto_total > 0 ? '4242' : NULL;
    $stmtTrans->execute([
        $id_usuario,
        $event_id,
        $monto_total,
        $metodo_pago,
        $referencia,
        $digitos
    ]);
    $id_transaccion = $pdo->lastInsertId();

    // 5. Crear la nueva Reservación
    // Al insertar, el Trigger trg_descontar_lugares_reservacion automáticamente:
    // a. Descontará los espacios de Zona_Boleto.lugares_disponibles
    // b. Generará los tickets individuales asociados en la tabla Boleto con códigos QR únicos.
    $stmtRes = $pdo->prepare("INSERT INTO Reservacion (id_usuario, id_zona, id_transaccion, espacios_reservados, estado_reservacion) VALUES (?, ?, ?, ?, 'Confirmado')");
    $stmtRes->execute([
        $id_usuario,
        $id_zona,
        $id_transaccion,
        $espacios
    ]);

    $pdo->commit();

    $message = $current_slots > 0 ? '¡Tu reservación ha sido actualizada con éxito!' : '¡Reservación realizada con éxito!';
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);

} catch (\Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Error al registrar la reservación en el servidor: ' . $e->getMessage()
    ]);
}
?>
