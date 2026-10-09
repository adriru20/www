-- Tabla del Parking. La crea sola apps/parking/api.php la primera vez que falta;
-- este fichero es solo de referencia (o para crearla a mano si la BD no permite CREATE).
CREATE TABLE IF NOT EXISTS parking_spots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id VARCHAR(50) NOT NULL,
  label VARCHAR(40) NOT NULL DEFAULT 'Coche',
  lat DOUBLE NOT NULL,
  lon DOUBLE NOT NULL,
  accuracy DOUBLE NULL,
  note VARCHAR(255) NOT NULL DEFAULT '',
  photo VARCHAR(80) NULL,
  parked_at INT NOT NULL,        -- segundos Unix
  meter_until INT NULL,          -- fin del parquímetro (segundos Unix)
  active TINYINT(1) NOT NULL DEFAULT 1,
  archived_at INT NULL,
  KEY idx_user_active (user_id, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
