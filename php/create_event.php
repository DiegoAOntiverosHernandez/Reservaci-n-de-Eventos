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

$nombre = trim($data['nombre'] ?? '');
$categoria = trim($data['categoria'] ?? '');
$descripcion = trim($data['descripcion'] ?? '');
$fecha = trim($data['fecha'] ?? '');
$hora_inicio = trim($data['hora_inicio'] ?? '');
$hora_cierre_req = trim($data['hora_cierre'] ?? '');
$capacidad = (int)($data['capacidad'] ?? 0);
$ubicacion = trim($data['ubicacion'] ?? 'Recinto Principal Ágora');
$itinerario = $data['itinerario'] ?? [];
$imagen_base64 = $data['imagen_base64'] ?? null;
$id_organizador = getCurrentUserId();

// Validaciones básicas
if (strlen($nombre) < 5 || strlen($nombre) > 33) {
    echo json_encode(['success' => false, 'message' => 'El nombre debe tener entre 5 y 33 caracteres.']);
    exit;
}

if (empty($categoria)) {
    echo json_encode(['success' => false, 'message' => 'La categoría es obligatoria.']);
    exit;
}

if (empty($fecha)) {
    echo json_encode(['success' => false, 'message' => 'La fecha del evento es obligatoria.']);
    exit;
}

if (empty($hora_inicio)) {
    echo json_encode(['success' => false, 'message' => 'La hora de inicio es obligatoria.']);
    exit;
}

if ($capacidad <= 0 || $capacidad > 500) {
    echo json_encode(['success' => false, 'message' => 'La capacidad debe ser un número entre 1 y 500.']);
    exit;
}

if (empty($ubicacion)) {
    echo json_encode(['success' => false, 'message' => 'La ubicación del evento es obligatoria.']);
    exit;
}

try {
    // 1-3. M1: Resolver categoría, recinto e imagen por defecto desde el módulo compartido
    $resolved = resolveEventCategoryAndVenue($pdo, $categoria);
    $id_categoria  = $resolved['id_categoria'];
    $id_recinto    = $resolved['id_recinto'];
    $imagen_banner = $resolved['default_banner'];

    // Calcular hora de cierre
    if (!empty($hora_cierre_req)) {
        $hora_cierre = $hora_cierre_req;
    } else {
        $time = strtotime($hora_inicio);
        $hora_cierre = date('H:i:s', $time + 3 * 3600);
    }

    // S2 + M1: Subida de imágenes controlada mediante función compartida
    if (!empty($imagen_base64)) {
        try {
            $imagen_banner = validateAndSaveEventImage($imagen_base64);
        } catch (\InvalidArgumentException $imgEx) {
            echo json_encode(['success' => false, 'message' => $imgEx->getMessage()]);
            exit;
        }
    }

    // Iniciar transacción de base de datos para inserción segura de múltiples tablas
    $pdo->beginTransaction();

    // Verificar si es su primer evento antes de insertarlo
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM Evento WHERE id_organizador = ?");
    $stmtCount->execute([$id_organizador]);
    $eventCount = (int)$stmtCount->fetchColumn();
    $isFirstEvent = ($eventCount === 0);

    // 4. Insertar el Evento
    $stmt = $pdo->prepare("INSERT INTO Evento (nombre_evento, descripcion, imagen_banner, ubicacion, id_recinto, fecha, hora_inicio, hora_cierre, capacidad_total, estado, id_organizador, id_categoria) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 'activo', ?, ?)");
    $stmt->execute([
        $nombre,
        $descripcion,
        $imagen_banner,
        $ubicacion,
        $id_recinto,
        $fecha,
        $hora_inicio,
        $hora_cierre,
        $id_organizador,
        $id_categoria
    ]);
    
    $id_evento = $pdo->lastInsertId();

    // 5. El Administrador establecerá las 5 zonas en el Paso 2 (organizar_recinto.php).
    // No insertamos la zona general predeterminada.

    // 6. Insertar secciones de itinerario si las hay
    if (!empty($itinerario) && is_array($itinerario)) {
        $stmtIt = $pdo->prepare("INSERT INTO Itinerario (id_evento, nombre_seccion, descripcion_seccion, hora_inicio, hora_fin) VALUES (?, ?, 'Sección de cronograma', ?, ?)");
        foreach ($itinerario as $sec) {
            $nombreSec = trim($sec['nombre'] ?? '');
            $horaSec = trim($sec['hora'] ?? '');
            if (!empty($nombreSec) && !empty($horaSec)) {
                // Calcular hora fin como 1 hora después del inicio de la sección por defecto
                $timeSec = strtotime($horaSec);
                $horaFinSec = date('H:i:s', $timeSec + 3600);
                $stmtIt->execute([$id_evento, $nombreSec, $horaSec, $horaFinSec]);
            }
        }
    }

    // Si es su primer evento, promover su rol a 'organizador' en la BD y actualizar la sesión activa
    if ($isFirstEvent) {
        $stmtUpdateRole = $pdo->prepare("UPDATE Usuario SET rol = 'organizador' WHERE id_usuario = ?");
        $stmtUpdateRole->execute([$id_organizador]);
        $_SESSION['usuario_rol'] = 'organizador';
    }

    // S3: Registrar creación de evento en la Bitácora de Auditoría
    $stmtBitacora = $pdo->prepare("
        INSERT INTO Bitacora (accion, detalle, id_evento_afectado)
        VALUES ('CREACION_EVENTO', ?, ?)
    ");
    $stmtBitacora->execute([
        "Organizador ID $id_organizador publicó el evento '$nombre' (Categoría ID $id_categoria, Recinto ID $id_recinto)",
        $id_evento
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => '¡Evento publicado exitosamente!',
        'id_evento' => $id_evento,
        'is_first_event' => $isFirstEvent
    ]);

} catch (\Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Agora S1][create_event.php] Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Ocurrió un error interno al publicar el evento. Inténtalo nuevamente.'
    ]);
}
?>
