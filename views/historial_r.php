<?php
require_once __DIR__ . '/../php/auth.php';

// Exigir inicio de sesión
requireLogin();

$id_usuario = getCurrentUserId();

// Obtener todas las reservaciones del usuario (Confirmadas y Canceladas)
try {
    $stmt = $pdo->prepare("
        SELECT r.id_reservacion, r.espacios_reservados, r.fecha_reservacion, r.estado_reservacion,
               z.nombre_zona, z.precio,
               e.id_evento, e.nombre_evento, e.fecha AS fecha_evento, e.hora_inicio, e.imagen_banner, e.estado AS estado_evento,
               c.nombre_categoria
        FROM Reservacion r
        JOIN Zona_Boleto z ON r.id_zona = z.id_zona
        JOIN Evento e ON z.id_evento = e.id_evento
        LEFT JOIN Categoria c ON e.id_categoria = c.id_categoria
        WHERE r.id_usuario = ?
        ORDER BY r.fecha_reservacion DESC
    ");
    $stmt->execute([$id_usuario]);
    $reservations = $stmt->fetchAll();
} catch (Exception $e) {
    $reservations = [];
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
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/styles_events/history.css">
    <title>Agora - Historial de Reservaciones</title>
    <link rel="shortcut icon" href="../Assets/img/logo.png" type="image/x-icon">
    <style>
        .empty-history-box {
            text-align: center;
            padding: 60px 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin: 20px 0;
        }
        .empty-history-box p {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 20px;
        }
        .empty-history-box a {
            display: inline-block;
            background: #4a5f8f;
            color: #fff;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: background 0.2s;
        }
        .empty-history-box a:hover {
            background: #3e5178;
        }
        
        .reservation-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px;
            border-radius: 10px;
            background: #fff;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            border: 1px solid #eef2f5;
        }
        .reservation-info {
            display: flex;
            align-items: center;
            gap: 20px;
            flex: 1;
            min-width: 0;
        }
        .reservation-image {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 8px;
            flex-shrink: 0;
        }
        .reservation-details {
            flex: 1;
            min-width: 0;
        }
        .reservation-title {
            font-size: 1.15rem;
            font-weight: 600;
            color: #091430;
            margin: 0 0 5px 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .reservation-date {
            font-size: 0.85rem;
            color: #666;
            margin: 0 0 5px 0;
        }
        .status-label {
            font-size: 0.8rem;
            font-weight: bold;
            margin: 0;
        }
        .reservation-spaces {
            padding: 0 30px;
            font-size: 1.4rem;
            font-weight: 700;
            color: #4a5f8f;
        }
        .reservation-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: flex-end;
        }
        .Agregar-mas a {
            color: #4a5f8f;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .Agregar-mas a:hover {
            text-decoration: underline;
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
            <h1>Mis reservaciones</h1>
            <p class="subtitle">Gestiona y haz seguimiento de tus eventos a los que asistirás</p>
        </header>
        
        <div class="filters-section">
            <div class="filter-tabs">
                <button class="tab-btn active" data-filter="all">Todas</button>
                <button class="tab-btn" data-filter="Confirmado">Confirmadas</button>
                <button class="tab-btn" data-filter="Cancelado">Canceladas</button>
            </div>
        </div>

        <div class="reservations-container">
            <?php if (empty($reservations)): ?>
                <div class="empty-history-box">
                    <p>No tienes reservaciones registradas.</p>
                    <a href="../index.php">Explorar Eventos →</a>
                </div>
            <?php else: ?>
                <div class="reservations-header">
                    <div class="header-col">Reservación</div>
                    <div class="header-col">Espacios reservados</div>
                </div>

                <?php foreach ($reservations as $res): ?>
                    <?php
                        $fechaOriginal = $res['fecha_evento'];
                        $timestamp = strtotime($fechaOriginal);
                        
                        $diasSemana = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
                        $mesesLetras = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
                        
                        $diaLetra = $diasSemana[date('w', $timestamp)];
                        $diaNum = date('d', $timestamp);
                        $mesLetra = $mesesLetras[(int)date('m', $timestamp) - 1];
                        $anio = date('Y', $timestamp);
                        
                        $fechaFormateada = "$diaLetra, $diaNum de $mesLetra de $anio";
                        $horaFormateada = date('h:i a', strtotime($res['hora_inicio']));
                        
                        $estadoReserva = $res['estado_reservacion']; // 'Confirmado' o 'Cancelado'
                        $estadoEvento = $res['estado_evento']; // 'activo', 'finalizado', 'cancelado'
                        
                        $cardStatus = $estadoReserva === 'Cancelado' ? 'cancelado' : ($estadoEvento === 'finalizado' ? 'finalizado' : 'activo');
                    ?>
                    <div class="reservation-card" data-status="<?php echo htmlspecialchars($estadoReserva); ?>">
                        <div class="reservation-info">
                            <img src="<?php echo htmlspecialchars('../' . ($res['imagen_banner'] ?: 'Assets/img/img_home.png')); ?>" alt="Banner" class="reservation-image">
                            <div class="reservation-details">
                                <h3 class="reservation-title"><?php echo htmlspecialchars($res['nombre_evento']); ?> - <span style="font-size: 0.9rem; color: #666; font-weight: normal;"><?php echo htmlspecialchars($res['nombre_categoria'] ?: 'General'); ?></span></h3>
                                <p class="reservation-date"><?php echo htmlspecialchars($fechaFormateada); ?> a las <?php echo htmlspecialchars($horaFormateada); ?></p>
                                
                                <?php if ($estadoReserva === 'Cancelado'): ?>
                                    <p class="status-label" style="color: #dc3545;">Tu Reserva: Cancelada</p>
                                <?php else: ?>
                                    <p class="status-label" style="color: #28a745;">Tu Reserva: Confirmada (Evento <?php echo ucfirst($estadoEvento); ?>)</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="reservation-spaces">
                            <span class="spaces-count"><?php echo (int)$res['espacios_reservados']; ?></span>
                        </div>
                        
                        <div class="reservation-actions">
                            <?php if ($estadoReserva === 'Confirmado' && $estadoEvento === 'activo'): ?>
                                <button class="Agregar-mas">
                                    <a href="reservar.php?event_id=<?php echo $res['id_evento']; ?>">Modificar</a>
                                </button>
                                <button class="btn-cancelar-res" data-event-id="<?php echo $res['id_evento']; ?>" style="background-color: #dc3545; color: white; border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer; font-size: 13px; font-weight: 500; margin-top: 5px;">
                                    Cancelar
                                </button>
                            <?php else: ?>
                                <span style="font-size: 0.85rem; color: #888; font-weight: 500;">Sin acciones</span>
                            <?php endif; ?>
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

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Manejar filtros por pestañas
        const tabs = document.querySelectorAll('.tab-btn');
        const cards = document.querySelectorAll('.reservation-card');

        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                tabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');

                const filter = this.getAttribute('data-filter');

                cards.forEach(card => {
                    const status = card.getAttribute('data-status');
                    if (filter === 'all' || status === filter) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });

        // Manejar cancelación
        const botonesCancelar = document.querySelectorAll('.btn-cancelar-res');

        botonesCancelar.forEach(boton => {
            boton.addEventListener('click', async function(e) {
                e.preventDefault();
                
                if(!confirm('¿Estás seguro de que deseas cancelar esta reservación? Los espacios quedarán libres para otros usuarios.')) {
                    return;
                }

                const eventId = this.getAttribute('data-event-id');
                const btn = this;
                
                btn.disabled = true;
                btn.textContent = 'Cancelando...';

                try {
                    const response = await fetch('../php/cancel_reservation.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ event_id: eventId })
                    });

                    const result = await response.json();

                    if (result.success) {
                        alert('Reservación cancelada exitosamente.');
                        window.location.reload();
                    } else {
                        alert('Error: ' + result.message);
                        btn.disabled = false;
                        btn.textContent = 'Cancelar';
                    }

                } catch (error) {
                    console.error('Error:', error);
                    alert('Hubo un error de conexión.');
                    btn.disabled = false;
                    btn.textContent = 'Cancelar';
                }
            });
        });
    });
    </script>
</body>
</html>
