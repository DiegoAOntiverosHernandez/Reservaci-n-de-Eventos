<?php
require_once __DIR__ . '/../php/auth.php';

// Exigir inicio de sesión
requireLogin();

$id_reservacion = isset($_GET['reservacion_id']) ? (int)$_GET['reservacion_id'] : 0;
$id_usuario = getCurrentUserId();

if ($id_reservacion <= 0) {
    die('<h2>Error</h2><p>ID de reservación inválido o ausente.</p>');
}

try {
    // Obtener detalles de la reservación
    $stmt = $pdo->prepare("
        SELECT r.*, z.nombre_zona, z.precio,
               e.id_evento, e.nombre_evento, e.fecha, e.hora_inicio, e.ubicacion,
               c.nombre_categoria,
               u.nombre AS nombre_usuario
        FROM Reservacion r
        JOIN Zona_Boleto z ON r.id_zona = z.id_zona
        JOIN Evento e ON z.id_evento = e.id_evento
        LEFT JOIN Categoria c ON e.id_categoria = c.id_categoria
        JOIN Usuario u ON r.id_usuario = u.id_usuario
        WHERE r.id_reservacion = ? AND r.id_usuario = ?
        LIMIT 1
    ");
    $stmt->execute([$id_reservacion, $id_usuario]);
    $res = $stmt->fetch();

    if (!$res) {
        die('<h2>Error</h2><p>No tienes acceso a este boleto o la reservación no existe.</p>');
    }

    $espacios = (int)$res['espacios_reservados'];
    $nombre_zona = $res['nombre_zona'] ?: 'General';
    $monto_total = (float)$res['precio'] * $espacios;

    // Intentar obtener asientos reales de la base de datos vinculados a los boletos de esta reservación
    $asientos_str = '';
    $stmtSeats = $pdo->prepare("
        SELECT a.fila, a.numero
        FROM Boleto b
        JOIN Asiento a ON b.id_asiento = a.id_asiento
        WHERE b.id_reservacion = ? AND b.estado_boleto = 'valido'
    ");
    $stmtSeats->execute([$id_reservacion]);
    $dbSeats = $stmtSeats->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($dbSeats)) {
        $seatsList = [];
        foreach ($dbSeats as $ds) {
            $seatsList[] = trim($ds['fila']) . trim($ds['numero']);
        }
        $asientos_str = implode(', ', $seatsList);
    } else {
        $asientos_str = isset($_GET['asientos']) ? trim($_GET['asientos']) : '';
    }

    if (empty($asientos_str)) {
        // Generar nombres de asientos deterministas (idénticos a confirmar_reserva y historial_r)
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
        
        // Un número inicial pseudo-aleatorio pero constante para este usuario + evento
        $start = (($res['id_evento'] * 7 + $id_usuario * 3) % 40) + 1;
        for ($i = 0; $i < $espacios; $i++) {
            $asientos[] = $prefix . ($start + $i);
        }
        $asientos_str = implode(', ', $asientos);
    }

    // Formatear Fecha
    $timestamp = strtotime($res['fecha']);
    $diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    $mesesShort = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    
    $diaLetra = $diasSemana[date('w', $timestamp)];
    $diaNum = date('j', $timestamp);
    $mesLetra = $mesesShort[(int)date('m', $timestamp) - 1];
    $horaFormateada = date('h:i a', strtotime($res['hora_inicio']));
    
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
    <title>Boleto Digital - Agora</title>
    <link rel="shortcut icon" href="../Assets/img/logo.png" type="image/x-icon">
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/styles_events/boleto.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="nav">
        <div class="logo"><a href="../index.php"><img src="../Assets/img/title.png" alt="Agora"></a></div>
    </div>

    <div class="main-container">
        <div class="ticket-title-container">
            <h1>Boleto del evento</h1>
            <p>Tu boleto ha sido generado. Puedes verlo en tu lista de reservaciones.</p>
        </div>

        <!-- El boleto digital visual -->
        <div class="ticket-box" id="ticketBox">
            <div class="ticket-header">
                <h2><?php echo htmlspecialchars($res['nombre_evento']); ?></h2>
                <p>📍 <?php echo htmlspecialchars($res['ubicacion']); ?></p>
            </div>
            
            <div class="ticket-body">
                <div class="ticket-info">
                    <div class="info-group">
                        <span class="info-label">Categoría</span>
                        <span class="info-value"><?php echo htmlspecialchars($res['nombre_categoria'] ?: 'General'); ?></span>
                    </div>

                    <div class="info-group">
                        <span class="info-label">Fecha y Hora</span>
                        <span class="info-value"><?php echo htmlspecialchars($fechaFormateada); ?></span>
                    </div>

                    <div class="info-group">
                        <span class="info-label">Asientos</span>
                        <span class="info-value"><?php echo htmlspecialchars($asientos_str); ?></span>
                    </div>

                    <div class="info-group">
                        <span class="info-label">Zona</span>
                        <span class="info-value"><?php echo htmlspecialchars($nombre_zona); ?></span>
                    </div>

                    <div class="info-group">
                        <span class="info-label">Titular</span>
                        <span class="info-value"><?php echo htmlspecialchars($res['nombre_usuario']); ?></span>
                    </div>

                    <div class="info-group">
                        <span class="info-label">Código de Reserva</span>
                        <span class="info-value" style="font-family: monospace; color: #404a6e;">AGORA-RES-<?php echo $id_reservacion; ?></span>
                    </div>
                </div>

                <div class="qr-column">
                    <div class="qr-container">
                        <!-- Generar código QR real simulado con API pública segura -->
                        <img id="qrImage" src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode("AGORA-TICKET-{$id_reservacion}-{$res['nombre_usuario']}"); ?>&color=0f172a" alt="Código QR del Boleto" crossorigin="anonymous">
                    </div>
                </div>
            </div>
        </div>

        <!-- Botones -->
        <div class="btn-group">
            <button id="btnDownload" class="btn-ticket btn-download">
                📥 Descargar boleto
            </button>
            <a href="historial_r.php" class="btn-ticket btn-history">
                Ver mis reservaciones
            </a>
        </div>
    </div>

    <!-- Canvas Renderer Downloader -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const btnDownload = document.getElementById('btnDownload');
            const qrImg = document.getElementById('qrImage');

            btnDownload.addEventListener('click', () => {
                // Deshabilitar botón temporalmente
                btnDownload.disabled = true;
                btnDownload.textContent = 'Generando descarga...';

                // Crear canvas offscreen de alta resolución (doble tamaño para calidad retina)
                const canvas = document.createElement('canvas');
                canvas.width = 1200;
                canvas.height = 800;
                const ctx = canvas.getContext('2d');

                // Variables del diseño del boleto
                const eventName = "<?php echo addslashes($res['nombre_evento']); ?>";
                const locationName = "📍 <?php echo addslashes($res['ubicacion']); ?>";
                const categoryName = "<?php echo addslashes($res['nombre_categoria'] ?: 'General'); ?>";
                const dateStr = "<?php echo addslashes($fechaFormateada); ?>";
                const seatsStr = "<?php echo addslashes($asientos_str); ?>";
                const zoneStr = "<?php echo addslashes($nombre_zona); ?>";
                const userName = "<?php echo addslashes($res['nombre_usuario']); ?>";
                const codeStr = "AGORA-RES-<?php echo $id_reservacion; ?>";

                // Dibujar fondo de lienzo
                ctx.fillStyle = '#f3f4f6';
                ctx.fillRect(0, 0, canvas.width, canvas.height);

                // Dibujar tarjeta del boleto
                const cardX = 100;
                const cardY = 80;
                const cardW = 1000;
                const cardH = 640;
                const radius = 24;

                // Dibujar forma redondeada de la tarjeta
                ctx.save();
                ctx.beginPath();
                ctx.moveTo(cardX + radius, cardY);
                ctx.lineTo(cardX + cardW - radius, cardY);
                ctx.quadraticCurveTo(cardX + cardW, cardY, cardX + cardW, cardY + radius);
                ctx.lineTo(cardX + cardW, cardY + cardH - radius);
                ctx.quadraticCurveTo(cardX + cardW, cardY + cardH, cardX + cardW - radius, cardY + cardH);
                ctx.lineTo(cardX + radius, cardY + cardH);
                ctx.quadraticCurveTo(cardX, cardY + cardH, cardX, cardY + cardH - radius);
                ctx.lineTo(cardX, cardY + radius);
                ctx.quadraticCurveTo(cardX, cardY, cardX + radius, cardY);
                ctx.closePath();
                ctx.fillStyle = '#e5e7eb'; // Gris claro del ticket
                ctx.fill();
                ctx.lineWidth = 2;
                ctx.strokeStyle = '#d1d5db';
                ctx.stroke();
                ctx.restore();

                // Cabecera azul marino
                const headerH = 180;
                ctx.save();
                ctx.beginPath();
                ctx.moveTo(cardX + radius, cardY);
                ctx.lineTo(cardX + cardW - radius, cardY);
                ctx.quadraticCurveTo(cardX + cardW, cardY, cardX + cardW, cardY + radius);
                ctx.lineTo(cardX + cardW, cardY + headerH);
                ctx.lineTo(cardX, cardY + headerH);
                ctx.lineTo(cardX, cardY + radius);
                ctx.quadraticCurveTo(cardX, cardY, cardX + radius, cardY);
                ctx.closePath();
                ctx.fillStyle = '#404a6e'; // Azul marino
                ctx.fill();
                ctx.restore();

                // Dibujar muescas circulares laterales entre cabecera y cuerpo
                ctx.fillStyle = '#f3f4f6';
                ctx.beginPath();
                ctx.arc(cardX, cardY + headerH, 20, 0, Math.PI * 2); // Izquierda
                ctx.fill();
                ctx.beginPath();
                ctx.arc(cardX + cardW, cardY + headerH, 20, 0, Math.PI * 2); // Derecha
                ctx.fill();

                // Dibujar línea punteada separatoria
                ctx.save();
                ctx.strokeStyle = '#cbd5e1';
                ctx.lineWidth = 4;
                ctx.setLineDash([12, 12]);
                ctx.beginPath();
                ctx.moveTo(cardX + 25, cardY + headerH);
                ctx.lineTo(cardX + cardW - 25, cardY + headerH);
                ctx.stroke();
                ctx.restore();

                // Textos de la Cabecera
                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 36px Outfit, sans-serif';
                ctx.fillText(eventName, cardX + 50, cardY + 70);

                ctx.fillStyle = 'rgba(255, 255, 255, 0.9)';
                ctx.font = '500 24px Outfit, sans-serif';
                ctx.fillText(locationName, cardX + 50, cardY + 125);

                // Textos del Cuerpo (Información)
                const textColX = cardX + 50;
                let textY = cardY + headerH + 70;

                function drawTicketMeta(label, value) {
                    ctx.fillStyle = '#6b7280';
                    ctx.font = 'bold 16px Outfit, sans-serif';
                    ctx.fillText(label.toUpperCase(), textColX, textY);
                    
                    ctx.fillStyle = '#1e293b';
                    ctx.font = 'bold 26px Outfit, sans-serif';
                    ctx.fillText(value, textColX, textY + 30);
                    
                    textY += 75;
                }

                drawTicketMeta("Categoría", categoryName);
                drawTicketMeta("Fecha y Hora", dateStr);
                drawTicketMeta("Asientos", seatsStr);
                drawTicketMeta("Zona", zoneStr);
                drawTicketMeta("Titular", userName);
                
                // Código de reserva
                ctx.fillStyle = '#4b5563';
                ctx.font = 'bold 16px Outfit, sans-serif';
                ctx.fillText("CÓDIGO DE RESERVA", textColX, textY);
                
                ctx.fillStyle = '#404a6e';
                ctx.font = 'bold 24px monospace';
                ctx.fillText(codeStr, textColX, textY + 30);

                // Dibujar el Código QR en el lienzo
                try {
                    const qrX = cardX + cardW - 350;
                    const qrY = cardY + headerH + 70;
                    const qrSize = 300;

                    // Fondo del contenedor QR
                    ctx.save();
                    ctx.fillStyle = '#ffffff';
                    ctx.beginPath();
                    ctx.roundRect ? ctx.roundRect(qrX - 20, qrY - 20, qrSize + 40, qrSize + 40, 16) : ctx.rect(qrX - 20, qrY - 20, qrSize + 40, qrSize + 40);
                    ctx.fill();
                    ctx.lineWidth = 2;
                    ctx.strokeStyle = '#cbd5e1';
                    ctx.stroke();
                    ctx.restore();

                    // Dibujar la imagen del QR cargado (CORS seguro)
                    ctx.drawImage(qrImg, qrX, qrY, qrSize, qrSize);

                    // Proceder con la descarga
                    triggerDownload(canvas);

                } catch (err) {
                    console.warn("Error dibujando QR externo al canvas (CORS fallback activo):", err);
                    
                    // Fallback offline: Dibujar código QR procedural estilizado
                    const qrX = cardX + cardW - 350;
                    const qrY = cardY + headerH + 70;
                    const qrSize = 300;

                    // Fondo
                    ctx.save();
                    ctx.fillStyle = '#ffffff';
                    ctx.beginPath();
                    ctx.roundRect ? ctx.roundRect(qrX - 20, qrY - 20, qrSize + 40, qrSize + 40, 16) : ctx.rect(qrX - 20, qrY - 20, qrSize + 40, qrSize + 40);
                    ctx.fill();
                    ctx.restore();

                    // Procedural QR mockup
                    ctx.fillStyle = '#0f172a';
                    const cellSize = 10;
                    const gridCount = qrSize / cellSize;
                    for (let row = 0; row < gridCount; row++) {
                        for (let col = 0; col < gridCount; col++) {
                            // Tres localizadores de esquinas fijos
                            const isFinder = (row < 7 && col < 7) || 
                                             (row < 7 && col >= gridCount - 7) || 
                                             (row >= gridCount - 7 && col < 7);
                            if (isFinder) {
                                // Patrón del localizador de QR
                                const edge = (row === 0 || row === 6 || col === 0 || col === 6) ||
                                             (row >= gridCount - 7 && (row === gridCount - 7 || row === gridCount - 1 || col === 0 || col === 6)) ||
                                             (col >= gridCount - 7 && (row === 0 || row === 6 || col === gridCount - 7 || col === gridCount - 1));
                                const center = (row >= 2 && row <= 4 && col >= 2 && col <= 4) ||
                                               (row >= gridCount - 5 && row <= gridCount - 3 && col >= 2 && col <= 4) ||
                                               (row >= 2 && row <= 4 && col >= gridCount - 5 && col <= gridCount - 3);
                                if (edge || center) {
                                    ctx.fillRect(qrX + col * cellSize, qrY + row * cellSize, cellSize, cellSize);
                                }
                            } else {
                                // Píxeles aleatorios
                                if (Math.random() > 0.5) {
                                    ctx.fillRect(qrX + col * cellSize, qrY + row * cellSize, cellSize, cellSize);
                                }
                            }
                        }
                    }

                    triggerDownload(canvas);
                }
            });

            function triggerDownload(canvas) {
                const dataUrl = canvas.toDataURL('image/png');
                const link = document.createElement('a');
                link.download = `Boleto-Agora-RES-<?php echo $id_reservacion; ?>.png`;
                link.href = dataUrl;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                // Restaurar botón
                btnDownload.disabled = false;
                btnDownload.textContent = '📥 Descargar boleto';
            }
        });
    </script>
</body>
</html>
