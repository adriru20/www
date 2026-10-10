-- =====================================================================================
-- MIGRACIÓN 005 · Grupos Amigos / Familia
-- =====================================================================================
-- Cada usuario puede ser «amigo», «familiar» o ambas cosas. En Gift list cada persona solo ve las listas
-- de quienes comparten grupo con ella (un amigo ve a los amigos, un familiar a los familiares, y quien es
-- las dos cosas ve a ambos grupos). Los administradores ven a todos. Se marca en Usuarios → Rol y secciones.
-- Ejecútalo UNA SOLA VEZ en phpMyAdmin (pestaña SQL). Hasta entonces la web funciona como antes (todos ven a todos).
-- Los usuarios que ya existen quedan en AMBOS grupos para que no cambie nada de golpe; ajústalos después en el panel.
-- Los usuarios nuevos empiezan sin grupo (no ven a nadie hasta que se les asigne).
-- =====================================================================================

USE dbs13691268;

ALTER TABLE login_user
  ADD COLUMN es_amigo    TINYINT(1) NOT NULL DEFAULT 0 AFTER active,
  ADD COLUMN es_familiar TINYINT(1) NOT NULL DEFAULT 0 AFTER es_amigo;

UPDATE login_user SET es_amigo = 1, es_familiar = 1;

-- Comprobación (debe mostrar las columnas nuevas con 1 en todos los usuarios):
SELECT user, permission, es_amigo, es_familiar FROM login_user ORDER BY user;
