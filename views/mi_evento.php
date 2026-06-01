<?php
require_once __DIR__ . '/../php/auth.php';

// Exigir inicio de sesión
requireLogin();

$id_organizador = getCurrentUserId();

// Obtener eventos del organizador
try {
    $stmt = $pdo->prepare("
        SELECT e.*, c.nombre_categoria,
               COALESCE(SUM(CASE WHEN r.estado_reservacion = 'Confirmado' THEN r.espacios_reservados ELSE 0 END), 0) AS total_asistentes,
               COALESCE(z.lugares_totales, e.capacidad_total) AS cap_total
        FROM Evento e
        LEFT JOIN Categoria c ON e.id_categoria = c.id_categoria
        LEFT JOIN Zona_Boleto z ON e.id_evento = z.id_evento
        LEFT JOIN Reservacion r ON z.id_zona = r.id_zona
        WHERE e.id_organizador = ?
        GROUP BY e.id_evento
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
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/styles_events/mievent.css">
    <script src="../Assets/Js/home.js"></script>
    <script src="../Assets/Js/mi_evento_actions.js"></script>
    <title>Agora - Mis Eventos</title>
    <link rel="shortcut icon" href="../Assets/img/logo.png" type="image/x-icon">

    <style>
        .actions-dropdown-container {
            position: relative;
            display: inline-block;
        }

        .actions-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: #f9f9f9;
            min-width: 150px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 10;
            border-radius: 6px;
            padding: 5px 0;
        }

        .actions-dropdown-content a,
        .actions-dropdown-content button {
            color: #000;
            padding: 10px 16px;
            text-decoration: none;
            display: block;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            font-size: 14px;
            cursor: pointer;
        }

        .actions-dropdown-content a:hover,
        .actions-dropdown-content button:hover {
            background-color: #ddd;
        }

        .actions-dropdown-content .action-delete {
            color: #d32f2f;
        }
        .actions-dropdown-content .action-delete:hover {
            background-color: #ffcdd2;
        }
        .actions-dropdown.active + .actions-dropdown-content {
            display: block;
        }
        .empty-events-box {
            text-align: center;
            padding: 60px 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin: 20px 0;
        }
        .empty-events-box p {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 20px;
        }
        .empty-events-box a {
            display: inline-block;
            background: #4a5f8f;
            color: #fff;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: background 0.2s;
        }
        .empty-events-box a:hover {
            background: #3e5178;
        }
    </style>
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
        <header class="page-header">
            <h1>Mis eventos</h1>
            <p class="subtitle">Gestiona y haz seguimiento de tus creaciones</p>
        </header>

        <div class="events-container">
            <?php if (empty($myEvents)): ?>
                <div class="empty-events-box">
                    <p>Aún no has creado ningún evento.</p>
                    <a href="crear_evento.php">Crear mi primer evento →</a>
                </div>
            <?php else: ?>
                <div class="events-header">
                    <div class="header-col">Evento</div>
                    <div class="header-col">Asistentes</div>
                    <div class="header-col">Estado / Visible</div>
                </div>

                <?php foreach ($myEvents as $event): ?>
                    <?php
                        $fechaOriginal = $event['fecha'];
                        $timestamp = strtotime($fechaOriginal);
                        
                        $diasSemana = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
                        $meses = ['diciembre' => 'diciembre', 'enero' => 'enero', 'febrero' => 'febrero', 'marzo' => 'marzo', 'abril' => 'abril', 'mayo' => 'mayo', 'junio' => 'junio', 'julio' => 'julio', 'agosto' => 'agosto', 'septiembre' => 'septiembre', 'octubre' => 'octubre', 'noviembre' => 'noviembre'];
                        
                        $diaLetra = $diasSemana[date('w', $timestamp)];
                        $diaNum = date('d', $timestamp);
                        $mesNum = (int)date('m', $timestamp);
                        
                        $mesesLetras = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
                        $mesLetra = $mesesLetras[$mesNum - 1];
                        $anio = date('Y', $timestamp);
                        
                        $fechaFormateada = "$diaLetra, $diaNum de $mesLetra de $anio";
                        $horaFormateada = date('h:i a', strtotime($event['hora_inicio']));
                        
                        $estado = $event['estado']; // 'activo', 'finalizado', 'cancelado'
                        $isChecked = $estado === 'activo' ? 'checked' : '';
                    ?>
                    <div class="event-card" data-estado="<?php echo htmlspecialchars($estado); ?>">
                        <div class="event-info">
                            <img style="width: 60px; height: 60px; object-fit: cover; flex-shrink: 0; border-radius: 8px;" src="<?php echo htmlspecialchars('../' . ($event['imagen_banner'] ?: 'Assets/img/img_home.png')); ?>" alt="Banner" class="event-image">
                            <div class="event-details" style="flex: 1; min-width: 0; overflow: hidden;">
                                <h3 class="event-title" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; color: #000; font-weight: 600;"><?php echo htmlspecialchars($event['nombre_evento']); ?></h3>
                                <p class="event-date"><?php echo htmlspecialchars($fechaFormateada); ?> a las <?php echo htmlspecialchars($horaFormateada); ?> <span class="event-category">(<?php echo htmlspecialchars($event['nombre_categoria'] ?: 'General'); ?>)</span></p>
                            </div>
                        </div>
                        
                        <div class="event-attendees">
                            <span class="attendees-count"><?php echo (int)$event['total_asistentes']; ?> / <?php echo (int)$event['cap_total']; ?></span>
                        </div>
                        
                        <div class="event-visibility">
                            <label class="toggle-switch" title="Cambiar visibilidad/estado">
                                <input type="checkbox" data-event-id="<?php echo $event['id_evento']; ?>" <?php echo $isChecked; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="event-status-label <?php echo htmlspecialchars($estado); ?>">
                                <?php echo ucfirst($estado); ?>
                            </span>
                        </div>
                        
                        <div class="event-actions">
                            <div class="actions-dropdown-container">
                                <button class="actions-dropdown" type="button">
                                    Acciones
                                    <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor">
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
