--
-- Estructura de tabla para la tabla `gift_items`
--

DROP TABLE IF EXISTS `gift_items`;

CREATE TABLE `gift_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` VARCHAR(50) COLLATE utf8mb4_general_ci NOT NULL,
  `item_name` VARCHAR(255) COLLATE utf8mb4_general_ci NOT NULL,
  `item_description` TEXT COLLATE utf8mb4_general_ci,
  `item_url` VARCHAR(2048) COLLATE utf8mb4_general_ci,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_user_gift` (`user_id`),
  CONSTRAINT `fk_user_gift` FOREIGN KEY (`user_id`) REFERENCES `login_user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;