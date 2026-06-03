<?php
require_once __DIR__ . '/../php/auth.php';

// Exigir inicio de sesión
requireLogin();

$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$id_organizador = getCurrentUserId();

if ($event_id <= 0) {
    die('<h2>Error</h2><p>ID de evento inválido.</p>');
}

try {
    // Obtener datos del evento y su capacidad (desde la zona General)
    $stmt = $pdo->prepare("
        SELECT e.*, COALESCE(z.lugares_totales, e.capacidad_total) AS cap_total
        FROM Evento e
        LEFT JOIN Zona_Boleto z ON e.id_evento = z.id_evento AND z.nombre_zona = 'General'
        WHERE e.id_evento = ?
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        die('<h2>Error</h2><p>El evento no existe.</p>');
    }

    if ((int)$event['id_organizador'] !== $id_organizador) {
        die('<h2>Acceso Denegado</h2><p>No tienes permisos para editar este evento.</p>');
    }

    // Obtener la categoría del evento para preseleccionar
    $currentCatName = '';
    if (isset($event['id_categoria'])) {
        $stmtCatName = $pdo->prepare("SELECT nombre_categoria FROM Categoria WHERE id_categoria = ?");
        $stmtCatName->execute([$event['id_categoria']]);
        $currentCatName = strtolower($stmtCatName->fetchColumn() ?: '');
        
        // Normalizar a los valores del selector
        if ($currentCatName === 'obra de teatro' || $currentCatName === 'teatro' || $currentCatName === 'arte') {
            $currentCatName = 'teatro';
        } elseif ($currentCatName === 'evento social' || $currentCatName === 'social' || $currentCatName === 'boda' || $currentCatName === 'salon' || $currentCatName === 'salón') {
            $currentCatName = 'social';
        } elseif ($currentCatName === 'evento deportivo' || $currentCatName === 'deporte') {
            $currentCatName = 'deporte';
        }
    }

    // Obtener secciones del itinerario
    $stmtIt = $pdo->prepare("SELECT * FROM Itinerario WHERE id_evento = ? ORDER BY id_itinerario ASC");
    $stmtIt->execute([$event_id]);
    $itinerary_sections = $stmtIt->fetchAll();

} catch (Exception $e) {
    die('Error en el servidor: ' . $e->getMessage());
}

// Formatear fecha y hora para flatpickr
$formattedDate = '';
if (!empty($event['fecha'])) {
    $formattedDate = date('d/m/Y', strtotime($event['fecha']));
}

$formattedTimeStart = '';
if (!empty($event['hora_inicio'])) {
    $formattedTimeStart = date('h:i A', strtotime($event['hora_inicio']));
}

$formattedTimeEnd = '';
if (!empty($event['hora_cierre'])) {
    $formattedTimeEnd = date('h:i A', strtotime($event['hora_cierre']));
}

