-- =====================================================================================
-- MIGRACIÓN 003 · Gift list: marcar regalos como comprados
-- =====================================================================================
-- Añade a cada regalo quién lo ha comprado y cuándo. El dueño de la lista NO ve esta información
-- (la web no la envía a su pantalla), para que siga siendo sorpresa.
-- Ejecútalo UNA SOLA VEZ en phpMyAdmin (pestaña SQL de tu base de datos). Antes de ejecutarlo, la web
-- funciona igual y simplemente no muestra los botones de "comprado".
-- =====================================================================================

ALTER TABLE gift_items
  ADD COLUMN purchased_by VARCHAR(50) NULL DEFAULT NULL,
  ADD COLUMN purchased_at DATETIME    NULL DEFAULT NULL,
  ADD KEY idx_gift_purchased_by (purchased_by);

-- Comprobación (debe mostrar las dos columnas nuevas):
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gift_items' AND COLUMN_NAME IN ('purchased_by', 'purchased_at');
