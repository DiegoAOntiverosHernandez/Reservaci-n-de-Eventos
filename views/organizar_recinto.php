<?php
require_once __DIR__ . '/../php/auth.php';

// Exigir inicio de sesión
requireLogin();

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$capacidad = isset($_GET['capacidad']) ? (int)$_GET['capacidad'] : 25;

if ($event_id <= 0) {
    die('<h2>Error</h2><p>ID de evento inválido.</p>');
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
    <title>Organiza tu Recinto - Agora</title>
    <link rel="shortcut icon" href="../Assets/img/logo.png" type="image/x-icon">
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/styles_events/organizar_recinto.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="nav">
        <div class="logo"><a href="../index.php"><img src="../Assets/img/title.png" alt="Agora"></a></div>
    </div>

    <div class="organize-container">
        <div class="organize-header">
            <h2>✍️ Organiza tu recinto</h2>
            <p>Establece el precio por zona antes de publicar tu evento</p>
        </div>

        <div class="layout-grid">
            <!-- Columna Izquierda: Mapa del estadio y Leyendas -->
            <div class="map-card-wrapper">
                <div class="stadium-map-container">
                    <!-- Mapa Vectorial SVG de Alta Fidelidad -->
                    <svg class="stadium-svg" viewBox="0 0 400 320" xmlns="http://www.w3.org/2000/svg">
                        <!-- Definiciones para gradientes premium del escenario -->
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

                            <!-- Zona VIP (Amarillo Mostaza/Dorado - Rectángulo Centrado) -->
                            <rect x="110" y="85" width="180" height="40" rx="6" fill="#d97706" opacity="0.85" stroke="#b45309" stroke-width="2" />
                            <text x="200" y="110" fill="white" font-family="Outfit" font-size="14" font-weight="bold" text-anchor="middle">VIP</text>

                            <!-- Zona B (Verde Esmeralda - Rectángulo Centrado) -->
                            <rect x="110" y="140" width="180" height="45" rx="6" fill="#10b981" opacity="0.85" stroke="#047857" stroke-width="2" />
                            <text x="200" y="168" fill="white" font-family="Outfit" font-size="16" font-weight="bold" text-anchor="middle">B</text>

                            <!-- Zona C (Naranja - Rectángulo Centrado) -->
                            <rect x="90" y="200" width="220" height="45" rx="6" fill="#f97316" opacity="0.85" stroke="#ea580c" stroke-width="2" />
                            <text x="200" y="228" fill="white" font-family="Outfit" font-size="16" font-weight="bold" text-anchor="middle">C</text>

                            <!-- Zona D (Púrpura - Rectángulo Centrado) -->
                            <rect x="90" y="260" width="220" height="40" rx="6" fill="#8b5cf6" opacity="0.85" stroke="#6d28d9" stroke-width="2" />
                            <text x="200" y="285" fill="white" font-family="Outfit" font-size="14" font-weight="bold" text-anchor="middle">D</text>

                            <!-- Zona A (Azul - Columnas Laterales / Balcones) -->
                            <!-- Balcón Izquierdo -->
                            <rect x="35" y="85" width="60" height="100" rx="6" fill="#2563eb" opacity="0.85" stroke="#1d4ed8" stroke-width="2" />
                            <text x="65" y="142" fill="white" font-family="Outfit" font-size="18" font-weight="bold" text-anchor="middle">A</text>
                            <!-- Balcón Derecho -->
                            <rect x="305" y="85" width="60" height="100" rx="6" fill="#2563eb" opacity="0.85" stroke="#1d4ed8" stroke-width="2" />
                            <text x="335" y="142" fill="white" font-family="Outfit" font-size="18" font-weight="bold" text-anchor="middle">A</text>

                        <?php else: ?>
                            <!-- ESTRUCTURA CONCÉNTRICA PARA ESTADIO -->
                            <!-- Oval exterior del estadio -->
                            <ellipse cx="200" cy="160" rx="180" ry="140" fill="none" stroke="#94a3b8" stroke-width="8" />
                            <ellipse cx="200" cy="160" rx="170" ry="130" fill="none" stroke="#cbd5e1" stroke-width="4" />
                            <ellipse cx="200" cy="160" rx="150" ry="110" fill="#f8fafc" stroke="#e2e8f0" stroke-width="3" />
                            
                            <!-- Escenario Ovalado con Neón -->
                            <rect x="110" y="48" width="180" height="24" rx="6" fill="url(#stageGrad)" stroke="url(#neonGlow)" stroke-width="2" />
                            <rect x="120" y="68" width="160" height="4" rx="2" fill="#10b981" opacity="0.8" />
                            <text x="200" y="63" fill="#f8fafc" font-family="Outfit" font-size="10" font-weight="800" letter-spacing="2" text-anchor="middle">★ ESCENARIO ★</text>

                            <!-- Zona VIP (Amarillo Mostaza/Dorado - Frente al escenario) -->
                            <path d="M 130 90 A 90 40 0 0 1 270 90 C 270 120, 130 120, 130 90 Z" fill="#d97706" opacity="0.85" stroke="#b45309" stroke-width="2" />
                            <text x="200" y="112" fill="white" font-family="Outfit" font-size="16" font-weight="bold" text-anchor="middle">VIP</text>

                            <!-- Zona B (Verde Esmeralda - Zona Media) -->
                            <path d="M 110 135 C 110 135, 110 195, 200 195 C 290 195, 290 135, 290 135 C 290 135, 260 155, 200 155 C 140 155, 110 135, 110 135 Z" fill="#10b981" opacity="0.85" stroke="#047857" stroke-width="2" />
                            <text x="200" y="178" fill="white" font-family="Outfit" font-size="20" font-weight="bold" text-anchor="middle">B</text>

                            <!-- Zona C (Naranja - Zona Posterior) -->
                            <path d="M 100 205 C 100 205, 100 245, 200 245 C 300 245, 300 205, 300 205 C 300 205, 270 220, 200 220 C 130 220, 100 205, 100 205 Z" fill="#f97316" opacity="0.85" stroke="#ea580c" stroke-width="2" />
                            <text x="200" y="232" fill="white" font-family="Outfit" font-size="18" font-weight="bold" text-anchor="middle">C</text>

                            <!-- Zona D (Púrpura - Últimas Filas) -->
                            <path d="M 90 255 C 90 255, 90 285, 200 285 C 310 285, 310 255, 310 255 C 310 255, 280 268, 200 268 C 120 268, 90 255, 90 255 Z" fill="#8b5cf6" opacity="0.85" stroke="#6d28d9" stroke-width="2" />
                            <text x="200" y="276" fill="white" font-family="Outfit" font-size="16" font-weight="bold" text-anchor="middle">D</text>

                            <!-- Zona A (Azul - Laterales) -->
                            <!-- Lateral Izquierdo -->
                            <path d="M 40 130 C 40 130, 45 230, 80 230 C 80 230, 85 180, 85 130 C 85 130, 60 130, 40 130 Z" fill="#2563eb" opacity="0.85" stroke="#1d4ed8" stroke-width="2" />
                            <text x="62" y="185" fill="white" font-family="Outfit" font-size="20" font-weight="bold" text-anchor="middle">A</text>
                            <!-- Lateral Derecho -->
                            <path d="M 360 130 C 360 130, 355 230, 320 230 C 320 230, 315 180, 315 130 C 315 130, 340 130, 360 130 Z" fill="#2563eb" opacity="0.85" stroke="#1d4ed8" stroke-width="2" />
                            <text x="338" y="185" fill="white" font-family="Outfit" font-size="20" font-weight="bold" text-anchor="middle">A</text>
                        <?php endif; ?>
                    </svg>
                </div>

                <!-- Leyendas de Zonas -->
                <div class="stadium-legend-bar">
                    <div class="legend-item">
                        <div class="legend-dot-label">
                            <div class="legend-color-dot" style="background-color: #d97706;"></div>
                            VIP
                        </div>
                        <div class="legend-desc">Zona premium<br>Frente al escenario</div>
                    </div>

                    <div class="legend-item">
                        <div class="legend-dot-label">
                            <div class="legend-color-dot" style="background-color: #2563eb;"></div>
                            Zona A
                        </div>
                        <div class="legend-desc">Excelente visibilidad<br>Cerca del escenario</div>
                    </div>

                    <div class="legend-item">
                        <div class="legend-dot-label">
                            <div class="legend-color-dot" style="background-color: #10b981;"></div>
                            Zona B
                        </div>
                        <div class="legend-desc">Buena visibilidad<br>Zona media</div>
                    </div>

                    <div class="legend-item">
                        <div class="legend-dot-label">
                            <div class="legend-color-dot" style="background-color: #f97316;"></div>
                            Zona C
                        </div>
                        <div class="legend-desc">Visibilidad general<br>Zona posterior</div>
                    </div>

                    <div class="legend-item">
                        <div class="legend-dot-label">
                            <div class="legend-color-dot" style="background-color: #8b5cf6;"></div>
                            Zona D
                        </div>
                        <div class="legend-desc">Visibilidad general<br>Últimas filas</div>
                    </div>
                </div>
            </div>

            <!-- Columna Derecha: Sidebar de Precios -->
            <div class="pricing-sidebar">
                <h3>Zona y precios</h3>
                
                <form id="pricingForm">
                    <!-- Tarjeta VIP -->
                    <div class="zone-input-card vip">
                        <div class="zone-title-wrapper">
                            <div class="zone-square" style="background-color: #d97706;"></div>
                            <span>Zona VIP</span>
                        </div>
                        <div class="price-input-row">
                            <span>$</span>
                            <input type="number" id="priceVIP" class="price-input" placeholder="0.00" min="0" value="500" required>
                            <span>MXN</span>
                        </div>
                    </div>

                    <!-- Tarjeta Zona A -->
                    <div class="zone-input-card zona-a">
                        <div class="zone-title-wrapper">
                            <div class="zone-square" style="background-color: #2563eb;"></div>
                            <span>Zona A</span>
                        </div>
                        <div class="price-input-row">
                            <span>$</span>
                            <input type="number" id="priceA" class="price-input" placeholder="0.00" min="0" value="300" required>
                            <span>MXN</span>
                        </div>
                    </div>

                    <!-- Tarjeta Zona B -->
                    <div class="zone-input-card zona-b">
                        <div class="zone-title-wrapper">
                            <div class="zone-square" style="background-color: #10b981;"></div>
                            <span>Zona B</span>
                        </div>
                        <div class="price-input-row">
                            <span>$</span>
                            <input type="number" id="priceB" class="price-input" placeholder="0.00" min="0" value="200" required>
                            <span>MXN</span>
                        </div>
                    </div>

                    <!-- Tarjeta Zona C -->
                    <div class="zone-input-card zona-c">
                        <div class="zone-title-wrapper">
                            <div class="zone-square" style="background-color: #f97316;"></div>
                            <span>Zona C</span>
                        </div>
                        <div class="price-input-row">
                            <span>$</span>
                            <input type="number" id="priceC" class="price-input" placeholder="0.00" min="0" value="150" required>
                            <span>MXN</span>
                        </div>
                    </div>

                    <!-- Tarjeta Zona D -->
                    <div class="zone-input-card zona-d">
                        <div class="zone-title-wrapper">
                            <div class="zone-square" style="background-color: #8b5cf6;"></div>
                            <span>Zona D</span>
                        </div>
                        <div class="price-input-row">
                            <span>$</span>
                            <input type="number" id="priceD" class="price-input" placeholder="0.00" min="0" value="100" required>
                            <span>MXN</span>
                        </div>
                    </div>

                    <!-- Botón de publicar -->
                    <button type="submit" id="btnPublish" class="btn-publish">Publicar</button>
                </form>
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

    <!-- Script de Configuración -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('pricingForm');
            const btnPublish = document.getElementById('btnPublish');

            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                const prices = {
                    'VIP': parseFloat(document.getElementById('priceVIP').value),
                    'Zona A': parseFloat(document.getElementById('priceA').value),
                    'Zona B': parseFloat(document.getElementById('priceB').value),
                    'Zona C': parseFloat(document.getElementById('priceC').value),
                    'Zona D': parseFloat(document.getElementById('priceD').value)
                };

                const dataToSend = {
                    event_id: <?php echo $event_id; ?>,
                    capacidad: <?php echo $capacidad; ?>,
                    precios: prices
                };

                btnPublish.disabled = true;
                btnPublish.textContent = 'Publicando evento...';

                try {
                    const response = await fetch('../php/setup_zones.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(dataToSend)
                    });

                    const result = await response.json();

                    if (result.success) {
                        alert('¡Felicidades! ' + result.message);
                        window.location.href = 'mi_evento.php';
                    } else {
                        alert('Error al publicar: ' + result.message);
                        btnPublish.disabled = false;
                        btnPublish.textContent = 'Publicar';
                    }

                } catch (error) {
                    console.error(error);
                    alert('Hubo un error de conexión con el servidor.');
                    btnPublish.disabled = false;
                    btnPublish.textContent = 'Publicar';
                }
            });
        });
    </script>
</body>
</html>
