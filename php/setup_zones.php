<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/event_helpers.php';

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
$capacidad = (int)($data['capacidad'] ?? 0);
$precios = $data['precios'] ?? [];

if ($event_id <= 0 || $capacidad <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de evento o capacidad inválidos.']);
    exit;
}

try {
    // Verificar que el evento exista y sea del organizador actual
    $stmt = $pdo->prepare("SELECT id_evento FROM Evento WHERE id_evento = ? AND id_organizador = ? LIMIT 1");
    $stmt->execute([$event_id, getCurrentUserId()]);
    $event = $stmt->fetch();

    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'El evento solicitado no existe o no tienes permisos para editarlo.']);
        exit;
    }

    // Iniciar transacción de base de datos
    $pdo->beginTransaction();

    // Eliminar posibles zonas previas creadas (por si acaso re-publica)
    $stmtDel = $pdo->prepare("DELETE FROM Zona_Boleto WHERE id_evento = ?");
    $stmtDel->execute([$event_id]);

    // M1: Distribuir capacidad de forma equitativa y exacta entre las 5 zonas mediante función compartida
    $dist = calculateFiveZonesDistribution($capacidad);
    $zonas = [];
    foreach ($dist as $zNombre => $zCupos) {
        $zonas[] = [
            'nombre' => $zNombre,
            'precio' => (float)($precios[$zNombre] ?? 0),
            'cupos'  => $zCupos
        ];
    }

    $stmtInsert = $pdo->prepare("
        INSERT INTO Zona_Boleto (id_evento, nombre_zona, precio, lugares_totales, lugares_disponibles)
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($zonas as $z) {
        $stmtInsert->execute([
            $event_id,
            $z['nombre'],
            $z['precio'],
            $z['cupos'],
            $z['cupos']
        ]);
    }

    // Lógica Manual de Trigger:
    // Calcular el total de asientos en las zonas de este evento y actualizar capacidad_total en Evento
    $stmtSum = $pdo->prepare("SELECT SUM(lugares_totales) FROM Zona_Boleto WHERE id_evento = ?");
    $stmtSum->execute([$event_id]);
    $total_cap = (int)$stmtSum->fetchColumn();

    $stmtUpdateCap = $pdo->prepare("UPDATE Evento SET capacidad_total = ? WHERE id_evento = ?");
    $stmtUpdateCap->execute([$total_cap, $event_id]);

    // S3: Registrar configuración de zonas del recinto en la Bitácora de Auditoría
    $id_organizador = getCurrentUserId();
    $stmtBitacora = $pdo->prepare("
        INSERT INTO Bitacora (accion, detalle, id_evento_afectado)
        VALUES ('CONFIGURACION_ZONAS', ?, ?)
    ");
    $stmtBitacora->execute([
        "Organizador ID $id_organizador configuró las 5 zonas del recinto (capacidad total: $total_cap)",
        $event_id
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => '¡Las zonas y precios del recinto se han configurado con éxito!'
    ]);

} catch (\Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Agora S1][setup_zones.php] Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Ocurrió un error interno al configurar el recinto. Inténtalo nuevamente.'
    ]);
}
?>
