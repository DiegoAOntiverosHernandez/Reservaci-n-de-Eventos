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
$categoria = $data['categoria'] ?? ''; // Puede ser ID entero o nombre de la categoría
$descripcion = trim($data['descripcion'] ?? '');
$fecha = trim($data['fecha'] ?? '');
$hora_inicio = trim($data['hora_inicio'] ?? '');
$capacidad = (int)($data['capacidad'] ?? 0);
$estado = trim($data['estado'] ?? 'activo');
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

    // 2. Resolver Categoria (si es entero, usar directamente; si es cadena, buscar o crear)
    $id_categoria = null;
    if (is_numeric($categoria)) {
        $id_categoria = (int)$categoria;
    } else {
        $catName = ucfirst(strtolower(trim($categoria)));
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
            $stmt = $pdo->prepare("INSERT INTO Categoria (nombre_categoria) VALUES (?)");
            $stmt->execute([$catName]);
            $id_categoria = $pdo->lastInsertId();
        }
    }

    // Calcular hora de cierre (por defecto 3 horas después del inicio)
    $time = strtotime($hora_inicio);
    $hora_cierre = date('H:i:s', $time + 3 * 3600);

    // Iniciar transacción
    $pdo->beginTransaction();

    // 3. Actualizar Evento
    $stmt = $pdo->prepare("UPDATE Evento SET nombre_evento = ?, descripcion = ?, id_categoria = ?, fecha = ?, hora_inicio = ?, hora_cierre = ?, estado = ? WHERE id_evento = ?");
    $stmt->execute([
        $nombre,
        $descripcion,
        $id_categoria,
        $fecha,
        $hora_inicio,
        $hora_cierre,
        $estado,
        $event_id
    ]);

    // 4. Actualizar la capacidad en Zona_Boleto (General)
    // Buscamos cuántos lugares totales había antes para calcular el cambio
    $stmt = $pdo->prepare("SELECT id_zona, lugares_totales, lugares_disponibles FROM Zona_Boleto WHERE id_evento = ? AND nombre_zona = 'General'");
    $stmt->execute([$event_id]);
    $zona = $stmt->fetch();

    if ($zona) {
        $oldTotales = (int)$zona['lugares_totales'];
        $oldDisponibles = (int)$zona['lugares_disponibles'];
        $diferencia = $capacidad - $oldTotales;
        
        $newDisponibles = $oldDisponibles + $diferencia;
        if ($newDisponibles < 0) {
            $newDisponibles = 0; // Evitar que sea menor a cero
        }

        $stmt = $pdo->prepare("UPDATE Zona_Boleto SET lugares_totales = ?, lugares_disponibles = ? WHERE id_zona = ?");
        $stmt->execute([$capacidad, $newDisponibles, $zona['id_zona']]);
    } else {
        // Por si acaso no existe, crear la zona General
        $stmt = $pdo->prepare("INSERT INTO Zona_Boleto (id_evento, nombre_zona, precio, lugares_totales, lugares_disponibles) VALUES (?, 'General', 0.00, ?, ?)");
        $stmt->execute([$event_id, $capacidad, $capacidad]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => '¡Evento actualizado exitosamente!'
    ]);

} catch (\Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar el evento en el servidor: ' . $e->getMessage()
    ]);
}
?>
