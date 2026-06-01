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
$capacidad = (int)($data['capacidad'] ?? 0);
$ubicacion = trim($data['ubicacion'] ?? 'Recinto Principal Ágora');
$itinerario = $data['itinerario'] ?? [];
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

if ($capacidad <= 0 || $capacidad > 30) {
    echo json_encode(['success' => false, 'message' => 'La capacidad debe ser un número entre 1 y 30.']);
    exit;
}

if (empty($ubicacion)) {
    echo json_encode(['success' => false, 'message' => 'La ubicación del evento es obligatoria.']);
    exit;
}

try {
    // 1. Mapear categoría a base de datos
    $catName = ucfirst(strtolower($categoria));
    if ($catName === 'Deporte') {
        $catName = 'Evento deportivo';
    } elseif ($catName === 'Arte') {
        $catName = 'Obra de teatro';
    } elseif ($catName === 'Boda' || $catName === 'Salon' || $catName === 'Salón') {
        $catName = 'Evento social';
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

    // 3. Asignar Recinto (opcional, por defecto NULL o 4 - Plaza Pública del Sol)
    // El script tiene 'Plaza Pública del Sol' en la posición 4
    $id_recinto = 4;

    // Calcular hora de cierre (por defecto 3 horas después del inicio)
    $time = strtotime($hora_inicio);
    $hora_cierre = date('H:i:s', $time + 3 * 3600);

    // Iniciar transacción de base de datos para inserción segura de múltiples tablas
    $pdo->beginTransaction();

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

    // 5. Insertar Zona de Boleto (General) con precio gratuito
    // Al insertar la zona, el trigger trg_actualizar_capacidad_insert actualizará automáticamente capacidad_total en la tabla Evento
    $stmt = $pdo->prepare("INSERT INTO Zona_Boleto (id_evento, nombre_zona, precio, lugares_totales, lugares_disponibles) VALUES (?, 'General', 0.00, ?, ?)");
    $stmt->execute([$id_evento, $capacidad, $capacidad]);

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

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => '¡Evento publicado exitosamente!',
        'id_evento' => $id_evento
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
