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

$incident_id = (int)($data['incident_id'] ?? 0);
$id_organizador = getCurrentUserId();
$user_role = $_SESSION['usuario_rol'] ?? 'cliente';

if ($incident_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de incidencia inválido.']);
    exit;
}

try {
    // 1. Obtener detalles de la incidencia para validar propiedad
    $stmt = $pdo->prepare("
        SELECT i.id_incidencia, i.id_evento, i.asunto, e.id_organizador 
        FROM Incidencia i
        LEFT JOIN Evento e ON i.id_evento = e.id_evento
        WHERE i.id_incidencia = ?
    ");
    $stmt->execute([$incident_id]);
    $incident = $stmt->fetch();

    if (!$incident) {
        echo json_encode(['success' => false, 'message' => 'La incidencia solicitada no existe.']);
        exit;
    }

    // 2. Control de accesos: Solo el administrador o el organizador del evento pueden eliminar incidencias
    $autorizado = false;
    if ($user_role === 'administrador') {
        $autorizado = true;
    } elseif ($user_role === 'organizador' && $incident['id_organizador'] !== null && (int)$incident['id_organizador'] === $id_organizador) {
        $autorizado = true;
    }

    if (!$autorizado) {
        echo json_encode([
            'success' => false,
            'message' => 'No tienes permisos suficientes para eliminar esta incidencia.'
        ]);
        exit;
    }

    // 3. Eliminar la incidencia
    $stmtDelete = $pdo->prepare("DELETE FROM Incidencia WHERE id_incidencia = ?");
    $stmtDelete->execute([$incident_id]);

    // 4. Registrar en la Bitácora de Auditoría
    $stmtBitacora = $pdo->prepare("
        INSERT INTO Bitacora (accion, detalle, id_evento_afectado)
        VALUES ('ELIMINACION_INCIDENCIA', ?, ?)
    ");
    $stmtBitacora->execute([
        "Incidencia ID $incident_id ('" . $incident['asunto'] . "') fue eliminada por usuario ID $id_organizador (" . $user_role . ")",
        $incident['id_evento']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'La incidencia ha sido eliminada exitosamente.'
    ]);

} catch (\Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al intentar eliminar la incidencia en el servidor: ' . $e->getMessage()
    ]);
}
?>
