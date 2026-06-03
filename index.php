<?php
require_once __DIR__ . '/php/auth.php';

// Obtener eventos activos de la base de datos
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


    <!-- Premium Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Premium Core Styling Overrides -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
        }

        body {
            background-color: #f8fafc;
            color: #0f172a;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Navbar & Footer Overrides */
        .nav {
            background-color: #091430 !important;
            padding: 20px 70px !important;
            height: auto !important;
        }

        .nav_links a,
        .menu-btn,
        .user-welcome {
            color: white !important;
            font-family: 'Outfit', sans-serif !important;
        }

        .btn-registrarse {
            background-color: #4a5f8f !important;
            color: white !important;
            border-radius: 8px !important;
            padding: 8px 20px !important;
            font-weight: 700 !important;
            transition: background-color 0.2s !important;
        }

        .btn-registrarse:hover {
            background-color: #3b4e78 !important;
        }
        
        .footer, .footer_content, .name, .contact_info, .div_3 {
            background-color: #091430 !important;
        }

        /* Premium Hero Section */
        .hero-container {
            display: flex;
            width: 100%;
            height: 440px;
            background-color: #091430;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .hero-text-box {
            flex: 1.2;
            background: linear-gradient(135deg, #091430 0%, #0d1e45 100%);
            padding: 50px 70px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            color: white;
            z-index: 2;
        }

        .hero-text-box h2 {
            font-size: clamp(2rem, 3.2vw, 3rem);
            font-weight: 800;
            margin-bottom: 15px;
            line-height: 1.15;
            letter-spacing: -0.02em;
        }

        .hero-text-box p {
            font-size: clamp(0.95rem, 1.4vw, 1.2rem);
            color: #cbd5e1;
            margin-bottom: 30px;
            line-height: 1.5;
            max-width: 550px;
        }

        .btn-create-now {
            background-color: #e09832;
            color: white;
            padding: 14px 28px;
            border-radius: 8px;
            font-weight: 700;
            text-decoration: none;
            align-self: flex-start;
            transition: transform 0.2s, background-color 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 12px rgba(224, 152, 50, 0.35);
            font-size: 0.95rem;
            text-transform: none;
        }

        .btn-create-now:hover {
            background-color: #c98223;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(224, 152, 50, 0.45);
        }

        .hero-image-box {
            flex: 1.8;
            display: flex;
            overflow: hidden;
        }

        .hero-image-box .hero-img-card {
            flex: 1;
            overflow: hidden;
            position: relative;
        }

        .hero-image-box .hero-img-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .hero-image-box .hero-img-card:hover img {
            transform: scale(1.06);
        }

        /* Explore & Filter Row styling */
        .explore-section-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding: 50px 70px 10px;
            max-width: 1440px;
            margin: 0 auto;
            width: 100%;
            flex-wrap: wrap;
            gap: 25px;
        }

        .explore-title-block h2 {
            font-size: clamp(1.8rem, 2.5vw, 2.4rem);
            font-weight: 800;
            color: #091430;
            margin: 0 0 8px 0;
            letter-spacing: -0.02em;
        }

        .explore-title-block p {
            font-size: 1.05rem;
            color: #64748b;
            margin: 0;
            font-weight: 500;
        }

        .explore-search-block {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .explore-search-block label {
            font-size: 1.35rem;
            font-weight: 800;
            color: #091430;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .explore-search-block input {
            padding: 10px 15px;
            border: 2px solid #091430;
            border-radius: 8px;
            width: 280px;
            font-size: 1rem;
            font-weight: 600;
            outline: none;
            color: #091430;
            transition: box-shadow 0.2s, border-color 0.2s;
        }

        .explore-search-block input:focus {
            box-shadow: 0 0 0 3px rgba(9, 20, 48, 0.15);
        }

        .explore-filter-row {
            display: flex;
            align-items: center;
            gap: 35px;
            padding: 20px 70px 0;
            max-width: 1440px;
            margin: 0 auto;
            width: 100%;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 18px;
        }

        .filter-pill {
            background-color: #4a5f8f;
            color: white;
            padding: 8px 18px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.2s;
            box-shadow: 0 4px 6px rgba(74, 95, 143, 0.15);
        }

        .filter-pill:hover {
            background-color: #3b4e78;
            transform: translateY(-1px);
        }

        .filter-pill-text {
            color: #091430;
            font-weight: 700;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: color 0.2s;
        }

        .filter-pill-text:hover {
            color: #4a5f8f;
        }

        /* Custom Cards Layout */
        .events-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)) !important;
            gap: 30px !important;
            padding: 40px 70px !important;
            max-width: 1440px !important;
            margin: 0 auto !important;
            width: 100% !important;
        }

        .event-card {
            position: relative;
            height: 420px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            background: #0f172a;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease;
            margin: 15px !important;
        }

        .event-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 35px rgba(9, 20, 48, 0.15);
        }

        .event-card-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 1;
            transition: transform 0.5s ease;
        }

        .event-card:hover .event-card-bg {
            transform: scale(1.06);
        }

        .event-card-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            background: linear-gradient(to top, 
                rgba(0,0,0,0.95) 0%, 
                rgba(0,0,0,0.8) 55%, 
                rgba(0,0,0,0.4) 80%, 
                rgba(0,0,0,0) 100%);
            padding: 25px;
            z-index: 2;
            display: flex;
            flex-direction: column;
            gap: 8px;
            color: white;
            box-sizing: border-box;
            text-align: left;
        }

        .event-card-title {
            font-size: 1.35rem;
            font-weight: 800;
            color: white;
            margin: 0;
            line-height: 1.25;
        }

        .event-card-category {
            font-size: 0.8rem;
            text-transform: uppercase;
            font-weight: 700;
            color: #cbd5e1;
            margin: 0;
            letter-spacing: 0.08em;
        }

        .event-card-location {
            font-size: 0.85rem;
            color: #cbd5e1;
            margin: 2px 0 6px 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.4;
            opacity: 0.85;
        }

        .event-card-date {
            font-size: 0.95rem;
            font-weight: 700;
            color: white;
            margin-bottom: 12px;
        }

        .event-card-btn {
            background-color: #4a5f8f;
            color: white;
            text-align: center;
            padding: 12px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 700;
            text-decoration: none;
            transition: background-color 0.2s, transform 0.1s;
            width: 100%;
            border: none;
            cursor: pointer;
            box-sizing: border-box;
            display: inline-block;
        }

        .event-card-btn:hover {
            background-color: #3b4e78;
        }

        .event-card-btn:active {
            transform: scale(0.98);
        }

        .no-events-message {
            text-align: center;
            padding: 50px;
            color: #666;
            grid-column: 1 / -1;
        }

        .ver-mas-btn {
            text-align: center;
            margin: 30px 0 60px;
        }

        .ver-mas-btn a {
            background-color: #4a5f8f;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.2s, transform 0.2s;
            font-weight: 700;
            box-shadow: 0 4px 10px rgba(74, 95, 143, 0.15);
        }

        .ver-mas-btn a:hover {
            background-color: #3b4e78;
            transform: translateY(-2px);
        }

        /* Pagination style */
        .explore-pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin: 40px auto 60px;
            font-weight: 600;
            color: #64748b;
        }

        .page-arrow {
            cursor: pointer;
            transition: color 0.2s;
            display: flex;
            align-items: center;
            font-size: 0.95rem;
        }

        .page-arrow:hover:not(.disabled) {
            color: #091430;
        }

        .page-arrow.disabled {
            color: #cbd5e1;
            cursor: not-allowed;
        }

        .page-num {
            cursor: pointer;
            padding: 5px 12px;
            transition: color 0.2s;
            font-size: 0.95rem;
        }

        .page-num:hover:not(.active) {
            color: #091430;
        }

        .page-num.active {
            color: #091430;
            border-bottom: 2px solid #091430;
            font-weight: 800;
        }

        .page-divider {
            color: #cbd5e1;
        }

        /* Restored Premium App Banner Styling */
        .event-selector-banner {
            margin: 50px auto;
            width: calc(100% - 140px);
            max-width: 1440px;
            padding: 45px 50px;
            background: linear-gradient(135deg, #091430 0%, #15254c 100%);
            border-radius: 24px;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.25);
            color: #fff;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .event-selector-banner h1 {
            font-size: clamp(1.8rem, 2.5vw, 2.6rem);
            font-weight: 800;
            margin: 0;
            line-height: 1.2;
            letter-spacing: -0.02em;
        }

        .event-selector-banner p {
            font-size: 1.05rem;
            color: #cbd5e1;
            margin: 0;
            line-height: 1.5;
            max-width: 800px;
        }

        .event-selector-features {
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            margin: 10px 0;
        }

        .event-selector-feature {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            flex: 1 1 250px;
        }

        .event-selector-feature-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            display: grid;
            place-items: center;
            font-size: 1.2rem;
            color: #fff;
            flex-shrink: 0;
        }

        .event-selector-feature-text {
            display: flex;
            flex-direction: column;
            gap: 4px;
            text-align: left;
        }

        .event-selector-feature-text strong {
            font-size: 1rem;
            font-weight: 700;
            color: #fff;
        }

        .event-selector-feature-text span {
            font-size: 0.88rem;
            color: #cbd5e1;
            line-height: 1.4;
        }

        .event-selector-actions {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .event-selector-button {
            display: inline-block;
            height: 48px;
            transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .event-selector-button:hover {
            transform: translateY(-3px);
        }

        .event-selector-button img {
            height: 48px;
            width: auto;
            display: block;
            object-fit: contain;
            border-radius: 8px;
        }
        
        /* Make Google Play badge look consistent with App Store black badge */
        .event-selector-button.google-play {
            background-color: #000;
            border-radius: 8px;
            padding: 0 10px;
            border: 1px solid rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .event-selector-button.google-play img {
            height: 38px; /* Slightly smaller inside the black capsule */
        }

        /* Responsive adjustments */
        @media (max-width: 1024px) {
            .nav {
                padding: 20px 40px !important;
            }
            .hero-text-box {
                padding: 40px;
            }
            .explore-section-header, 
            .explore-filter-row,
            .events-grid {
                padding-left: 40px;
                padding-right: 40px;
            }
            .event-selector-banner {
                width: calc(100% - 80px);
                padding: 30px;
            }
        }

        @media (max-width: 768px) {
            .nav {
                padding: 20px 30px !important;
            }
            .hero-container {
                flex-direction: column;
                height: auto;
            }
            .hero-text-box {
                padding: 40px 24px;
                align-items: center;
                text-align: center;
            }
            .btn-create-now {
                align-self: center;
                width: 100%;
                text-align: center;
            }
            .hero-image-box {
                display: none;
            }
            .explore-section-header {
                flex-direction: column;
                align-items: flex-start;
                padding: 30px 24px 10px;
            }
            .explore-search-block {
                width: 100%;
            }
            .explore-search-block input {
                width: 100%;
            }
            .explore-filter-row {
                padding: 15px 24px;
                gap: 15px;
                overflow-x: auto;
                white-space: nowrap;
            }
            .events-grid {
                padding: 20px 24px;
                grid-template-columns: 1fr;
            }
            .event-selector-banner {
                width: calc(100% - 48px);
                padding: 24px 20px;
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

    <!-- Premium Split Hero Section with Active Carousel -->
    <div class="hero-container">
        <div class="hero-text-box">
            <h2>Tu escenario, tu evento.</h2>
            <p>Encuentra, crea y gestiona eventos que inspiran.</p>
            <a href="views/crear_evento.php" class="btn-create-now">Empieza a crear tu evento ahora</a>
        </div>
        <div class="hero-image-box">
            <div class="carousel__track-container" style="width: 100%; height: 100%; overflow: hidden; position: relative;">
                <ul class="carousel__track" style="display: flex; list-style: none; margin: 0; padding: 0; transition: transform 0.8s ease-in-out; height: 100%;">
                    <li class="carousel__slide current-slide" style="min-width: 100%; height: 100%;"><img src="Assets/img/imgconcierto.jpg" alt="Concierto" style="width: 100%; height: 100%; object-fit: cover;"></li>
                    <li class="carousel__slide" style="min-width: 100%; height: 100%;"><img src="Assets/img/imgconferencia.jpg" alt="Conferencia" style="width: 100%; height: 100%; object-fit: cover;"></li>
                    <li class="carousel__slide" style="min-width: 100%; height: 100%;"><img src="Assets/img/imgdeporte.jpg" alt="Deporte" style="width: 100%; height: 100%; object-fit: cover;"></li>
                    <li class="carousel__slide" style="min-width: 100%; height: 100%;"><img src="Assets/img/imgevento_social.jpg" alt="Evento social" style="width: 100%; height: 100%; object-fit: cover;"></li>
                    <li class="carousel__slide" style="min-width: 100%; height: 100%;"><img src="Assets/img/imgsocial.jpg" alt="Social" style="width: 100%; height: 100%; object-fit: cover;"></li>
                    <li class="carousel__slide" style="min-width: 100%; height: 100%;"><img src="Assets/img/imgteatro.jpg" alt="Teatro" style="width: 100%; height: 100%; object-fit: cover;"></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Explore, Search & Filter row -->
    <div class="explore-section-header">
        <div class="explore-title-block">
            <h2>Explora y reserva el evento ideal</h2>
            <p>¡Encuentra el evento perfecto para ti!</p>
        </div>
        <div class="explore-search-block">
            <label for="search-input"><span class="search-icon">🔍</span> Buscar</label>
            <input type="text" id="search-input" placeholder="">
        </div>
    </div>

    <div class="explore-filter-row">
        <div class="filter-pill">
            <span>Próximos eventos</span> <span>▾</span>
        </div>
        <div class="filter-pill-text">
            <span>Categoría (Todas)</span> <span>▾</span>
        </div>
        <div class="filter-pill-text">
            <span>Fecha (Reciente)</span> <span>▾</span>
        </div>
    </div>

    <!-- Cargar eventos dinámicos -->
    <section class="event-cards-section" style="padding: 20px 0; background-color: transparent;">
        <div class="events-grid" style="display: grid !important; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)) !important; gap: 30px !important; padding: 40px 70px !important; max-width: 1440px !important; margin: 0 auto !important; width: 100% !important; background: transparent !important; box-shadow: none !important; border-radius: 0 !important; overflow: visible !important;">
            <?php if (empty($events)): ?>
                <div class="no-events-message">
                    <p style="font-size: 1.2rem; color: #555; font-weight: 600;">No hay eventos disponibles en este momento. ¡Sé el primero en publicar uno!</p>
                    <a href="views/crear_evento.php" class="event-card-btn" style="display: inline-block; margin-top: 20px; padding: 12px 30px; width: auto;">Publicar Evento</a>
                </div>
            <?php else: ?>
                <?php foreach ($events as $event): ?>
                    <?php
                        // Formatear fecha
                        $fechaOriginal = $event['fecha'];
                        $timestamp = strtotime($fechaOriginal);
                        
                        // Traducción manual de día y mes
                        $diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                        $mesesShort = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
                        
                        $diaLetra = $diasSemana[date('w', $timestamp)];
                        $diaNum = date('j', $timestamp);
                        $mesLetra = $mesesShort[(int)date('m', $timestamp) - 1];
                        
                        $fechaFormateada = "$diaLetra $diaNum $mesLetra";
                        $horaFormateada = date('h:i a', strtotime($event['hora_inicio']));
                        
                        $totales = (int)$event['lugares_totales'];
                        $disponibles = (int)$event['lugares_disponibles'];
                        $cuposClass = ($disponibles <= 0) ? 'full' : 'available';
                        
                        if ($disponibles <= 0) {
                            $cuposText = "Agotado";
                        } else {
                            $cuposText = "Asientos: $disponibles / $totales";
                        }
                    ?>
                    <div class="event-card" style="position: relative !important; height: 420px !important; border-radius: 16px !important; overflow: hidden !important; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08) !important; background: #0f172a !important; display: flex !important; flex-direction: column !important; justify-content: flex-end !important; margin: 0 !important;">
                        <!-- Insignia de Asientos Disponibles Premium -->
                        <span class="event-card-badge <?php echo $cuposClass; ?>" style="position: absolute !important; top: 15px !important; right: 15px !important; background-color: <?php echo ($disponibles <= 0) ? 'rgba(239, 68, 68, 0.95)' : 'rgba(16, 185, 129, 0.95)'; ?> !important; color: white !important; padding: 6px 12px !important; border-radius: 20px !important; font-size: 0.8rem !important; font-weight: 700 !important; z-index: 3 !important; box-shadow: 0 4px 10px rgba(0,0,0,0.15) !important; text-transform: uppercase !important; letter-spacing: 0.5px !important;">
                            <?php echo $cuposText; ?>
                        </span>
                        <img class="event-card-bg" src="<?php echo htmlspecialchars($event['imagen_banner'] ?: 'Assets/img/img_home.png'); ?>" alt="<?php echo htmlspecialchars($event['nombre_evento']); ?>">
                        <div class="event-card-overlay">
                            <h3 class="event-card-title"><?php echo htmlspecialchars($event['nombre_evento']); ?></h3>
                            <p class="event-card-category"><?php echo htmlspecialchars($event['nombre_categoria'] ?: 'General'); ?></p>
                            <p class="event-card-location"><?php echo htmlspecialchars(substr($event['descripcion'], 0, 80)) . (strlen($event['descripcion']) > 80 ? '...' : ''); ?></p>
                            <div class="event-card-date"><?php echo htmlspecialchars($fechaFormateada); ?></div>
                            <a href="views/reservar.php?event_id=<?php echo $event['id_evento']; ?>" class="event-card-btn">Ver detalles</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Centered Pagination row matching mockup -->
        <div class="explore-pagination">
            <span class="page-arrow disabled">❮ Anterior</span>
            <span class="page-num active">1</span>
            <span class="page-divider">|</span>
            <span class="page-num">2</span>
            <span class="page-arrow">Siguiente ❯</span>
        </div>

        <div class="ver-mas-btn">
            <a href="views/eventos.php">Ver todos los eventos →</a>
        </div>
    </section>

    <!-- Restored & Adapted Premium App Banner -->
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
                <a href="https://www.apple.com/mx/app-store/" class="event-selector-button app-store" target="_blank" rel="noopener noreferrer">
                    <img src="Assets/img/available-on-the-app-store.svg" alt="App Store">
                </a>
                <a href="https://play.google.com/store/apps?hl=es_MX&pli=1" class="event-selector-button google-play" target="_blank" rel="noopener noreferrer">
                    <img src="Assets/img/Google_Play-Logo.wine.svg" alt="Google Play">
                </a>
            </div>
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

    <!-- Script del Carrusel (Restaurado) -->
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
