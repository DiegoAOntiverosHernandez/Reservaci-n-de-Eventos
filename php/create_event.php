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
    // 1. Mapear categoría a base de datos
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
        // Si no existe, crear la categoría
        $stmt = $pdo->prepare("INSERT INTO Categoria (nombre_categoria) VALUES (?)");
        $stmt->execute([$catName]);
        $id_categoria = $pdo->lastInsertId();
    }

    // 2. Definir imagen de banner por defecto según la categoría
    $imagen_banner = 'Assets/img/img_home.png'; // default
    $lowerCat = strtolower($categoria);
    if (strpos($lowerCat, 'concierto') !== false) {
        $imagen_banner = 'Assets/img/tag_concert.png';
    } elseif (strpos($lowerCat, 'conferencia') !== false) {
        $imagen_banner = 'Assets/img/tag_conf.png';
    } elseif (strpos($lowerCat, 'boda') !== false) {
        $imagen_banner = 'Assets/img/tag_boda.png';
    } elseif (strpos($lowerCat, 'deporte') !== false) {
        $imagen_banner = 'Assets/img/tag_sport.png';
    } elseif (strpos($lowerCat, 'arte') !== false) {
        $imagen_banner = 'Assets/img/tag_art.png';
    } elseif (strpos($lowerCat, 'salon') !== false || strpos($lowerCat, 'salón') !== false) {
        $imagen_banner = 'Assets/img/tag_salon.png';
    }

    // 3. Asignar Recinto dinámicamente según la categoría
    $id_recinto = 4; // Plaza Pública (General) por defecto
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
