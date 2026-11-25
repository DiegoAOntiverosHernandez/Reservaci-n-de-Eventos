<?php
/**
 * Sistema Agora - Bloqueo en Tiempo Real de Asientos en Selección (F1 - Prevención de Doble Selección/Venta)
 *
 * Permite que cuando un usuario ("Susana") selecciona un asiento en el mapa interactivo,
 * dicho asiento quede bloqueado temporalmente en la base de datos (estado 'Bloqueado')
 * para cualquier otra ventana o usuario ("Pepe") en tiempo real antes de llegar al pago.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/auth.php';

requireLogin();

$id_usuario = getCurrentUserId();

try {
    // Crear tabla de bloqueos temporales atómicos si no existe
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS Bloqueo_Asiento (
            id_evento INT NOT NULL,
            codigo_asiento VARCHAR(20) NOT NULL,
            token_ventana VARCHAR(120) NOT NULL,
            id_usuario INT NOT NULL,
            fecha_expiracion DATETIME NOT NULL,
            PRIMARY KEY (id_evento, codigo_asiento),
            INDEX idx_expiracion (fecha_expiracion)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Limpiar bloqueos expirados (> 5 minutos sin completar pago)
    $pdo->exec("DELETE FROM Bloqueo_Asiento WHERE fecha_expiracion < NOW()");

    $method = $_SERVER['REQUEST_METHOD'];
    $input = ($method === 'POST') ? (json_decode(file_get_contents('php://input'), true) ?: []) : $_GET;

    $action   = trim($input['action'] ?? 'status');
    $event_id = (int)($input['event_id'] ?? 0);
    $token    = trim($input['token'] ?? '');
    $seat_id  = trim($input['seat_id'] ?? '');

    if ($event_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de evento inválido.']);
        exit;
    }

    // Helper para obtener asientos vendidos y asientos bloqueados por otras ventanas
    $getMapStatus = function () use ($pdo, $event_id, $token) {
        // 1. Asientos ya vendidos (pagados)
        $stmtSold = $pdo->prepare("
            SELECT a.fila, a.numero
            FROM Boleto b
            JOIN Asiento a ON b.id_asiento = a.id_asiento
            JOIN Zona_Boleto z ON a.id_zona = z.id_zona
            WHERE z.id_evento = ? AND b.estado_boleto = 'valido'
        ");
        $stmtSold->execute([$event_id]);
        $sold = [];
        foreach ($stmtSold->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $sold[] = trim($s['fila']) . trim($s['numero']);
        }

        // 2. Asientos bloqueados temporalmente en selección por OTRAS ventanas/usuarios
        $stmtLocked = $pdo->prepare("
            SELECT codigo_asiento, token_ventana
            FROM Bloqueo_Asiento
            WHERE id_evento = ? AND fecha_expiracion >= NOW()
        ");
        $stmtLocked->execute([$event_id]);
        $blockedByOthers = [];
        $lockedByMe = [];
        foreach ($stmtLocked->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($token !== '' && $row['token_ventana'] === $token) {
                $lockedByMe[] = $row['codigo_asiento'];
            } else {
                $blockedByOthers[] = $row['codigo_asiento'];
            }
        }

        return [
            'sold'    => array_values(array_unique($sold)),
            'blocked' => array_values(array_unique($blockedByOthers)),
            'mine'    => array_values(array_unique($lockedByMe)),
        ];
    };

    if ($action === 'status') {
        // Renovar vigencia de los asientos de esta ventana activa
        if ($token !== '') {
            $stmtRenew = $pdo->prepare("
                UPDATE Bloqueo_Asiento
                SET fecha_expiracion = DATE_ADD(NOW(), INTERVAL 5 MINUTE)
                WHERE id_evento = ? AND token_ventana = ?
            ");
            $stmtRenew->execute([$event_id, $token]);
        }
        $status = $getMapStatus();
        echo json_encode(['success' => true] + $status);
        exit;
    }

    if ($token === '') {
        echo json_encode(['success' => false, 'message' => 'Token de sesión de ventana requerido.']);
        exit;
    }

    if ($action === 'lock') {
        if ($seat_id === '') {
            echo json_encode(['success' => false, 'message' => 'Código de asiento requerido.']);
            exit;
        }

        $pdo->beginTransaction();

        // 1. Verificar que el asiento no esté ya vendido en Boleto/Asiento
        $fila   = substr($seat_id, 0, 1);
        $numero = substr($seat_id, 1);
        $stmtSoldCheck = $pdo->prepare("
            SELECT b.id_boleto
            FROM Boleto b
            JOIN Asiento a ON b.id_asiento = a.id_asiento
            JOIN Zona_Boleto z ON a.id_zona = z.id_zona
            WHERE z.id_evento = ? AND a.fila = ? AND a.numero = ? AND b.estado_boleto = 'valido'
            LIMIT 1 FOR UPDATE
        ");
        $stmtSoldCheck->execute([$event_id, $fila, $numero]);
        if ($stmtSoldCheck->fetch()) {
            $pdo->rollBack();
            $status = $getMapStatus();
            echo json_encode([
                'success' => false,
                'message' => "El asiento $seat_id ya fue vendido a otra persona."
            ] + $status);
            exit;
        }

        // 2. Verificar si otra ventana ya tiene bloqueado este asiento en Bloqueo_Asiento
        $stmtLockCheck = $pdo->prepare("
            SELECT token_ventana
            FROM Bloqueo_Asiento
            WHERE id_evento = ? AND codigo_asiento = ? AND fecha_expiracion >= NOW()
            LIMIT 1 FOR UPDATE
        ");
        $stmtLockCheck->execute([$event_id, $seat_id]);
        $existingLock = $stmtLockCheck->fetch();

        if ($existingLock && $existingLock['token_ventana'] !== $token) {
            $pdo->rollBack();
            $status = $getMapStatus();
            echo json_encode([
                'success' => false,
                'message' => "El asiento $seat_id ya está siendo seleccionado por otra persona en este momento."
            ] + $status);
            exit;
        }

        // 3. Registrar o actualizar el bloqueo a favor de esta ventana por 5 minutos
        $stmtUpsert = $pdo->prepare("
            INSERT INTO Bloqueo_Asiento (id_evento, codigo_asiento, token_ventana, id_usuario, fecha_expiracion)
            VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE))
            ON DUPLICATE KEY UPDATE
                token_ventana = VALUES(token_ventana),
                id_usuario = VALUES(id_usuario),
                fecha_expiracion = VALUES(fecha_expiracion)
        ");
        $stmtUpsert->execute([$event_id, $seat_id, $token, $id_usuario]);

        $pdo->commit();

        $status = $getMapStatus();
        echo json_encode(['success' => true, 'seat_id' => $seat_id] + $status);
        exit;
    }

    if ($action === 'unlock') {
        $stmtDel = $pdo->prepare("
            DELETE FROM Bloqueo_Asiento
            WHERE id_evento = ? AND codigo_asiento = ? AND token_ventana = ?
        ");
        $stmtDel->execute([$event_id, $seat_id, $token]);
        $status = $getMapStatus();
        echo json_encode(['success' => true, 'seat_id' => $seat_id] + $status);
        exit;
    }

    if ($action === 'unlock_all') {
        $stmtDelAll = $pdo->prepare("
            DELETE FROM Bloqueo_Asiento
            WHERE id_evento = ? AND token_ventana = ?
        ");
        $stmtDelAll->execute([$event_id, $token]);
        $status = $getMapStatus();
        echo json_encode(['success' => true] + $status);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
} catch (\Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Agora F1][seat_lock.php] Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'No se pudo bloquear el asiento porque ya fue seleccionado por otro usuario.'
    ]);
}
?>
