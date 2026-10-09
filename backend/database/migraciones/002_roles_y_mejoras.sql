-- =====================================================================================
-- MIGRACIÓN 002 · Roles y permisos + mejoras de la base de datos
-- =====================================================================================
-- Qué hace:
--   1. Usuarios: nombre único, activo/desactivado, fecha de alta y último acceso, rol "visitante".
--   2. Permisos: tabla user_permissions (qué secciones y acciones puede usar cada usuario).
--   3. Inventario: precio, "en la caja", año y duración pasan a ser números, fechas de
--      creación/modificación, índices, y las localizaciones se separan en su propia relación
--      (tabla inv_objeto_localizacion) en vez de un texto "Caja 1, Estantería".
--
-- ANTES DE EJECUTAR (importante):
--   · Haz una copia: phpMyAdmin → Exportar, o Inventario → Backups → Guardar backup.
--   · Ejecútalo UNA SOLA VEZ y de arriba abajo (en phpMyAdmin, pestaña SQL, pegar todo y "Continuar").
--   · Si algún paso da error, PARA y copia el mensaje: no sigas ejecutando el resto.
--   · Esta migración incluye una copia de seguridad de la tabla de objetos (inv_objetos_copia_002)
--     por si algo de los datos convertidos no es como esperabas. Bórrala cuando lo hayas comprobado.
--
-- DESPUÉS: fusiona el PR en GitHub (el código nuevo ya sabe usar estas tablas) y entra en
--   "Usuarios" para marcar qué ve cada persona.
-- =====================================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------- 0. Copia de seguridad
CREATE TABLE inv_objetos_copia_002 AS SELECT * FROM inv_objetos;
CREATE TABLE login_user_copia_002  AS SELECT id, user, permission FROM login_user;   -- sin contraseñas

-- ---------------------------------------------------------------- 1. Usuarios
-- (Si el paso 1 da "Duplicate entry" es que hay dos usuarios con el mismo nombre: renombra uno y repite.)
ALTER TABLE login_user
  MODIFY permission ENUM('admin','user','visitante') NOT NULL DEFAULT 'user',
  ADD COLUMN active     TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN created_at DATETIME   NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN last_login DATETIME   NULL,
  ADD UNIQUE KEY uq_login_user_user (user);

-- Tu usuario figuraba con rol "user" en la base de datos: se le da el rol de administrador.
-- (Sin esto nadie podría entrar en el panel de Usuarios.)
UPDATE login_user SET permission = 'admin' WHERE user = 'adriru';

-- El índice pk_login_user repite la clave primaria (confirmado en tu diagnóstico): se elimina.
ALTER TABLE login_user DROP INDEX pk_login_user;

