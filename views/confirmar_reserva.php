<?php
require_once __DIR__ . '/../php/auth.php';

// Exigir inicio de sesión
requireLogin();

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$espacios = isset($_GET['espacios']) ? (int)$_GET['espacios'] : 1;
$current_slots = isset($_GET['current_slots']) ? (int)$_GET['current_slots'] : 0;
$id_usuario = getCurrentUserId();

if ($event_id <= 0 || $espacios <= 0) {
    die('<h2>Error</h2><p>ID de evento o cantidad de espacios inválidos.</p>');
}

try {
    // Obtener detalles del evento y categoría
    $stmt = $pdo->prepare("
        SELECT e.*, c.nombre_categoria,
               z.id_zona, z.nombre_zona, z.precio
        FROM Evento e
        LEFT JOIN Categoria c ON e.id_categoria = c.id_categoria
        LEFT JOIN Zona_Boleto z ON e.id_evento = z.id_evento
        WHERE e.id_evento = ?
        LIMIT 1
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        die('<h2>Error</h2><p>El evento solicitado no existe.</p>');
    }

    // Obtener precios de zonas para el cálculo exacto
    $stmtZones = $pdo->prepare("SELECT nombre_zona, precio FROM Zona_Boleto WHERE id_evento = ?");
    $stmtZones->execute([$event_id]);
    $dbZones = $stmtZones->fetchAll();

    $zonePrices = [];
    foreach ($dbZones as $z) {
        $zonePrices[$z['nombre_zona']] = (float)$z['precio'];
    }

    $asientos_str = isset($_GET['asientos']) ? trim($_GET['asientos']) : '';

    if (!empty($asientos_str)) {
        // Obtener zona a partir de los prefijos de los asientos elegidos: V (VIP), A (Zona A), B (Zona B), C (Zona C), D (Zona D)
        $firstSeat = explode(',', $asientos_str)[0];
        $prefix = substr(trim($firstSeat), 0, 1);
        
        $nombre_zona = 'General';
        if ($prefix === 'V') $nombre_zona = 'VIP';
        elseif ($prefix === 'A') $nombre_zona = 'Zona A';
        elseif ($prefix === 'B') $nombre_zona = 'Zona B';
        elseif ($prefix === 'C') $nombre_zona = 'Zona C';
        elseif ($prefix === 'D') $nombre_zona = 'Zona D';
        
        $precio_unitario = (float)($zonePrices[$nombre_zona] ?? ($event['precio'] ?? 0));
        $monto_total = $precio_unitario * $espacios;
    } else {
        // Fallback determinista
        $nombre_zona = $event['nombre_zona'] ?: 'General';
        $precio_unitario = (float)($event['precio'] ?? 0);
        $monto_total = $precio_unitario * $espacios;

        $asientos = [];
        $prefix = 'G'; // General
        $nombre_zona_clean = trim(strtoupper($nombre_zona));
        if ($nombre_zona_clean === 'VIP') {
            $prefix = 'V';
        } elseif ($nombre_zona_clean === 'ZONA A' || $nombre_zona_clean === 'A') {
            $prefix = 'A';
        } elseif ($nombre_zona_clean === 'ZONA B' || $nombre_zona_clean === 'B') {
            $prefix = 'B';
        } elseif ($nombre_zona_clean === 'ZONA C' || $nombre_zona_clean === 'C') {
            $prefix = 'C';
        } elseif ($nombre_zona_clean === 'ZONA D' || $nombre_zona_clean === 'D') {
            $prefix = 'D';
        }
        
        // Un número inicial constante para este usuario + evento
        $start = (($event_id * 7 + $id_usuario * 3) % 40) + 1;
        for ($i = 0; $i < $espacios; $i++) {
            $asientos[] = $prefix . ($start + $i);
        }
        $asientos_str = implode(', ', $asientos);
    }

    // Formatear Fecha al estilo: Martes 4 Nov, 05:00 pm
    $timestamp = strtotime($event['fecha']);
    $diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    $mesesShort = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    
    $diaLetra = $diasSemana[date('w', $timestamp)];
    $diaNum = date('j', $timestamp);
    $mesLetra = $mesesShort[(int)date('m', $timestamp) - 1];
    $horaFormateada = date('h:i a', strtotime($event['hora_inicio']));
    
    $fechaFormateada = "$diaLetra $diaNum $mesLetra, $horaFormateada";

} catch (Exception $e) {
    die('Error en el servidor: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/home_styles/nav_footer.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <title>Confirmar Reservación - Agora</title>
    <link rel="shortcut icon" href="../Assets/img/logo.png" type="image/x-icon">
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/styles_events/confirmar_reserva.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="nav">
        <div class="logo"><a href="../index.php"><img src="../Assets/img/title.png" alt="Agora"></a></div>
        <nav class="nav_links" style="display: none;"></nav> <!-- Se mantiene estructura limpia -->
    </div>

    <div class="main-container">
        <div class="confirm-card">
            <h1>Confirmar reservación</h1>
            <p class="subtitle">Revise el resumen de su reservacion antes de confirmar</p>

            <div class="details-blue-card">
                <h2><?php echo htmlspecialchars($event['nombre_evento']); ?></h2>
                
                <div class="detail-row">
                    <span class="detail-label">Fecha</span>
                    <span class="detail-value"><?php echo htmlspecialchars($fechaFormateada); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Ubicación</span>
                    <span class="detail-value"><?php echo htmlspecialchars($event['ubicacion']); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Asientos</span>
                    <span class="detail-value"><?php echo htmlspecialchars($asientos_str); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Zona</span>
                    <span class="detail-value"><?php echo htmlspecialchars($nombre_zona); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Cantidad</span>
                    <span class="detail-value"><?php echo $espacios; ?> boletos</span>
                </div>

                <div class="total-row">
                    <span class="total-label">Total a pagar</span>
                    <span class="total-value">$<?php echo number_format($monto_total, 2); ?> MXN</span>
                </div>
            </div>

            <div class="button-group">
                <a href="pagar.php?event_id=<?php echo $event_id; ?>&espacios=<?php echo $espacios; ?>&current_slots=<?php echo $current_slots; ?>&asientos=<?php echo urlencode($asientos_str); ?>" class="btn-action btn-confirm">Confirmar y pagar</a>
                <a href="reservar.php?event_id=<?php echo $event_id; ?>" class="btn-action btn-cancel">Cancelar</a>
            </div>
        </div>
    </div>

    <div id="footer">
        <footer class="footer">
            <div class="footer_content">
                <div class="name">
                    <img src="../Assets/img/title.png" alt="Agora">
                </div>
                <div class="contact_info">
                    Contáctanos <br>
                    Av. Calle falso #1234 <br>
                    <a href="mailto:Agoraeventos@correofalso.com">Agoraeventos@correofalso.com</a> <br>
                    012 000 45-150 <br><br>
                    © 2026 Agora Todos los derechos reservados.
                </div>
                <div class="div_3"></div>
            </div>
        </footer>
    </div>
</body>
</html>
