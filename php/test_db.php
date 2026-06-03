<?php
require_once __DIR__ . '/db.php';

echo "=== DIAS DE EVENTOS ===\n";
$stmt = $pdo->query("SELECT id_evento, nombre_evento, fecha, hora_inicio FROM Evento WHERE estado = 'activo' ORDER BY fecha ASC, hora_inicio ASC");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id_evento']} | Nombre: {$row['nombre_evento']} | Fecha: {$row['fecha']} | Hora: {$row['hora_inicio']}\n";
}
?>
