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
    // 1. Obtener detalles del evento y categoría
    $stmt = $pdo->prepare("
        SELECT e.*, c.nombre_categoria,
               COALESCE(z.id_zona, 0) AS id_zona,
               COALESCE(z.lugares_totales, e.capacidad_total) AS cap_total,
               COALESCE(z.lugares_disponibles, 0) AS cap_disponible
        FROM Evento e
        LEFT JOIN Categoria c ON e.id_categoria = c.id_categoria
        LEFT JOIN Zona_Boleto z ON e.id_evento = z.id_evento AND z.nombre_zona = 'General'
        WHERE e.id_evento = ?
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        die('<h2>Error</h2><p>El evento solicitado no existe o no está disponible.</p>');
    }

    $id_zona = (int)$event['id_zona'];
    
    // Si ya tiene una reservación, sumamos esos cupos al límite máximo reservable en el lado del cliente
    // porque al actualizar, esos cupos se liberan primero antes de verificar stock.
    $stmtRes = $pdo->prepare("SELECT espacios_reservados FROM Reservacion WHERE id_usuario = ? AND id_zona = ? AND estado_reservacion = 'Confirmado' LIMIT 1");
    $stmtRes->execute([$id_usuario, $id_zona]);
    $res = $stmtRes->fetch();
    
    $currentSlots = 0;
    if ($res) {
        $currentSlots = (int)$res['espacios_reservados'];
    }

    $disponibles = (int)$event['cap_disponible'];
    $totales = (int)$event['cap_total'];
    $maxReservable = $disponibles + $currentSlots; // Espacios libres reales para este usuario específico

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
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/home_styles/home_welcom.css">
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/home_styles/home_events.css">
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/styles_events/events.css">
    <script src="../Assets/Js/home.js"></script>
    <script src="../Assets/Js/reservar.js"></script> 
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/styles_events/reservar.css">
    <title>Agora - Detalles de <?php echo htmlspecialchars($event['nombre_evento']); ?></title>
    <link rel="shortcut icon" href="../Assets/img/logo.png" type="image/x-icon">

    <style>
        /* Banner Principal del Evento */
        .event-hero {
            width: 100%;
            height: 250px;
            background-image: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('<?php echo htmlspecialchars("../" . ($event['imagen_banner'] ?: "Assets/img/img_home.png")); ?>');
            background-size: cover;
            background-position: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 0 10%;
            color: white;
            margin-bottom: 40px;
        }

        .event-hero h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            color: #fff;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.6);
        }

        .event-hero p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64b5f6;
            font-weight: 600;
        }

        /* Contenedor Principal a dos columnas */
        .event-details-layout {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 2fr 1fr; 
            gap: 50px;
            font-family: 'Arial', sans-serif;
            color: #333;
        }

        /* Columna Izquierda (Información) */
        .event-info h2 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: #091430;
            border-bottom: 2px solid #eaeaea;
            padding-bottom: 8px;
        }

        .event-info > p {
            line-height: 1.6;
            margin-bottom: 30px;
            color: #444;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 40px;
        }

        .info-item h4 {
            font-size: 1rem;
            margin-bottom: 5px;
            color: #091430;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-item p {
            font-size: 0.95rem;
            color: #555;
            margin: 0;
        }

        .status-badge {
            background-color: #4ade80;
            color: #000;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
            display: inline-block;
        }
        
        .status-badge.cancelado {
            background-color: #f87171;
            color: #fff;
        }

        /* Itinerario */
        .itinerary-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 40px;
        }

        .itinerary-item {
            display: grid;
            grid-template-columns: 120px 1fr;
            font-size: 0.95rem;
            border-bottom: 1px dashed #eaeaea;
            padding-bottom: 8px;
        }

        .itinerary-item span:first-child {
            font-weight: 700;
            color: #4a5f8f;
        }

        /* Columna Derecha (Sidebar) */
        .event-sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .btn-tickets {
            background-color: #eab308;
            color: white;
            border: none;
            padding: 15px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s;
            width: 100%;
        }

        .btn-tickets:hover {
            background-color: #ca9a04;
            transform: translateY(-2px);
        }
        
        .btn-tickets:disabled {
            background-color: #cccccc;
            color: #666666;
            cursor: not-allowed;
            transform: none;
        }

        .promo-ad-space {
            background-color: #262626;
            border-radius: 12px;
            padding: 20px;
            min-height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #a3a3a3;
            text-align: center;
            border: 1px dashed #555;
            position: relative;
            overflow: hidden;
        }

        @media (max-width: 768px) {
            .event-details-layout {
                grid-template-columns: 1fr;
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Controles de Capacidad en el Widget */
        .capacity-control {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 5px;
            background-color: #f9f9f9;
        }
        .control-btn {
            background: #4a5f8f;
            color: white;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            width: 40px;
            height: 40px;
            border-radius: 6px;
            display: grid;
            place-items: center;
            font-weight: bold;
            transition: background 0.2s;
        }
        .control-btn:hover {
            background: #3e5178;
        }
    </style>
</head>
<body>
    <div class="nav">
        <div class="logo"><a href="../index.php"><img src="../Assets/img/title.png" alt="Logo"></a></div>
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

    <?php
        // Formatear Fecha
        $timestamp = strtotime($event['fecha']);
        $diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        $fechaFormateada = $diasSemana[date('w', $timestamp)] . " " . date('d', $timestamp) . " de " . $meses[(int)date('m', $timestamp) - 1] . ", " . date('Y', $timestamp);
        
        $horaInicio = date('h:i a', strtotime($event['hora_inicio']));
        $horaCierre = date('h:i a', strtotime($event['hora_cierre']));
    ?>

    <div class="event-hero">
        <h1><?php echo htmlspecialchars($event['nombre_evento']); ?></h1>
        <p><?php echo htmlspecialchars($event['nombre_categoria'] ?: 'General'); ?></p>
    </div>

    <div class="event-details-layout">
        <div class="event-info">
            <h2>Sobre el evento</h2>
            <p><?php echo nl2br(htmlspecialchars($event['descripcion'])); ?></p>
            
            <div class="info-grid">
                <div class="info-item">
                    <h4>📅 Fecha</h4>
                    <p><?php echo htmlspecialchars($fechaFormateada); ?></p>
                </div>
                <div class="info-item">
                    <h4>🕒 Horario</h4>
                    <p><?php echo htmlspecialchars($horaInicio) . ' - ' . htmlspecialchars($horaCierre); ?></p>
                </div>
                <div class="info-item">
                    <h4>👥 Espacios</h4>
                    <p><?php echo $disponibles; ?> / <?php echo $totales; ?> espacios libres</p>
                </div>
                <div class="info-item">
                    <h4>ℹ️ Estado</h4>
                    <p>
                        <span class="status-badge <?php echo htmlspecialchars($event['estado']); ?>">
                            <?php echo ucfirst($event['estado']); ?>
                        </span>
                    </p>
                </div>
                <div class="info-item" style="grid-column: 1 / -1;">
                    <h4>📍 Ubicación</h4>
                    <p><?php echo htmlspecialchars($event['ubicacion']); ?></p>
                </div>
            </div>

            <?php if (!empty($itinerary)): ?>
                <h2>Itinerario</h2>
                <div class="itinerary-list">
                    <?php foreach ($itinerary as $item): ?>
                        <div class="itinerary-item">
                            <span><?php echo date('h:i a', strtotime($item['hora_inicio'])); ?></span>
                            <span><?php echo htmlspecialchars($item['nombre_seccion']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="event-sidebar">
            <!-- Widget de Reservación -->
            <div class="booking-widget" id="formulario-reserva" style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); border: 1px solid #eaeaea;">
                <h3 style="margin-top: 0; color: #091430; font-size: 1.3rem;">Reservar Entradas</h3>
                <p style="color: #666; font-size: 0.9rem; margin-bottom: 20px;">
                    Entradas 100% gratuitas. Gestiona tu reserva de manera rápida y segura.
                </p>
                
                <form id="reservationForm" data-event-id="<?php echo $event['id_evento']; ?>" data-max-reservable="<?php echo $maxReservable; ?>">
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 0.9rem;">Cantidad de Espacios:</label>
                        <div class="capacity-control">
                            <button type="button" class="control-btn" data-action="decrement">—</button>
                            <input type="number" id="espacios" name="espacios" value="<?php echo $currentSlots > 0 ? $currentSlots : 1; ?>" data-current-slots="<?php echo $currentSlots; ?>" min="1" max="<?php echo min(30, $maxReservable); ?>" style="border: none; text-align: center; font-size: 1.1rem; font-weight: bold; width: 60px; pointer-events: none; background: transparent;" readonly>
                            <button type="button" class="control-btn" data-action="increment">+</button>
                        </div>
                        <span style="font-size: 0.8rem; color: #777; display: block; margin-top: 5px; text-align: center;">Máximo reservable: <?php echo min(30, $maxReservable); ?> cupos</span>
                    </div>
                    
                    <button type="submit" id="btnReservar" class="btn-tickets" <?php echo $maxReservable <= 0 && $currentSlots == 0 ? 'disabled' : ''; ?>>
                        <?php echo $maxReservable <= 0 && $currentSlots == 0 ? 'Agotado' : ($currentSlots > 0 ? 'Actualizar reserva' : 'Reservar lugares'); ?>
                    </button>
                </form>
            </div>

            <div class="promo-ad-space">
                <span>Espacio reservado para<br>Banner Promocional</span>
            </div>
        </div>
    </div>
    <br>

    <div id="footer">
        <footer class="footer">
            <div class="footer_content">
                <div class="name">
                    <img src="../Assets/img/title.png" alt="Logo">
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
