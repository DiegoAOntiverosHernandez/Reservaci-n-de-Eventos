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
$asientos_str = trim($data['asientos'] ?? '');
$id_usuario = getCurrentUserId();

if ($event_id <= 0 || $espacios <= 0) {
    echo json_encode(['success' => false, 'message' => 'Parámetros de reservación inválidos.']);
    exit;
}

try {
    // 1. Obtener la zona de boletos específica para este evento y nombre de zona
    $nombre_zona = 'General';
    if (!empty($asientos_str)) {
        $firstSeat = explode(',', $asientos_str)[0];
        $prefix = substr(trim($firstSeat), 0, 1);
        
        if ($prefix === 'V') $nombre_zona = 'VIP';
        elseif ($prefix === 'A') $nombre_zona = 'Zona A';
        elseif ($prefix === 'B') $nombre_zona = 'Zona B';
        elseif ($prefix === 'C') $nombre_zona = 'Zona C';
        elseif ($prefix === 'D') $nombre_zona = 'Zona D';
    }

    $stmt = $pdo->prepare("SELECT id_zona, precio, lugares_disponibles FROM Zona_Boleto WHERE id_evento = ? AND nombre_zona = ? LIMIT 1");
    $stmt->execute([$event_id, $nombre_zona]);
    $zona = $stmt->fetch();

    if (!$zona) {
        // Fallback si la zona específica no existe, obtener la primera disponible
        $stmt = $pdo->prepare("SELECT id_zona, precio, lugares_disponibles FROM Zona_Boleto WHERE id_evento = ? LIMIT 1");
        $stmt->execute([$event_id]);
        $zona = $stmt->fetch();
    }

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
            // Obtener espacios de la reservación anterior para restaurarlos en Zona_Boleto
            $stmtResOld = $pdo->prepare("SELECT id_zona, espacios_reservados FROM Reservacion WHERE id_reservacion = ?");
            $stmtResOld->execute([$prevRes['id_reservacion']]);
            $resOld = $stmtResOld->fetch();
            
            if ($resOld) {
                // Actualizar la reserva vieja a 'Cancelado'
                $stmtCancel = $pdo->prepare("UPDATE Reservacion SET estado_reservacion = 'Cancelado' WHERE id_reservacion = ?");
                $stmtCancel->execute([$prevRes['id_reservacion']]);
                
                // Cancelar boletos viejos en la tabla Boleto
                $stmtCancelBoletos = $pdo->prepare("UPDATE Boleto SET estado_boleto = 'cancelado' WHERE id_reservacion = ?");
                $stmtCancelBoletos->execute([$prevRes['id_reservacion']]);
                
                // Liberar asientos viejos asociados (cambiarlos a 'disponible')
                $stmtGetAsientos = $pdo->prepare("SELECT id_asiento FROM Boleto WHERE id_reservacion = ? AND id_asiento IS NOT NULL");
                $stmtGetAsientos->execute([$prevRes['id_reservacion']]);
                $oldAsientos = $stmtGetAsientos->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($oldAsientos)) {
                    $inQuery = implode(',', array_fill(0, count($oldAsientos), '?'));
                    $stmtFreeAsientos = $pdo->prepare("UPDATE Asiento SET estado = 'disponible' WHERE id_asiento IN ($inQuery)");
                    $stmtFreeAsientos->execute($oldAsientos);
                }
                
                // Sumar los lugares devueltos al stock disponible en Zona_Boleto
                $stmtInc = $pdo->prepare("UPDATE Zona_Boleto SET lugares_disponibles = lugares_disponibles + ? WHERE id_zona = ?");
                $stmtInc->execute([$resOld['espacios_reservados'], $resOld['id_zona']]);
            }
        }
    }

    // 3. F1: Bloquear la fila de la zona (FOR UPDATE) para verificar stock disponible sin condición de carrera
    $stmtStock = $pdo->prepare("SELECT lugares_disponibles FROM Zona_Boleto WHERE id_zona = ? FOR UPDATE");
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
    $stmtRes = $pdo->prepare("INSERT INTO Reservacion (id_usuario, id_zona, id_transaccion, espacios_reservados, estado_reservacion) VALUES (?, ?, ?, ?, 'Confirmado')");
    $stmtRes->execute([
        $id_usuario,
        $id_zona,
        $id_transaccion,
        $espacios
    ]);

    $id_reservacion = $pdo->lastInsertId();

    // Lógica Manual de Trigger:
    // a. Descontar los espacios de Zona_Boleto.lugares_disponibles
    $stmtDec = $pdo->prepare("UPDATE Zona_Boleto SET lugares_disponibles = lugares_disponibles - ? WHERE id_zona = ?");
    $stmtDec->execute([$espacios, $id_zona]);

    // b. Generar los tickets individuales asociados en la tabla Boleto con códigos QR únicos.
    $stmtInsertBoleto = $pdo->prepare("INSERT INTO Boleto (id_reservacion, codigo_unico, estado_boleto) VALUES (?, ?, 'valido')");
    for ($i = 0; $i < $espacios; $i++) {
        $codigo_unico = 'QR-' . $id_reservacion . '-' . time() . '-' . rand(100000, 999999);
        $stmtInsertBoleto->execute([$id_reservacion, $codigo_unico]);
    }

    // 5b. Vincular asientos seleccionados reales a los boletos generados automáticamente
    if (!empty($asientos_str)) {
        $seats = array_map('trim', explode(',', $asientos_str));
        
        // Obtener los boletos recién insertados por el trigger
        $stmtBoleto = $pdo->prepare("SELECT id_boleto FROM Boleto WHERE id_reservacion = ? ORDER BY id_boleto ASC");
        $stmtBoleto->execute([$id_reservacion]);
        $boletos = $stmtBoleto->fetchAll(PDO::FETCH_ASSOC);

        foreach ($seats as $index => $seat) {
            if (empty($seat)) continue;
            
            // Separar fila (letra) y número
            $fila = substr($seat, 0, 1);
            $numero = substr($seat, 1);

            // F1: Bloquear asiento con FOR UPDATE y rechazar compra si ya fue ocupado por otro usuario
            $stmtCheck = $pdo->prepare("SELECT id_asiento, estado FROM Asiento WHERE id_zona = ? AND fila = ? AND numero = ? LIMIT 1 FOR UPDATE");
            $stmtCheck->execute([$id_zona, $fila, $numero]);
            $asiento = $stmtCheck->fetch();

            if ($asiento) {
                if ($asiento['estado'] === 'ocupado' || $asiento['estado'] === 'reservado') {
                    $pdo->rollBack();
                    echo json_encode([
                        'success' => false,
                        'message' => "El asiento $seat ya fue ocupado por otra persona. Por favor, elige otro asiento disponible."
                    ]);
                    exit;
                }
                $id_asiento = $asiento['id_asiento'];
                $stmtUpdateAsiento = $pdo->prepare("UPDATE Asiento SET estado = 'ocupado' WHERE id_asiento = ?");
                $stmtUpdateAsiento->execute([$id_asiento]);
            } else {
                $stmtInsertAsiento = $pdo->prepare("INSERT INTO Asiento (id_zona, fila, numero, estado) VALUES (?, ?, ?, 'ocupado')");
                $stmtInsertAsiento->execute([$id_zona, $fila, $numero]);
                $id_asiento = $pdo->lastInsertId();
            }

            // Si hay un boleto generado disponible, vincularlo al asiento
            if (isset($boletos[$index])) {
                $id_boleto = $boletos[$index]['id_boleto'];
                $stmtLink = $pdo->prepare("UPDATE Boleto SET id_asiento = ? WHERE id_boleto = ?");
                $stmtLink->execute([$id_asiento, $id_boleto]);
            }
        }
    }

    // S3: Registrar creación/actualización de reservación en la Bitácora de Auditoría
    $accionBitacora = $current_slots > 0 ? 'ACTUALIZACION_RESERVACION' : 'CREACION_RESERVACION';
    $stmtBitacora = $pdo->prepare("
        INSERT INTO Bitacora (accion, detalle, id_evento_afectado)
        VALUES (?, ?, ?)
    ");
    $stmtBitacora->execute([
        $accionBitacora,
        "Usuario ID $id_usuario reservó $espacios lugar(es) en zona '$nombre_zona' (Reservación ID $id_reservacion, Asientos: " . ($asientos_str ?: 'General') . ")",
        $event_id
    ]);

    $pdo->commit();

    $message = $current_slots > 0 ? '¡Tu reservación ha sido actualizada con éxito!' : '¡Reservación realizada con éxito!';
    echo json_encode([
        'success' => true,
        'message' => $message,
        'id_reservacion' => $id_reservacion
    ]);

} catch (\Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Agora S1][make_reservation.php] Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Ocurrió un error interno al procesar la reservación. Inténtalo nuevamente.'
    ]);
}
?>
