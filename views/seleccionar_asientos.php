<?php
require_once __DIR__ . '/../php/auth.php';

// Exigir inicio de sesión
requireLogin();

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$espacios = isset($_GET['espacios']) ? (int)$_GET['espacios'] : 3; // Default a 3 para coincidir con la imagen
$id_usuario = getCurrentUserId();

if ($event_id <= 0 || $espacios <= 0) {
    die('<h2>Error</h2><p>Parámetros de selección inválidos.</p>');
}

try {
    // Obtener detalles del evento y su recinto
    $stmt = $pdo->prepare("
        SELECT e.*, r.tipo_recinto, r.nombre_recinto 
        FROM Evento e 
        LEFT JOIN Recinto r ON e.id_recinto = r.id_recinto 
        WHERE e.id_evento = ? 
        LIMIT 1
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        die('<h2>Error</h2><p>El evento solicitado no existe o no está disponible.</p>');
    }
    
    // Auto-corrección de recinto según la categoría del evento para mantener consistencia
    $lowerCategory = '';
    if (isset($event['id_categoria'])) {
        $stmtCat = $pdo->prepare("SELECT nombre_categoria FROM Categoria WHERE id_categoria = ?");
        $stmtCat->execute([$event['id_categoria']]);
        $lowerCategory = strtolower($stmtCat->fetchColumn() ?: '');
    }

    $expected_recinto = $event['id_recinto'];
    if (strpos($lowerCategory, 'concierto') !== false || strpos($lowerCategory, 'deporte') !== false || strpos($lowerCategory, 'deportivo') !== false) {
        $expected_recinto = 2; // Estadio Olímpico Ágora
    } elseif (strpos($lowerCategory, 'teatro') !== false || strpos($lowerCategory, 'obra') !== false || strpos($lowerCategory, 'arte') !== false) {
        $expected_recinto = 1; // Teatro Ágora Principal
    } elseif (strpos($lowerCategory, 'conferencia') !== false) {
        $expected_recinto = 3; // Sala de Conferencias Ágora
    }

    if ($expected_recinto !== $event['id_recinto']) {
        $stmtUpdate = $pdo->prepare("UPDATE Evento SET id_recinto = ? WHERE id_evento = ?");
        $stmtUpdate->execute([$expected_recinto, $event_id]);
        
        // Volver a cargar el evento con el recinto corregido
        $stmt->execute([$event_id]);
        $event = $stmt->fetch();
    }
    
    $tipo_recinto = $event['tipo_recinto'] ?? 'estadio';

    // Obtener los precios de las zonas para este evento desde Zona_Boleto
    $stmtZones = $pdo->prepare("SELECT id_zona, nombre_zona, precio, lugares_totales, lugares_disponibles FROM Zona_Boleto WHERE id_evento = ?");
    $stmtZones->execute([$event_id]);
    $dbZones = $stmtZones->fetchAll();

    // Valores por defecto si aún no se han fijado precios en el recinto
    $prices = [
        'VIP' => 500.00,
        'Zona A' => 300.00,
        'Zona B' => 200.00,
        'Zona C' => 150.00,
        'Zona D' => 100.00
    ];

    foreach ($dbZones as $z) {
        $prices[$z['nombre_zona']] = (float)$z['precio'];
    }

    // Mapear los datos reales de las zonas desde la base de datos
    $zonesInfo = [];
    foreach ($dbZones as $z) {
        $zonesInfo[$z['nombre_zona']] = [
            'totales' => (int)$z['lugares_totales'],
            'disponibles' => (int)$z['lugares_disponibles']
        ];
    }

    // Obtener los asientos vendidos reales para este evento
    $stmtSold = $pdo->prepare("
        SELECT a.fila, a.numero 
        FROM Boleto b
        JOIN Asiento a ON b.id_asiento = a.id_asiento
        JOIN Zona_Boleto z ON a.id_zona = z.id_zona
        WHERE z.id_evento = ? AND b.estado_boleto = 'valido'
    ");
    $stmtSold->execute([$event_id]);
    $soldSeatsData = $stmtSold->fetchAll(PDO::FETCH_ASSOC);

    $soldSeats = [];
    foreach ($soldSeatsData as $s) {
        $soldSeats[] = trim($s['fila']) . trim($s['numero']);
    }

     function renderDynamicZoneSeats($zoneName, $prices, $color, $zonesInfo) {
        global $tipo_recinto, $soldSeats;
        $defaultCircles = [
            'VIP' => 8,
            'Zona A' => 12,
            'Zona B' => 12,
            'Zona C' => 10,
            'Zona D' => 12
        ];
        $fallbackTotales = isset($defaultCircles[$zoneName]) ? $defaultCircles[$zoneName] : 8;
        
        $dbTotales = isset($zonesInfo[$zoneName]) ? $zonesInfo[$zoneName]['totales'] : $fallbackTotales;
        $dbDisponibles = isset($zonesInfo[$zoneName]) ? $zonesInfo[$zoneName]['disponibles'] : $dbTotales;
        $dbVendidos = $dbTotales - $dbDisponibles;
        $price = isset($prices[$zoneName]) ? $prices[$zoneName] : 100.00;

        $zonePrefix = 'G';
        if ($zoneName === 'VIP') {
            $zonePrefix = 'V';
        } elseif (strpos($zoneName, 'Zona ') === 0) {
            $zonePrefix = substr($zoneName, 5, 1);
        } else {
            $zonePrefix = substr($zoneName, 0, 1);
        }

        $rows = [];
        
        if ($tipo_recinto === 'teatro' || $tipo_recinto === 'conferencia') {
            // DISEÑO RECTO DE ALTA FIDELIDAD PARA TEATROS Y CONFERENCIAS
            if ($zoneName === 'VIP') {
                $rows = [
                    ['xStart' => 120, 'xEnd' => 280, 'y' => 95],
                    ['xStart' => 120, 'xEnd' => 280, 'y' => 110],
                    ['xStart' => 120, 'xEnd' => 280, 'y' => 125]
                ];
            } elseif ($zoneName === 'Zona B') {
                $rows = [
                    ['xStart' => 120, 'xEnd' => 280, 'y' => 150],
                    ['xStart' => 120, 'xEnd' => 280, 'y' => 165],
                    ['xStart' => 120, 'xEnd' => 280, 'y' => 180]
                ];
            } elseif ($zoneName === 'Zona C') {
                $rows = [
                    ['xStart' => 100, 'xEnd' => 300, 'y' => 210],
                    ['xStart' => 100, 'xEnd' => 300, 'y' => 225],
                    ['xStart' => 100, 'xEnd' => 300, 'y' => 240]
                ];
            } elseif ($zoneName === 'Zona D') {
                $rows = [
                    ['xStart' => 100, 'xEnd' => 300, 'y' => 265],
                    ['xStart' => 100, 'xEnd' => 300, 'y' => 280],
                    ['xStart' => 100, 'xEnd' => 300, 'y' => 295]
                ];
            } elseif ($zoneName === 'Zona A') {
                // Balcones laterales rectos (columnas verticales a la izquierda y derecha)
                $rows = [
                    ['x' => 45, 'yStart' => 95, 'yEnd' => 180, 'wing' => 'left'],
                    ['x' => 60, 'yStart' => 95, 'yEnd' => 180, 'wing' => 'left'],
                    ['x' => 355, 'yStart' => 95, 'yEnd' => 180, 'wing' => 'right'],
                    ['x' => 340, 'yStart' => 95, 'yEnd' => 180, 'wing' => 'right']
                ];
            }
        } else {
            // DISEÑO RADIAL CONCÉNTRICO PARA ESTADIO
            if ($zoneName === 'VIP') {
                $rows = [
                    ['rx' => 50, 'ry' => 45, 'startAngle' => 40, 'endAngle' => 140],
                    ['rx' => 70, 'ry' => 60, 'startAngle' => 40, 'endAngle' => 140],
                    ['rx' => 90, 'ry' => 75, 'startAngle' => 45, 'endAngle' => 135]
                ];
            } elseif ($zoneName === 'Zona B') {
                $rows = [
                    ['rx' => 110, 'ry' => 95, 'startAngle' => 45, 'endAngle' => 135],
                    ['rx' => 130, 'ry' => 110, 'startAngle' => 45, 'endAngle' => 135],
                    ['rx' => 150, 'ry' => 125, 'startAngle' => 50, 'endAngle' => 130]
                ];
            } elseif ($zoneName === 'Zona C') {
                $rows = [
                    ['rx' => 170, 'ry' => 140, 'startAngle' => 50, 'endAngle' => 130],
                    ['rx' => 190, 'ry' => 155, 'startAngle' => 50, 'endAngle' => 130],
                    ['rx' => 210, 'ry' => 170, 'startAngle' => 55, 'endAngle' => 125]
                ];
            } elseif ($zoneName === 'Zona D') {
                $rows = [
                    ['rx' => 230, 'ry' => 185, 'startAngle' => 55, 'endAngle' => 125],
                    ['rx' => 250, 'ry' => 200, 'startAngle' => 55, 'endAngle' => 125],
                    ['rx' => 270, 'ry' => 215, 'startAngle' => 60, 'endAngle' => 120]
                ];
            } elseif ($zoneName === 'Zona A') {
                $rows = [
                    ['rx' => 120, 'ry' => 127, 'startAngle' => 125, 'endAngle' => 235, 'cx' => 200, 'cy' => 160, 'wing' => 'left'],
                    ['rx' => 152, 'ry' => 116, 'startAngle' => 125, 'endAngle' => 235, 'cx' => 200, 'cy' => 160, 'wing' => 'left'],
                    ['rx' => 126, 'ry' => 127, 'startAngle' => -55, 'endAngle' => 55, 'cx' => 200, 'cy' => 160, 'wing' => 'right'],
                    ['rx' => 152, 'ry' => 116, 'startAngle' => -55, 'endAngle' => 55, 'cx' => 200, 'cy' => 160, 'wing' => 'right']
                ];
            }
        }

        $totalSeatsToDraw = $dbTotales;
        if ($totalSeatsToDraw <= 0) return;

        if ($tipo_recinto === 'teatro' || $tipo_recinto === 'conferencia') {
            if ($zoneName === 'Zona A') {
                $leftSeats = (int)ceil($totalSeatsToDraw / 2);
                $rightSeats = $totalSeatsToDraw - $leftSeats;
                
                $leftRows = array_filter($rows, function($r) { return $r['wing'] === 'left'; });
                $rightRows = array_filter($rows, function($r) { return $r['wing'] === 'right'; });
                
                $nextIndex = drawStraightRowsSeats($leftRows, $leftSeats, $zoneName, $price, $color, $dbVendidos, $zonePrefix, 1, $soldSeats);
                drawStraightRowsSeats($rightRows, $rightSeats, $zoneName, $price, $color, $dbVendidos, $zonePrefix, $nextIndex, $soldSeats);
            } else {
                drawStraightRowsSeats($rows, $totalSeatsToDraw, $zoneName, $price, $color, $dbVendidos, $zonePrefix, 1, $soldSeats);
            }
        } else {
            if ($zoneName === 'Zona A') {
                $leftSeats = (int)ceil($totalSeatsToDraw / 2);
                $rightSeats = $totalSeatsToDraw - $leftSeats;
                
                $leftRows = array_filter($rows, function($r) { return $r['wing'] === 'left'; });
                $rightRows = array_filter($rows, function($r) { return $r['wing'] === 'right'; });
                
                $nextIndex = drawRowsSeats($leftRows, $leftSeats, $zoneName, $price, $color, $dbVendidos, $zonePrefix, 1, $soldSeats);
                drawRowsSeats($rightRows, $rightSeats, $zoneName, $price, $color, $dbVendidos, $zonePrefix, $nextIndex, $soldSeats);
            } else {
                drawRowsSeats($rows, $totalSeatsToDraw, $zoneName, $price, $color, $dbVendidos, $zonePrefix, 1, $soldSeats);
            }
        }
    }

    function drawStraightRowsSeats($rows, $seatsCount, $zoneName, $price, $color, $dbVendidos, $prefix, $startSeatIndex = 1, $soldSeats = []) {
        $rows = array_values($rows);
        $numRows = count($rows);
        if ($numRows === 0 || $seatsCount === 0) return $startSeatIndex;

        // Distribución de asientos por fila
        $remainingSeats = $seatsCount;
        $allocated = array_fill(0, $numRows, 0);

        if (isset($rows[0]['x'])) {
            // Distribución simétrica equitativa para balcones verticales (Zona A)
            for ($r = 0; $r < $numRows; $r++) {
                $rowsLeft = $numRows - $r;
                $take = (int)ceil($remainingSeats / $rowsLeft);
                $allocated[$r] = $take;
                $remainingSeats -= $take;
            }
        } else {
            // Llenado horizontal preferencial para VIP, B, C, D (máximo ideal de asientos por fila)
            $idealSeatsPerRow = 12;
            if ($seatsCount <= $idealSeatsPerRow) {
                $allocated[0] = $seatsCount;
            } else {
                $rowsToUse = min($numRows, (int)ceil($seatsCount / $idealSeatsPerRow));
                for ($r = 0; $r < $rowsToUse; $r++) {
                    $rowsLeft = $rowsToUse - $r;
                    $take = (int)ceil($remainingSeats / $rowsLeft);
                    $allocated[$r] = $take;
                    $remainingSeats -= $take;
                }
            }
        }

        $seatIndex = $startSeatIndex;

        for ($r = 0; $r < $numRows; $r++) {
            $seatsInThisRow = $allocated[$r];
            if ($seatsInThisRow <= 0) continue;

            $rowInfo = $rows[$r];

            // Determinar dinámicamente el radio del círculo según el número de asientos en la fila para evitar colisiones
            $r_circle = 6;
            $spacing = 14.5;
            if ($seatsInThisRow > 25) {
                $r_circle = 3.5;
                $spacing = 8.5;
            } elseif ($seatsInThisRow > 18) {
                $r_circle = 4.5;
                $spacing = 10.5;
            } elseif ($seatsInThisRow > 12) {
                $r_circle = 5.2;
                $spacing = 12.0;
            }

            // Comprobar si es columna vertical (Zona A Balcones) o fila horizontal (VIP, B, C, D)
            if (isset($rowInfo['x'])) {
                // Balcón vertical (Zona A)
                $x = $rowInfo['x'];
                $yStart = $rowInfo['yStart'];
                $yEnd = $rowInfo['yEnd'];

                $totalHeight = ($seatsInThisRow - 1) * $spacing;
                $maxHeight = abs($yEnd - $yStart);

                if ($totalHeight > $maxHeight && $seatsInThisRow > 1) {
                    $spacing = $maxHeight / ($seatsInThisRow - 1);
                    $rowStart = min($yStart, $yEnd);
                } else {
                    $rowCenterY = ($yStart + $yEnd) / 2;
                    $rowStart = $rowCenterY - ($totalHeight / 2);
                }

                for ($i = 0; $i < $seatsInThisRow; $i++) {
                    $cx = $x;
                    $cy = $rowStart + ($i * $spacing);

                    $id = $prefix . $seatIndex;
                    $isSold = in_array($id, $soldSeats);

                    $class = 'seat';
                    if ($isSold) {
                        $class .= ' sold';
                        $fillColor = '#ef4444';
                    } else {
                        $class .= ' available';
                        $fillColor = $color;
                    }

                    echo sprintf(
                        '<circle cx="%d" cy="%d" r="%.1f" fill="%s" class="%s" data-id="%s" data-zone="%s" data-price="%.2f" />' . "\n",
                        round($cx), round($cy), $r_circle, $fillColor, $class, $id, $zoneName, $price
                    );

                    $seatIndex++;
                }
            } else {
                // Fila horizontal (VIP, B, C, D)
                $y = $rowInfo['y'];
                $xStart = $rowInfo['xStart'];
                $xEnd = $rowInfo['xEnd'];

                $totalWidth = ($seatsInThisRow - 1) * $spacing;
                $maxWidth = abs($xEnd - $xStart);

                if ($totalWidth > $maxWidth && $seatsInThisRow > 1) {
                    $spacing = $maxWidth / ($seatsInThisRow - 1);
                    $rowStart = min($xStart, $xEnd);
                } else {
                    $rowCenterX = ($xStart + $xEnd) / 2;
                    $rowStart = $rowCenterX - ($totalWidth / 2);
                }

                for ($i = 0; $i < $seatsInThisRow; $i++) {
                    $cx = $rowStart + ($i * $spacing);
                    $cy = $y;

                    $id = $prefix . $seatIndex;
                    $isSold = in_array($id, $soldSeats);

                    $class = 'seat';
                    if ($isSold) {
                        $class .= ' sold';
                        $fillColor = '#ef4444';
                    } else {
                        $class .= ' available';
                        $fillColor = $color;
                    }

                    echo sprintf(
                        '<circle cx="%d" cy="%d" r="%.1f" fill="%s" class="%s" data-id="%s" data-zone="%s" data-price="%.2f" />' . "\n",
                        round($cx), round($cy), $r_circle, $fillColor, $class, $id, $zoneName, $price
                    );

                    $seatIndex++;
                }
            }
        }
        return $seatIndex;
    }

    function drawRowsSeats($rows, $seatsCount, $zoneName, $price, $color, $dbVendidos, $prefix, $startSeatIndex = 1, $soldSeats = []) {
        $rows = array_values($rows);
        $numRows = count($rows);
        if ($numRows === 0 || $seatsCount === 0) return $startSeatIndex;

        // Determinar la capacidad máxima por fila según la zona
        $standardMax = 12;
        if ($zoneName === 'VIP') {
            $standardMax = 8;
        } elseif ($zoneName === 'Zona A') {
            $standardMax = 10;
        }
        
        $capacityWithStandard = $numRows * $standardMax;
        if ($seatsCount > $capacityWithStandard) {
            $maxSeatsPerRow = (int)ceil($seatsCount / $numRows);
        } else {
            $maxSeatsPerRow = $standardMax;
        }
        
        // Ajustar dinámicamente el radio del círculo según la densidad para evitar superposición excesiva
        $r_circle = 6;
        if ($maxSeatsPerRow > 20) {
            $r_circle = 3.5;
        } elseif ($maxSeatsPerRow > 15) {
            $r_circle = 4.5;
        } elseif ($maxSeatsPerRow > 12) {
            $r_circle = 5.2;
        }
        
        $seatIndex = $startSeatIndex;
        $countDrawn = 0;
        
        for ($r = 0; $r < $numRows; $r++) {
            if ($countDrawn >= $seatsCount) break;
            
            $rowInfo = $rows[$r];
            $rx = $rowInfo['rx'];
            $ry = $rowInfo['ry'];
            $startDeg = $rowInfo['startAngle'];
            $endDeg = $rowInfo['endAngle'];
            
            $seatsInThisRow = min($maxSeatsPerRow, $seatsCount - $countDrawn);
            if ($seatsInThisRow <= 0) break;
            
            // Empaquetamiento premium: calcular el espaciado físico de centro a centro constante
            $physicalSpacing = 14.5;
            if ($r_circle < 4.0) {
                $physicalSpacing = 9.0;
            } elseif ($r_circle < 5.0) {
                $physicalSpacing = 11.5;
            } elseif ($r_circle < 5.5) {
                $physicalSpacing = 13.0;
            }
            
            $deltaAngleRad = $physicalSpacing / $rx;
            $deltaAngleDeg = rad2deg($deltaAngleRad);
            $totalSpanDeg = ($seatsInThisRow - 1) * $deltaAngleDeg;
            $maxAllowedSpan = abs($endDeg - $startDeg);
            
            if ($seatsInThisRow > 1 && $totalSpanDeg < $maxAllowedSpan) {
                $midAngle = ($startDeg + $endDeg) / 2;
                $rowStart = $midAngle - ($totalSpanDeg / 2);
                $rowEnd = $midAngle + ($totalSpanDeg / 2);
            } else {
                $rowStart = $startDeg;
                $rowEnd = $endDeg;
            }
            
            for ($i = 0; $i < $seatsInThisRow; $i++) {
                $angleDeg = ($seatsInThisRow === 1) 
                    ? ($rowStart + $rowEnd) / 2 
                    : $rowStart + ($i * ($rowEnd - $rowStart) / ($seatsInThisRow - 1));
                
                $angleRad = deg2rad($angleDeg);
                
                // Distribución elíptica con centro configurable (por defecto 200, 60 para escenario)
                $centerX = isset($rowInfo['cx']) ? $rowInfo['cx'] : 200;
                $centerY = isset($rowInfo['cy']) ? $rowInfo['cy'] : 60;
                $cx = round($centerX + $rx * cos($angleRad));
                $cy = round($centerY + $ry * sin($angleRad));
                
                $id = $prefix . $seatIndex;
                $isSold = in_array($id, $soldSeats);
                
                $class = 'seat';
                if ($isSold) {
                    $class .= ' sold';
                    $fillColor = '#ef4444';
                } else {
                    $class .= ' available';
                    $fillColor = $color;
                }
                
                echo sprintf(
                    '<circle cx="%d" cy="%d" r="%.1f" fill="%s" class="%s" data-id="%s" data-zone="%s" data-price="%.2f" />' . "\n",
                    $cx, $cy, $r_circle, $fillColor, $class, $id, $zoneName, $price
                );
                
                $seatIndex++;
                $countDrawn++;
            }
        }
        return $seatIndex;
    }
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
    <title>Selección de Asientos - Agora</title>
    <link rel="shortcut icon" href="../Assets/img/logo.png" type="image/x-icon">
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/styles_events/seleccionar_asientos.css?v=<?php echo time(); ?>">
    <style>
        .seat.blocked {
            fill: #9ca3af !important;
            cursor: not-allowed !important;
            opacity: 0.55 !important;
            pointer-events: auto !important;
            stroke: #4b5563 !important;
            stroke-width: 1px !important;
        }
    </style>
</head>
<body>
    <div class="nav">
        <div class="logo"><a href="../index.php"><img src="../Assets/img/title.png" alt="Agora"></a></div>
    </div>

    <div class="select-container">
        <div class="select-header">
            <h2><?php echo htmlspecialchars($event['nombre_evento']); ?></h2>
            <p style="font-size: 1.05rem; color: #4b5563; font-weight: 500; display: flex; align-items: center; gap: 10px;">
                <span>Escoge haciendo clic en los asientos disponibles</span> • 
                <span style="background-color: #cbd5e1; color: #0f172a; padding: 4px 10px; border-radius: 12px; font-size: 0.85rem; font-weight: 700;">
                    Capacidad Real: <?php echo (int)$event['capacidad_total']; ?> Asientos
                </span>
            </p>
        </div>

        <div class="layout-grid">
            <!-- Columna Izquierda: Mapa de Asientos Estadio SVG -->
            <div class="map-card-wrapper">
                <div class="stadium-map-container">
                    <svg class="stadium-svg" viewBox="0 0 400 320" xmlns="http://www.w3.org/2000/svg">
                        <!-- Definiciones para gradientes premium del escenario siempre disponibles -->
                        <defs>
                            <linearGradient id="stageGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" stop-color="#1e293b" />
                                <stop offset="100%" stop-color="#0f172a" />
                            </linearGradient>
                            <linearGradient id="neonGlow" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" stop-color="#3b82f6" />
                                <stop offset="50%" stop-color="#10b981" />
                                <stop offset="100%" stop-color="#3b82f6" />
                            </linearGradient>
                        </defs>

                        <?php if ($tipo_recinto === 'teatro' || $tipo_recinto === 'conferencia'): ?>
                            <!-- ESTRUCTURA RECTA PARA TEATRO / CONFERENCIAS (Vector de alta fidelidad, sin foto de fondo) -->
                            <!-- Borde de la Sala -->
                            <rect x="15" y="15" width="370" height="290" rx="10" fill="none" stroke="#cbd5e1" stroke-width="4" />
                            
                            <!-- Escenario Recto Moderno con Neón -->
                            <rect x="60" y="30" width="280" height="35" rx="6" fill="url(#stageGrad)" stroke="url(#neonGlow)" stroke-width="2" />
                            <text x="200" y="52" fill="#f8fafc" font-family="Outfit" font-size="11" font-weight="800" letter-spacing="2" text-anchor="middle">★ ESCENARIO ★</text>
                        <?php else: ?>
                            <!-- Oval exterior del estadio -->
                            <ellipse cx="200" cy="160" rx="180" ry="140" fill="none" stroke="#e2e8f0" stroke-width="6" />

                            <!-- Estructura del escenario moderno con neón -->
                            <rect x="110" y="48" width="180" height="24" rx="6" fill="url(#stageGrad)" stroke="url(#neonGlow)" stroke-width="2" />
                            <rect x="120" y="68" width="160" height="4" rx="2" fill="#10b981" opacity="0.8" />
                            <text x="200" y="63" fill="#f8fafc" font-family="Outfit" font-size="10" font-weight="800" letter-spacing="2" text-anchor="middle">★ ESCENARIO ★</text>
                        <?php endif; ?>

                        <!-- Dibujar Asientos Dinámicos de forma Concéntrica -->
                        <?php
                        renderDynamicZoneSeats('VIP', $prices, '#d97706', $zonesInfo);
                        renderDynamicZoneSeats('Zona B', $prices, '#10b981', $zonesInfo);
                        renderDynamicZoneSeats('Zona C', $prices, '#f97316', $zonesInfo);
                        renderDynamicZoneSeats('Zona D', $prices, '#8b5cf6', $zonesInfo);
                        renderDynamicZoneSeats('Zona A', $prices, '#2563eb', $zonesInfo);
                        ?>
                    </svg>
                </div>

                <!-- Leyendas de Zonas y Precios -->
                <div class="stadium-legend-bar">
                    <div class="legend-item">
                        <div class="legend-dot-label">
                            <div class="legend-color-dot" style="background-color: #d97706;"></div>
                            VIP - $<?php echo number_format($prices['VIP'], 0); ?>
                        </div>
                        <div class="legend-desc">Frente al escenario • <strong><?php echo isset($zonesInfo['VIP']) ? $zonesInfo['VIP']['totales'] : 0; ?> asientos</strong></div>
                    </div>

                    <div class="legend-item">
                        <div class="legend-dot-label">
                            <div class="legend-color-dot" style="background-color: #2563eb;"></div>
                            Zona A - $<?php echo number_format($prices['Zona A'], 0); ?>
                        </div>
                        <div class="legend-desc">Cerca del escenario • <strong><?php echo isset($zonesInfo['Zona A']) ? $zonesInfo['Zona A']['totales'] : 0; ?> asientos</strong></div>
                    </div>

                    <div class="legend-item">
                        <div class="legend-dot-label">
                            <div class="legend-color-dot" style="background-color: #10b981;"></div>
                            Zona B - $<?php echo number_format($prices['Zona B'], 0); ?>
                        </div>
                        <div class="legend-desc">Zona media • <strong><?php echo isset($zonesInfo['Zona B']) ? $zonesInfo['Zona B']['totales'] : 0; ?> asientos</strong></div>
                    </div>

                    <div class="legend-item">
                        <div class="legend-dot-label">
                            <div class="legend-color-dot" style="background-color: #f97316;"></div>
                            Zona C - $<?php echo number_format($prices['Zona C'], 0); ?>
                        </div>
                        <div class="legend-desc">Zona posterior • <strong><?php echo isset($zonesInfo['Zona C']) ? $zonesInfo['Zona C']['totales'] : 0; ?> asientos</strong></div>
                    </div>

                    <div class="legend-item">
                        <div class="legend-dot-label">
                            <div class="legend-color-dot" style="background-color: #8b5cf6;"></div>
                            Zona D - $<?php echo number_format($prices['Zona D'], 0); ?>
                        </div>
                        <div class="legend-desc">Últimas filas • <strong><?php echo isset($zonesInfo['Zona D']) ? $zonesInfo['Zona D']['totales'] : 0; ?> asientos</strong></div>
                    </div>
                </div>
            </div>

            <!-- Columna Derecha: Sidebar de Tu Selección -->
            <div class="selection-sidebar">
                <div class="selection-header-row">
                    <h3>Tu selección</h3>
                    <div class="selection-count" id="selectionLabel">0 / <?php echo $espacios; ?></div>
                </div>

                <!-- Leyenda de Estados en el Sidebar -->
                <div class="status-legend-sidebar">
                    <div class="status-legend-item">
                        <div class="status-legend-box" style="background-color: #ef4444;"></div>
                        <span>Vendido</span>
                    </div>
                    <div class="status-legend-item">
                        <div class="status-legend-box" style="background-color: #f59e0b;"></div>
                        <span>Tu asiento</span>
                    </div>
                    <div class="status-legend-item">
                        <div class="status-legend-box" style="background-color: #10b981;"></div>
                        <span>Disponible</span>
                    </div>
                    <div class="status-legend-item">
                        <div class="status-legend-box" style="background-color: #9ca3af;"></div>
                        <span>Bloqueado</span>
                    </div>
                </div>

                <!-- Lista de asientos agregados dinámicamente -->
                <div class="selected-list" id="selectedListContainer">
                    <div style="color: rgba(255,255,255,0.6); text-align: center; margin-top: 40px; font-weight: 500; font-size: 0.95rem;" id="noSelectionText">
                        Ningún asiento seleccionado.<br>Haz clic en los asientos disponibles del mapa.
                    </div>
                </div>

                <!-- Fila de Total -->
                <div class="total-row">
                    <span class="total-label">Total</span>
                    <span class="total-value" id="totalLabel">$0 MXN</span>
                </div>

                <!-- Botón de Reservar -->
                <button type="button" id="btnReserve" class="btn-reserve" disabled onclick="submitSeatsSelection()">Reservar</button>
            </div>
        </div>
    </div>

    <!-- Footer -->
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

    <!-- Script del Mapa Interactivo con Bloqueo en Tiempo Real entre Ventanas (F1) -->
    <script>
        const eventId = <?php echo (int)$event_id; ?>;
        const maxSeats = <?php echo (int)$espacios; ?>;
        const selectedSeats = new Map(); // Guarda {seatId => {zone, price}}
        let lockedZone = null;

        // Generar un token único por pestaña/ventana (incluso dentro del mismo navegador)
        if (!window.name || !window.name.startsWith('agora_tab_')) {
            window.name = 'agora_tab_' + Math.random().toString(36).substring(2, 12) + '_' + Date.now();
        }
        const clientLockToken = window.name;

        // Aplicar en el mapa SVG los asientos vendidos (rojo) y los bloqueados por otra ventana (gris)
        function applyRemoteSeatStatus(soldList, blockedList) {
            const soldSet = new Set(soldList || []);
            const blockedSet = new Set(blockedList || []);
            let removedAny = false;

            document.querySelectorAll('.seat').forEach(seat => {
                const id = seat.getAttribute('data-id');

                if (soldSet.has(id)) {
                    seat.classList.remove('available', 'selected', 'blocked');
                    seat.classList.add('sold');
                    seat.setAttribute('fill', '#ef4444');
                    if (selectedSeats.has(id)) {
                        selectedSeats.delete(id);
                        removedAny = true;
                    }
                } else if (blockedSet.has(id)) {
                    // Bloqueado en tiempo real por otra persona (ej. Susana)
                    seat.classList.remove('available', 'selected');
                    seat.classList.add('blocked');
                    if (selectedSeats.has(id)) {
                        selectedSeats.delete(id);
                        removedAny = true;
                    }
                } else {
                    // Si antes estaba bloqueado por otro y ya lo soltó, restaurarlo a disponible
                    if (seat.classList.contains('blocked')) {
                        seat.classList.remove('blocked');
                        if (!selectedSeats.has(id)) {
                            seat.classList.add('available');
                        }
                    }
                }
            });

            if (removedAny) {
                if (selectedSeats.size === 0) {
                    lockedZone = null;
                }
                refreshZoneDisableState();
                updateSidebar();
            }
        }

        function refreshZoneDisableState() {
            if (selectedSeats.size === 0) {
                lockedZone = null;
                document.querySelectorAll('.seat').forEach(s => {
                    if (!s.classList.contains('blocked') && !s.classList.contains('sold')) {
                        s.classList.remove('disabled-zone');
                    }
                });
            } else {
                document.querySelectorAll('.seat').forEach(s => {
                    if (s.getAttribute('data-zone') !== lockedZone) {
                        s.classList.add('disabled-zone');
                    } else if (!s.classList.contains('blocked') && !s.classList.contains('sold')) {
                        s.classList.remove('disabled-zone');
                    }
                });
            }
        }

        async function syncSeatStatus() {
            try {
                const res = await fetch(`../php/seat_lock.php?action=status&event_id=${eventId}&token=${encodeURIComponent(clientLockToken)}`, {
                    cache: 'no-store'
                });
                if (!res.ok) return;
                const data = await res.json();
                if (data.success) {
                    applyRemoteSeatStatus(data.sold, data.blocked);
                }
            } catch (e) {
                // Ignorar error de red transitorio en polling
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const seats = document.querySelectorAll('.seat');

            // Liberar bloqueos previos de esta misma pestaña al cargar mapa limpio
            fetch('../php/seat_lock.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'unlock_all', event_id: eventId, token: clientLockToken })
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    applyRemoteSeatStatus(data.sold, data.blocked);
                }
            }).catch(() => {});

            // Sincronización en tiempo real cada 1.5 segundos entre ventanas
            setInterval(syncSeatStatus, 1500);

            seats.forEach(seat => {
                seat.addEventListener('click', async () => {
                    const id = seat.getAttribute('data-id');
                    const zone = seat.getAttribute('data-zone');
                    const price = parseFloat(seat.getAttribute('data-price'));

                    // Si está vendido o bloqueado por otro usuario, rechazar clic
                    if (seat.classList.contains('sold')) {
                        alert(`El asiento ${id} ya fue vendido.`);
                        return;
                    }
                    if (seat.classList.contains('blocked')) {
                        alert(`El asiento ${id} ya está siendo seleccionado por otra persona en este momento.`);
                        return;
                    }

                    // Validar bloqueo de zona
                    if (lockedZone && lockedZone !== zone) {
                        alert(`Para mantener consistencia en tu tarifa, todos los boletos seleccionados deben pertenecer a la misma zona (${lockedZone}).`);
                        return;
                    }

                    if (selectedSeats.has(id)) {
                        // Deseleccionar y liberar en el servidor para que otros usuarios puedan tomarlo
                        selectedSeats.delete(id);
                        seat.classList.remove('selected');
                        refreshZoneDisableState();
                        updateSidebar();

                        try {
                            const res = await fetch('../php/seat_lock.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ action: 'unlock', event_id: eventId, seat_id: id, token: clientLockToken })
                            });
                            const data = await res.json();
                            if (data.sold || data.blocked) {
                                applyRemoteSeatStatus(data.sold, data.blocked);
                            }
                        } catch (e) {}
                    } else {
                        // Seleccionar (si no excede el límite)
                        if (selectedSeats.size >= maxSeats) {
                            alert(`Solo puedes elegir un máximo de ${maxSeats} boletos.`);
                            return;
                        }

                        // Bloquear inmediatamente en el servidor antes de confirmar la selección
                        seat.style.pointerEvents = 'none';
                        try {
                            const res = await fetch('../php/seat_lock.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ action: 'lock', event_id: eventId, seat_id: id, token: clientLockToken })
                            });
                            const data = await res.json();
                            seat.style.pointerEvents = '';

                            if (data.sold || data.blocked) {
                                applyRemoteSeatStatus(data.sold, data.blocked);
                            }

                            if (!data.success) {
                                alert(data.message || `El asiento ${id} acaba de ser ocupado por otro usuario.`);
                                return;
                            }

                            selectedSeats.set(id, { zone, price });
                            seat.classList.add('selected');
                            lockedZone = zone;
                            refreshZoneDisableState();
                            updateSidebar();
                        } catch (e) {
                            seat.style.pointerEvents = '';
                            alert('Error de conexión al verificar disponibilidad del asiento.');
                        }
                    }
                });
            });
        });

        // Actualizar el listado lateral
        function updateSidebar() {
            const container = document.getElementById('selectedListContainer');
            const noSelectionText = document.getElementById('noSelectionText');
            const label = document.getElementById('selectionLabel');
            const totalLabel = document.getElementById('totalLabel');
            const btnReserve = document.getElementById('btnReserve');

            // Vaciar
            container.innerHTML = '';
            
            label.textContent = `${selectedSeats.size} / ${maxSeats}`;

            if (selectedSeats.size === 0) {
                container.appendChild(noSelectionText);
                totalLabel.textContent = '$0 MXN';
                btnReserve.disabled = true;
                return;
            }

            let total = 0;
            selectedSeats.forEach((val, id) => {
                total += val.price;

                const card = document.createElement('div');
                card.className = 'selected-seat-card';
                card.innerHTML = `
                    <div class="seat-name-price">
                        <span class="seat-code">${id}</span>
                        <span class="seat-zone-price">${val.zone} — $${val.price.toFixed(0)} MXN</span>
                    </div>
                    <button class="btn-delete-seat" onclick="removeSeat('${id}')">Eliminar</button>
                `;
                container.appendChild(card);
            });

            totalLabel.textContent = `$${total.toFixed(0)} MXN`;
            btnReserve.disabled = (selectedSeats.size !== maxSeats);
        }

        // Eliminar asiento individual y desbloquearlo en tiempo real en el servidor
        async function removeSeat(id) {
            if (selectedSeats.has(id)) {
                selectedSeats.delete(id);
                const seatCircle = document.querySelector(`.seat[data-id="${id}"]`);
                if (seatCircle) {
                    seatCircle.classList.remove('selected');
                }

                refreshZoneDisableState();
                updateSidebar();

                try {
                    const res = await fetch('../php/seat_lock.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'unlock', event_id: eventId, seat_id: id, token: clientLockToken })
                    });
                    const data = await res.json();
                    if (data.sold || data.blocked) {
                        applyRemoteSeatStatus(data.sold, data.blocked);
                    }
                } catch (e) {}
            }
        }

        // Enviar la selección pasando el token de bloqueo de esta ventana
        function submitSeatsSelection() {
            if (selectedSeats.size !== maxSeats) {
                alert(`Por favor, selecciona exactamente ${maxSeats} asientos antes de continuar.`);
                return;
            }

            const chosenSeatIds = Array.from(selectedSeats.keys());
            const seatsStr = chosenSeatIds.join(', ');

            // Redirigir a confirmar_reserva.php conservando el lock_token de esta ventana
            window.location.href = `confirmar_reserva.php?event_id=<?php echo $event_id; ?>&espacios=${maxSeats}&asientos=${encodeURIComponent(seatsStr)}&lock_token=${encodeURIComponent(clientLockToken)}`;
        }
    </script>
</body>
</html>
