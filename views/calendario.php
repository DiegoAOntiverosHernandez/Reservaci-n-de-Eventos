<?php
require_once __DIR__ . '/../php/auth.php';

// Exigir inicio de sesión
requireLogin();

$id_usuario = getCurrentUserId();

try {
    // Obtener todos los eventos para el calendario
    $stmt = $pdo->prepare("
        SELECT e.id_evento, e.nombre_evento, e.fecha, e.hora_inicio, e.estado,
               c.nombre_categoria,
               COALESCE(z.id_zona, 0) AS id_zona,
               COALESCE(z.lugares_totales, e.capacidad_total) AS cap_total,
               COALESCE(z.lugares_disponibles, 0) AS cap_disponible,
               COALESCE((
                   SELECT SUM(r.espacios_reservados) 
                   FROM Reservacion r 
                   WHERE r.id_zona = z.id_zona AND r.estado_reservacion = 'Confirmado'
               ), 0) AS total_asistentes,
               COALESCE((
                   SELECT SUM(r.espacios_reservados) 
                   FROM Reservacion r 
                   WHERE r.id_usuario = ? AND r.id_zona = z.id_zona AND r.estado_reservacion = 'Confirmado'
               ), 0) AS mi_reserva
        FROM Evento e
        LEFT JOIN Categoria c ON e.id_categoria = c.id_categoria
        LEFT JOIN Zona_Boleto z ON e.id_evento = z.id_evento AND z.nombre_zona = 'General'
        ORDER BY e.fecha ASC
    ");
    $stmt->execute([$id_usuario]);
    $dbEvents = $stmt->fetchAll();

    $calendarEvents = [];
    $today = date('Y-m-d');

    foreach ($dbEvents as $ev) {
        $date = $ev['fecha'];
        
        // Determinar status
        $status = 'available';
        if ($ev['estado'] === 'cancelado') {
            $status = 'cancelled';
        } elseif ($date < $today) {
            $status = 'passed';
        } elseif ((int)$ev['cap_disponible'] <= 0) {
            $status = 'full';
        }

        $calendarEvents[] = [
            'id'        => (int)$ev['id_evento'],
            'title'     => $ev['nombre_evento'],
            'date'      => $date,
            'time'      => date('h:i a', strtotime($ev['hora_inicio'])),
            'category'  => $ev['nombre_categoria'] ?: 'General',
            'attendees' => (int)$ev['total_asistentes'],
            'capacity'  => (int)$ev['cap_total'],
            'my_spots'  => (int)$ev['mi_reserva'],
            'status'    => $status
        ];
    }

} catch (Exception $e) {
    $calendarEvents = [];
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
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/styles_events/calendario.css">
    <title>Agora - Calendario</title>
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
        <div class="calendar-wrapper">
            <div class="calendar-header">
                <h1>Calendario</h1>
                <div class="category-filter">
                    <select id="category-select">
                        <option value="all">Categoría (Todas)</option>
                        <option value="Concierto">Concierto</option>
                        <option value="Conferencia">Conferencia</option>
                        <option value="Evento deportivo">Evento deportivo</option>
                        <option value="Obra de teatro">Obra de teatro</option>
                        <option value="Festival">Festival</option>
                        <option value="Evento social">Evento social</option>
                    </select>
                </div>
            </div>

            <div class="month-navigation">
                <button class="nav-btn" id="prev-month">
                    <span>‹</span> Mes anterior
                </button>
                <div class="current-month" id="current-month"></div>
                <button class="nav-btn" id="next-month">
                    Mes siguiente <span>›</span>
                </button>
            </div>

            <div class="calendar-grid">
                <div class="weekday-header">Domingo</div>
                <div class="weekday-header">Lunes</div>
                <div class="weekday-header">Martes</div>
                <div class="weekday-header">Miércoles</div>
                <div class="weekday-header">Jueves</div>
                <div class="weekday-header">Viernes</div>
                <div class="weekday-header">Sábado</div>

                <div id="calendar-days"></div>
            </div>
        </div>

        <aside class="legend">
            <h3>Leyenda</h3>
            <div class="legend-item">
                <span class="legend-color available"></span>
                <span>Evento disponible (Activo)</span>
            </div>
            <div class="legend-item">
                <span class="legend-color full"></span>
                <span>Evento lleno</span>
            </div>
            <div class="legend-item">
                <span class="legend-color passed"></span>
                <span>Evento pasado</span>
            </div>
            <div class="legend-item">
                <span class="legend-color cancelled"></span>
                <span>Evento cancelado</span>
            </div>
        </aside>
    </div>

    <div id="event-modal" class="modal">
        <div class="modal-content">
            <button class="modal-close" id="close-modal">&times;</button>
            <h2 id="modal-title"></h2>
            <div id="modal-body"></div>
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
        const events = <?php echo json_encode($calendarEvents); ?>;

        let currentDate = new Date();
        let currentMonth = currentDate.getMonth();
        let currentYear = currentDate.getFullYear();
        let activeFilter = 'all';

        function initCalendar() {
            updateMonthDisplay();
            renderCalendar();
        }

        function updateMonthDisplay() {
            const months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            document.getElementById('current-month').textContent = `${months[currentMonth]}, ${currentYear}`;
        }

        function renderCalendar() {
            const calendarDays = document.getElementById('calendar-days');
            calendarDays.innerHTML = '';

            const firstDay = new Date(currentYear, currentMonth, 1).getDay();
            const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
            const daysInPrevMonth = new Date(currentYear, currentMonth, 0).getDate();

            const today = new Date();
            const isCurrentMonth = today.getMonth() === currentMonth && today.getFullYear() === currentYear;

            for (let i = firstDay - 1; i >= 0; i--) {
                const dayCell = createDayCell(daysInPrevMonth - i, true);
                calendarDays.appendChild(dayCell);
            }

            for (let day = 1; day <= daysInMonth; day++) {
                const isToday = isCurrentMonth && day === today.getDate();
                const dayCell = createDayCell(day, false, isToday);
                
                const monthStr = String(currentMonth + 1).padStart(2, '0');
                const dayStr = String(day).padStart(2, '0');
                const dateStr = `${currentYear}-${monthStr}-${dayStr}`;
                
                // Filtrar eventos por fecha y categoría
                const dayEvents = events.filter(event => {
                    const dateMatch = event.date === dateStr;
                    const catMatch = activeFilter === 'all' || event.category === activeFilter;
                    return dateMatch && catMatch;
                });
                
                dayEvents.forEach(event => {
                    const eventEl = document.createElement('div');
                    eventEl.className = `event-item ${event.status}`;
                    eventEl.textContent = event.title;
                    eventEl.title = `${event.time} - ${event.category}`; 
                    eventEl.onclick = (e) => {
                        e.stopPropagation();
                        showEventModal(event);
                    };
                    dayCell.appendChild(eventEl);
                });
                
                calendarDays.appendChild(dayCell);
            }

            const totalCells = calendarDays.children.length;
            const remainingCells = 42 - totalCells; 
            if(remainingCells < 7) { 
                for (let day = 1; day <= remainingCells; day++) {
                    const dayCell = createDayCell(day, true);
                    calendarDays.appendChild(dayCell);
                }
            }
        }

        function createDayCell(day, isOtherMonth, isToday = false) {
            const dayCell = document.createElement('div');
            dayCell.className = 'day-cell';
            if (isOtherMonth) dayCell.classList.add('other-month');
            if (isToday) dayCell.classList.add('today');
            
            const dayNumber = document.createElement('div');
            dayNumber.className = 'day-number';
            dayNumber.textContent = day;
            dayCell.appendChild(dayNumber);
            
            return dayCell;
        }

        function showEventModal(event) {
            const modal = document.getElementById('event-modal');
            const modalTitle = document.getElementById('modal-title');
            const modalBody = document.getElementById('modal-body');
            
            modalTitle.textContent = event.title;
            
            const statusText = {
                available: 'Disponible',
                full: 'Lleno',
                passed: 'Pasado',
                cancelled: 'Cancelado'
            };
            
            modalBody.innerHTML = `
                <div class="event-detail" style="margin-bottom: 12px;">
                    <strong>Fecha:</strong>
                    ${formatDate(event.date)}
                </div>
                <div class="event-detail" style="margin-bottom: 12px;">
                    <strong>Hora:</strong>
                    ${event.time}
                </div>
                <div class="event-detail" style="margin-bottom: 12px;">
                    <strong>Categoría:</strong>
                    ${event.category}
                </div>
                <div class="event-detail" style="margin-bottom: 12px;">
                    <strong>Asistentes Totales:</strong>
                    ${event.attendees} / ${event.capacity}
                </div>
                <div class="event-detail" style="background-color: #e8f5e9; padding: 8px; border-radius: 6px; margin-bottom: 12px;">
                    <strong>Tu Reservación:</strong>
                    ${event.my_spots > 0 ? `${event.my_spots} lugares` : 'Sin reservar'}
                </div>
                <div class="event-detail" style="margin-bottom: 18px;">
                    <strong>Estado del Evento:</strong>
                    <span style="font-weight: bold; color: ${event.status === 'available' ? 'green' : 'red'}">${statusText[event.status] || event.status}</span>
                </div>
                ${event.status === 'available' ? `<a href="reservar.php?event_id=${event.id}" class="card-button" style="text-align: center; display: block; text-decoration: none; margin-top: 10px;">Reservar ahora</a>` : ''}
            `;
            
            modal.classList.add('active');
        }

        function closeModal() {
            document.getElementById('event-modal').classList.remove('active');
        }

        function formatDate(dateStr) {
            const [year, month, day] = dateStr.split('-');
            const date = new Date(year, month - 1, day);
            
            const days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
            const months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            
            return `${days[date.getDay()]} ${date.getDate()} de ${months[date.getMonth()]}, ${date.getFullYear()}`;
        }

        document.getElementById('prev-month').addEventListener('click', () => {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            updateMonthDisplay();
            renderCalendar();
        });

        document.getElementById('next-month').addEventListener('click', () => {
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            updateMonthDisplay();
            renderCalendar();
        });

        document.getElementById('close-modal').addEventListener('click', closeModal);

        document.getElementById('event-modal').addEventListener('click', (e) => {
            if (e.target.id === 'event-modal') {
                closeModal();
            }
        });

        document.getElementById('category-select').addEventListener('change', (e) => {
            activeFilter = e.target.value;
            renderCalendar();
        });

        // Iniciar
        initCalendar();
    </script>
</body>
</html>
