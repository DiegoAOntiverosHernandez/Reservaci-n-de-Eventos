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
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/home_styles/crearevento.css">
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/home_styles/home_events.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="../Assets/Js/home.js?v=2"></script>
    <script src="../Assets/Js/newevent.js"></script>
    <title>Agora - Crear Evento</title>
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
    
    <br> <!-- Espacio para el contenido principal -->
    <div class="form-container">
        <div class="form-header">
            <h2>Crea tu evento con nosotros</h2>
            <p>Completa la información para dar vida a tu evento perfecto.</p>
        </div>

        <form id="createEventForm">
            <div class="form-section">
                <h3>Información básica</h3>
                <div class="form-group">
                    <label for="nombre">Nombre</label>
                    <input type="text" id="nombre" name="nombre" placeholder="Ej: “Concierto benéfico” Max. 33 caracteres" maxlength="33">
                    <div class="error-txt" id="error-nombre"></div>
                </div>

                <div class="form-group">
                    <label for="categoria">Categoría</label>
                    <select id="categoria" name="categoria" required>
                        <option value="">Selecciona una categoría</option>
                        <option value="concierto">Concierto</option>
                        <option value="conferencia">Conferencia</option>
                        <option value="boda">Boda</option>
                        <option value="deporte">Deporte</option>
                        <option value="arte">Arte</option>
                        <option value="salon">Salón</option>
                    </select>
                    <div class="error-txt" id="error-categoria"></div>
                </div>

                <div class="form-group area-descripcion">
                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" name="descripcion" rows="5"></textarea>
                    <div class="error-txt" id="error-descripcion"></div>
                </div>
            </div>

            <div class="form-section">
                <h3>Fecha, horario y capacidad</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="fecha-evento">Fecha del evento</label>
                        <div class="input-with-icon">
                            <input type="date" id="fecha-evento" name="fecha-evento"> 
                            <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/>
                            </svg>
                        </div>
                        <div class="error-txt" id="error-fecha"></div>
                    </div>

                    <div class="form-group">
                        <label for="hora-inicio">Hora de inicio</label>
                        <div class="input-with-icon">
                            <input type="time" id="hora-inicio" name="hora-inicio" placeholder="06:30 p.m.">
                            <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/>
                                <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/>
                            </svg>
                        </div>
                        <div class="error-txt" id="error-hora"></div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="capacidad">Capacidad total</label>
                        <input type="number" id="capacidad" name="capacidad" value="25" min="1" max="30">
                        <span class="input-hint">Máximo 30 personas</span>
                        <div class="error-txt" id="error-capacidad"></div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Ubicación</h3>
                <div class="form-group">
                    <label for="ubicacion">¿En dónde será tu evento?</label>
                    <input type="text" id="ubicacion" name="ubicacion" placeholder="Buscar aquí">
                    <div class="error-txt" id="error-ubicacion"></div>
                </div>
            </div>

            <div class="form-section">
                <h3>Itinerario</h3>
                <p class="itinerario-hint">¡El espacio perfecto para que tus espectadores puedan ver en qué parte va tu evento!</p>
                <div id="secciones-container"></div>
                <button type="button" class="btn btn-agregar-seccion" id="btn-agregar-seccion">
                    Agregar sección
                </button>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-publicar" id="btnPublicar">
                    Continuar
                </button>
                <button type="button" class="btn btn-cancelar" onclick="window.location.href='../index.php'">Cancelar</button>
            </div>
        </form>
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
        // Manejo de secciones del itinerario
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
            seccionDiv.className = 'seccion-itinerario';
            seccionDiv.id = `seccion-${numSecciones}`;
            seccionDiv.innerHTML = `
                <div class="seccion-header">
                    <h4>Sección ${numSecciones}</h4>
                    <button type="button" class="btn-eliminar-seccion" onclick="eliminarSeccion(${numSecciones})">✕</button>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="nombre-seccion-${numSecciones}">Nombre de la sección</label>
                        <input type="text" id="nombre-seccion-${numSecciones}" name="nombre-seccion-${numSecciones}" placeholder="Ej: Apertura" class="nombre-seccion">
                    </div>
                    <div class="form-group">
                        <label for="hora-seccion-${numSecciones}">Hora de inicio</label>
                        <div class="input-with-icon">
                            <input type="time" id="hora-seccion-${numSecciones}" name="hora-seccion-${numSecciones}" class="hora-seccion">
                        </div>
                    </div>
                </div>
            `;
            
            container.appendChild(seccionDiv);
        }

        function eliminarSeccion(id) {
            const seccion = document.getElementById(`seccion-${id}`);
            if (seccion) {
                seccion.remove();
            }
        }

        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            const btnAgregarSeccion = document.getElementById('btn-agregar-seccion');
            btnAgregarSeccion.addEventListener('click', function(e) {
                e.preventDefault();
                agregarSeccion();
            });

            // Agregar 3 secciones iniciales
            agregarSeccion();
            agregarSeccion();
            agregarSeccion();
        });
    </script>
</body>
</html>
