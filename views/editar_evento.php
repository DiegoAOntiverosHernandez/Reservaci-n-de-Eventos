<?php
require_once __DIR__ . '/../php/auth.php';

// Exigir inicio de sesión
requireLogin();

$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$id_organizador = getCurrentUserId();

if ($event_id <= 0) {
    die('<h2>Error</h2><p>ID de evento inválido.</p>');
}

// Obtener datos del evento y su capacidad (desde la zona General)
try {
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

    // Obtener todas las categorías para listarlas dinámicamente
    $stmtCat = $pdo->query("SELECT * FROM Categoria ORDER BY nombre_categoria ASC");
    $categories = $stmtCat->fetchAll();

} catch (Exception $e) {
    die('Error en el servidor: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/home_styles/nav_footer.css">
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/styles_events/edit_event.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="../Assets/Js/home.js"></script>
    <script src="../Assets/Js/edit_event.js"></script>
    <title>Agora - Editar Evento</title>
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
    <div class="form-container">
        <div class="form-header">
            <h2>Modifica la información de tu evento</h2>
            <p>Edita los detalles necesarios y mantén informados a tus asistentes.</p>
        </div>

        <form id="editEventForm" data-event-id="<?php echo $event['id_evento']; ?>">
            <div class="form-section">
                <h3>Información básica</h3>
                <div class="form-group">
                    <label for="nombre">Nombre</label>
                    <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($event['nombre_evento']); ?>" placeholder="Ej: “Concierto benéfico” Max. 33 caracteres" maxlength="33">
                    <div class="error-txt" id="error-nombre"></div>
                </div>

                <div class="form-group">
                    <label for="categoria">Categoría</label>
                    <select id="categoria" name="categoria" required>
                        <option value="">Selecciona una categoría</option>
                        <?php foreach ($categories as $cat): ?>
                            <?php 
                                $selected = ((int)$event['id_categoria'] === (int)$cat['id_categoria']) ? 'selected' : '';
                            ?>
                            <option value="<?php echo $cat['id_categoria']; ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($cat['nombre_categoria']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="error-txt" id="error-categoria"></div>
                </div>

                <div class="form-group area-descripcion">
                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" name="descripcion" rows="5"><?php echo htmlspecialchars($event['descripcion']); ?></textarea>
                    <div class="error-txt" id="error-descripcion"></div>
                </div>
            </div>

            <div class="form-section">
                <h3>Fecha, horario y capacidad</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="fecha">Fecha del evento</label>
                        <div class="input-with-icon">
                            <input type="date" id="fecha" name="fecha" value="<?php echo htmlspecialchars($event['fecha']); ?>"> 
                        </div>
                        <div class="error-txt" id="error-fecha"></div>
                    </div>

                    <div class="form-group">
                        <label for="hora">Hora de inicio</label>
                        <div class="input-with-icon">
                            <input type="time" id="hora" name="hora" value="<?php echo htmlspecialchars(substr($event['hora_inicio'], 0, 5)); ?>">
                        </div>
                        <div class="error-txt" id="error-hora"></div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="capacidad">Capacidad total</label>
                        <div class="capacity-control">
                            <button type="button" class="capacity-btn" data-action="decrement">—</button>
                            <input type="number" id="capacidad" name="capacidad" value="<?php echo (int)$event['cap_total']; ?>" min="1" max="30">
                            <button type="button" class="capacity-btn" data-action="increment">+</button>
                        </div>
                        <span class="input-hint">Máximo 30 personas</span>
                        <div class="error-txt" id="error-capacidad"></div>
                    </div>
                </div>
            </div>

            <div class="form-group toggle-group" style="margin-top: 20px;">
                <label for="visibilidad">Visibilidad (Activo/Cancelado)</label>
                <label class="switch">
                    <input type="checkbox" id="visibilidad" name="visibilidad" <?php echo $event['estado'] === 'activo' ? 'checked' : ''; ?>>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-publicar" id="btnSaveEdit">
                    Guardar cambios
                </button>
                <button type="button" class="btn btn-cancelar" onclick="window.location.href='mi_evento.php'">Cancelar</button>
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
</body>
</html>