$bannerUrl = '';
if (!empty($event['imagen_banner'])) {
    $bannerUrl = '../' . $event['imagen_banner'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/home_styles/nav_footer.css">
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/home_styles/crearevento.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="../Assets/Js/home.js"></script>
    <title>Agora - Editar Evento</title>
    <link rel="shortcut icon" href="../Assets/img/logo.png" type="image/x-icon">
</head>
<body>
    <!-- Navbar Premium -->
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
                
                <li>
                    <button class="user-welcome" onclick="window.location.href='perfil.php'" style="color: #64b5f6; font-weight: 600;">
                        Hola, <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?>
                    </button>
                </li>
            </ul>
        </nav>
    </div>
    
    <div class="form-container">
        <div class="form-header">
            <h2>✨ Edita tu evento</h2>
            <p>Completa la información para dar vida a tu evento perfecto.</p>
        </div>

        <form id="editEventForm" data-event-id="<?php echo $event['id_evento']; ?>">
            <!-- 1. Información básica -->
            <div class="form-section-title">Información básica</div>
            
            <div class="form-row-2">
                <div class="form-group">
                    <label for="nombre">Nombre <span class="required">*</span></label>
                    <input type="text" id="nombre" name="nombre" placeholder="Ej. “Concierto benéfico”" value="<?php echo htmlspecialchars($event['nombre_evento']); ?>" maxlength="33" required>
                    <div class="error-txt" id="error-nombre"></div>
                </div>

                <div class="form-group">
                    <label for="categoria">Categoría <span class="required">*</span></label>
                    <select id="categoria" name="categoria" required>
                        <option value="">Selecciona una categoría</option>
                        <?php
                        $categoriesOptions = [
                            'concierto' => 'Concierto',
                            'teatro' => 'Obra de teatro',
                            'conferencia' => 'Conferencia',
                            'festival' => 'Festival',
                            'social' => 'Evento social'
                        ];
                        foreach ($categoriesOptions as $val => $label) {
                            $selected = ($currentCatName === $val) ? 'selected' : '';
                            echo "<option value=\"$val\" $selected>$label</option>";
                        }
                        ?>
                    </select>
                    <div class="error-txt" id="error-categoria"></div>
                </div>
            </div>

            <div class="form-group">
                <label for="descripcion">Descripción</label>
                <textarea id="descripcion" name="descripcion" rows="5" placeholder="Cuéntanos más sobre el cronograma, invitados y detalles importantes..."><?php echo htmlspecialchars($event['descripcion']); ?></textarea>
                <div class="error-txt" id="error-descripcion"></div>
            </div>

            <!-- 2. Imagen + Fecha/Horario/Capacidad -->
            <div class="form-section-title">Imagen del evento y horarios</div>
            
            <div class="special-media-layout">
                <!-- Columna Izquierda: Dropzone de Imagen -->
                <div class="form-group">
                    <label>Imagen del evento</label>
                    <div class="image-upload-card" onclick="document.getElementById('fileInput').click();">
                        <span class="upload-icon">📷</span>
                        <span class="upload-text">Subir Imagen / Banner</span>
                        <!-- Input oculto -->
                        <input type="file" id="fileInput" accept="image/*" style="display: none;">
                        <img id="imagePreview" class="image-preview" src="<?php echo htmlspecialchars($bannerUrl); ?>" style="<?php echo $bannerUrl ? 'display: block;' : ''; ?>" alt="Vista previa">
                    </div>
                </div>

                <!-- Columna Derecha: Controles de Horario/Capacidad -->
                <div style="display: flex; flex-direction: column; justify-content: space-between;">
                    <div class="form-group">
                        <label for="fecha-evento">Fecha del evento <span class="required">*</span></label>
                        <div class="input-icon-wrapper">
                            <span class="input-field-icon">📅</span>
                            <input type="text" id="fecha-evento" name="fecha-evento" value="<?php echo htmlspecialchars($formattedDate); ?>" required>
                        </div>
                        <div class="error-txt" id="error-fecha"></div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="hora-inicio">Hora de inicio <span class="required">*</span></label>
                            <div class="input-icon-wrapper">
                                <span class="input-field-icon">🕒</span>
                                <input type="text" id="hora-inicio" name="hora-inicio" value="<?php echo htmlspecialchars($formattedTimeStart); ?>" required>
                            </div>
                            <div class="error-txt" id="error-hora"></div>
                        </div>

                        <div class="form-group">
                            <label for="hora-cierre">Hora de cierre <span class="required">*</span></label>
                            <div class="input-icon-wrapper">
                                <span class="input-field-icon">🕒</span>
                                <input type="text" id="hora-cierre" name="hora-cierre" value="<?php echo htmlspecialchars($formattedTimeEnd); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="capacidad">Capacidad del evento <span class="required">*</span></label>
                        <input type="number" id="capacidad" name="capacidad" value="<?php echo (int)$event['cap_total']; ?>" min="5" max="500">
                        <div class="error-txt" id="error-capacidad"></div>
                    </div>
                </div>
            </div>

            <!-- 3. Ubicación -->
            <div class="form-section-title">Ubicación</div>
            <div class="form-group">
                <label for="ubicacion">Ubicación <span class="required">*</span></label>
                <div class="input-icon-wrapper">
                    <span class="input-field-icon">📍</span>
                    <input type="text" id="ubicacion" name="ubicacion" placeholder="Buscar aquí" value="<?php echo htmlspecialchars($event['ubicacion']); ?>" required>
                </div>
                <div class="error-txt" id="error-ubicacion"></div>
            </div>

            <!-- 4. Itinerario -->
            <div class="form-section-title">Itinerario</div>
            <p class="itinerario-hint">¡El espacio perfecto para que tus espectadores puedan ver en qué parte va tu evento!</p>
            <div id="secciones-container"></div>
            <button type="button" class="btn-add-seccion" id="btn-agregar-seccion">
                ➕ Agregar sección
            </button>

            <!-- 5. Visibilidad / Estado -->
            <div class="form-section-title">Estado del evento</div>
            <div class="form-group" style="display: flex; flex-direction: row; align-items: center; gap: 15px;">
                <label for="visibilidad" style="margin-bottom: 0; font-weight: 600; color: #374151;">¿El evento está activo?</label>
                <input type="checkbox" id="visibilidad" name="visibilidad" <?php echo $event['estado'] === 'activo' ? 'checked' : ''; ?> style="width: auto; height: 20px; cursor: pointer;">
            </div>

            <!-- Botones -->
            <div class="form-actions">
                <button type="submit" class="btn-form-action btn-submit" id="btnSaveEdit">
                    Guardar cambios ➔
                </button>
                <a href="mi_evento.php" class="btn-form-action btn-cancel">Cancelar</a>
            </div>
        </form>
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

    <!-- Integración y Lógica -->
    <script>
        const initialSections = <?php echo json_encode($itinerary_sections); ?>;

        // Inicializar selectores de fecha y horas
        const fechaEventoInput = flatpickr("#fecha-evento", {
            dateFormat: "d/m/Y",
            placeholder: "dd/mm/aaaa",
            allowInput: false
        });

        const horaInicioInput = flatpickr("#hora-inicio", {
            enableTime: true,  
            noCalendar: true,    
            dateFormat: "h:i K",
            placeholder: "06:30 p.m.",
            allowInput: false
        });

        const horaCierreInput = flatpickr("#hora-cierre", {
            enableTime: true,  
            noCalendar: true,    
            dateFormat: "h:i K",
            placeholder: "09:30 p.m.",
            allowInput: false
        });

        // Simulación visual para la vista previa de la imagen cargada
        const fileInput = document.getElementById('fileInput');
        const imagePreview = document.getElementById('imagePreview');
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    imagePreview.src = event.target.result;
                    imagePreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });

        // Manejo de secciones del itinerario dinámico
        let numSecciones = 0;
        const maxSecciones = 10;

        function agregarSeccion(nombreSec = '', horaSec = '') {
            if (numSecciones >= maxSecciones) {
                alert(`Máximo ${maxSecciones} secciones permitidas`);
                return;
            }

            numSecciones++;
            const container = document.getElementById('secciones-container');
            
            const seccionDiv = document.createElement('div');
            seccionDiv.className = 'seccion-card';
            seccionDiv.id = `seccion-${numSecciones}`;
            seccionDiv.innerHTML = `
                <div class="seccion-header">
                    <h4>Sección ${numSecciones}</h4>
                    <button type="button" class="btn-remove-seccion" onclick="eliminarSeccion(${numSecciones})">Eliminar</button>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label for="nombre-seccion-${numSecciones}">Nombre de la sección</label>
                        <input type="text" id="nombre-seccion-${numSecciones}" value="${nombreSec}" placeholder="Ej: Apertura" class="nombre-seccion" required>
                    </div>
                    <div class="form-group">
                        <label for="hora-seccion-${numSecciones}">Hora de inicio</label>
                        <input type="text" id="hora-seccion-${numSecciones}" value="${horaSec}" class="hora-seccion" placeholder="06:30 p.m." required>
                    </div>
                </div>
            `;
            
            container.appendChild(seccionDiv);

            // Inicializar flatpickr para la hora de la sección
            flatpickr(`#hora-seccion-${numSecciones}`, {
                enableTime: true,  
                noCalendar: true,    
                dateFormat: "h:i K",
                placeholder: "06:30 p.m.",
                allowInput: false
            });
        }

        function eliminarSeccion(id) {
            const seccion = document.getElementById(`seccion-${id}`);
            if (seccion) {
                seccion.remove();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const btnAgregarSeccion = document.getElementById('btn-agregar-seccion');
            btnAgregarSeccion.addEventListener('click', function(e) {
                e.preventDefault();
                agregarSeccion();
            });

            // Cargar itinerarios existentes si los hay
            if (initialSections && initialSections.length > 0) {
                initialSections.forEach(sec => {
                    let formattedTime = sec.hora_inicio;
                    if (sec.hora_inicio) {
                        const [hours, minutes] = sec.hora_inicio.split(':');
                        const h = parseInt(hours, 10);
                        const modifier = h >= 12 ? 'PM' : 'AM';
                        const displayHour = h % 12 === 0 ? 12 : h % 12;
                        formattedTime = `${displayHour.toString().padStart(2, '0')}:${minutes} ${modifier}`;
                    }
                    agregarSeccion(sec.nombre_seccion, formattedTime);
                });
            } else {
                // Si no hay secciones, crear 3 por defecto
                agregarSeccion();
                agregarSeccion();
                agregarSeccion();
            }

            // Google Places Autocomplete API Loader
            if (typeof google !== 'undefined' && google.maps && google.maps.places) {
                const input = document.getElementById('ubicacion');
                new google.maps.places.Autocomplete(input);
            }
        });

        // Conversión y envío AJAX
        const convertDateToSqlFormat = (dateStr) => {
            if (!dateStr) return null;
            if (dateStr.includes('-')) return dateStr;
            const [day, month, year] = dateStr.split('/');
            return `${year}-${month}-${day}`;
        };

        const convertTimeToSqlFormat = (timeStr) => {
            if (!timeStr) return null;
            if (timeStr.split(':').length === 3) return timeStr;
            const parts = timeStr.split(' ');
            const time = parts[0];
            const modifier = parts[1];
            let [hours, minutes] = time.split(':');

            if (hours === '12') {
                hours = '00';
            }
            if (modifier && modifier.toUpperCase() === 'PM') {
                hours = parseInt(hours, 10) + 12;
            }

            return `${hours.toString().padStart(2, '0')}:${minutes}:00`;
        };

        const form = document.getElementById('editEventForm');
        const btnSaveEdit = document.getElementById('btnSaveEdit');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const eventId = form.getAttribute('data-event-id');
            const data = {
                event_id: eventId,
                nombre: document.getElementById('nombre').value.trim(),
                categoria: document.getElementById('categoria').value,
                descripcion: document.getElementById('descripcion').value.trim(),
                fecha: document.getElementById('fecha-evento').value,
                hora_inicio: document.getElementById('hora-inicio').value,
                hora_cierre: document.getElementById('hora-cierre').value,
                capacidad: parseInt(document.getElementById('capacidad').value),
                ubicacion: document.getElementById('ubicacion').value.trim(),
                estado: document.getElementById('visibilidad').checked ? 'activo' : 'cancelado'
            };

            const sqlDate = convertDateToSqlFormat(data.fecha);
            const sqlTimeStart = convertTimeToSqlFormat(data.hora_inicio);
            const sqlTimeEnd = convertTimeToSqlFormat(data.hora_cierre);

            // Recopilar Itinerario
            const secciones = [];
            document.querySelectorAll('.seccion-card').forEach(div => {
                const nombreInput = div.querySelector('.nombre-seccion');
                const horaInput = div.querySelector('.hora-seccion');
                if (nombreInput && horaInput) {
                    const nombreSec = nombreInput.value.trim();
                    const horaSec = horaInput.value.trim();
                    if (nombreSec && horaSec) {
                        secciones.push({
                            nombre: nombreSec,
                            hora: convertTimeToSqlFormat(horaSec) || horaSec
                        });
                    }
                }
            });

            // Si hay banner de imagen cargado
            let bannerToSend = null;
            if (imagePreview.src && imagePreview.style.display === 'block') {
                if (imagePreview.src.startsWith('data:image')) {
                    bannerToSend = imagePreview.src;
                }
            }

            const dataToSend = {
                event_id: data.event_id,
                nombre: data.nombre,
                categoria: data.categoria,
                descripcion: data.descripcion,
                fecha: sqlDate,
                hora_inicio: sqlTimeStart,
                hora_cierre: sqlTimeEnd,
                capacidad: data.capacidad,
                ubicacion: data.ubicacion,
                estado: data.estado,
                itinerario: secciones,
                imagen_base64: bannerToSend
            };

            btnSaveEdit.disabled = true;
            btnSaveEdit.innerHTML = 'Guardando...';

            try {
                const response = await fetch('../php/update_event.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(dataToSend)
                });

                const result = await response.json();

                if (result.success) {
                    alert('¡Evento actualizado exitosamente!');
                    window.location.href = 'mi_evento.php';
                } else {
                    alert('Fallo al actualizar: ' + result.message);
                    btnSaveEdit.disabled = false;
                    btnSaveEdit.innerHTML = 'Guardando cambios ➔';
                }
            } catch (error) {
                console.error(error);
                alert('Hubo un error de conexión.');
                btnSaveEdit.disabled = false;
                btnSaveEdit.innerHTML = 'Guardando cambios ➔';
            }
        });
    </script>
</body>
</html>
