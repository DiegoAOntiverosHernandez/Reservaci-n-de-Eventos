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
$nombre = trim($data['nombre'] ?? '');
$categoria = trim($data['categoria'] ?? '');
$descripcion = trim($data['descripcion'] ?? '');
$fecha = trim($data['fecha'] ?? '');
$hora_inicio = trim($data['hora_inicio'] ?? '');
$hora_cierre_req = trim($data['hora_cierre'] ?? '');
$capacidad = (int)($data['capacidad'] ?? 0);
$ubicacion = trim($data['ubicacion'] ?? 'Recinto Principal Ágora');
$estado = trim($data['estado'] ?? 'activo');
$itinerario = $data['itinerario'] ?? [];
$imagen_base64 = $data['imagen_base64'] ?? null;
$id_organizador = getCurrentUserId();

// Validaciones básicas
if ($event_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de evento inválido.']);
    exit;
}

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
    // 1. Verificar si el evento pertenece al organizador logueado
    $stmt = $pdo->prepare("SELECT id_evento, id_organizador FROM Evento WHERE id_evento = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'El evento solicitado no existe.']);
        exit;
    }

    if ((int)$event['id_organizador'] !== $id_organizador) {
        echo json_encode(['success' => false, 'message' => 'No tienes permisos para modificar este evento.']);
        exit;
    }

    // 2. Mapear categoría a base de datos
    $catName = '';
    $lowerCat = strtolower($categoria);
    if (strpos($lowerCat, 'concierto') !== false) {
        $catName = 'Concierto';
    } elseif (strpos($lowerCat, 'teatro') !== false || strpos($lowerCat, 'obra') !== false || strpos($lowerCat, 'arte') !== false) {
        $catName = 'Obra de teatro';
    } elseif (strpos($lowerCat, 'conferencia') !== false) {
        $catName = 'Conferencia';
    } elseif (strpos($lowerCat, 'festival') !== false) {
        $catName = 'Festival';
    } elseif (strpos($lowerCat, 'social') !== false || strpos($lowerCat, 'boda') !== false || strpos($lowerCat, 'salon') !== false || strpos($lowerCat, 'salón') !== false) {
        $catName = 'Evento social';
    } elseif (strpos($lowerCat, 'deporte') !== false || strpos($lowerCat, 'deportivo') !== false) {
        $catName = 'Evento deportivo';
    } else {
        $catName = ucfirst($lowerCat);
    }

    $stmt = $pdo->prepare("SELECT id_categoria FROM Categoria WHERE nombre_categoria = ?");
    $stmt->execute([$catName]);
    $row = $stmt->fetch();
    if ($row) {
        $id_categoria = $row['id_categoria'];
    } else {
        $stmt = $pdo->prepare("INSERT INTO Categoria (nombre_categoria) VALUES (?)");
        $stmt->execute([$catName]);
        $id_categoria = $pdo->lastInsertId();
    }

    // Asignar Recinto dinámicamente según la categoría
    $id_recinto = 4; // Plaza Pública (General) por defecto
    $lowerCat = strtolower($categoria);
    if (strpos($lowerCat, 'concierto') !== false || strpos($lowerCat, 'deporte') !== false || strpos($lowerCat, 'deportivo') !== false) {
        $id_recinto = 2; // Estadio Olímpico Ágora (estadio)
    } elseif (strpos($lowerCat, 'teatro') !== false || strpos($lowerCat, 'obra') !== false || strpos($lowerCat, 'arte') !== false) {
        $id_recinto = 1; // Teatro Ágora Principal (teatro)
    } elseif (strpos($lowerCat, 'conferencia') !== false) {
        $id_recinto = 3; // Sala de Conferencias Ágora (conferencia)
    }

    // Calcular hora de cierre
    if (!empty($hora_cierre_req)) {
        $hora_cierre = $hora_cierre_req;
    } else {
        $time = strtotime($hora_inicio);
        $hora_cierre = date('H:i:s', $time + 3 * 3600);
    }

    // S2: Subida de imágenes controlada (validar tipo MIME real, límite de 5 MB y permisos 0755)
    $imagen_banner = null;
    if (!empty($imagen_base64)) {
        $parts = explode(',', $imagen_base64, 2);
        $imgData = isset($parts[1]) ? base64_decode($parts[1], true) : false;

        if ($imgData === false) {
            echo json_encode(['success' => false, 'message' => 'Archivo de imagen inválido o corrupto.']);
            exit;
        }

        // Límite estricto de 5 MB (5 * 1024 * 1024 bytes)
        $maxSizeBytes = 5 * 1024 * 1024;
        if (strlen($imgData) > $maxSizeBytes) {
            echo json_encode(['success' => false, 'message' => 'La imagen excede el tamaño máximo permitido de 5 MB.']);
            exit;
        }

        // Verificar el contenido binario real del archivo (evita archivos .php renombrados como imagen)
        $realMime = '';
        if (class_exists('finfo')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $realMime = $finfo->buffer($imgData);
        }
        $imgInfo = @getimagesizefromstring($imgData);
        if (empty($realMime) && $imgInfo && isset($imgInfo['mime'])) {
            $realMime = $imgInfo['mime'];
        }

        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        if (!$imgInfo || !isset($allowedMimes[$realMime]) || strpos($imgData, '<?php') !== false) {
            echo json_encode(['success' => false, 'message' => 'Tipo de archivo no permitido. Solo se aceptan imágenes reales (JPG, PNG, WEBP) de máximo 5 MB.']);
            exit;
        }

        $uploadDir = __DIR__ . '/../Assets/img/uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        } else {
            @chmod($uploadDir, 0755);
        }

        $ext = $allowedMimes[$realMime];
        $fileName = 'event_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $fullPath = $uploadDir . $fileName;
        if (file_put_contents($fullPath, $imgData) === false) {
            echo json_encode(['success' => false, 'message' => 'No se pudo almacenar la imagen del evento.']);
            exit;
        }
        @chmod($fullPath, 0644);
        $imagen_banner = 'Assets/img/uploads/' . $fileName;
    }

    // Iniciar transacción de base de datos
    $pdo->beginTransaction();

    // 3. Actualizar el Evento
    $sql = "UPDATE Evento SET nombre_evento = ?, descripcion = ?, id_categoria = ?, id_recinto = ?, fecha = ?, hora_inicio = ?, hora_cierre = ?, estado = ?, ubicacion = ?";
    $params = [$nombre, $descripcion, $id_categoria, $id_recinto, $fecha, $hora_inicio, $hora_cierre, $estado, $ubicacion];
    
    if ($imagen_banner !== null) {
        $sql .= ", imagen_banner = ?";
        $params[] = $imagen_banner;
    }
    
    $sql .= " WHERE id_evento = ?";
    $params[] = $event_id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // 4. Actualizar secciones de itinerario (eliminar anteriores e insertar las nuevas)
    $stmtDelIt = $pdo->prepare("DELETE FROM Itinerario WHERE id_evento = ?");
    $stmtDelIt->execute([$event_id]);

    if (!empty($itinerario) && is_array($itinerario)) {
        $stmtIt = $pdo->prepare("INSERT INTO Itinerario (id_evento, nombre_seccion, descripcion_seccion, hora_inicio, hora_fin) VALUES (?, ?, 'Sección de cronograma', ?, ?)");
        foreach ($itinerario as $sec) {
            $nombreSec = trim($sec['nombre'] ?? '');
            $horaSec = trim($sec['hora'] ?? '');
            if (!empty($nombreSec) && !empty($horaSec)) {
                $timeSec = strtotime($horaSec);
                $horaFinSec = date('H:i:s', $timeSec + 3600);
                $stmtIt->execute([$event_id, $nombreSec, $horaSec, $horaFinSec]);
            }
        }
    }

    // 5. Actualizar la capacidad en Zona_Boleto
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM Zona_Boleto WHERE id_evento = ? AND nombre_zona IN ('VIP', 'Zona A', 'Zona B', 'Zona C', 'Zona D')");
    $stmtCheck->execute([$event_id]);
    $hasZones = ((int)$stmtCheck->fetchColumn() > 0);

    if ($hasZones) {
        // Redistribuir la nueva capacidad de forma proporcional entre las 5 zonas
        $seatsVIP = max(1, (int)round($capacidad * 0.15));
        $seatsA = max(1, (int)round($capacidad * 0.20));
        $seatsB = max(1, (int)round($capacidad * 0.30));
        $seatsC = max(1, (int)round($capacidad * 0.20));
        $seatsD = $capacidad - ($seatsVIP + $seatsA + $seatsB + $seatsC);
        if ($seatsD <= 0) {
            $seatsD = 1;
        }

        $newSeats = [
            'VIP' => $seatsVIP,
            'Zona A' => $seatsA,
            'Zona B' => $seatsB,
            'Zona C' => $seatsC,
            'Zona D' => $seatsD
        ];

        // Actualizar cada zona manteniendo el precio anterior
        foreach ($newSeats as $zName => $zTotales) {
            // Buscamos la zona para calcular la diferencia de disponibles
            $stmt = $pdo->prepare("SELECT id_zona, lugares_totales, lugares_disponibles FROM Zona_Boleto WHERE id_evento = ? AND nombre_zona = ? LIMIT 1");
            $stmt->execute([$event_id, $zName]);
            $zona = $stmt->fetch();

            if ($zona) {
                $oldTotales = (int)$zona['lugares_totales'];
                $oldDisponibles = (int)$zona['lugares_disponibles'];
                $diferencia = $zTotales - $oldTotales;
                $newDisponibles = max(0, $oldDisponibles + $diferencia);

                $stmtUp = $pdo->prepare("UPDATE Zona_Boleto SET lugares_totales = ?, lugares_disponibles = ? WHERE id_zona = ?");
                $stmtUp->execute([$zTotales, $newDisponibles, $zona['id_zona']]);
            } else {
                $stmtIn = $pdo->prepare("INSERT INTO Zona_Boleto (id_evento, nombre_zona, precio, lugares_totales, lugares_disponibles) VALUES (?, ?, 0.00, ?, ?)");
                $stmtIn->execute([$event_id, $zName, $zTotales, $zTotales]);
            }
        }
    } else {
        // Si no tiene las 5 zonas, actualizar o crear la zona General
        $stmt = $pdo->prepare("SELECT id_zona, lugares_totales, lugares_disponibles FROM Zona_Boleto WHERE id_evento = ? AND nombre_zona = 'General'");
        $stmt->execute([$event_id]);
        $zona = $stmt->fetch();

        if ($zona) {
            $oldTotales = (int)$zona['lugares_totales'];
            $oldDisponibles = (int)$zona['lugares_disponibles'];
            $diferencia = $capacidad - $oldTotales;
            
            $newDisponibles = max(0, $oldDisponibles + $diferencia);

            $stmt = $pdo->prepare("UPDATE Zona_Boleto SET lugares_totales = ?, lugares_disponibles = ? WHERE id_zona = ?");
            $stmt->execute([$capacidad, $newDisponibles, $zona['id_zona']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO Zona_Boleto (id_evento, nombre_zona, precio, lugares_totales, lugares_disponibles) VALUES (?, 'General', 0.00, ?, ?)");
            $stmt->execute([$event_id, $capacidad, $capacidad]);
        }
    }

    // Lógica Manual de Trigger:
    // Calcular el total de asientos en las zonas de este evento y actualizar capacidad_total en Evento
    $stmtSum = $pdo->prepare("SELECT SUM(lugares_totales) FROM Zona_Boleto WHERE id_evento = ?");
    $stmtSum->execute([$event_id]);
    $total_cap = (int)$stmtSum->fetchColumn();

    $stmtUpdateCap = $pdo->prepare("UPDATE Evento SET capacidad_total = ? WHERE id_evento = ?");
    $stmtUpdateCap->execute([$total_cap, $event_id]);

    // S3: Registrar actualización de evento en la Bitácora de Auditoría
    $stmtBitacora = $pdo->prepare("
        INSERT INTO Bitacora (accion, detalle, id_evento_afectado)
        VALUES ('ACTUALIZACION_EVENTO', ?, ?)
    ");
    $stmtBitacora->execute([
        "Organizador ID $id_organizador actualizó el evento '$nombre' (Estado: '$estado', Capacidad: $total_cap)",
        $event_id
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => '¡Evento actualizado exitosamente!'
    ]);

} catch (\Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Agora S1][update_event.php] Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Ocurrió un error interno al actualizar el evento. Inténtalo nuevamente.'
    ]);
}
?>
