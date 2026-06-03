<?php
require_once __DIR__ . '/../php/auth.php';

// Obtener todos los eventos activos de la base de datos
try {
    $stmtEvents = $pdo->query("
        SELECT e.*, c.nombre_categoria, 
               COALESCE(SUM(z.lugares_totales), e.capacidad_total) AS lugares_totales,
               COALESCE(SUM(z.lugares_disponibles), e.capacidad_total) AS lugares_disponibles
        FROM Evento e
        LEFT JOIN Categoria c ON e.id_categoria = c.id_categoria
        LEFT JOIN Zona_Boleto z ON e.id_evento = z.id_evento
        WHERE e.estado = 'activo'
        GROUP BY e.id_evento, c.nombre_categoria
        ORDER BY e.fecha ASC, e.hora_inicio ASC
    ");
    $events = $stmtEvents->fetchAll();
} catch (Exception $e) {
    $events = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/home_styles/nav_footer.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="../Assets/Js/home.js"></script>
    <title>Agora - Explorar Eventos</title>
    <link rel="shortcut icon" href="../Assets/img/logo.png" type="image/x-icon">
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/styles_events/events.css?v=<?php echo time(); ?>">
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
                <li><button class="menu-btn"><a href="historial_r.php">Historial de reservaciones</a></button></li>
                <li><button class="menu-btn"><a href="calendario.php">Calendario</a></button></li>
                
                <?php if (isLoggedIn()): ?>
                    <li>
                        <button class="user-welcome" onclick="window.location.href='perfil.php'" style="color: #64b5f6; font-weight: 600;">
                            Hola, <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?>
                        </button>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>

    <div class="container">
        <!-- Banner Hero Fotográfico Collages -->
        <div class="hero-banner">
            <div class="hero-content">
                <h2>Tu escenario, tu evento.</h2>
                <p>Encuentra, crea y gestiona eventos que inspiran y conectan comunidades.</p>
                <a href="crear_evento.php" class="hero-btn">Empieza a crear tu evento ahora</a>
            </div>
            <div class="hero-images-grid">
                <div class="hero-img-box">
                    <img src="../Assets/img/imgconcierto.jpg" alt="Concierto">
                </div>
                <div class="hero-img-box">
                    <img src="../Assets/img/imgdeporte.jpg" alt="Deporte">
                </div>
                <div class="hero-img-box">
                    <img src="../Assets/img/imgconferencia.jpg" alt="Conferencia">
                </div>
            </div>
        </div>
        
        <!-- Encabezado del Tablero -->
        <div class="dashboard-header">
            <h2>Explora y reserva el evento ideal</h2>
            <p>¡Encuentra el evento perfecto para ti!</p>
        </div>
        
        <div class="events-grid">
            <?php if (empty($events)): ?>
                <div class="no-events-message">
                    <p style="font-size: 1.2rem; color: #555;">No hay eventos disponibles en este momento. ¡Sé el primero en publicar uno!</p>
                    <a href="crear_evento.php" class="card-button" style="display: inline-block; margin-top: 15px; padding: 10px 25px;">Publicar Evento</a>
                </div>
            <?php else: ?>
                <?php foreach ($events as $event): ?>
                    <?php
                        $fechaOriginal = $event['fecha'];
                        $timestamp = strtotime($fechaOriginal);
                        
                        $diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
                        
                        $diaLetra = $diasSemana[date('w', $timestamp)];
                        $diaNum = date('d', $timestamp);
                        $mesLetra = $meses[(int)date('m', $timestamp) - 1];
                        $anio = date('Y', $timestamp);
                        
                        $fechaFormateada = "$diaLetra, $diaNum de $mesLetra de $anio";
                        $horaFormateada = date('h:i a', strtotime($event['hora_inicio']));
                        
                        $totales = (int)$event['lugares_totales'];
                        $disponibles = (int)$event['lugares_disponibles'];
                        $cuposClass = ($disponibles <= 0) ? 'full' : 'available';
                        
                        if ($disponibles <= 0) {
                            $cuposText = "Agotado";
                        } else {
                            $cuposText = "Cupos: $disponibles / $totales";
                        }
                    ?>
                    <div class="event-card">
                        <div class="card-image-placeholder">
                            <img src="<?php echo htmlspecialchars('../' . ($event['imagen_banner'] ?: 'Assets/img/img_home.png')); ?>" alt="Banner">
                        </div>
                        <div class="card-content">
                            <p class="card-category"><?php echo htmlspecialchars($event['nombre_categoria'] ?: 'General'); ?></p>
                            <h3 class="card-title"><?php echo htmlspecialchars($event['nombre_evento']); ?></h3>
                            <p class="card-description"><?php echo htmlspecialchars(substr($event['descripcion'], 0, 120)) . (strlen($event['descripcion']) > 120 ? '...' : ''); ?></p>
                            
                            <div class="card-footer">
                                <div class="card-date-time" style="max-width: 60%;">
                                    <strong><?php echo htmlspecialchars($fechaFormateada); ?></strong>
                                    <br>
                                    <span>a las <?php echo htmlspecialchars($horaFormateada); ?></span>
                                </div>
                                <p class="card-capacity <?php echo $cuposClass; ?>">
                                    <?php echo $cuposText; ?>
                                </p>
                            </div>
                            
                            <a href="reservar.php?event_id=<?php echo $event['id_evento']; ?>" class="card-button">
                                <?php echo $disponibles <= 0 ? 'Ver detalles' : '¡Reservar ahora!'; ?>
                            </a>
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
