-- Taller Arley — PATCH: Kanban (Semana 3-4)
-- Agrega datos de vehículo y mecánico asignado a orders.
-- Idempotente.

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'vehicle_brand'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE orders
     ADD COLUMN vehicle_brand VARCHAR(100) AFTER plate,
     ADD COLUMN vehicle_model VARCHAR(100) AFTER vehicle_brand,
     ADD COLUMN vehicle_year INT AFTER vehicle_model,
     ADD COLUMN assigned_mechanic_id INT NULL AFTER status_id,
     ADD CONSTRAINT fk_orders_mechanic FOREIGN KEY (assigned_mechanic_id) REFERENCES users(id)',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND INDEX_NAME = 'idx_assigned_mechanic'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE orders ADD INDEX idx_assigned_mechanic (assigned_mechanic_id)',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
