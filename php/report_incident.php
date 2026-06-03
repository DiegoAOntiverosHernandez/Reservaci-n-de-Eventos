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

$event_id = isset($data['event_id']) ? (int)$data['event_id'] : 0;
$asunto = isset($data['asunto']) ? trim($data['asunto']) : '';
$descripcion = isset($data['descripcion']) ? trim($data['descripcion']) : '';
$id_usuario = getCurrentUserId();

if (empty($asunto) || empty($descripcion)) {
    echo json_encode(['success' => false, 'message' => 'El asunto y la descripción son obligatorios.']);
    exit;
}

try {
    // Insertar incidencia en la base de datos
    $stmt = $pdo->prepare("
        INSERT INTO Incidencia (id_usuario, id_evento, asunto, descripcion, estado)
        VALUES (?, ?, ?, ?, 'pendiente')
    ");
    $stmt->execute([
        $id_usuario,
        $event_id > 0 ? $event_id : NULL,
        $asunto,
        $descripcion
    ]);

    // Registrar en la Bitácora de Auditoría
    $stmtBitacora = $pdo->prepare("
        INSERT INTO Bitacora (accion, detalle, id_evento_afectado)
        VALUES ('REPORTE_INCIDENCIA', ?, ?)
    ");
    $stmtBitacora->execute([
        "Usuario ID $id_usuario reportó incidencia: '$asunto'",
        $event_id > 0 ? $event_id : NULL
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Tu incidencia ha sido reportada exitosamente. Nuestro equipo técnico la revisará a la brevedad.'
    ]);

} catch (\Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al registrar la incidencia en el servidor: ' . $e->getMessage()
    ]);
}
?>
