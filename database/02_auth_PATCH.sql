-- Taller Arley — PATCH: Auth (Semana 1)
-- Agrega rate-limiting (login_attempts) y password_version.
-- Idempotente: seguro correr múltiples veces.

-- password_version: invalida sesiones activas cuando se cambia la contraseña
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password_version'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE users ADD COLUMN password_version INT NOT NULL DEFAULT 1 AFTER password_hash',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Rate limiting de login/register (mismo patrón que FHJ)
CREATE TABLE IF NOT EXISTS login_attempts (
  id INT PRIMARY KEY AUTO_INCREMENT,
  ip_address VARCHAR(45) NOT NULL,
  email VARCHAR(255) DEFAULT '',
  attempt_context VARCHAR(20) NOT NULL DEFAULT 'login',
  attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (attempt_context, ip_address, attempted_at),
  INDEX (attempt_context, email, attempted_at)
);
