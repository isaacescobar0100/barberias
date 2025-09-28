-- =================================================================
-- ESQUEMA DE BASE DE DATOS MULTI-TENANT PARA BARBERÍAS
-- =================================================================

--
-- Tabla para gestionar las diferentes barberías (tenants)
-- El superadmin puede crear, habilitar o deshabilitar barberías desde aquí.
--
CREATE TABLE `barberias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) NOT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tabla para gestionar los usuarios y sus roles.
--
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('superadmin','admin') NOT NULL,
  `barberia_id` int(11) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `barberia_id` (`barberia_id`),
  CONSTRAINT `fk_usuario_barberia` FOREIGN KEY (`barberia_id`) REFERENCES `barberias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Insertar un superadministrador por defecto.
-- ¡IMPORTANTE! Cambia la contraseña en una aplicación real.
-- Contraseña hasheada para: 'superadmin_password'
--
INSERT INTO `usuarios` (`nombre`, `email`, `password`, `rol`, `barberia_id`) VALUES
('Super Admin', 'superadmin@example.com', '$2y$10$g.pD/5B8v9r8EwXzC4qZ5uY.V.ZzB1c3D9kE7fG6hJ0iL2mN4oP5q', 'superadmin', NULL);


-- =================================================================
-- MODIFICACIONES A LAS TABLAS EXISTENTES
-- =================================================================

--
-- Modificar la tabla `barberos` para asociar cada barbero a una barbería.
--
ALTER TABLE `barberos`
ADD COLUMN `barberia_id` INT(11) NULL AFTER `id`;

ALTER TABLE `barberos`
ADD CONSTRAINT `fk_barbero_barberia`
FOREIGN KEY (`barberia_id`) REFERENCES `barberias`(`id`)
ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Modificar la tabla `tipos_servicio` para que cada tipo de servicio pertenezca a una barbería.
--
ALTER TABLE `tipos_servicio`
ADD COLUMN `barberia_id` INT(11) NULL AFTER `id`;

ALTER TABLE `tipos_servicio`
ADD CONSTRAINT `fk_tipos_servicio_barberia`
FOREIGN KEY (`barberia_id`) REFERENCES `barberias`(`id`)
ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Modificar la tabla `servicios` para que cada registro de servicio esté ligado a una barbería.
-- No es estrictamente necesario si ya tienes `barbero_id` y el barbero pertenece a una barbería,
-- pero añadirlo mejora la consistencia y facilita las consultas.
--
ALTER TABLE `servicios`
ADD COLUMN `barberia_id` INT(11) NULL AFTER `id`;

ALTER TABLE `servicios`
ADD CONSTRAINT `fk_servicio_barberia`
FOREIGN KEY (`barberia_id`) REFERENCES `barberias`(`id`)
ON DELETE CASCADE ON UPDATE CASCADE;

-- =================================================================
-- NOTAS DE IMPLEMENTACIÓN
-- =================================================================
--
-- Después de ejecutar este script, los datos existentes no tendrán una
-- `barberia_id` asignada. Deberás crear tu primera barbería y luego
-- asignar los registros existentes a ella.
--
-- Ejemplo para asignar todos los datos existentes a una barbería nueva:
--
-- 1. Crear la primera barbería:
-- INSERT INTO `barberias` (`nombre`) VALUES ('Barbería Principal');
--
-- 2. Obtener el ID de esa barbería (asumamos que es 1).
--
-- 3. Actualizar los registros existentes:
-- UPDATE `barberos` SET `barberia_id` = 1 WHERE `barberia_id` IS NULL;
-- UPDATE `tipos_servicio` SET `barberia_id` = 1 WHERE `barberia_id` IS NULL;
-- UPDATE `servicios` SET `barberia_id` = 1 WHERE `barberia_id` IS NULL;
--
-- 4. Finalmente, es recomendable cambiar las columnas `barberia_id` a NOT NULL
--    para asegurar la integridad de los datos nuevos.
--
-- ALTER TABLE `barberos` MODIFY `barberia_id` INT(11) NOT NULL;
-- ALTER TABLE `tipos_servicio` MODIFY `barberia_id` INT(11) NOT NULL;
-- ALTER TABLE `servicios` MODIFY `barberia_id` INT(11) NOT NULL;
--
-- =================================================================