-- ---------------------------------------------------------------- 2. Permisos
-- perm = 'wiki' | 'parking' | 'giftlist' | 'inventario' | 'inventario.add' | '.edit' | '.delete' | '.backup'
-- Los administradores lo tienen todo sin filas. Sin clave foránea a propósito: la aplicación
-- borra estos permisos al borrar un usuario, y así no depende de la colación de login_user.
CREATE TABLE user_permissions (
  user_id VARCHAR(50) NOT NULL,
  perm    VARCHAR(50) NOT NULL,
  PRIMARY KEY (user_id, perm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Para que nadie se quede sin acceso de golpe, los usuarios con rol "user" (ahora: roro) conservan todo lo que ven hoy.
-- Después, entra en Usuarios y ajusta (por ejemplo, pon a roro como "Visitante" y marca solo lo que quieras).
INSERT INTO user_permissions (user_id, perm)
SELECT u.id, p.perm
FROM login_user u
CROSS JOIN (SELECT 'wiki' AS perm UNION ALL SELECT 'parking' UNION ALL SELECT 'giftlist' UNION ALL SELECT 'inventario'
            UNION ALL SELECT 'inventario.add' UNION ALL SELECT 'inventario.edit'
            UNION ALL SELECT 'inventario.delete' UNION ALL SELECT 'inventario.backup') p
WHERE u.permission = 'user';

-- ---------------------------------------------------------------- 3. Inventario: limpiar valores
UPDATE inv_objetos SET cantidad = 1 WHERE cantidad IS NULL OR cantidad < 1;

-- Precio: "30,50" -> 30.50, lo que no sea un número -> 0
UPDATE inv_objetos SET precio_de_venta = REPLACE(TRIM(precio_de_venta), ',', '.');
UPDATE inv_objetos SET precio_de_venta = '0'
 WHERE precio_de_venta IS NULL OR precio_de_venta NOT REGEXP '^[0-9]+(\\.[0-9]+)?$';

-- "En la caja": sí/1/true -> 1, lo demás -> 0
UPDATE inv_objetos SET en_la_caja = CASE WHEN LOWER(TRIM(en_la_caja)) IN ('1','si','sí','true','yes','on','x') THEN '1' ELSE '0' END;

-- Año: solo 4 cifras, si no -> vacío
UPDATE inv_objetos SET anio_de_estreno = NULL WHERE anio_de_estreno IS NULL OR anio_de_estreno NOT REGEXP '^[0-9]{4}$';

-- Duración en minutos: "120" o "117 min" -> 120 / 117, cualquier otro formato -> vacío (el original está en la copia 002)
UPDATE inv_objetos SET duracion = CASE
    WHEN duracion REGEXP '^[0-9]+$'        THEN duracion
    WHEN duracion REGEXP '^[0-9]+ ?min'    THEN SUBSTRING_INDEX(TRIM(REPLACE(LOWER(duracion), 'min', '')), ' ', 1)
    ELSE NULL END;

-- ---------------------------------------------------------------- 4. Inventario: tipos, fechas e índices
-- fk_loc_obj es un índice sobrante de una clave antigua sobre el texto de localización (ya no hay clave foránea).
ALTER TABLE inv_objetos DROP INDEX fk_loc_obj;

ALTER TABLE inv_objetos
  MODIFY cantidad        INT UNSIGNED NOT NULL DEFAULT 1,
  MODIFY precio_de_venta DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  MODIFY en_la_caja      TINYINT(1)   NOT NULL DEFAULT 0,
  MODIFY anio_de_estreno SMALLINT UNSIGNED NULL,
  MODIFY duracion        SMALLINT UNSIGNED NULL,
  ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  ADD KEY idx_obj_objeto (objeto),
  ADD KEY idx_obj_tipo (tipo),
  ADD KEY idx_obj_categoria (tipo_de_objeto);

-- ---------------------------------------------------------------- 5. Inventario: localizaciones separadas
CREATE TABLE inv_objeto_localizacion (
  objeto_id       INT NOT NULL,
  localizacion_id INT NOT NULL,
  PRIMARY KEY (objeto_id, localizacion_id),
  KEY idx_ol_localizacion (localizacion_id),
  CONSTRAINT fk_ol_objeto       FOREIGN KEY (objeto_id)       REFERENCES inv_objetos(id)       ON DELETE CASCADE,
  CONSTRAINT fk_ol_localizacion FOREIGN KEY (localizacion_id) REFERENCES inv_localizaciones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Crea las localizaciones que solo existían escritas a mano en algún objeto (hasta 20 por objeto)...
INSERT IGNORE INTO inv_localizaciones (nombre)
SELECT DISTINCT t.nombre FROM (
  SELECT TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(o.localizacion, ',', n.n), ',', -1)) AS nombre
  FROM inv_objetos o
  JOIN (SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7
        UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13
        UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19
        UNION ALL SELECT 20) n
    ON n.n <= 1 + LENGTH(o.localizacion) - LENGTH(REPLACE(o.localizacion, ',', ''))
  WHERE o.localizacion IS NOT NULL AND o.localizacion <> ''
) t WHERE t.nombre <> '';

-- ...y enlaza cada objeto con sus localizaciones
INSERT IGNORE INTO inv_objeto_localizacion (objeto_id, localizacion_id)
SELECT DISTINCT t.id, l.id FROM (
  SELECT o.id, TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(o.localizacion, ',', n.n), ',', -1)) AS nombre
  FROM inv_objetos o
  JOIN (SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7
        UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13
        UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19
        UNION ALL SELECT 20) n
    ON n.n <= 1 + LENGTH(o.localizacion) - LENGTH(REPLACE(o.localizacion, ',', ''))
  WHERE o.localizacion IS NOT NULL AND o.localizacion <> ''
) t JOIN inv_localizaciones l ON l.nombre = t.nombre
WHERE t.nombre <> '';

-- (La columna de texto inv_objetos.localizacion se mantiene como copia, la web ya solo lee la relación.
--  Cuando todo vaya bien durante unas semanas, se podrá eliminar con:  ALTER TABLE inv_objetos DROP COLUMN localizacion, )

-- ---------------------------------------------------------------- 6. Comprobaciones (solo muestran datos)
SELECT 'objetos' AS tabla, COUNT(*) AS filas FROM inv_objetos
UNION ALL SELECT 'objetos en la copia', COUNT(*) FROM inv_objetos_copia_002
UNION ALL SELECT 'enlaces objeto-localización', COUNT(*) FROM inv_objeto_localizacion
UNION ALL SELECT 'localizaciones', COUNT(*) FROM inv_localizaciones
UNION ALL SELECT 'usuarios', COUNT(*) FROM login_user
UNION ALL SELECT 'permisos', COUNT(*) FROM user_permissions;

-- Objetos con localización escrita que NO se han podido enlazar (debería salir vacío):
SELECT o.id, o.objeto, o.localizacion FROM inv_objetos o
WHERE o.localizacion IS NOT NULL AND o.localizacion <> ''
  AND NOT EXISTS (SELECT 1 FROM inv_objeto_localizacion j WHERE j.objeto_id = o.id);

-- Debe salir al menos un administrador (adriru):
SELECT user, permission, active FROM login_user ORDER BY permission, user;
