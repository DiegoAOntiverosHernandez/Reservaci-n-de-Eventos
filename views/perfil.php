<?php
require_once __DIR__ . '/../php/auth.php';

// Exigir inicio de sesión
requireLogin();

$id_usuario = getCurrentUserId();
$user = getCurrentUser();

// Obtener reservaciones activas del usuario
try {
    $stmt = $pdo->prepare("
        SELECT r.id_reservacion, r.espacios_reservados, r.fecha_reservacion,
               z.nombre_zona, z.precio,
               e.id_evento, e.nombre_evento, e.fecha AS fecha_evento, e.hora_inicio, e.imagen_banner, e.estado AS estado_evento,
               c.nombre_categoria
        FROM Reservacion r
        JOIN Zona_Boleto z ON r.id_zona = z.id_zona
        JOIN Evento e ON z.id_evento = e.id_evento
        LEFT JOIN Categoria c ON e.id_categoria = c.id_categoria
        WHERE r.id_usuario = ? AND r.estado_reservacion = 'Confirmado'
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
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/styles_events/perfil.css">
    <script src="../Assets/Js/home.js"></script>
    <title>Agora - Perfil de Usuario</title>
    <link rel="shortcut icon" href="../Assets/img/logo.png" type="image/x-icon">
    <style>
        .empty-reservations-box {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        .empty-reservations-box p {
            font-size: 1.1rem;
            margin-bottom: 15px;
        }
        .empty-reservations-box a {
            display: inline-block;
            background: #4a5f8f;
            color: #fff;
            padding: 10px 25px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
        }
        .empty-reservations-box a:hover {
            background: #3e5178;
        }
        .profile-sidebar {
            flex-shrink: 0;
        }
        .reservation-card {
            border: 1px solid #eef2f5;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }
        .reservation-info {
            display: flex;
            align-items: center;
            gap: 15px;
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
            font-size: 1.1rem;
            margin: 0 0 5px 0;
            color: #333;
            font-weight: 600;
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
            font-weight: 600;
            margin: 0;
        }
        .reservation-spaces {
            padding: 0 20px;
            font-size: 1.3rem;
            font-weight: 700;
            color: #4a5f8f;
            text-align: center;
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
        
        @media (max-width: 768px) {
            .reservation-card {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .reservation-spaces {
                padding: 0;
                text-align: left;
            }
            .reservation-actions {
                width: 100%;
                align-items: flex-start;
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
                
                <li>
                    <button class="user-welcome" onclick="window.location.href='perfil.php'" style="color: #64b5f6; font-weight: 600;">
                        Hola, <?php echo htmlspecialchars($user['nombre']); ?>
                    </button>
                </li>
            </ul>
        </nav>
    </div>

    <br>
    <div class="container">
        <aside class="profile-sidebar">
            <div class="profile-card">
                <div class="avatar">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                </div>
                
                <button class="btn-logout" onclick="logout()">Cerrar sesión</button>
                
                <div class="profile-info">
                    <div class="info-group">
                        <label>Nombre:</label>
                        <p><?php echo htmlspecialchars($user['nombre']); ?></p>
                    </div>
                    
                    <div class="info-group">
                        <label>Correo electrónico:</label>
                        <p><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                </div>
                
                <button class="btn-edit" style="width: 100%; border: none; padding: 10px; border-radius: 6px; background-color: #4a5f8f; margin-top: 15px; cursor: pointer;">
                    <a href="edit_info.php" style="color: white; text-decoration: none; font-weight: 500; display: block; width: 100%;">Editar información</a>
                </button>
            </div>
        </aside>

        <main class="main-content">
            <h1>Perfil</h1>
            <div class="reservations-card">
                <div class="header-row">
                    <h2>Mis Reservaciones Activas</h2>
                    <h2>Lugares</h2>
                </div>
                
                <div class="reservations-list">
                    <?php if (empty($reservations)): ?>
                        <div class="empty-reservations-box">
                            <p>No tienes reservaciones activas en este momento.</p>
                            <a href="../index.php">Explorar Eventos</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($reservations as $res): ?>
                            <?php
                                $fechaOriginal = $res['fecha_evento'];
                                $timestamp = strtotime($fechaOriginal);
                                
                                $diasSemana = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
                                $mesesLetras = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
                                
                                $diaLetra = $diasSemana[date('w', $timestamp)];
                                $diaNum = date('d', $timestamp);
                                $mesLetra = $mesesLetras[(int)date('m', $timestamp) - 1];
                                
                                $fechaFormateada = "$diaLetra, $diaNum de $mesLetra";
                                $horaFormateada = date('h:i a', strtotime($res['hora_inicio']));
                                
                                $estado = $res['estado_evento'];
                                $colorEstado = $estado === 'activo' ? 'green' : ($estado === 'cancelado' ? 'red' : 'gray');
                                $textoEstado = $estado === 'activo' ? 'Activo' : ($estado === 'cancelado' ? 'Cancelado' : 'Finalizado');
                            ?>
                            <div class="reservation-card">
                                <div class="reservation-info">
                                    <img src="<?php echo htmlspecialchars('../' . ($res['imagen_banner'] ?: 'Assets/img/img_home.png')); ?>" alt="Banner" class="reservation-image">
                                    <div class="reservation-details">
                                        <h3 class="reservation-title"><?php echo htmlspecialchars($res['nombre_evento']); ?> - <span style="font-size: 0.9rem; color: #666; font-weight: normal;"><?php echo htmlspecialchars($res['nombre_categoria'] ?: 'General'); ?></span></h3>
                                        <p class="reservation-date"><?php echo htmlspecialchars($fechaFormateada); ?>, <?php echo htmlspecialchars($horaFormateada); ?></p>
                                        <p class="status-label" style="color: <?php echo $colorEstado; ?>;">Estado del Evento: <?php echo $textoEstado; ?></p>
                                    </div>
                                </div>
                                
                                <div class="reservation-spaces">
                                    <span class="spaces-count"><?php echo (int)$res['espacios_reservados']; ?></span>
                                </div>
                                
                                <div class="reservation-actions">
                                    <button class="Agregar-mas" style="background: none; border: none; cursor: pointer;">
                                        <a href="reservar.php?event_id=<?php echo $res['id_evento']; ?>">Modificar</a>
                                    </button>
                                    <button class="btn-cancelar-res" data-event-id="<?php echo $res['id_evento']; ?>" style="background-color: #dc3545; color: white; border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer; font-size: 13px; font-weight: 500;">
                                        Cancelar
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
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
    function logout() {
        if (confirm('¿Estás seguro de que deseas cerrar sesión?')) {
            window.location.href = '../php/logout.php';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
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
