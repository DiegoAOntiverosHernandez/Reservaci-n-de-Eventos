-- =====================================================
-- BASE DE DATOS: AGORA (Esquema Unificado Definitivo y Ampliado)
-- =====================================================
DROP DATABASE IF EXISTS sistema_agora;
CREATE DATABASE sistema_agora CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sistema_agora;

-- =====================================================
-- 1. USUARIOS (Autenticación, perfiles y roles de acceso)
-- =====================================================
CREATE TABLE Usuario (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    correo_electronico VARCHAR(255) NOT NULL UNIQUE,
    contrasena VARCHAR(255) NOT NULL,
    foto_perfil VARCHAR(500) DEFAULT NULL,
    telefono VARCHAR(20) DEFAULT NULL,
    biografia TEXT DEFAULT NULL,
    rol ENUM('cliente', 'organizador', 'administrador') DEFAULT 'cliente' NOT NULL,
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- 2. CATEGORÍAS (Filtros dinámicos de búsqueda en la Landing)
-- =====================================================
CREATE TABLE Categoria (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre_categoria VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- Insertar las categorías requeridas por la interfaz
INSERT INTO Categoria (nombre_categoria) VALUES
('Concierto'), ('Evento deportivo'), ('Obra de teatro'), 
('Conferencia'), ('Festival'), ('Evento social');

-- =====================================================
-- 3. RECINTOS (Espacios físicos para la maquetación de asientos)
-- =====================================================
CREATE TABLE Recinto (
    id_recinto INT AUTO_INCREMENT PRIMARY KEY,
    nombre_recinto VARCHAR(150) NOT NULL,
    tipo_recinto ENUM('teatro', 'estadio', 'conferencia', 'sin_recinto') NOT NULL,
    capacidad_maxima INT NOT NULL CHECK (capacidad_maxima >= 0),
    direccion VARCHAR(255) NOT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Insertar algunos recintos de muestra correspondientes al diseño
INSERT INTO Recinto (nombre_recinto, tipo_recinto, capacidad_maxima, direccion) VALUES
('Teatro Ágora Principal', 'teatro', 300, 'Av. de las Artes 456, Centro Histórico'),
('Estadio Olímpico Ágora', 'estadio', 5000, 'Complejo Deportivo Norte'),
('Sala de Conferencias Ágora', 'conferencia', 150, 'Pasaje de la Innovación 102, Edificio Ágora'),
('Plaza Pública del Sol (General)', 'sin_recinto', 1000, 'Calle Libre 789');

-- =====================================================
-- 4. EVENTOS (Información general, banners y estados)
-- =====================================================
CREATE TABLE Evento (
    id_evento INT AUTO_INCREMENT PRIMARY KEY,
    nombre_evento VARCHAR(150) NOT NULL,
    descripcion TEXT NOT NULL,
    imagen_banner VARCHAR(500) DEFAULT NULL, 
    ubicacion VARCHAR(255) NOT NULL, -- Mantiene redundancia amigable o nombre del lugar específico
    id_recinto INT DEFAULT NULL, -- Relación directa para cargar el layout de asientos
    fecha DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_cierre TIME NOT NULL,
    capacidad_total INT NOT NULL DEFAULT 0, -- Se calculará automáticamente sumando las zonas con Triggers
    estado ENUM('activo', 'finalizado', 'cancelado') DEFAULT 'activo' NOT NULL,
    id_organizador INT NOT NULL,
    id_categoria INT NOT NULL,
    FOREIGN KEY (id_organizador) REFERENCES Usuario(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_categoria) REFERENCES Categoria(id_categoria) ON DELETE RESTRICT,
    FOREIGN KEY (id_recinto) REFERENCES Recinto(id_recinto) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- 5. ITINERARIO (Cronograma dinámico para la vista de detalles)
-- =====================================================
CREATE TABLE Itinerario (
    id_itinerario INT AUTO_INCREMENT PRIMARY KEY,
    id_evento INT NOT NULL,
    nombre_seccion VARCHAR(150) NOT NULL, 
    descripcion_seccion VARCHAR(255) DEFAULT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    FOREIGN KEY (id_evento) REFERENCES Evento(id_evento) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 6. ZONA BOLETO (Asientos, precios y monetización por áreas)
-- =====================================================
CREATE TABLE Zona_Boleto (
    id_zona INT AUTO_INCREMENT PRIMARY KEY,
    id_evento INT NOT NULL,
    nombre_zona VARCHAR(100) NOT NULL, -- Ej: 'VIP', 'Zona A', 'General'
    precio DECIMAL(10,2) NOT NULL DEFAULT 0.00 CHECK (precio >= 0),
    lugares_totales INT NOT NULL CHECK (lugares_totales > 0),
    lugares_disponibles INT NOT NULL CHECK (lugares_disponibles >= 0),
    FOREIGN KEY (id_evento) REFERENCES Evento(id_evento) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 7. ASIENTOS (Distribución física numerada para recintos estructurados)
-- =====================================================
CREATE TABLE Asiento (
    id_asiento INT AUTO_INCREMENT PRIMARY KEY,
    id_zona INT NOT NULL,
    fila VARCHAR(10) NOT NULL, -- Ej: 'A', 'B', 'Fila 1'
    numero VARCHAR(10) NOT NULL, -- Ej: '15', 'Asiento 3'
    estado ENUM('disponible', 'reservado', 'ocupado', 'mantenimiento') DEFAULT 'disponible' NOT NULL,
    FOREIGN KEY (id_zona) REFERENCES Zona_Boleto(id_zona) ON DELETE CASCADE,
    UNIQUE KEY uq_asiento_zona (id_zona, fila, numero)
) ENGINE=InnoDB;

-- =====================================================
-- 8. TRANSACCIÓN (Control de facturación e ingresos para Dashboard)
-- =====================================================
CREATE TABLE Transaccion (
    id_transaccion INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_evento INT NOT NULL,
    monto_total DECIMAL(10,2) NOT NULL CHECK (monto_total >= 0),
    metodo_pago ENUM('tarjeta', 'paypal', 'transferencia', 'efectivo', 'gratis') NOT NULL DEFAULT 'tarjeta',
    referencia_pago VARCHAR(100) DEFAULT NULL, -- ID devuelto por pasarela (Stripe, PayPal, etc.)
    digitos_tarjeta VARCHAR(4) DEFAULT NULL, -- Últimos 4 dígitos para comprobante
    fecha_transaccion DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado_pago ENUM('completado', 'pendiente', 'reembolsado') DEFAULT 'completado' NOT NULL,
    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_evento) REFERENCES Evento(id_evento) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 9. RESERVACIONES (Vinculación de usuario con zonas y compras)
-- =====================================================
CREATE TABLE Reservacion (
    id_reservacion INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL, 
    id_zona INT NOT NULL,
    id_transaccion INT DEFAULT NULL, -- NULL si es evento gratuito
    espacios_reservados INT NOT NULL DEFAULT 1 CHECK (espacios_reservados > 0), 
    estado_reservacion ENUM('Confirmado', 'Cancelado') DEFAULT 'Confirmado' NOT NULL,
    fecha_reservacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_zona) REFERENCES Zona_Boleto(id_zona) ON DELETE CASCADE,
    FOREIGN KEY (id_transaccion) REFERENCES Transaccion(id_transaccion) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- 10. BOLETOS INDIVIDUALES (Tickets digitales con QR para validación)
-- =====================================================
CREATE TABLE Boleto (
    id_boleto INT AUTO_INCREMENT PRIMARY KEY,
    id_reservacion INT NOT NULL,
    id_asiento INT DEFAULT NULL, -- NULL si es General/Sin recinto
    codigo_unico VARCHAR(100) NOT NULL UNIQUE, -- Código QR/UUID hash para validador en puerta
    estado_boleto ENUM('valido', 'usado', 'cancelado') DEFAULT 'valido' NOT NULL,
    fecha_generacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_reservacion) REFERENCES Reservacion(id_reservacion) ON DELETE CASCADE,
    FOREIGN KEY (id_asiento) REFERENCES Asiento(id_asiento) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- 11. INCIDENCIAS (Soporte técnico directo desde el mockup)
-- =====================================================
CREATE TABLE Incidencia (
    id_incidencia INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_evento INT DEFAULT NULL, -- Opcional, si ocurre en un evento
    asunto VARCHAR(150) NOT NULL,
    descripcion TEXT NOT NULL,
    estado ENUM('pendiente', 'en_revision', 'resuelto') DEFAULT 'pendiente' NOT NULL,
    fecha_reporte DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_evento) REFERENCES Evento(id_evento) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- 12. BITÁCORA DE AUDITORÍA (Monitoreo de seguridad interna)
-- =====================================================
CREATE TABLE Bitacora (
    id_bitacora INT AUTO_INCREMENT PRIMARY KEY,
    accion VARCHAR(100) NOT NULL,
    fecha_accion DATETIME DEFAULT CURRENT_TIMESTAMP,
    detalle TEXT NOT NULL,
    id_evento_afectado INT DEFAULT NULL
) ENGINE=InnoDB;

-- =====================================================
-- 13. VISTAS SQL (Estadísticas automatizadas para el Dashboard)
-- =====================================================
CREATE VIEW Vista_Dashboard_Organizador AS
SELECT 
    e.id_evento,
    e.nombre_evento,
    e.fecha,
    e.estado AS estado_evento,
    e.id_organizador,
    e.capacidad_total,
    COALESCE(SUM(CASE WHEN r.estado_reservacion = 'Confirmado' THEN r.espacios_reservados ELSE 0 END), 0) AS total_boletos_vendidos,
    ROUND(
        (COALESCE(SUM(CASE WHEN r.estado_reservacion = 'Confirmado' THEN r.espacios_reservados ELSE 0 END), 0) / e.capacidad_total) * 100, 
        2
    ) AS porcentaje_ocupacion,
    COALESCE(SUM(CASE WHEN t.estado_pago = 'completado' THEN t.monto_total ELSE 0.00 END), 0.00) AS ingresos_totales
FROM 
    Evento e
LEFT JOIN 
    Zona_Boleto z ON e.id_evento = z.id_evento
LEFT JOIN 
    Reservacion r ON z.id_zona = r.id_zona
LEFT JOIN 
    Transaccion t ON r.id_transaccion = t.id_transaccion
GROUP BY 
    e.id_evento;

-- =====================================================
-- 14. TRIGGERS DE AUTOMATIZACIÓN AVANZADA
-- =====================================================

-- Trigger A: Auditoría al crear un evento
DELIMITER //
CREATE TRIGGER trg_auditoria_evento
AFTER INSERT ON Evento
FOR EACH ROW
BEGIN
    INSERT INTO Bitacora (accion, detalle, id_evento_afectado)
    VALUES ('CREACION_EVENTO', CONCAT('Evento publicado: ', NEW.nombre_evento, ' por organizador ID: ', NEW.id_organizador), NEW.id_evento);
END //
DELIMITER ;

-- Trigger B: Descontar lugares disponibles al hacer una reservación y generar tickets dinámicos
DELIMITER //
CREATE TRIGGER trg_descontar_lugares_reservacion
AFTER INSERT ON Reservacion
FOR EACH ROW
BEGIN
    DECLARE i INT DEFAULT 0;
    
    -- Descontar los cupos en la zona
    IF NEW.estado_reservacion = 'Confirmado' THEN
        UPDATE Zona_Boleto 
        SET lugares_disponibles = lugares_disponibles - NEW.espacios_reservados
        WHERE id_zona = NEW.id_zona;
        
        -- Auditoría
        INSERT INTO Bitacora (accion, detalle, id_evento_afectado)
        VALUES ('CREACION_RESERVACION', CONCAT('Usuario ', NEW.id_usuario, ' reservó ', NEW.espacios_reservados, ' lugar(es) en zona ID ', NEW.id_zona), NULL);
        
        -- Bucle para generar los Boletos individuales asociados y códigos QR simulados
        WHILE i < NEW.espacios_reservados DO
            INSERT INTO Boleto (id_reservacion, id_asiento, codigo_unico)
            VALUES (
                NEW.id_reservacion, 
                NULL, -- Se asigna dinámicamente si se implementa selección de asiento específico
                CONCAT('QR-', NEW.id_reservacion, '-', UNIX_TIMESTAMP(), '-', FLOOR(RAND() * 1000000))
            );
            SET i = i + 1;
        END WHILE;
    END IF;
END //
DELIMITER ;

-- Trigger C: Restaurar lugares al cancelar una reservación y cancelar boletos
DELIMITER //
CREATE TRIGGER trg_restaurar_lugares_cancelacion
AFTER UPDATE ON Reservacion
FOR EACH ROW
BEGIN
    -- Si la reservación pasa a cancelada, devolvemos los asientos al stock y cancelamos boletos
    IF OLD.estado_reservacion = 'Confirmado' AND NEW.estado_reservacion = 'Cancelado' THEN
        UPDATE Zona_Boleto 
        SET lugares_disponibles = lugares_disponibles + OLD.espacios_reservados
        WHERE id_zona = OLD.id_zona;
        
        -- Cancelar boletos vinculados
        UPDATE Boleto 
        SET estado_boleto = 'cancelado'
        WHERE id_reservacion = OLD.id_reservacion;
        
        -- Auditoría
        INSERT INTO Bitacora (accion, detalle, id_evento_afectado)
        VALUES ('CANCELACION_RESERVACION', CONCAT('Reserva ID ', OLD.id_reservacion, ' cancelada. Se restauraron ', OLD.espacios_reservados, ' lugares.'), NULL);
    END IF;
END //
DELIMITER ;

-- Trigger D: Actualizar la capacidad total del evento de forma automática al insertar zonas
DELIMITER //
CREATE TRIGGER trg_actualizar_capacidad_insert
AFTER INSERT ON Zona_Boleto
FOR EACH ROW
BEGIN
    UPDATE Evento 
    SET capacidad_total = capacidad_total + NEW.lugares_totales
    WHERE id_evento = NEW.id_evento;
END //
DELIMITER ;

-- Trigger E: Actualizar la capacidad total del evento al modificar zonas
DELIMITER //
CREATE TRIGGER trg_actualizar_capacidad_update
AFTER UPDATE ON Zona_Boleto
FOR EACH ROW
BEGIN
    UPDATE Evento 
    SET capacidad_total = capacidad_total - OLD.lugares_totales + NEW.lugares_totales
    WHERE id_evento = NEW.id_evento;
END //
DELIMITER ;

-- Trigger F: Descontar la capacidad total al eliminar zonas
DELIMITER //
CREATE TRIGGER trg_actualizar_capacidad_delete
AFTER DELETE ON Zona_Boleto
FOR EACH ROW
BEGIN
    UPDATE Evento 
    SET capacidad_total = capacidad_total - OLD.lugares_totales
    WHERE id_evento = OLD.id_evento;
END //
DELIMITER ;
