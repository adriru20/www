CREATE TABLE login_user (
  id char NOT NULL PRIMARY KEY,
  user varchar(50) NOT NULL,
  pass varchar(255) NOT NULL,
  permission enum('admin', 'user') NOT NULL
);

CREATE INDEX pk_login_user ON login_user (id);

-- 1. Ampliar la longitud del ID en la tabla de usuarios
ALTER TABLE login_user MODIFY id VARCHAR(50) NOT NULL;

-- 2. Crear la tabla de listas de regalos
CREATE TABLE gift_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id VARCHAR(50) NOT NULL,
  item_name VARCHAR(255) NOT NULL,
  is_purchased TINYINT(1) DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES login_user(id) ON DELETE CASCADE
);