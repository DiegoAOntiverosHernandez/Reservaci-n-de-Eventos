<?php
require_once __DIR__ . '/../php/auth.php';

// Obtener todos los eventos activos de la base de datos
try {
    $stmtEvents = $pdo->query("
        SELECT e.*, c.nombre_categoria, 
               COALESCE(z.lugares_totales, 0) AS lugares_totales,
               COALESCE(z.lugares_disponibles, 0) AS lugares_disponibles
        FROM Evento e
        LEFT JOIN Categoria c ON e.id_categoria = c.id_categoria
        LEFT JOIN Zona_Boleto z ON e.id_evento = z.id_evento AND z.nombre_zona = 'General'
        WHERE e.estado = 'activo'
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
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/home_styles/home_welcom.css">
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/home_styles/home_events.css">
    <script src="../Assets/Js/home.js"></script>
    <title>Agora - Explorar Eventos</title>
    <link rel="shortcut icon" href="../Assets/img/logo.png" type="image/x-icon">
    
    <style>
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 20px;
            padding: 50px;
            margin-top: -350px;
        }
        .event-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform 0.2s;
        }
        .event-card:hover {
            transform: translateY(-5px);
        }
        .card-image-placeholder {
            height: 180px;
            background-color: #4a5f8f;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .card-image-placeholder img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .card-content {
            padding: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex-grow: 1;
        }
        .card-category {
            font-size: 0.85rem;
            color: #4a5f8f;
            font-weight: 600;
            text-transform: uppercase;
            margin: 0;
        }
        .card-title {
            font-size: 1.4rem;
            margin: 0;
            color: #333;
            font-weight: 700;
        }
        .card-description {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 10px;
            flex-grow: 1;
            line-height: 1.5;
        }
        .card-footer {
            border-top: 1px solid #eee;
            padding-top: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-date-time {
            font-size: 0.8rem;
            color: #555;
            margin: 0;
        }
        .card-capacity {
            font-size: 0.85rem;
            font-weight: 600;
            margin: 0;
        }
        .card-capacity.full {
             color: #d32f2f;
        }
        .card-capacity.available {
             color: #388e3c;
        }
        .card-button {
            background-color: #4a5f8f;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: background-color 0.2s;
            margin-top: 10px;
            font-weight: 500;
        }
        .card-button:hover {
            background-color: #3e5178;
        }
        .no-events-message {
            text-align: center;
            padding: 80px 20px;
            color: #666;
            grid-column: 1 / -1;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        @media (max-width: 600px) {
            .events-grid {
                grid-template-columns: 1fr;
            }
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

    <section class="info_main_e">
        <div class="info_e">
            <div class="cont_img_e">
                <img src="../Assets/img/img_home_2.png" alt="Agora">
                <div class="info_img_e">
                    <h1>Tu Escenario, Tu Evento.</h1>
                    <p>Encuentra, crea y gestiona eventos que inspiran.</p>
                    <ul>
                        <li>Descubre y asiste: Conciertos, conferencias, bodas y más.</li>
                        <li>Crea en minutos: Arma tu propio evento fácilmente.</li>
                        <li>Gestión amigable: Gestiona y administra tus eventos.</li>
                    </ul>
                    <div class="buttons">
                        <a href="crear_evento.php" class="button_1">Empieza a crear tu evento ahora</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <section class="info_main_2e">
        <h2>Explora y reserva el evento ideal</h2>
        <p>¡Encuentra el evento perfecto para ti!</p>
    </section>
    
    <div class="container">
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
