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

    // Si hay una imagen en base64 cargada, la guardamos
    if (!empty($imagen_base64) && strpos($imagen_base64, 'data:image') === 0) {
        try {
            $parts = explode(',', $imagen_base64);
            $imgData = base64_decode($parts[1]);
            $uploadDir = __DIR__ . '/../Assets/img/uploads/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileName = 'event_' . uniqid() . '.png';
            file_put_contents($uploadDir . $fileName, $imgData);
            $imagen_banner = 'Assets/img/uploads/' . $fileName;
        } catch (\Exception $exImg) {
            // Ignorar y mantener imagen por defecto si falla
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
    echo json_encode([
        'success' => false,
        'message' => 'Error al crear el evento en el servidor: ' . $e->getMessage()
    ]);
}
?>
