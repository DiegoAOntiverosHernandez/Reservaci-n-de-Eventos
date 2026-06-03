-- =====================================================
-- BASE DE DATOS: AGORA (Versión compatible con Hosting Gratuito)
-- =====================================================

-- 1. USUARIOS
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

-- 2. CATEGORÍAS
CREATE TABLE Categoria (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre_categoria VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO Categoria (nombre_categoria) VALUES
('Concierto'), ('Evento deportivo'), ('Obra de teatro'), 
('Conferencia'), ('Festival'), ('Evento social');

-- 3. RECINTOS
CREATE TABLE Recinto (
    id_recinto INT AUTO_INCREMENT PRIMARY KEY,
    nombre_recinto VARCHAR(150) NOT NULL,
    tipo_recinto ENUM('teatro', 'estadio', 'conferencia', 'sin_recinto') NOT NULL,
    capacidad_maxima INT NOT NULL CHECK (capacidad_maxima >= 0),
    direccion VARCHAR(255) NOT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO Recinto (nombre_recinto, tipo_recinto, capacidad_maxima, direccion) VALUES
('Teatro Ágora Principal', 'teatro', 300, 'Av. de las Artes 456, Centro Histórico'),
('Estadio Olímpico Ágora', 'estadio', 5000, 'Complejo Deportivo Norte'),
('Sala de Conferencias Ágora', 'conferencia', 150, 'Pasaje de la Innovación 102, Edificio Ágora'),
('Plaza Pública del Sol (General)', 'sin_recinto', 1000, 'Calle Libre 789');

-- 4. EVENTOS
CREATE TABLE Evento (
    id_evento INT AUTO_INCREMENT PRIMARY KEY,
    nombre_evento VARCHAR(150) NOT NULL,
    descripcion TEXT NOT NULL,
    imagen_banner VARCHAR(500) DEFAULT NULL, 
    ubicacion VARCHAR(255) NOT NULL,
    id_recinto INT DEFAULT NULL,
    fecha DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_cierre TIME NOT NULL,
    capacidad_total INT NOT NULL DEFAULT 0,
    estado ENUM('activo', 'finalizado', 'cancelado') DEFAULT 'activo' NOT NULL,
    id_organizador INT NOT NULL,
    id_categoria INT NOT NULL,
    FOREIGN KEY (id_organizador) REFERENCES Usuario(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_categoria) REFERENCES Categoria(id_categoria) ON DELETE RESTRICT,
    FOREIGN KEY (id_recinto) REFERENCES Recinto(id_recinto) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 5. ITINERARIO
CREATE TABLE Itinerario (
    id_itinerario INT AUTO_INCREMENT PRIMARY KEY,
    id_evento INT NOT NULL,
    nombre_seccion VARCHAR(150) NOT NULL, 
    descripcion_seccion VARCHAR(255) DEFAULT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    FOREIGN KEY (id_evento) REFERENCES Evento(id_evento) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. ZONA BOLETO
CREATE TABLE Zona_Boleto (
    id_zona INT AUTO_INCREMENT PRIMARY KEY,
    id_evento INT NOT NULL,
    nombre_zona VARCHAR(100) NOT NULL,
    precio DECIMAL(10,2) NOT NULL DEFAULT 0.00 CHECK (precio >= 0),
    lugares_totales INT NOT NULL CHECK (lugares_totales > 0),
    lugares_disponibles INT NOT NULL CHECK (lugares_disponibles >= 0),
    FOREIGN KEY (id_evento) REFERENCES Evento(id_evento) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7. ASIENTOS
CREATE TABLE Asiento (
    id_asiento INT AUTO_INCREMENT PRIMARY KEY,
    id_zona INT NOT NULL,
    fila VARCHAR(10) NOT NULL,
    numero VARCHAR(10) NOT NULL,
    estado ENUM('disponible', 'reservado', 'ocupado', 'mantenimiento') DEFAULT 'disponible' NOT NULL,
    FOREIGN KEY (id_zona) REFERENCES Zona_Boleto(id_zona) ON DELETE CASCADE,
    UNIQUE KEY uq_asiento_zona (id_zona, fila, numero)
) ENGINE=InnoDB;

-- 8. TRANSACCIÓN
CREATE TABLE Transaccion (
    id_transaccion INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_evento INT NOT NULL,
    monto_total DECIMAL(10,2) NOT NULL CHECK (monto_total >= 0),
    metodo_pago ENUM('tarjeta', 'paypal', 'transferencia', 'efectivo', 'gratis') NOT NULL DEFAULT 'tarjeta',
    referencia_pago VARCHAR(100) DEFAULT NULL,
    digitos_tarjeta VARCHAR(4) DEFAULT NULL,
    fecha_transaccion DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado_pago ENUM('completado', 'pendiente', 'reembolsado') DEFAULT 'completado' NOT NULL,
    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_evento) REFERENCES Evento(id_evento) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 9. RESERVACIONES
CREATE TABLE Reservacion (
    id_reservacion INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL, 
    id_zona INT NOT NULL,
    id_transaccion INT DEFAULT NULL,
    espacios_reservados INT NOT NULL DEFAULT 1 CHECK (espacios_reservados > 0), 
    estado_reservacion ENUM('Confirmado', 'Cancelado') DEFAULT 'Confirmado' NOT NULL,
    fecha_reservacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_zona) REFERENCES Zona_Boleto(id_zona) ON DELETE CASCADE,
    FOREIGN KEY (id_transaccion) REFERENCES Transaccion(id_transaccion) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 10. BOLETOS
CREATE TABLE Boleto (
    id_boleto INT AUTO_INCREMENT PRIMARY KEY,
    id_reservacion INT NOT NULL,
    id_asiento INT DEFAULT NULL,
    codigo_unico VARCHAR(100) NOT NULL UNIQUE,
    estado_boleto ENUM('valido', 'usado', 'cancelado') DEFAULT 'valido' NOT NULL,
    fecha_generacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_reservacion) REFERENCES Reservacion(id_reservacion) ON DELETE CASCADE,
    FOREIGN KEY (id_asiento) REFERENCES Asiento(id_asiento) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 11. INCIDENCIAS
CREATE TABLE Incidencia (
    id_incidencia INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_evento INT DEFAULT NULL,
    asunto VARCHAR(150) NOT NULL,
    descripcion TEXT NOT NULL,
    estado ENUM('pendiente', 'en_revision', 'resuelto') DEFAULT 'pendiente' NOT NULL,
    fecha_reporte DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_evento) REFERENCES Evento(id_evento) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 12. BITÁCORA
CREATE TABLE Bitacora (
    id_bitacora INT AUTO_INCREMENT PRIMARY KEY,
    accion VARCHAR(100) NOT NULL,
    fecha_accion DATETIME DEFAULT CURRENT_TIMESTAMP,
    detalle TEXT NOT NULL,
    id_evento_afectado INT DEFAULT NULL
) ENGINE=InnoDB;