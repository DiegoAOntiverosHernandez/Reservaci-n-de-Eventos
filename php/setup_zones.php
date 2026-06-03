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

    // Distribuir capacidad de forma equitativa y exacta entre las 5 zonas
    // VIP: 15%, Zona A: 20%, Zona B: 30%, Zona C: 20%, Zona D: 15%
    $seatsVIP = max(1, (int)round($capacidad * 0.15));
    $seatsA = max(1, (int)round($capacidad * 0.20));
    $seatsB = max(1, (int)round($capacidad * 0.30));
    $seatsC = max(1, (int)round($capacidad * 0.20));
    $seatsD = $capacidad - ($seatsVIP + $seatsA + $seatsB + $seatsC);
    
    if ($seatsD <= 0) {
        $seatsD = 1;
    }

    $zonas = [
        ['nombre' => 'VIP', 'precio' => (float)($precios['VIP'] ?? 0), 'cupos' => $seatsVIP],
        ['nombre' => 'Zona A', 'precio' => (float)($precios['Zona A'] ?? 0), 'cupos' => $seatsA],
        ['nombre' => 'Zona B', 'precio' => (float)($precios['Zona B'] ?? 0), 'cupos' => $seatsB],
        ['nombre' => 'Zona C', 'precio' => (float)($precios['Zona C'] ?? 0), 'cupos' => $seatsC],
        ['nombre' => 'Zona D', 'precio' => (float)($precios['Zona D'] ?? 0), 'cupos' => $seatsD]
    ];

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

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => '¡Las zonas y precios del recinto se han configurado con éxito!'
    ]);

} catch (\Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Error al configurar el recinto en el servidor: ' . $e->getMessage()
    ]);
}
?>
