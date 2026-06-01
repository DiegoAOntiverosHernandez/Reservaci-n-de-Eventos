<?php
require_once __DIR__ . '/php/auth.php';

// Obtener eventos activos de la base de datos
try {
    $stmtEvents = $pdo->query("
        SELECT e.*, c.nombre_categoria, 
               COALESCE(z.lugares_totales, 0) AS lugares_totales,
               COALESCE(z.lugares_disponibles, 0) AS lugares_disponibles
        FROM Evento e
        LEFT JOIN Categoria c ON e.id_categoria = c.id_categoria
        LEFT JOIN Zona_Boleto z ON e.id_evento = z.id_evento
        WHERE e.estado = 'activo'
        ORDER BY e.fecha ASC, e.hora_inicio ASC
        LIMIT 6
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
    <link rel="stylesheet" type="text/css" href="Assets/Styles/home_styles/nav_footer.css">
    <link rel="stylesheet" type="text/css" href="Assets/Styles/home_styles/home_welcom.css">
    <link rel="stylesheet" type="text/css" href="Assets/Styles/home_styles/home_events.css">
    <link rel="stylesheet" type="text/css" href="Assets/Styles/home_styles/login.css">
    <script src="Assets/Js/home.js?v=2"></script>
    <script src="Assets/Js/register.js"></script>
    <script src="Assets/Js/login.js"></script>
    <title>Agora - Reservación de Eventos</title>
    <link rel="shortcut icon" href="Assets/img/logo.png" type="image/x-icon">

    <!-- Carrusel de imágenes -->
    <style>
        .carousel {
            position: relative;
        }

        .carousel__track-container {
            overflow: hidden;
            width: 100%;
            height: 100%;
            max-height: 60vh;
        }

        .carousel__track {
            display: flex;
            align-items: stretch;
            list-style: none;
            margin: 0;
            padding: 0;
            font-size: 0;
            transition: transform 0.8s ease-in-out;
            width: 100%;
            height: 100%;
        }

        .carousel__slide {
            flex: 0 0 100%;
            width: 100%;
            max-width: 100%;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            background-color: #000;
            border: none;
        }

        .carousel__slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            display: block;   
        }

        .event-selector-banner {
            position: absolute;
            transform: translate(30%, -30%);
            margin: -90px auto 40px;
            width: calc(100% - 40px);
            max-width: 1180px;
            padding: 28px 32px;
            background: rgba(9, 20, 48, 0.9);
            border-radius: 28px;
            box-shadow: 0 24px 50px rgba(0, 0, 0, 0.35);
            z-index: 10;
            color: #fff;
        }

        .event-selector-banner h1 {
            margin: 0 0 12px;
            font-size: clamp(2rem, 3vw, 3.2rem);
            line-height: 1.05;
            letter-spacing: -0.03em;
        }

        .event-selector-banner p {
            margin: 0 0 18px;
            max-width: 760px;
            color: rgba(255,255,255,0.88);
            font-size: 1rem;
            line-height: 1.7;
        }

        .event-selector-features {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 22px;
        }

        .event-selector-feature {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            flex: 1 1 220px;
            min-width: 180px;
        }

        .event-selector-feature-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: rgba(255,255,255,0.16);
            display: grid;
            place-items: center;
            font-size: 1.1rem;
            color: #fff;
            flex-shrink: 0;
        }

        .event-selector-feature-text {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .event-selector-feature-text strong {
            font-size: 0.98rem;
            line-height: 1.3;
        }

        .event-selector-feature-text span {
            font-size: 0.89rem;
            color: rgba(255,255,255,0.78);
            line-height: 1.5;
        }

        .event-selector-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: center;
            justify-content: flex-start;
        }

        .event-selector-button {
            display: inline-block;
            text-decoration: none;
            transition: transform 0.2s ease, opacity 0.2s ease;
        }

        .event-selector-button img {
            max-width: 200px;
            height: auto;
            display: block;
        }

        .event-selector-button:hover {
            transform: scale(1.05);
            opacity: 0.85;
        }

        @media (max-width: 900px) {
            .event-selector-banner {
                width: calc(100% - 24px);
                padding: 22px 20px;
                transform: translate(0, 0);
                position: relative;
                margin: 20px auto;
            }

            .event-selector-feature {
                flex: 1 1 100%;
            }
        }

        @media (max-width: 600px) {
            .carousel__track-container { height: 200px; }
            .event-selector-banner {
                padding: 18px 16px;
                border-radius: 20px;
            }

            .event-selector-banner h1 {
                font-size: 1.75rem;
            }
        }

        .carousel__button { display: none !important; }

        @media (max-width: 800px) {
            .carousel__track-container { height: 260px; }
        }
    </style>

    <!-- Hojas de estilo de los eventos -->
    <style>
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 20px;
            padding: 50px;
            max-width: 1400px;
            margin: 0 auto;
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
            margin: 0;
            text-transform: uppercase;
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
            margin: 0 0 10px 0;
            flex-grow: 1;
            line-height: 1.5;
        }
        .card-footer {
            border-top: 1px solid #eee;
            padding-top: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
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
            display: block;
            margin-top: 10px;
            font-weight: 500;
        }
        .card-button:hover {
            background-color: #3e5178;
        }
        .no-events-message {
            text-align: center;
            padding: 50px;
            color: #666;
            grid-column: 1 / -1;
        }
        .ver-mas-btn {
            text-align: center;
            margin: 30px 0;
        }
        .ver-mas-btn a {
            background-color: #4a5f8f;
            color: white;
            padding: 12px 30px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.2s;
            font-weight: 500;
        }
        .ver-mas-btn a:hover {
            background-color: #3e5178;
        }

        @media (max-width: 600px) {
            .events-grid {
                grid-template-columns: 1fr;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="nav">
        <div class="logo"><a href="index.php"><img src="Assets/img/title.png" alt="Agora"></a></div>
        <nav class="nav_links">
            <ul class="nav-menus">
                <li class="menu">
                    <button id="btn" class="menu-btn" aria-haspopup="true" aria-expanded="false">
                        Eventos ▾
                    </button>
                    <div class="menu-content">
                        <a href="views/eventos.php">Eventos próximos y pasados</a>
                        <a href="views/crear_evento.php">Crear un evento</a>
                        <a href="views/mi_evento.php">Mis eventos</a>
                    </div>
                </li>
                <li class="menu">
                    <button id="btn" class="menu-btn" aria-haspopup="true" aria-expanded="false">
                        Reservaciones ▾
                    </button>
                    <div class="menu-content">
                        <a href="views/reservar.php">Reservar evento</a>
                        <a href="views/historial_r.php">Historial de reservaciones</a>
                    </div>
                </li>
                <li><button class="menu-btn"><a href="views/calendario.php">Calendario</a></button></li>
                
                <?php if (isLoggedIn()): ?>
                    <li>
                        <button class="user-welcome" onclick="window.location.href='views/perfil.php'" style="color: #64b5f6; font-weight: 600;">
                            Hola, <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?>
                        </button>
                    </li>
                <?php else: ?>
                    <li>
                        <button id="abrirModalBtn" class="btn menu-btn">Iniciar sesión</button>
                    </li>
                <?php endif; ?>
            </ul>
            
            <?php if (!isLoggedIn()): ?>
                <button id="btn_registrarse" class="btn-registrarse">Registrarse</button>
            <?php endif; ?>
            
            <!-- Modal de Registro / Inicio de sesión -->
            <div id="miModal" class="modal">
                <div class="modal-contenido">
                    <div id="vistaRegistro">
                        <span class="cerrar">&times;</span>
                        <h3>Regístrate con nosotros</h3>
                        <form id="formRegistro" novalidate>
                            <label for="nombre">Nombre</label>
                            <input type="text" id="nombre" name="nombre"><br>
                            <div class="error-txt"></div>
                            
                            <label for="email-reg">Correo</label>
                            <input type="email" id="email-reg" name="email" placeholder="Ejem: correo@gmail.com">
                            <div class="error-txt"></div>
                            
                            <label for="pass-reg">Contraseña</label>
                            <div class="password-wrapper">
                                <input type="password" id="pass-reg" name="pass-reg">
                            </div>
                            <div class="error-txt"></div> 
                            
                            <button type="submit" class="btn btn-primario">Crear cuenta</button>
                        </form>   
                        <div class="toggle-seccion">
                            <span>¿Ya tienes cuenta?</span>
                            <button id="mostrarLoginBtn" class="btn btn-secundario">Iniciar sesión</button>
                        </div>
                    </div>

                    <div id="vistaLogin" style="display: none;">
                        <span class="cerrar">&times;</span>
                        <h3>Iniciar sesión</h3>
                        <form id="formLogin" novalidate>
                            <label for="email-login">Correo</label>
                            <input class="mail" type="email" id="email-login" name="email" placeholder="Ejem: correo@gmail.com">
                            <div class="error-txt"></div> 
                            
                            <label for="pass-login">Contraseña</label> 
                            <div class="password-wrapper"> 
                                <input type="password" id="pass-login" name="pass-login"> 
                            </div> 
                            <div class="error-txt"></div> 
                            
                            <button type="submit" class="btn btn-primario">Entrar</button>
                        </form>
                        <div class="toggle-seccion">
                            <span>¿No tienes cuenta?</span>
                            <button id="mostrarRegistroBtn" class="btn btn-secundario">Regístrate</button>
                        </div>
                    </div>
                </div>
            </div>  
        </nav>
    </div>

    <section class="info_main">
        <div class="info">
            <div class="cont_img">
                <div class="carousel">
                    <div class="carousel__track-container">
                        <ul class="carousel__track">
                            <li class="carousel__slide current-slide"><img src="Assets/img/imgconcierto.jpg" alt="Concierto"></li>
                            <li class="carousel__slide"><img src="Assets/img/imgconferencia.jpg" alt="Conferencia"></li>
                            <li class="carousel__slide"><img src="Assets/img/imgdeporte.jpg" alt="Deporte"></li>
                            <li class="carousel__slide"><img src="Assets/img/imgevento_social.jpg" alt="Evento social"></li>
                            <li class="carousel__slide"><img src="Assets/img/imgsocial.jpg" alt="Social"></li>
                            <li class="carousel__slide"><img src="Assets/img/imgteatro.jpg" alt="Teatro"></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <br>

    <section>
        <div class="event-selector-banner">
            <h1>Tu Pasaporte a Experiencias Inolvidables</h1>
            <p>La aplicación definitiva para descubrir, reservar y disfrutar de los mejores eventos cerca de ti. Desde conciertos íntimos hasta grandes conferencias.</p>
            <div class="event-selector-features">
                <div class="event-selector-feature">
                    <div class="event-selector-feature-icon">⚡</div>
                    <div class="event-selector-feature-text">
                        <strong>Exploración Rápida</strong>
                        <span>Encuentra tus eventos favoritos al instante.</span>
                    </div>
                </div>
                <div class="event-selector-feature">
                    <div class="event-selector-feature-icon">🔒</div>
                    <div class="event-selector-feature-text">
                        <strong>Reservas Seguras</strong>
                        <span>Compra con confianza y gestiona tus tickets fácilmente.</span>
                    </div>
                </div>
                <div class="event-selector-feature">
                    <div class="event-selector-feature-icon">⭐</div>
                    <div class="event-selector-feature-text">
                        <strong>Alertas Personalizadas</strong>
                        <span>Sé el primero en enterarte de tus eventos ideales.</span>
                    </div>
                </div>
            </div>
            <div class="event-selector-actions">
                <div class="store-button-wrapper app-store-wrapper">
                    <a href="https://www.apple.com/mx/app-store/" class="event-selector-button app-store" target="_blank" rel="noopener noreferrer">
                        <img src="Assets/img/available-on-the-app-store.svg" alt="App Store">
                    </a>
                </div>
                <div class="store-button-wrapper google-play-wrapper">
                    <a href="https://play.google.com/store/apps?hl=es_MX&pli=1" class="event-selector-button google-play" target="_blank" rel="noopener noreferrer">
                        <img src="Assets/img/Google_Play-Logo.wine.svg" alt="Google Play">
                    </a>
                </div>
            </div>
        </div>
    </section>

    <br>

    <!-- Cargar eventos dinámicos -->
    <section class="event-cards-section">
        <h2 style="text-align: center; font-size: 2rem; color: #091430; margin-top: 30px; font-weight: 700;">Próximos Eventos Destacados</h2>
        <p style="text-align: center; color: #666; margin-bottom: 20px;">¡No te pierdas de las mejores experiencias!</p>
        
        <div class="events-grid">
            <?php if (empty($events)): ?>
                <div class="no-events-message">
                    <p style="font-size: 1.2rem; color: #555;">No hay eventos disponibles en este momento. ¡Sé el primero en publicar uno!</p>
                    <a href="views/crear_evento.php" class="card-button" style="display: inline-block; margin-top: 15px; padding: 10px 25px;">Publicar Evento</a>
                </div>
            <?php else: ?>
                <?php foreach ($events as $event): ?>
                    <?php
                        // Formatear fecha
                        $fechaOriginal = $event['fecha'];
                        $timestamp = strtotime($fechaOriginal);
                        
                        // Traducción manual de día y mes
                        $diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
                        
                        $diaLetra = $diasSemana[date('w', $timestamp)];
                        $diaNum = date('d', $timestamp);
                        $mesNum = (int)date('m', $timestamp) - 1;
                        $mesLetra = $meses[$mesNum];
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
                            <img src="<?php echo htmlspecialchars($event['imagen_banner'] ?: 'Assets/img/img_home.png'); ?>" alt="<?php echo htmlspecialchars($event['nombre_evento']); ?>">
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
                                <p class="card-capacity <?php echo $cuposClass; ?>"><?php echo $cuposText; ?></p>
                            </div>
                            <a href="views/reservar.php?event_id=<?php echo $event['id_evento']; ?>" class="card-button">Ver Detalles</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="ver-mas-btn">
            <a href="views/eventos.php">Ver todos los eventos →</a>
        </div>
    </section>

    <div id="footer">
        <footer class="footer">
            <div class="footer_content">
                <div class="name">
                    <img src="Assets/img/title.png" alt="Agora">
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

    <!-- Script del Carrusel -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const track = document.querySelector('.carousel__track');
            const originalSlides = Array.from(document.querySelectorAll('.carousel__slide'));
            const container = document.querySelector('.carousel__track-container');
            const intervalMs = 2500; 
            let currentIndex = 1; 
            let autoSlideInterval = null;

            if (originalSlides.length === 0) return;

            const firstClone = originalSlides[0].cloneNode(true);
            const lastClone = originalSlides[originalSlides.length - 1].cloneNode(true);
            firstClone.id = 'first-clone';
            lastClone.id = 'last-clone';
            track.appendChild(firstClone);
            track.insertBefore(lastClone, track.firstChild);

            function getSlides() {
                return Array.from(track.querySelectorAll('.carousel__slide'));
            }

            let slideWidth = 0;

            function updateSlidePosition(withTransition = true) {
                const amountToMove = -currentIndex * slideWidth;
                track.style.transition = withTransition ? 'transform 0.8s ease-in-out' : 'none';
                track.style.transform = `translateX(${amountToMove}px)`;
            }

            function goToNextSlide() {
                const slides = getSlides();
                currentIndex += 1;
                updateSlidePosition(true);
                if (slides[currentIndex] && slides[currentIndex].id === 'first-clone') {
                    track.addEventListener('transitionend', () => {
                        currentIndex = 1;
                        updateSlidePosition(false);
                    }, { once: true });
                }
            }

            function startAutoSlide() {
                stopAutoSlide();
                autoSlideInterval = setInterval(goToNextSlide, intervalMs);
            }

            function stopAutoSlide() {
                if (autoSlideInterval) {
                    clearInterval(autoSlideInterval);
                    autoSlideInterval = null;
                }
            }

            container.addEventListener('mouseenter', stopAutoSlide);
            container.addEventListener('mouseleave', startAutoSlide);

            function setSlideWidths() {
                const slides = getSlides();
                const w = container.getBoundingClientRect().width;
                slideWidth = w;
                slides.forEach(slide => {
                    slide.style.width = `${slideWidth}px`;
                });
                updateSlidePosition(false);
            }

            window.addEventListener('resize', () => setSlideWidths());

            setSlideWidths();
            startAutoSlide();
        });
    </script>
</body>
</html>
