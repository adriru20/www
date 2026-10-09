-- DIAGNÓSTICO DE LA BASE DE DATOS (solo lectura: no modifica nada)
-- Ejecútalo en phpMyAdmin (pestaña SQL) y cópiame los resultados de cada consulta.
-- No muestra contraseñas.

-- 1) Qué tablas hay, motor y colación
SELECT TABLE_NAME, ENGINE, TABLE_COLLATION, TABLE_ROWS
FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME;

-- 2) Estructura real de cada tabla (copia la columna "Create Table" de cada una)
SHOW CREATE TABLE login_user;
SHOW CREATE TABLE inv_objetos;
SHOW CREATE TABLE inv_localizaciones;
SHOW CREATE TABLE gift_items;
SHOW CREATE TABLE parking_spots;

-- 3) Usuarios (sin contraseñas): si tienen la contraseña cifrada y si hay nombres repetidos
SELECT id, user, permission,
       (pass LIKE '$2y$%' OR pass LIKE '$argon2%') AS contrasena_cifrada,
       CHAR_LENGTH(pass) AS longitud_contrasena
FROM login_user;
SELECT user, COUNT(*) AS veces FROM login_user GROUP BY user HAVING COUNT(*) > 1;

-- 4) Cuántas filas hay en cada tabla
SELECT 'inv_objetos' AS tabla, COUNT(*) AS filas FROM inv_objetos
UNION ALL SELECT 'inv_localizaciones', COUNT(*) FROM inv_localizaciones
UNION ALL SELECT 'gift_items', COUNT(*) FROM gift_items
UNION ALL SELECT 'parking_spots', COUNT(*) FROM parking_spots;

-- 5) Valores "raros" que afectan a limpiar los tipos de datos del inventario
SELECT DISTINCT precio_de_venta FROM inv_objetos WHERE precio_de_venta IS NOT NULL AND precio_de_venta NOT REGEXP '^[0-9]+(\\.[0-9]+)?$' LIMIT 30;
SELECT en_la_caja, COUNT(*) AS veces FROM inv_objetos GROUP BY en_la_caja;
SELECT DISTINCT anio_de_estreno FROM inv_objetos WHERE anio_de_estreno IS NOT NULL AND anio_de_estreno NOT REGEXP '^[0-9]{4}$' LIMIT 30;
SELECT DISTINCT duracion FROM inv_objetos WHERE duracion IS NOT NULL AND duracion != '' LIMIT 30;
SELECT DISTINCT formato FROM inv_objetos LIMIT 30;
SELECT DISTINCT tipo FROM inv_objetos LIMIT 30;

-- 6) Objetos cuya localización no existe en la tabla de localizaciones (texto suelto)
SELECT DISTINCT o.localizacion FROM inv_objetos o
LEFT JOIN inv_localizaciones l ON l.nombre = o.localizacion
WHERE o.localizacion IS NOT NULL AND o.localizacion != '' AND o.localizacion NOT LIKE '%,%' AND l.id IS NULL LIMIT 30;
SELECT COUNT(*) AS objetos_con_varias_localizaciones FROM inv_objetos WHERE localizacion LIKE '%,%';
