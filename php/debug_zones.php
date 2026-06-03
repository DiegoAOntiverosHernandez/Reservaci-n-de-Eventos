<?php
require_once __DIR__ . '/db.php';

$event_id = 28;
echo "=== ZONAS DE BOLETO PARA EVENTO 28 ===\n";
$stmt = $pdo->prepare("SELECT * FROM Zona_Boleto WHERE id_evento = ?");
$stmt->execute([$event_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}

echo "=== DETALLES DEL EVENTO 28 ===\n";
$stmt = $pdo->prepare("SELECT * FROM Evento WHERE id_evento = ?");
$stmt->execute([$event_id]);
print_r($stmt->fetch(PDO::FETCH_ASSOC));

echo "=== SUM DE ZONAS EN BD ===\n";
$stmt = $pdo->prepare("
    SELECT SUM(lugares_totales) AS tot, SUM(lugares_disponibles) AS disp
    FROM Zona_Boleto WHERE id_evento = ?
");
$stmt->execute([$event_id]);
print_r($stmt->fetch(PDO::FETCH_ASSOC));
?>
