<?php
require_once __DIR__ . '/../php/auth.php';

// Exigir inicio de sesión
requireLogin();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/home_styles/nav_footer.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <title>Crea tu Evento - Agora</title>
    <link rel="shortcut icon" href="../Assets/img/logo.png" type="image/x-icon">
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/home_styles/crearevento.css?v=<?php echo time(); ?>">
    <style>
        /* Premium Modal Styles */
        .premium-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
            font-family: 'Outfit', sans-serif;
        }
        .premium-modal-overlay.active {
            opacity: 1;
            pointer-events: all;
        }
        .premium-modal-card {
            background: white;
            color: #1e293b;
            border-radius: 20px;
            padding: 35px;
            max-width: 440px;
            width: 90%;
            text-align: center;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            transform: scale(0.9);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .premium-modal-overlay.active .premium-modal-card {
            transform: scale(1);
        }
        .premium-modal-title {
            font-size: 1.4rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 12px;
            line-height: 1.3;
        }
        .premium-modal-text {
            font-size: 0.95rem;
            color: #4b5563;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        .premium-modal-btn {
            background-color: #00b347; /* Verde idéntico */
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(0, 179, 71, 0.2);
            font-family: 'Outfit', sans-serif;
        }
        .premium-modal-btn:hover {
            background-color: #00993c;
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(0, 179, 71, 0.3);
        }
    </style>
</head>
<body>
    <!-- Navbar Premium -->
    <div class="nav">
        <div class="logo"><a href="../index.php"><img src="../Assets/img/title.png" alt="Agora"></a></div>
    </div>
    
    <div class="form-container">
        <div class="form-header">
            <h2>🪄 Crea tu evento con nosotros</h2>
            <p>Completa la información para dar vida a tu evento perfecto.</p>
        </div>

        <form id="createEventForm">
            <!-- 1. Información básica -->
            <div class="form-section-title">Información básica</div>
            
            <div class="form-row-2">
                <div class="form-group">
                    <label for="nombre">Nombre <span class="required">*</span></label>
                    <input type="text" id="nombre" name="nombre" placeholder="Ej. “Concierto benéfico”" maxlength="33" required>
                    <div class="error-txt" id="error-nombre"></div>
                </div>

                <div class="form-group">
                    <label for="categoria">Categoría <span class="required">*</span></label>
                    <select id="categoria" name="categoria" required>
                        <option value="">Selecciona una categoría</option>
                        <option value="concierto">Concierto</option>
                        <option value="teatro">Obra de teatro</option>
                        <option value="conferencia">Conferencia</option>
                        <option value="festival">Festival</option>
                        <option value="social">Evento social</option>
                    </select>
                    <div class="error-txt" id="error-categoria"></div>
                </div>
            </div>

            <div class="form-group">
                <label for="descripcion">Descripción</label>
                <textarea id="descripcion" name="descripcion" rows="5" placeholder="Cuéntanos más sobre el cronograma, invitados y detalles importantes..."></textarea>
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
                        <img id="imagePreview" class="image-preview" alt="Vista previa">
                    </div>
                </div>

                <!-- Columna Derecha: Controles de Horario/Capacidad -->
                <div style="display: flex; flex-direction: column; justify-content: space-between;">
                    <div class="form-group">
                        <label for="fecha-evento">Fecha del evento <span class="required">*</span></label>
                        <div class="input-icon-wrapper">
                            <span class="input-field-icon">📅</span>
                            <input type="text" id="fecha-evento" name="fecha-evento" required>
                        </div>
                        <div class="error-txt" id="error-fecha"></div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="hora-inicio">Hora de inicio <span class="required">*</span></label>
                            <div class="input-icon-wrapper">
                                <span class="input-field-icon">🕒</span>
                                <input type="text" id="hora-inicio" name="hora-inicio" required>
                            </div>
                            <div class="error-txt" id="error-hora"></div>
                        </div>

                        <div class="form-group">
                            <label for="hora-cierre">Hora de cierre <span class="required">*</span></label>
                            <div class="input-icon-wrapper">
                                <span class="input-field-icon">🕒</span>
                                <input type="text" id="hora-cierre" name="hora-cierre" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="capacidad">Capacidad del evento <span class="required">*</span></label>
                        <input type="number" id="capacidad" name="capacidad" value="25" min="5" max="500">
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
                    <input type="text" id="ubicacion" name="ubicacion" placeholder="Buscar aquí" required>
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

            <!-- Botones -->
            <div class="form-actions">
                <button type="submit" class="btn-form-action btn-submit" id="btnPublicar">
                    Continuar ➔
                </button>
                <a href="../index.php" class="btn-form-action btn-cancel">Cancelar</a>
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

    <!-- Integración predictiva con Google Maps Autocomplete + Lógica Formulario -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Inicializar selectores de fecha y horas premium
        const fechaEventoInput = flatpickr("#fecha-evento", {
            dateFormat: "d/m/Y",
            placeholder: "dd/mm/aaaa",
            allowInput: false,
            minDate: "today"
        });

        const horaInicioInput = flatpickr("#hora-inicio", {
            enableTime: true,  
            noCalendar: true,    
            dateFormat: "h:i K",
            defaultHour: 18,     
            defaultMinute: 30,
            placeholder: "06:30 p.m.",
            allowInput: false
        });

        const horaCierreInput = flatpickr("#hora-cierre", {
            enableTime: true,  
            noCalendar: true,    
            dateFormat: "h:i K",
            defaultHour: 21,     
            defaultMinute: 30,
            placeholder: "09:30 p.m.",
            allowInput: false
        });

        // Simulación predictiva y visual para la vista previa de la imagen cargada
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

        // Manejo de secciones del itinerario dinámico idéntico a la imagen
        let numSecciones = 0;
        const maxSecciones = 10;

        function agregarSeccion() {
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
                        <input type="text" id="nombre-seccion-${numSecciones}" placeholder="Ej: Apertura" class="nombre-seccion" required>
                    </div>
                    <div class="form-group">
                        <label for="hora-seccion-${numSecciones}">Hora de inicio</label>
                        <input type="text" id="hora-seccion-${numSecciones}" class="hora-seccion" placeholder="06:30 p.m." required>
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

        // Agregar 3 secciones iniciales por defecto como en la imagen
        document.addEventListener('DOMContentLoaded', function() {
            const btnAgregarSeccion = document.getElementById('btn-agregar-seccion');
            btnAgregarSeccion.addEventListener('click', function(e) {
                e.preventDefault();
                agregarSeccion();
            });

            agregarSeccion();
            agregarSeccion();
            agregarSeccion();

            // Google Places Autocomplete API Fallback/Loader
            // Si el script de Google Maps API se carga externamente, inicializarlo
            if (typeof google !== 'undefined' && google.maps && google.maps.places) {
                const input = document.getElementById('ubicacion');
                new google.maps.places.Autocomplete(input);
            }
        });

        // Envío AJAX e integración con create_event.php
        const convertDateToSqlFormat = (dateStr) => {
            if (!dateStr) return null;
            const [day, month, year] = dateStr.split('/');
            return `${year}-${month}-${day}`;
        };

        const convertTimeToSqlFormat = (timeStr) => {
            if (!timeStr) return null;
            const [time, modifier] = timeStr.split(' ');
            let [hours, minutes] = time.split(':');

            if (hours === '12') {
                hours = '00';
            }
            if (modifier && modifier.toUpperCase() === 'PM') {
                hours = parseInt(hours, 10) + 12;
            }

            return `${hours.toString().padStart(2, '0')}:${minutes}:00`;
        };

        const form = document.getElementById('createEventForm');
        const btnPublicar = document.getElementById('btnPublicar');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const data = {
                nombre: document.getElementById('nombre').value.trim(),
                categoria: document.getElementById('categoria').value,
                descripcion: document.getElementById('descripcion').value.trim(),
                fecha: document.getElementById('fecha-evento').value,
                hora_inicio: document.getElementById('hora-inicio').value,
                hora_cierre: document.getElementById('hora-cierre').value,
                capacidad: parseInt(document.getElementById('capacidad').value),
                ubicacion: document.getElementById('ubicacion').value.trim()
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

            // Si hay banner de imagen cargado, lo simulamos para persistir
            let bannerToSend = null;
            if (imagePreview.src && imagePreview.style.display === 'block') {
                bannerToSend = imagePreview.src;
            }

            const dataToSend = {
                nombre: data.nombre,
                categoria: data.categoria,
                descripcion: data.descripcion,
                fecha: sqlDate,
                hora_inicio: sqlTimeStart,
                hora_cierre: sqlTimeEnd,
                capacidad: data.capacidad,
                ubicacion: data.ubicacion,
                itinerario: secciones,
                imagen_base64: bannerToSend
            };

            btnPublicar.disabled = true;
            btnPublicar.innerHTML = 'Procesando...';

            try {
                const response = await fetch('../php/create_event.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(dataToSend)
                });

                const result = await response.json();

                if (result.success) {
                    const evId = result.id_evento;
                    if (result.is_first_event) {
                        // Mostrar modal de felicitación premium
                        const modal = document.getElementById('premiumAdminModal');
                        const acceptBtn = document.getElementById('premiumModalAcceptBtn');
                        modal.classList.add('active');
                        acceptBtn.onclick = () => {
                            window.location.href = `organizar_recinto.php?event_id=${evId}&capacidad=${data.capacidad}`;
                        };
                    } else {
                        alert('Paso 1 Completado: Información básica guardada.');
                        window.location.href = `organizar_recinto.php?event_id=${evId}&capacidad=${data.capacidad}`;
                    }
                } else {
                    alert('Fallo al publicar: ' + result.message);
                    btnPublicar.disabled = false;
                    btnPublicar.innerHTML = 'Continuar ➔';
                }
            } catch (error) {
                console.error(error);
                alert('Hubo un error de conexión.');
                btnPublicar.disabled = false;
                btnPublicar.innerHTML = 'Continuar ➔';
            }
        });
    </script>

    <!-- Modal de Felicitación Premium de Rol -->
    <div id="premiumAdminModal" class="premium-modal-overlay">
        <div class="premium-modal-card">
            <h3 class="premium-modal-title">Usted ahora es administrador de eventos</h3>
            <p class="premium-modal-text">
                Privilegios concedidos. Al publicar su primer evento con nosotros, su cuenta ha sido promovida al rol de <strong>Organizador</strong> para que pueda administrar sus eventos de manera integral.
            </p>
            <button id="premiumModalAcceptBtn" class="premium-modal-btn">Continuar</button>
        </div>
    </div>
</body>
</html>
