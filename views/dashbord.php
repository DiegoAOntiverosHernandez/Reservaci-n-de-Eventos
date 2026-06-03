<?php
require_once __DIR__ . '/../php/auth.php';

// Exigir inicio de sesión
requireLogin();

$id_organizador = getCurrentUserId();
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

// Si no se proporciona un ID de evento, obtener el más reciente del organizador
if ($event_id <= 0) {
    try {
        $stmt = $pdo->prepare("SELECT id_evento FROM Evento WHERE id_organizador = ? ORDER BY fecha DESC, hora_inicio DESC LIMIT 1");
        $stmt->execute([$id_organizador]);
        $latest = $stmt->fetch();
        if ($latest) {
            $event_id = (int)$latest['id_evento'];
        } else {
            // Si el organizador no tiene eventos, redirigir a mi_evento.php
            header('Location: mi_evento.php');
            exit;
        }
    } catch (Exception $e) {
        header('Location: mi_evento.php');
        exit;
    }
}

try {
    // 1. Obtener detalles del evento
    $stmt = $pdo->prepare("
        SELECT e.*, c.nombre_categoria, r.nombre_recinto, u.correo_electronico AS email_organizador
        FROM Evento e
        LEFT JOIN Categoria c ON e.id_categoria = c.id_categoria
        LEFT JOIN Recinto r ON e.id_recinto = r.id_recinto
        LEFT JOIN Usuario u ON e.id_organizador = u.id_usuario
        WHERE e.id_evento = ?
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        die("<h2>Error</h2><p>El evento solicitado no existe.</p>");
    }

    // Validar propiedad del evento (Solo el organizador creador o el administrador pueden verlo)
    $user_role = $_SESSION['usuario_rol'] ?? 'cliente';
    if ($user_role !== 'administrador' && (int)$event['id_organizador'] !== $id_organizador) {
        die("<h2>Acceso denegado</h2><p>No tienes los permisos necesarios para gestionar este evento.</p>");
    }

    // 2. Calcular la Capacidad Total real
    $stmtCap = $pdo->prepare("SELECT SUM(lugares_totales) FROM Zona_Boleto WHERE id_evento = ?");
    $stmtCap->execute([$event_id]);
    $sum_zones = $stmtCap->fetchColumn();
    $capacidad_total = ($sum_zones !== null && $sum_zones > 0) ? (int)$sum_zones : (int)$event['capacidad_total'];
    if ($capacidad_total <= 0) {
        $capacidad_total = 1;
    }

    // 3. Calcular Boletos Vendidos
    $stmtSold = $pdo->prepare("
        SELECT COALESCE(SUM(r.espacios_reservados), 0)
        FROM Reservacion r
        JOIN Zona_Boleto z ON r.id_zona = z.id_zona
        WHERE z.id_evento = ? AND r.estado_reservacion = 'Confirmado'
    ");
    $stmtSold->execute([$event_id]);
    $boletos_vendidos = (int)$stmtSold->fetchColumn();

    // 4. Calcular Ingresos Generados
    $stmtRev = $pdo->prepare("
        SELECT COALESCE(SUM(r.espacios_reservados * z.precio), 0.00)
        FROM Reservacion r
        JOIN Zona_Boleto z ON r.id_zona = z.id_zona
        WHERE z.id_evento = ? AND r.estado_reservacion = 'Confirmado'
    ");
    $stmtRev->execute([$event_id]);
    $ingresos_totales = (float)$stmtRev->fetchColumn();

    // 5. Ocupación
    $porcentaje_ocupacion = round(($boletos_vendidos / $capacidad_total) * 100, 2);

    // 6. Incidencias
    $stmtIncCount = $pdo->prepare("SELECT COUNT(*) FROM Incidencia WHERE id_evento = ?");
    $stmtIncCount->execute([$event_id]);
    $incidencias_total = (int)$stmtIncCount->fetchColumn();

    // 7. Desglose de Zonas para gráficos
    $stmtZones = $pdo->prepare("
        SELECT z.id_zona, z.nombre_zona, z.precio, z.lugares_totales,
               (SELECT COALESCE(SUM(r.espacios_reservados), 0)
                FROM Reservacion r
                WHERE r.id_zona = z.id_zona AND r.estado_reservacion = 'Confirmado') AS vendidos
        FROM Zona_Boleto z
        WHERE z.id_evento = ?
        ORDER BY z.precio DESC
    ");
    $stmtZones->execute([$event_id]);
    $zones = $stmtZones->fetchAll();

    // 8. Lista de incidencias reportadas
    $stmtInc = $pdo->prepare("
        SELECT i.*, u.nombre AS nombre_usuario, u.foto_perfil
        FROM Incidencia i
        LEFT JOIN Usuario u ON i.id_usuario = u.id_usuario
        WHERE i.id_evento = ?
        ORDER BY i.fecha_reporte DESC
    ");
    $stmtInc->execute([$event_id]);
    $incidencias = $stmtInc->fetchAll();

} catch (Exception $e) {
    die("<h2>Error del Servidor</h2><p>" . htmlspecialchars($e->getMessage()) . "</p>");
}

// Formatear fechas en español
$timestamp = strtotime($event['fecha']);
$diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$mesesLetras = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$mesesCompletos = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

$diaLetra = $diasSemana[date('w', $timestamp)];
$diaNum = date('d', $timestamp);
$mesNum = (int)date('m', $timestamp);
$mesLetra = $mesesLetras[$mesNum - 1];

$fechaFormateadaStr = "$diaLetra " . (int)$diaNum . " $mesLetra";
$fechaCompletaStr = "$diaLetra " . (int)$diaNum . " " . $mesesCompletos[$mesNum - 1] . ", " . date('Y', $timestamp);

$horaInicio = date('h:i a', strtotime($event['hora_inicio']));
$horaCierre = date('h:i a', strtotime($event['hora_cierre']));
$horarioFormateado = "$horaInicio - $horaCierre";

// Calcular timeline progreso
$pubDate = date('Y-m-d', strtotime($event['fecha'] . ' - 15 days'));
$today = date('Y-m-d');
$eventDate = $event['fecha'];

$pubTime = strtotime($pubDate);
$todayTime = strtotime($today);
$eventTime = strtotime($eventDate);

$totalDuration = $eventTime - $pubTime;
if ($totalDuration <= 0) $totalDuration = 1;
$elapsed = $todayTime - $pubTime;
$elapsedPercentage = ($elapsed / $totalDuration) * 100;
$elapsedPercentage = max(0, min(100, $elapsedPercentage));

$pubDateStr = date('j', $pubTime) . " " . $mesesLetras[(int)date('m', $pubTime) - 1];
$todayStr = date('j', $todayTime) . " " . $mesesLetras[(int)date('m', $todayTime) - 1];
$eventDateStr = date('j', $eventTime) . " " . $mesesLetras[(int)date('m', $eventTime) - 1];

// Paleta de colores temáticos para las zonas
$colorPalette = [
    '#f59e0b', // VIP (Gold)
    '#3b82f6', // Zona A (Blue)
    '#10b981', // Zona B (Green)
    '#f97316', // Zona C (Orange)
    '#8b5cf6', // Zona D (Purple)
    '#ec4899', // Pink
    '#14b8a6', // Teal
    '#6366f1'  // Indigo
];

$maxVendidos = 1;
foreach ($zones as $z) {
    if ($z['vendidos'] > $maxVendidos) {
        $maxVendidos = $z['vendidos'];
    }
}

// Calcular segmentos para el gráfico de dona
$cumulativePercent = 0;
$donutSegments = [];
foreach ($zones as $idx => $z) {
    $revenue = $z['vendidos'] * $z['precio'];
    $percent = $ingresos_totales > 0 ? ($revenue / $ingresos_totales) * 100 : 0;
    if ($percent > 0) {
        $dasharray = "$percent " . (100 - $percent);
        $dashoffset = 25 - $cumulativePercent;
        $donutSegments[] = [
            'color' => $colorPalette[$idx % count($colorPalette)],
            'dasharray' => $dasharray,
            'dashoffset' => $dashoffset,
            'name' => $z['nombre_zona'],
            'revenue' => $revenue,
            'percent' => $percent
        ];
        $cumulativePercent += $percent;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agora - Dashboard de Evento</title>
    <link rel="shortcut icon" href="../Assets/img/logo.png" type="image/x-icon">
    <!-- Google Fonts Outfit & Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/home_styles/nav_footer.css">
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/styles_events/dashbord.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Barra de navegación premium de Agora -->
    <div class="nav">
        <div class="logo"><a href="../index.php"><img src="../Assets/img/title.png" alt="Agora"></a></div>
        <nav class="nav_links">
            <ul class="nav-menus">
                <li class="menu">
                    <button id="btn" class="menu-btn" aria-haspopup="true" aria-expanded="false">
                        Eventos ▾
                    </button>
                    <div class="menu-content">
                        <a href="eventos.php">Eventos próximos y pasados</a>
                        <a href="crear_evento.php">Crear un evento</a>
                        <a href="mi_evento.php">Mis eventos</a>
                    </div>
                </li>
                <li class="menu">
                    <button id="btn" class="menu-btn" aria-haspopup="true" aria-expanded="false">
                        Reservaciones ▾
                    </button>
                    <div class="menu-content">
                        <a href="reservar.php">Reservar evento</a>
                        <a href="historial_r.php">Historial de reservaciones</a>
                    </div>
                </li>
                <li><button class="menu-btn"><a href="calendario.php">Calendario</a></button></li>
                
                <li>
                    <button class="user-welcome" onclick="window.location.href='perfil.php'" style="color: #64b5f6; font-weight: 600;">
                        Hola, <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?>
                    </button>
                </li>
            </ul>
        </nav>
    </div>

    <!-- Contenedor Principal del Dashboard -->
    <div class="dashboard-container">
        
        <!-- Tarjeta de Cabecera del Evento -->
        <header class="card header-card">
            <div class="header-info">
                <img src="<?php echo htmlspecialchars('../' . ($event['imagen_banner'] ?: 'Assets/img/img_home.png')); ?>" alt="Banner" class="header-img">
                <div class="header-text">
                    <h1 id="titulo-evento"><?php echo htmlspecialchars($event['nombre_evento']); ?></h1>
                    <p id="subtitulo-evento">
                        <?php echo htmlspecialchars($event['nombre_categoria']); ?> - 
                        <?php echo htmlspecialchars($fechaFormateadaStr); ?>, 
                        <?php echo htmlspecialchars($horaInicio); ?> - 
                        <?php echo htmlspecialchars($event['nombre_recinto'] ?: $event['ubicacion']); ?>
                    </p>
                </div>
            </div>
            <div class="header-status">
                <span class="badge <?php echo htmlspecialchars($event['estado']); ?>" id="estado-evento-pill">
                    <?php echo ucfirst($event['estado'] === 'activo' ? 'Activo' : ($event['estado'] === 'cancelado' ? 'Cancelado' : 'Finalizado')); ?>
                </span>
            </div>
        </header>

        <h2 class="section-title">Métricas generales</h2>

        <!-- Rejilla de Tarjetas de Métricas -->
        <section class="metrics-grid">
            <div class="card metric-card">
                <div class="metric-icon-box">
                    <div class="metric-icon">🎟️</div>
                </div>
                <h3 class="metric-value" id="boletos-vendidos"><?php echo number_format($boletos_vendidos); ?></h3>
                <p class="metric-label" id="boletos-total">Boletos vendidos de <?php echo number_format($capacidad_total); ?> disponibles</p>
            </div>
            
            <div class="card metric-card">
                <div class="metric-icon-box">
                    <div class="metric-icon">💲</div>
                </div>
                <h3 class="metric-value" id="ingresos-totales">$<?php echo number_format($ingresos_totales); ?></h3>
                <p class="metric-label">Total de ingresos generados</p>
            </div>
            
            <div class="card metric-card">
                <div class="metric-icon-box">
                    <div class="metric-icon">📊</div>
                </div>
                <h3 class="metric-value" id="ocupacion-porcentaje"><?php echo $porcentaje_ocupacion; ?>%</h3>
                <p class="metric-label">Ocupación del aforo total</p>
            </div>
            
            <div class="card metric-card">
                <div class="metric-icon-box">
                    <div class="metric-icon">⚠️</div>
                </div>
                <h3 class="metric-value" id="incidencias-total"><?php echo $incidencias_total; ?></h3>
                <p class="metric-label">Incidencias registradas</p>
            </div>
        </section>

        <!-- Sección Media: Gráficos e Información -->
        <section class="middle-layout">
            
            <div class="left-col">
                <!-- Gráfica de Barras Dinámica -->
                <div class="card chart-card">
                    <h3>Boletos vendidos por categoría</h3>
                    
                    <div class="chart-container-bars">
                        <?php if (empty($zones)): ?>
                            <div class="no-chart-data">No hay categorías configuradas para este evento.</div>
                        <?php else: ?>
                            <div class="bar-chart-y-axis">
                                <span><?php echo number_format($maxVendidos); ?></span>
                                <span><?php echo number_format(round($maxVendidos / 2)); ?></span>
                                <span>0</span>
                            </div>
                            
                            <div class="bar-chart-visual">
                                <?php foreach ($zones as $idx => $z): ?>
                                    <?php 
                                        $color = $colorPalette[$idx % count($colorPalette)];
                                        $height = $maxVendidos > 0 ? ($z['vendidos'] / $maxVendidos) * 100 : 0;
                                        // Asegurar una altura mínima visible si hay boletos vendidos
                                        if ($z['vendidos'] > 0 && $height < 8) $height = 8;
                                    ?>
                                    <div class="chart-bar-wrapper">
                                        <div class="bar-value-tooltip"><?php echo $z['vendidos']; ?></div>
                                        <div class="chart-bar-fill" style="height: <?php echo $height; ?>%; background-color: <?php echo $color; ?>;"></div>
                                        <span class="bar-label-text"><?php echo htmlspecialchars($z['nombre_zona']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Slider Timeline de progreso del Evento -->
                    <div class="event-timeline-box">
                        <div class="timeline-labels">
                            <span class="label-pub">Publicado: <strong><?php echo $pubDateStr; ?></strong></span>
                            <span class="label-today">Hoy: <strong><?php echo $todayStr; ?></strong></span>
                            <span class="label-event">Evento: <strong><?php echo $eventDateStr; ?></strong></span>
                        </div>
                        <div class="timeline-bar-bg">
                            <div class="timeline-bar-fill" style="width: <?php echo $elapsedPercentage; ?>%;"></div>
                            <div class="timeline-bullet" style="left: <?php echo $elapsedPercentage; ?>%;"></div>
                        </div>
                    </div>
                </div>

                <!-- Tarjeta de Información Detallada del Evento -->
                <div class="card info-card">
                    <h3>Información del evento</h3>
                    <div class="info-grid">
                        <span class="info-label">Nombre</span> 
                        <span class="info-data" id="info-nombre"><?php echo htmlspecialchars($event['nombre_evento']); ?></span>
                        
                        <span class="info-label">Fecha</span> 
                        <span class="info-data" id="info-fecha"><?php echo htmlspecialchars($fechaCompletaStr); ?></span>
                        
                        <span class="info-label">Horario</span> 
                        <span class="info-data" id="info-horario"><?php echo htmlspecialchars($horarioFormateado); ?></span>
                        
                        <span class="info-label">Ubicación</span> 
                        <span class="info-data" id="info-ubicacion"><?php echo htmlspecialchars($event['nombre_recinto'] ?: $event['ubicacion']); ?></span>
                        
                        <span class="info-label">Categoría</span> 
                        <span class="info-data" id="info-categoria"><?php echo htmlspecialchars($event['nombre_categoria']); ?></span>
                    </div>
                    
                    <!-- Botones de Acción directos -->
                    <div class="action-buttons">
                        <a href="editar_evento.php?id=<?php echo $event['id_evento']; ?>" class="btn btn-warning" id="btn-editar">Editar información</a>
                        <button class="btn btn-secondary" id="btn-cancelar" onclick="toggleEventStatus(<?php echo $event['id_evento']; ?>, '<?php echo $event['estado']; ?>')">
                            <?php echo $event['estado'] === 'cancelado' ? 'Activar evento' : 'Cancelar evento'; ?>
                        </button>
                        <button class="btn btn-danger" id="btn-eliminar" onclick="deleteEvent(<?php echo $event['id_evento']; ?>)">Eliminar evento</button>
                    </div>
                </div>
            </div>

            <div class="right-col">
                <!-- Gráfica de Dona Dinámica SVG -->
                <div class="card chart-card pie-chart-card">
                    <h3>Distribución de ingresos</h3>
                    
                    <div class="chart-container-donut">
                        <?php if ($ingresos_totales <= 0): ?>
                            <div class="donut-visual-box">
                                <svg viewBox="0 0 42 42" class="donut-svg">
                                    <circle class="donut-hole" cx="21" cy="21" r="15.91549430918954" fill="transparent"></circle>
                                    <circle class="donut-ring" cx="21" cy="21" r="15.91549430918954" fill="transparent" stroke="#515a81" stroke-width="4.5"></circle>
                                </svg>
                                <div class="donut-center-text">
                                    <span class="donut-center-val">$0</span>
                                    <span class="donut-center-lbl">Ingresos</span>
                                </div>
                            </div>
                            <div class="donut-legend-list">
                                <p style="color: #b0b4cf; font-size: 0.85rem; text-align: center; width: 100%;">No se han registrado ingresos por ventas aún.</p>
                            </div>
                        <?php else: ?>
                            <div class="donut-visual-box">
                                <svg viewBox="0 0 42 42" class="donut-svg">
                                    <circle class="donut-hole" cx="21" cy="21" r="15.91549430918954" fill="transparent"></circle>
                                    <circle class="donut-ring" cx="21" cy="21" r="15.91549430918954" fill="transparent" stroke="#2a304e" stroke-width="4.5"></circle>
                                    
                                    <?php foreach ($donutSegments as $seg): ?>
                                        <circle class="donut-segment" cx="21" cy="21" r="15.91549430918954" fill="transparent" 
                                                stroke="<?php echo $seg['color']; ?>" stroke-width="4.5" 
                                                stroke-dasharray="<?php echo $seg['dasharray']; ?>" 
                                                stroke-dashoffset="<?php echo $seg['dashoffset']; ?>"></circle>
                                    <?php endforeach; ?>
                                </svg>
                                
                                <div class="donut-center-text">
                                    <span class="donut-center-val">$<?php echo number_format($ingresos_totales); ?></span>
                                    <span class="donut-center-lbl">Ingresos</span>
                                </div>
                            </div>
                            
                            <div class="donut-legend-list">
                                <?php foreach ($donutSegments as $seg): ?>
                                    <div class="donut-legend-item">
                                        <div class="legend-label-box">
                                            <span class="legend-color-dot" style="background-color: <?php echo $seg['color']; ?>;"></span>
                                            <span class="legend-name"><?php echo htmlspecialchars($seg['name']); ?></span>
                                        </div>
                                        <span class="legend-value">$<?php echo number_format($seg['revenue']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tarjeta de Alertas Automáticas -->
                <div class="card alerts-card">
                    <h3>Alertas automáticas</h3>
                    <p class="alerts-desc">Las alertas se enviarán a:</p>
                    <p class="highlight-email" id="email-admin"><?php echo htmlspecialchars($event['email_organizador']); ?></p>
                    <p class="alerts-desc">como correo electrónico y notificaciones push en la app móvil sobre:</p>
                    <ul class="alerts-list">
                        <li>Evento cancelado</li>
                        <li>Evento eliminado</li>
                        <li>Próximo a comenzar</li>
                    </ul>
                </div>
            </div>

        </section>

        <!-- Sección de Incidencias Reportadas -->
        <section class="card incidents-section">
            <h3>Incidencias reportadas</h3>
            
            <div class="incidents-list" id="lista-incidencias">
                <?php if (empty($incidencias)): ?>
                    <div class="no-incidents-box">
                        <p class="no-incidents-msg">🎉 ¡Excelente! No se han reportado incidencias para este evento.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($incidencias as $inc): ?>
                        <?php
                            $incTime = strtotime($inc['fecha_reporte']);
                            $incDateStr = date('j ', $incTime) . $mesesLetras[(int)date('m', $incTime) - 1] . ", " . date('h:i a', $incTime);
                            $author = $inc['nombre_usuario'] ?: 'Cliente de Agora';
                        ?>
                        <div class="incident-item" id="incident-card-<?php echo $inc['id_incidencia']; ?>">
                            <div class="incident-content">
                                <h4 class="incident-author"><?php echo htmlspecialchars($author); ?></h4>
                                <span class="incident-date"><?php echo htmlspecialchars($incDateStr); ?></span>
                                <p class="incident-text"><?php echo htmlspecialchars($inc['descripcion']); ?></p>
                            </div>
                            <div class="incident-action">
                                <button class="btn-danger-small" onclick="deleteIncident(<?php echo $inc['id_incidencia']; ?>)">Eliminar</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <!-- Pie de página premium -->
    <div id="footer">
        <footer class="footer">
            <div class="footer_content">
                <div class="name">
                    <img src="../Assets/img/title.png" alt="Agora">
                </div>
                <div class="contact_info">
                    Contáctanos 
                    <br>
                    Av. Calle falsa #1234
                    <br>
                    <a href="mailto:agoraeventos@gmail.com">agoraeventos@gmail.com</a>
                    <br>
                    012 000 45450 
                    <br>
                    <br>
                    © 2026 Agora Todos los derechos reservados.
                </div>
                <div class="div_3"></div>
            </div>
        </footer>
    </div>

    <!-- Lógica de interacciones JS AJAX -->
    <script>
        // Cambiar el estado del evento (Activo / Cancelado) de manera interactiva
        function toggleEventStatus(eventId, currentStatus) {
            const nextStatus = currentStatus === 'activo' ? 'cancelado' : 'activo';
            const actionText = currentStatus === 'activo' ? 'cancelar' : 'activar';
            
            if (!confirm(`¿Estás seguro de que deseas ${actionText} este evento?`)) {
                return;
            }

            fetch('../php/toggle_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    event_id: eventId,
                    status: nextStatus
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Recargar la página para actualizar todas las métricas y leyendas reflejando el estado
                    window.location.reload();
                } else {
                    alert(data.message || 'Error al cambiar el estado del evento.');
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('Ocurrió un error al procesar la solicitud.');
            });
        }

        // Eliminar el evento completo con confirmación
        function deleteEvent(eventId) {
            if (!confirm('🚨 ¡ATENCIÓN! Estás a punto de eliminar este evento de manera PERMANENTE.\nEsta acción no se puede deshacer y cancelará todas las reservas asociadas.\n\n¿Realmente deseas continuar?')) {
                return;
            }

            fetch('../php/delete_event.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    event_id: eventId
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('El evento ha sido eliminado exitosamente. Redirigiendo a tus creaciones...');
                    window.location.href = 'mi_evento.php';
                } else {
                    alert(data.message || 'Error al intentar eliminar el evento.');
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('Ocurrió un error al conectar con el servidor.');
            });
        }

        // Eliminar una incidencia de forma asíncrona con animación suave
        function deleteIncident(incidentId) {
            if (!confirm('¿Deseas marcar esta incidencia como resuelta y eliminarla del panel?')) {
                return;
            }

            fetch('../php/delete_incident.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    incident_id: incidentId
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const card = document.getElementById(`incident-card-${incidentId}`);
                    if (card) {
                        // Aplicar animación de salida suave
                        card.style.transition = 'opacity 0.5s ease, transform 0.5s ease, margin-bottom 0.5s ease, height 0.5s ease';
                        card.style.opacity = '0';
                        card.style.transform = 'translateY(-20px)';
                        
                        setTimeout(() => {
                            card.remove();
                            
                            // Si ya no quedan incidencias, recargar la página para mostrar el mensaje optimista de éxito
                            const remaining = document.querySelectorAll('#lista-incidencias .incident-item');
                            if (remaining.length === 0) {
                                window.location.reload();
                            } else {
                                // Actualizar el contador de incidencias visualmente
                                const counter = document.getElementById('incidencias-total');
                                if (counter) {
                                    const currentVal = parseInt(counter.textContent) || 0;
                                    counter.textContent = Math.max(0, currentVal - 1);
                                }
                            }
                        }, 500);
                    }
                } else {
                    alert(data.message || 'Error al eliminar la incidencia.');
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('Error de conexión al eliminar la incidencia.');
            });
        }
    </script>
</body>
</html>
