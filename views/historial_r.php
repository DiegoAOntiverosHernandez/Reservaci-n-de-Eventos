<?php
require_once __DIR__ . '/../php/auth.php';

// Exigir inicio de sesión
requireLogin();

$id_usuario = getCurrentUserId();
$usuario_nombre = isset($_SESSION['usuario_nombre']) ? $_SESSION['usuario_nombre'] : 'Usuario';

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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <title>Mis Reservaciones - Agora</title>
    <link rel="shortcut icon" href="../Assets/img/logo.png" type="image/x-icon">
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/styles_events/history.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Navbar Premium Agora -->
    <div class="nav">
        <div class="logo">
            <a href="../index.php"><img src="../Assets/img/title.png" alt="Agora"></a>
        </div>
        
        <ul class="nav-menus">
            <li class="menu">
                <a href="eventos.php">Eventos</a>
            </li>
            <li>
                <a href="historial_r.php" class="active">Mis reservaciones</a>
            </li>
            <li>
                <a href="calendario.php">Calendario</a>
            </li>
        </ul>

        <!-- Badge de Perfil de Usuario -->
        <a href="perfil.php" class="user-profile-badge">
            <span style="color: #94a3b8; font-weight: 500; font-size: 0.92rem;"><?php echo htmlspecialchars($usuario_nombre); ?></span>
            <div class="user-avatar">
                <?php 
                // Simulamos avatar con la inicial
                echo strtoupper(substr($usuario_nombre, 0, 1));
                ?>
            </div>
        </a>
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
            <h1>Mis reservaciones</h1>
            <p>Gestiona y haz seguimiento de tus reservaciones</p>
        </div>

        <!-- Fila de Controles (Buscador, Estado, Ordenamiento) -->
        <div class="controls-row">
            <!-- Buscar por Evento -->
            <div class="search-box-container">
                <span class="search-icon">🔍</span>
                <input type="text" id="searchInput" class="search-input" placeholder="Buscar por evento...">
            </div>

            <!-- Filtros de Estado y Ordenación -->
            <div class="filters-group">
                <select id="statusFilter" class="select-filter">
                    <option value="todos">Confirmadas</option> <!-- Por defecto muestra Confirmadas como en el mockup -->
                    <option value="confirmado">Confirmado</option>
                    <option value="finalizado">Finalizado</option>
                    <option value="cancelado">Cancelado</option>
                    <option value="todos_real">Todas</option>
                </select>

                <select id="orderFilter" class="select-filter">
                    <option value="reciente">Fecha (Reciente)</option>
                    <option value="antiguo">Fecha (Antiguo)</option>
                </select>
            </div>
        </div>

        <!-- Listado de Reservaciones -->
        <div class="reservations-list-wrapper">
            <?php if (empty($reservations)): ?>
                <div class="empty-history-box">
                    <p>No tienes reservaciones registradas.</p>
                    <a href="../index.php">Explorar Eventos</a>
                </div>
            <?php else: ?>
                <div id="reservationsGrid">
                    <?php foreach ($reservations as $res): ?>
                        <?php
                        $id_reservacion = $res['id_reservacion'];
                        $espacios = (int)$res['espacios_reservados'];
                        $nombre_zona = $res['nombre_zona'] ?: 'General';
                        $precio_total = (float)$res['precio'] * $espacios;

                        // Intentar obtener asientos reales de la base de datos vinculados a los boletos de esta reservación
                        $asientos_str = '';
                        $stmtSeats = $pdo->prepare("
                            SELECT a.fila, a.numero
                            FROM Boleto b
                            JOIN Asiento a ON b.id_asiento = a.id_asiento
                            WHERE b.id_reservacion = ? AND b.estado_boleto = 'valido'
                        ");
                        $stmtSeats->execute([$id_reservacion]);
                        $dbSeats = $stmtSeats->fetchAll(PDO::FETCH_ASSOC);

                        if (!empty($dbSeats)) {
                            $seatsList = [];
                            foreach ($dbSeats as $ds) {
                                $seatsList[] = trim($ds['fila']) . trim($ds['numero']);
                            }
                            $asientos_str = implode(', ', $seatsList);
                        } else {
                            // Generar nombres de asientos deterministas e idénticos (fallback)
                            $asientos = [];
                            $prefix = 'G'; // General
                            $nombre_zona_clean = trim(strtoupper($nombre_zona));
                            if ($nombre_zona_clean === 'VIP') {
                                $prefix = 'V';
                            } elseif ($nombre_zona_clean === 'ZONA A' || $nombre_zona_clean === 'A') {
                                $prefix = 'A';
                            } elseif ($nombre_zona_clean === 'ZONA B' || $nombre_zona_clean === 'B') {
                                $prefix = 'B';
                            } elseif ($nombre_zona_clean === 'ZONA C' || $nombre_zona_clean === 'C') {
                                $prefix = 'C';
                            } elseif ($nombre_zona_clean === 'ZONA D' || $nombre_zona_clean === 'D') {
                                $prefix = 'D';
                            }
                            
                            $start = (($res['id_evento'] * 7 + $id_usuario * 3) % 40) + 1;
                            for ($i = 0; $i < $espacios; $i++) {
                                $asientos[] = $prefix . ($start + $i);
                            }
                            $asientos_str = implode(', ', $asientos);
                        }

                        // Formatear Fecha
                        $timestamp = strtotime($res['fecha_evento']);
                        $diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
                        $mesesShort = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
                        
                        $diaLetra = $diasSemana[date('w', $timestamp)];
                        $diaNum = date('j', $timestamp);
                        $mesLetra = $mesesShort[(int)date('m', $timestamp) - 1];
                        $horaFormateada = date('h:i a', strtotime($res['hora_inicio']));
                        
                        $fechaFormateada = "$diaLetra $diaNum $mesLetra, $horaFormateada";

                        $estadoReserva = $res['estado_reservacion']; // 'Confirmado' o 'Cancelado'
                        $estadoEvento = $res['estado_evento']; // 'activo', 'finalizado', 'cancelado'

                        // Determinar estado final para la UI
                        $statusClass = '';
                        $statusText = '';
                        if ($estadoReserva === 'Cancelado') {
                            $statusClass = 'cancelado';
                            $statusText = 'cancelado';
                        } elseif ($estadoEvento === 'finalizado') {
                            $statusClass = 'finalizado';
                            $statusText = 'finalizado';
                        } else {
                            $statusClass = 'confirmado';
                            $statusText = 'confirmado';
                        }

                        // Verificar si está activo para habilitar cancelación
                        $isCancelable = ($estadoReserva === 'Confirmado' && $estadoEvento === 'activo');
                        $isViewable = ($estadoReserva === 'Confirmado');
                        ?>
                        <div class="res-premium-card" 
                             data-title="<?php echo htmlspecialchars(strtolower($res['nombre_evento'])); ?>" 
                             data-status="<?php echo $statusText; ?>"
                             data-timestamp="<?php echo strtotime($res['fecha_reservacion']); ?>">
                            
                            <div class="card-main-body">
                                <div class="card-left-info">
                                    <!-- Imagen miniatura -->
                                    <img src="<?php echo htmlspecialchars('../' . ($res['imagen_banner'] ?: 'Assets/img/img_home.png')); ?>" alt="Banner" class="card-thumbnail">
                                    
                                    <div class="card-details-info">
                                        <h3><?php echo htmlspecialchars($res['nombre_evento']); ?></h3>
                                        <p class="card-meta-line"><?php echo htmlspecialchars($res['nombre_categoria'] ?: 'General'); ?> — <?php echo htmlspecialchars($fechaFormateada); ?> — 📍 <?php echo htmlspecialchars($res['ubicacion']); ?></p>
                                        <p class="card-seats-line">Asientos: <?php echo htmlspecialchars($asientos_str); ?> — <?php echo htmlspecialchars($nombre_zona); ?> — $<?php echo number_format($precio_total, 2); ?> MXN</p>
                                    </div>
                                </div>

                                <!-- Badge de estado -->
                                <div class="res-status-badge <?php echo $statusClass; ?>">
                                    <?php echo $statusText; ?>
                                </div>
                            </div>

                            <!-- Barra de botones inferior -->
                            <div class="card-buttons-bar">
                                <a href="boleto.php?reservacion_id=<?php echo $id_reservacion; ?>&asientos=<?php echo urlencode($asientos_str); ?>" class="btn-card-action btn-card-view <?php echo $isViewable ? '' : 'disabled'; ?>">
                                    Ver boleto
                                </a>
                                
                                <button type="button" class="btn-card-action btn-card-incident" onclick="openIncidentModal(<?php echo $res['id_evento']; ?>, '<?php echo addslashes($res['nombre_evento']); ?>')">
                                    Reportar incidencia
                                </button>
                                
                                <button type="button" class="btn-card-action btn-card-cancel <?php echo $isCancelable ? '' : 'disabled'; ?>" onclick="cancelReservation(<?php echo $res['id_evento']; ?>, this)">
                                    Cancelar reservación
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de Incidencias Interactivo -->
    <div id="incidentModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeIncidentModal()">&times;</button>
            <h3>Reportar Incidencia</h3>
            <p>Por favor, detalle el problema que experimenta con el evento: <br><strong id="modalEventName" style="color: #404a6e;"></strong></p>
            
            <form id="incidentForm">
                <input type="hidden" id="modalEventId" value="">
                
                <div class="modal-form-group">
                    <label for="incidentSubject">Asunto del Reporte</label>
                    <select id="incidentSubject" required>
                        <option value="">Seleccione una opción...</option>
                        <option value="Problema con mis boletos">Problema con mis boletos / Asientos</option>
                        <option value="Duda sobre el recinto">Duda sobre el recinto / Ubicación</option>
                        <option value="Cambio de datos">Cambio de datos personales</option>
                        <option value="Solicitud de reembolso">Solicitud de reembolso</option>
                        <option value="Otro">Otro asunto</option>
                    </select>
                </div>

                <div class="modal-form-group">
                    <label for="incidentDescription">Descripción del inconveniente</label>
                    <textarea id="incidentDescription" rows="5" placeholder="Describa a detalle lo ocurrido..." required></textarea>
                </div>

                <button type="submit" id="btnIncidentSubmit" class="btn-modal-submit">Enviar reporte</button>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <div id="footer">
        <footer class="footer">
            <div class="footer_content">
                <div class="name">
                    <img src="../Assets/img/title.png" alt="Agora">
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

    <!-- Script del Dashboard -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('searchInput');
            const statusFilter = document.getElementById('statusFilter');
            const orderFilter = document.getElementById('orderFilter');
            const cards = document.querySelectorAll('.res-premium-card');

            // Filtrado interactivo dinámico (Buscar + Estado)
            function filterReservations() {
                const query = searchInput.value.toLowerCase().trim();
                const filterVal = statusFilter.value; // 'todos', 'confirmado', 'finalizado', 'cancelado', 'todos_real'

                cards.forEach(card => {
                    const title = card.getAttribute('data-title');
                    const status = card.getAttribute('data-status');

                    const matchesQuery = title.includes(query);
                    let matchesStatus = false;

                    if (filterVal === 'todos') {
                        // Por defecto en el mockup, "Confirmadas" muestra confirmadas y finalizadas
                        matchesStatus = (status === 'confirmado' || status === 'finalizado');
                    } else if (filterVal === 'todos_real') {
                        matchesStatus = true;
                    } else {
                        matchesStatus = (status === filterVal);
                    }

                    if (matchesQuery && matchesStatus) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
            }

            // Ordenamiento dinámico
            function sortReservations() {
                const grid = document.getElementById('reservationsGrid');
                if (!grid) return;
                
                const cardsArray = Array.from(cards);
                const orderVal = orderFilter.value;

                cardsArray.sort((a, b) => {
                    const timeA = parseInt(a.getAttribute('data-timestamp'));
                    const timeB = parseInt(b.getAttribute('data-timestamp'));

                    return orderVal === 'reciente' ? timeB - timeA : timeA - timeB;
                });

                // Re-insertar en el DOM ordenado
                cardsArray.forEach(card => grid.appendChild(card));
            }

            // Event Listeners para controles
            if(searchInput) searchInput.addEventListener('input', filterReservations);
            if(statusFilter) statusFilter.addEventListener('change', filterReservations);
            if(orderFilter) orderFilter.addEventListener('change', sortReservations);

            // Inicializar filtros
            filterReservations();
        });

        // Cancelación de Reservación
        async function cancelReservation(eventId, buttonElement) {
            if(!confirm('¿Estás seguro de que deseas cancelar esta reservación? Los espacios quedarán libres para otros usuarios.')) {
                return;
            }

            buttonElement.disabled = true;
            buttonElement.textContent = 'Cancelando...';

            try {
                const response = await fetch('../php/cancel_reservation.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ event_id: eventId })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Reservación cancelada exitosamente.');
                    window.location.reload();
                } else {
                    alert('Error: ' + result.message);
                    buttonElement.disabled = false;
                    buttonElement.textContent = 'Cancelar reservación';
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Hubo un error de conexión.');
                buttonElement.disabled = false;
                buttonElement.textContent = 'Cancelar reservación';
            }
        }

        // Modal de Incidencias
        const modal = document.getElementById('incidentModal');
        const modalEventId = document.getElementById('modalEventId');
        const modalEventName = document.getElementById('modalEventName');
        const incidentForm = document.getElementById('incidentForm');
        const btnIncidentSubmit = document.getElementById('btnIncidentSubmit');

        function openIncidentModal(eventId, eventName) {
            modalEventId.value = eventId;
            modalEventName.textContent = eventName;
            modal.classList.add('open');
        }

        function closeIncidentModal() {
            modal.classList.remove('open');
            incidentForm.reset();
        }

        // Enviar Reporte de Incidencia
        if (incidentForm) {
            incidentForm.addEventListener('submit', async (e) => {
                e.preventDefault();

                const eventId = parseInt(modalEventId.value);
                const subject = document.getElementById('incidentSubject').value;
                const desc = document.getElementById('incidentDescription').value;

                btnIncidentSubmit.disabled = true;
                btnIncidentSubmit.textContent = 'Enviando reporte...';

                try {
                    const response = await fetch('../php/report_incident.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            event_id: eventId,
                            asunto: subject,
                            descripcion: desc
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        alert(result.message);
                        closeIncidentModal();
                    } else {
                        alert('Fallo al reportar: ' + result.message);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Hubo un error al conectar con el servidor.');
                } finally {
                    btnIncidentSubmit.disabled = false;
                    btnIncidentSubmit.textContent = 'Enviar reporte';
                }
            });
        }
    </script>
</body>
</html>
