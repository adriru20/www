DROP TABLE IF EXISTS `inv_objetos`;

CREATE TABLE `inv_objetos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `objeto` VARCHAR(255) COLLATE utf8mb4_general_ci, -- Aquí irá el título (sea objeto, juego o peli)
  `localizacion` VARCHAR(255) COLLATE utf8mb4_general_ci,
  `descripcion` TEXT COLLATE utf8mb4_general_ci,
  `tipo` VARCHAR(255) COLLATE utf8mb4_general_ci,
  `tipo_de_objeto` VARCHAR(255) COLLATE utf8mb4_general_ci,
  `cantidad` INT(11) DEFAULT 1,
  `generos` VARCHAR(255) COLLATE utf8mb4_general_ci,
  `plataformas` VARCHAR(255) COLLATE utf8mb4_general_ci,
  `anio_de_estreno` VARCHAR(50) COLLATE utf8mb4_general_ci,
  `formato` VARCHAR(255) COLLATE utf8mb4_general_ci,
  `precio_de_venta` VARCHAR(50) COLLATE utf8mb4_general_ci,
  `duracion` VARCHAR(50) COLLATE utf8mb4_general_ci,
  `formato_de_archivo` VARCHAR(255) COLLATE utf8mb4_general_ci,
  `en_la_caja` VARCHAR(50) COLLATE utf8mb4_general_ci,
  `portada_http` TEXT COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id`),
  KEY `fk_loc_obj` (`localizacion`),
  CONSTRAINT `fk_loc_obj` FOREIGN KEY (`localizacion`) REFERENCES `inv_localizaciones` (`nombre`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;