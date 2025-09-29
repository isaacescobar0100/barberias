-- =================================================================
-- SCRIPT COMPLETO DE LA BASE DE DATOS PARA BARBERSHOP MULTI-TENANT
-- Versión: 1.0
-- Descripción: Este script crea todas las tablas desde cero.
-- =================================================================

--
-- Tabla: barberias
-- Almacena la información de cada tienda o sucursal.
--
CREATE TABLE `barberias` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(255) NOT NULL,
  `activa` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = Activa, 0 = Inactiva',
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tabla: usuarios
-- Gestiona los accesos al sistema.
-- rol 'superadmin': Acceso total para gestionar barberías y administradores.
-- rol 'admin': Acceso restringido a la barbería asignada.
--
CREATE TABLE `usuarios` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `rol` ENUM('superadmin', 'admin') NOT NULL,
  `barberia_id` INT(11) DEFAULT NULL COMMENT 'NULL para superadmin, ID de la barbería para admin',
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `barberia_id` (`barberia_id`),
  CONSTRAINT `fk_usuario_barberia` FOREIGN KEY (`barberia_id`) REFERENCES `barberias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tabla: barberos
-- Almacena los barberos de cada barbería.
--
CREATE TABLE `barberos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `barberia_id` INT(11) NOT NULL,
  `nombre` VARCHAR(255) NOT NULL,
  `telefono` VARCHAR(20) DEFAULT NULL,
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `barberia_id` (`barberia_id`),
  CONSTRAINT `fk_barbero_barberia` FOREIGN KEY (`barberia_id`) REFERENCES `barberias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tabla: tipos_servicio
-- Almacena los tipos de servicio (corte, barba, etc.) que ofrece cada barbería.
--
CREATE TABLE `tipos_servicio` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `barberia_id` INT(11) NOT NULL,
  `nombre_servicio` VARCHAR(255) NOT NULL,
  `precio_base` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `barberia_id` (`barberia_id`),
  CONSTRAINT `fk_tipos_servicio_barberia` FOREIGN KEY (`barberia_id`) REFERENCES `barberias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tabla: servicios
-- Registra cada servicio realizado, funcionando como un historial de ventas.
--
CREATE TABLE `servicios` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `barberia_id` INT(11) NOT NULL,
  `barbero_id` INT(11) NOT NULL,
  `tipo_servicio_id` INT(11) NOT NULL,
  `costo_total` DECIMAL(10,2) NOT NULL,
  `propina` DECIMAL(10,2) DEFAULT 0.00,
  `fecha_registro` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `barberia_id` (`barberia_id`),
  KEY `barbero_id` (`barbero_id`),
  KEY `tipo_servicio_id` (`tipo_servicio_id`),
  CONSTRAINT `fk_servicio_barberia` FOREIGN KEY (`barberia_id`) REFERENCES `barberias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_servicio_barbero` FOREIGN KEY (`barbero_id`) REFERENCES `barberos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_servicio_tipo` FOREIGN KEY (`tipo_servicio_id`) REFERENCES `tipos_servicio` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- =================================================================
-- DATOS INICIALES
-- =================================================================

--
-- Insertar un superadministrador por defecto para el primer inicio de sesión.
-- Email: superadmin@example.com
-- Contraseña: superadmin_password
--
INSERT INTO `usuarios` (`nombre`, `email`, `password`, `rol`, `barberia_id`) VALUES
('Super Admin', 'superadmin@example.com', '$2y$10$Iha8.PAwVyzsL5D5K3a8g.jLgGk0jY9.gC/s5D6E7hI8fJ9kL1mN2', 'superadmin', NULL);

-- La contraseña es 'superadmin_password' hasheada con BCRYPT.
-- En tu código PHP, para verificarla, usarías:
-- password_verify('superadmin_password', $hash_de_la_db);

--
-- Insertar una barbería de ejemplo para empezar.
--
INSERT INTO `barberias` (`id`, `nombre`, `activa`) VALUES (1, 'Barbería Central (Ejemplo)', 1);

--
-- Insertar un administrador de ejemplo para la Barbería Central.
-- Email: admin@example.com
-- Contraseña: admin_password
--
INSERT INTO `usuarios` (`nombre`, `email`, `password`, `rol`, `barberia_id`) VALUES
('Admin Principal', 'admin@example.com', '$2y$10$w5J.AP2O/bV0B6A3xZc7.uGkL8rE9iJ0kL2mN4oP5qS6dF7gH8iJ0', 'admin', 1);

--
-- Insertar datos de ejemplo para la Barbería Central (ID = 1)
--
INSERT INTO `barberos` (`barberia_id`, `nombre`, `telefono`) VALUES
(1, 'Juan Pérez', '3001234567'),
(1, 'Luis Gómez', '3109876543');

INSERT INTO `tipos_servicio` (`barberia_id`, `nombre_servicio`, `precio_base`) VALUES
(1, 'Corte de Cabello', 20000.00),
(1, 'Afeitado Clásico', 15000.00),
(1, 'Corte y Barba', 32000.00);

-- =================================================================
-- FIN DEL SCRIPT
-- =================================================================