-- =====================================================================================
-- MIGRACIÓN 004 · Usuarios ocultos
-- =====================================================================================
-- Añade a cada usuario la marca «oculto»: un usuario oculto sigue pudiendo entrar, pero no sale
-- en las listas de la web (por ejemplo, las personas de Gift list). Se gestiona desde
-- Usuarios → botón Ocultar / Mostrar y pestaña Ocultos.
-- Ejecútalo UNA SOLA VEZ en phpMyAdmin (pestaña SQL). Hasta entonces la web funciona igual y el
-- panel de usuarios avisa de que falta ejecutarlo.
-- =====================================================================================

USE dbs13691268;

ALTER TABLE login_user
  ADD COLUMN oculto TINYINT(1) NOT NULL DEFAULT 0 AFTER active;

-- Comprobación (debe mostrar la columna nueva, con valor 0 en todos los usuarios):
SELECT user, permission, active, oculto FROM login_user ORDER BY user;
