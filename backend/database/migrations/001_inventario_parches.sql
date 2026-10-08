-- Parches de esquema que antes se ejecutaban en cada visita a apps/inventario/index.php.
-- Ya están aplicados en producción; sirven de referencia para instalaciones nuevas.
-- Cada sentencia puede fallar si ya se aplicó (columna existente): es normal.

ALTER TABLE inv_objetos DROP FOREIGN KEY fk_loc_obj;
ALTER TABLE inv_objetos ADD COLUMN cantidad INT DEFAULT 1;
ALTER TABLE inv_objetos ADD COLUMN generos VARCHAR(255) DEFAULT '';
ALTER TABLE inv_objetos ADD COLUMN formato VARCHAR(50) DEFAULT 'Físico';
ALTER TABLE inv_objetos ADD COLUMN formato_de_archivo VARCHAR(255) DEFAULT '';
ALTER TABLE inv_objetos ADD COLUMN en_la_caja TINYINT(1) DEFAULT 0;
ALTER TABLE inv_objetos ADD COLUMN precio_de_venta DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE inv_localizaciones ADD COLUMN descripcion_del_contenido TEXT;
UPDATE inv_objetos SET tipo = 'Películas' WHERE tipo = 'Pelis';
UPDATE inv_objetos SET portada_http = SUBSTRING_INDEX(portada_http, '/', -1) WHERE portada_http LIKE '%/%' AND portada_http NOT LIKE 'http%';
UPDATE inv_localizaciones SET foto_http = SUBSTRING_INDEX(foto_http, '/', -1) WHERE foto_http LIKE '%/%' AND foto_http NOT LIKE 'http%';
