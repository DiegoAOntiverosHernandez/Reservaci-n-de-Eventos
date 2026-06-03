<?php
require_once __DIR__ . '/../php/auth.php';

// Exigir inicio de sesión
requireLogin();

$id_organizador = getCurrentUserId();

// Obtener eventos del organizador
try {
    $stmt = $pdo->prepare("
        SELECT e.*, c.nombre_categoria, rec.nombre_recinto,
               (SELECT COALESCE(SUM(r.espacios_reservados), 0) 
                FROM Reservacion r
                WHERE r.id_zona IN (SELECT id_zona FROM Zona_Boleto WHERE id_evento = e.id_evento) 
                  AND r.estado_reservacion = 'Confirmado') AS total_asistentes,
               COALESCE((SELECT SUM(lugares_totales) FROM Zona_Boleto WHERE id_evento = e.id_evento), e.capacidad_total) AS cap_total
        FROM Evento e
        LEFT JOIN Categoria c ON e.id_categoria = c.id_categoria
        LEFT JOIN Recinto rec ON e.id_recinto = rec.id_recinto
        WHERE e.id_organizador = ?
        ORDER BY e.fecha DESC, e.hora_inicio DESC
    ");
    $stmt->execute([$id_organizador]);
    $myEvents = $stmt->fetchAll();
} catch (Exception $e) {
    $myEvents = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/home_styles/nav_footer.css">
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/home_styles/home_welcom.css">
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/home_styles/home_events.css">
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/styles_events/events.css">
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/styles_events/mievent.css?v=<?php echo time(); ?>">
    <script src="../Assets/Js/home.js"></script>
    <script src="../Assets/Js/mi_evento_actions.js"></script>
    <title>Agora - Mis Eventos</title>
    <link rel="shortcut icon" href="../Assets/img/logo.png" type="image/x-icon">
</head>
<body>
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
    
    <br>
    <div class="container">
        <!-- Premium Hero Banner -->
        <div class="hero-container-mievent">
            <div class="hero-text-mievent">
                <h2>Tu escenario, tu evento.</h2>
                <p>Encuentra, crea y gestiona eventos que inspiran.</p>
                <a href="crear_evento.php" class="btn-create-mievent">Empieza a crear tu evento ahora</a>
            </div>
            <div class="hero-images-mievent">
                <div class="hero-img-slide" style="background-image: url('../Assets/img/tag_concert.png');"></div>
                <div class="hero-img-slide" style="background-image: url('../Assets/img/imgconferencia.jpg');"></div>
            </div>
        </div>

        <!-- Filters & Search Section -->
        <div class="search-sort-section">
            <div class="title-search-row">
                <div class="page-title-box">
                    <h1>Mis eventos creados</h1>
                    <p class="subtitle">Gestiona y haz seguimiento de tus creaciones</p>
                </div>
                <div class="search-box">
                    <span class="search-icon">🔍 Buscar</span>
                    <input type="text" id="searchInput" placeholder="" onkeyup="filterEvents()">
                </div>
            </div>
            
            <div class="filters-row">
                <div class="filter-dropdown-box">
                    <select id="filterCategory" onchange="filterEvents()">
                        <option value="all">Eventos (Todos)</option>
                        <option value="concierto">Concierto</option>
                        <option value="teatro">Obra de teatro</option>
                        <option value="conferencia">Conferencia</option>
                        <option value="festival">Festival</option>
                        <option value="social">Evento social</option>
                    </select>
                </div>
                
                <div class="sort-dropdown-box">
                    <select id="sortDate" onchange="sortEvents()">
                        <option value="recent">Fecha (Reciente)</option>
                        <option value="oldest">Fecha (Antigua)</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="events-container">
            <?php if (empty($myEvents)): ?>
                <div class="empty-events-box">
                    <p>Aún no has creado ningún evento.</p>
                    <a href="crear_evento.php">Crear mi primer evento →</a>
                </div>
            <?php else: ?>
                <div class="events-header">
                    <div class="header-col">Evento</div>
                    <div class="header-col">Fecha</div>
                    <div class="header-col">Boletos vendidos</div>
                    <div class="header-col">Estado</div>
                    <div class="header-col" style="text-align: right;">Acciones</div>
                </div>

                <?php foreach ($myEvents as $event): ?>
                    <?php
                        $fechaOriginal = $event['fecha'];
                        $timestamp = strtotime($fechaOriginal);
                        
                        $diasSemana = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
                        $mesesLetras = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
                        
                        $diaLetra = $diasSemana[date('w', $timestamp)];
                        $diaNum = date('d', $timestamp);
                        $mesNum = (int)date('m', $timestamp);
                        $mesLetra = $mesesLetras[$mesNum - 1];
                        
                        $diaLetraCapital = ucfirst($diaLetra);
                        $mesLetraCompact = ucfirst(substr($mesLetra, 0, 3));
                        $fechaMockup = "$diaLetraCapital " . (int)$diaNum . " $mesLetraCompact";
                        
                        $horaFormateada = date('h:i a', strtotime($event['hora_inicio']));
                        
                        $total_asistentes = (int)$event['total_asistentes'];
                        $cap_total = (int)$event['cap_total'];
                        $disponibles = max(0, $cap_total - $total_asistentes);
                        
                        $estado = $event['estado']; // 'activo', 'finalizado', 'cancelado'
                        if ($total_asistentes >= $cap_total && $estado === 'activo') {
                            $estado = 'lleno';
                        }
                        $isChecked = $estado === 'activo' || $estado === 'lleno' ? 'checked' : '';
                        
                        $nombre_recinto = $event['nombre_recinto'] ?: 'Plaza Pública del Sol';
                    ?>
                    <div class="event-card" data-estado="<?php echo htmlspecialchars($estado); ?>" data-category="<?php echo htmlspecialchars(strtolower($event['nombre_categoria'])); ?>" data-timestamp="<?php echo $timestamp; ?>">
                        <!-- 1. Columna Evento -->
                        <div class="event-info">
                            <img src="<?php echo htmlspecialchars('../' . ($event['imagen_banner'] ?: 'Assets/img/img_home.png')); ?>" alt="Banner" class="event-image">
                            <div class="event-details">
                                <h3 class="event-title"><?php echo htmlspecialchars($event['nombre_evento']); ?></h3>
                                <p class="event-date"><span class="event-category"><?php echo htmlspecialchars($event['nombre_categoria'] ?: 'General'); ?></span></p>
                                <p class="event-location">📍 <?php echo htmlspecialchars($nombre_recinto); ?></p>
                            </div>
                        </div>
                        
                        <!-- 2. Columna Fecha -->
                        <div class="event-date-col">
                            <span><?php echo htmlspecialchars($fechaMockup); ?></span>
                            <span class="time"><?php echo htmlspecialchars($horaFormateada); ?></span>
                        </div>
                        
                        <!-- 3. Columna Boletos vendidos -->
                        <div class="event-attendees">
                            <span class="attendees-count"><?php echo $total_asistentes; ?>/<?php echo $cap_total; ?> Ocupados</span>
                            <span class="available"><?php echo $disponibles; ?> disponibles</span>
                        </div>
                        
                        <!-- 4. Columna Estado -->
                        <div class="event-visibility" style="gap: 8px;">
                            <label class="toggle-switch" title="Cambiar visibilidad/estado" style="margin-right: 6px;">
                                <input type="checkbox" data-event-id="<?php echo $event['id_evento']; ?>" <?php echo $isChecked; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="event-status-pill <?php echo htmlspecialchars($estado); ?>">
                                <?php echo ucfirst($estado); ?>
                            </span>
                        </div>
                        
                        <!-- 5. Columna Acciones -->
                        <div class="event-actions-col">
                            <a href="dashbord.php?event_id=<?php echo $event['id_evento']; ?>" class="btn-statistics">Ver estadísticas</a>
                            <div class="actions-dropdown-container">
                                <button class="actions-dropdown" type="button">
                                    Acciones
                                    <svg width="10" height="10" viewBox="0 0 12 12" fill="currentColor">
                                        <path d="M6 9L1 4h10z"/>
                                    </svg>
                                </button>
                                <div class="actions-dropdown-content">
                                    <a href="editar_evento.php?id=<?php echo $event['id_evento']; ?>" class="action-edit">Editar Evento</a>
                                    <button type="button" class="action-delete" data-event-id="<?php echo $event['id_evento']; ?>">Eliminar Evento</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <br>
    <div id="footer">
        <footer class="footer">
            <div class="footer_content">
                <div class="name">
                    <img src="../Assets/img/title.png" alt="Agora">
                </div>
                <div class="contact_info">
                    Contáctanos 
                    <br>
                    Av. Calle falso #1234
                    <br>
                    <a href="mailto: Agoraeventos@correofalso.com">Agoraeventos@correofalso.com</a>
                    <br>
                    012 000 45-150 
                    <br>
                    <br>
                    © 2026 Agora Todos los derechos reservados.
                </div>
                <div class="div_3"></div>
            </div>
        </footer>
    </div>
</body>
</html>
