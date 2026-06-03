<?php
require_once __DIR__ . '/../php/auth.php';

// Exigir inicio de sesión
requireLogin();

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$id_usuario = getCurrentUserId();

if ($event_id <= 0) {
    die('<h2>Error</h2><p>ID de evento inválido.</p>');
}

try {
    // 1. Obtener detalles del evento y categoría y zona boleto
    $stmt = $pdo->prepare("
        SELECT e.*, c.nombre_categoria,
               COALESCE(z.id_zona, 0) AS id_zona,
               COALESCE(z.nombre_zona, 'General') AS nombre_zona,
               COALESCE(z.precio, 0.00) AS precio_zona,
               COALESCE(z.lugares_totales, e.capacidad_total) AS cap_total,
               COALESCE(z.lugares_disponibles, 0) AS cap_disponible
        FROM Evento e
        LEFT JOIN Categoria c ON e.id_categoria = c.id_categoria
        LEFT JOIN Zona_Boleto z ON e.id_evento = z.id_evento AND z.nombre_zona = 'Zona B'
        WHERE e.id_evento = ?
        LIMIT 1
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        // Fallback si no hay Zona B, obtener la primera zona disponible
        $stmtFallback = $pdo->prepare("
            SELECT e.*, c.nombre_categoria,
                   COALESCE(z.id_zona, 0) AS id_zona,
                   COALESCE(z.nombre_zona, 'General') AS nombre_zona,
                   COALESCE(z.precio, 0.00) AS precio_zona,
                   COALESCE(z.lugares_totales, e.capacidad_total) AS cap_total,
                   COALESCE(z.lugares_disponibles, 0) AS cap_disponible
            FROM Evento e
            LEFT JOIN Categoria c ON e.id_categoria = c.id_categoria
            LEFT JOIN Zona_Boleto z ON e.id_evento = z.id_evento
            WHERE e.id_evento = ?
            LIMIT 1
        ");
        $stmtFallback->execute([$event_id]);
        $event = $stmtFallback->fetch();
    }

    if (!$event) {
        die('<h2>Error</h2><p>El evento solicitado no existe.</p>');
    }

    $id_zona = (int)$event['id_zona'];
    $disponibles = (int)$event['cap_disponible'];
    $totales = (int)$event['cap_total'];

    // Si el total de cupos es 0 (ej. aún no tiene zonas), le damos un fallback elegante
    if ($totales <= 0) {
        $totales = 54;
        $disponibles = 40;
    }

    // 2. Obtener el itinerario del evento
    $stmtIt = $pdo->prepare("SELECT * FROM Itinerario WHERE id_evento = ? ORDER BY hora_inicio ASC");
    $stmtIt->execute([$event_id]);
    $itinerary = $stmtIt->fetchAll();

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
    <title>Agora - <?php echo htmlspecialchars($event['nombre_evento']); ?></title>
    <link rel="shortcut icon" href="../Assets/img/logo.png" type="image/x-icon">
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/styles_events/reservar.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="nav">
        <div class="logo"><a href="../index.php"><img src="../Assets/img/title.png" alt="Agora"></a></div>
    </div>

    <!-- Banner Hero del Evento -->
    <div class="event-details-hero" style="background-image: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.5)), url('<?php echo htmlspecialchars("../" . ($event['imagen_banner'] ?: "Assets/img/img_home.png")); ?>');">
        <h1><?php echo htmlspecialchars($event['nombre_evento']); ?></h1>
        <p>🏷️ <?php echo htmlspecialchars($event['nombre_categoria'] ?: 'General'); ?></p>
    </div>

    <div class="details-layout">
        <!-- Columna Izquierda: Información General del Evento -->
        <div class="left-column">
            <h2>Sobre el evento</h2>
            <p class="about-event-text">
                <?php echo nl2br(htmlspecialchars($event['descripcion'] ?: 'No hay descripción disponible para este evento. Ven y disfruta de una experiencia única junto a la comunidad de Agora. Asegura tus boletos desde la plataforma.')); ?>
            </p>

            <div class="details-grid">
                <div class="detail-item">
                    <div class="detail-icon">📅</div>
                    <div class="detail-text-block">
                        <span class="detail-title">Fecha</span>
                        <span class="detail-value">
                            <?php 
                            $timestamp = strtotime($event['fecha']);
                            $diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                            $meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
                            echo $diasSemana[date('w', $timestamp)] . " " . date('j', $timestamp) . " " . $meses[(int)date('m', $timestamp) - 1] . ", " . date('Y', $timestamp);
                            ?>
                        </span>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-icon">🕒</div>
                    <div class="detail-text-block">
                        <span class="detail-title">Horario</span>
                        <span class="detail-value">
                            <?php echo date('h:i a', strtotime($event['hora_inicio'])) . ' - ' . date('h:i a', strtotime($event['hora_cierre'])); ?>
                        </span>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-icon">👥</div>
                    <div class="detail-text-block">
                        <span class="detail-title">Espacios</span>
                        <span class="detail-value"><?php echo $disponibles; ?> / <?php echo $totales; ?> espacios libres</span>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-icon">ℹ️</div>
                    <div class="detail-text-block">
                        <span class="detail-title">Estado</span>
                        <span class="detail-value">
                            <span class="status-badge">
                                <?php echo ucfirst($event['estado']); ?>
                            </span>
                        </span>
                    </div>
                </div>

                <div class="detail-item" style="grid-column: 1 / -1;">
                    <div class="detail-icon">📍</div>
                    <div class="detail-text-block">
                        <span class="detail-title">Ubicación</span>
                        <span class="detail-value"><?php echo htmlspecialchars($event['ubicacion']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Sección de Itinerario Estilizado -->
            <?php if (!empty($itinerary)): ?>
                <h2>Itinerario</h2>
                <div class="itinerary-timeline">
                    <?php 
                    $index = 0;
                    foreach ($itinerary as $item): 
                        $index++;
                        // El 3er elemento o la sección que contenga "Inicio" se marca como "En curso" para simular
                        $isInProgress = ($index === 3 || stripos($item['nombre_seccion'], 'inicio') !== false);
                    ?>
                        <div class="itinerary-row <?php echo $isInProgress ? 'in-progress' : ''; ?>">
                            <span class="itinerary-time"><?php echo date('h:i a', strtotime($item['hora_inicio'])); ?></span>
                            <span class="itinerary-section-name">
                                <?php echo htmlspecialchars($item['nombre_seccion']); ?>
                            </span>
                            <?php if ($isInProgress): ?>
                                <span class="in-progress-label">
                                    <span>➔</span> En curso
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Columna Derecha: Mapa + Compra y Notificaciones -->
        <div class="sidebar">
            <!-- Google Maps API Embed Widget (100% Funcional sin Keys) -->
            <div class="maps-card">
                <iframe width="100%" height="220" style="border:0;" allowfullscreen="" loading="lazy" src="https://maps.google.com/maps?q=<?php echo urlencode($event['ubicacion']); ?>&t=&z=14&ie=UTF8&iwloc=&output=embed"></iframe>
            </div>

            <!-- Widget de Compra -->
            <div class="booking-widget-card">
                <h3>Reserva de entradas</h3>
                
                <form id="preReservationForm">
                    <div style="margin-bottom: 20px; display: flex; flex-direction: column; gap: 8px;">
                        <label for="espacios" style="font-size: 0.9rem; font-weight: 600; color: #475569;">Cantidad de boletos a reservar:</label>
                        <select id="espacios" style="padding: 12px; border-radius: 8px; border: 1px solid #cbd5e1; font-family: inherit; font-size: 0.95rem; font-weight: 600; cursor: pointer;">
                            <option value="1">1 boleto</option>
                            <option value="2">2 boletos</option>
                            <option value="3" selected>3 boletos</option>
                            <option value="4">4 boletos</option>
                            <option value="5">5 boletos</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-view-tickets">Ver boletos</button>
                </form>
            </div>

            <!-- Banner Promocional Notificaciones de App Agora (Imagen de publicidad cargada) -->
            <div class="app-info-card" style="border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px rgba(9, 20, 48, 0.15); border: 1px solid #e2e8f0; width: 100%;">
                <img src="../Assets/img/info_app.png" alt="¡Recibe notificaciones de tus reservaciones!" style="width: 100%; display: block; height: auto;">
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

    <!-- Script de Redirección a la selección de asientos -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('preReservationForm');
            const selectEspacios = document.getElementById('espacios');

            form.addEventListener('submit', (e) => {
                e.preventDefault();
                const espacios = selectEspacios.value;
                // Redirigir a la pantalla de Selección de Asientos del Estadio
                window.location.href = `seleccionar_asientos.php?event_id=<?php echo $event_id; ?>&espacios=${espacios}`;
            });
        });
    </script>
</body>
</html>
