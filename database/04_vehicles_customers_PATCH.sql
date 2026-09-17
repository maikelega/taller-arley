-- Taller Arley — PATCH: Vehículos como entidad + contacto de entrega
-- Separa vehículos de orders (reutilizable entre visitas, buscable por VIN/placa).
-- Idempotente.

CREATE TABLE IF NOT EXISTS vehicles (
  id INT PRIMARY KEY AUTO_INCREMENT,
  customer_id INT NOT NULL,
  plate VARCHAR(20) UNIQUE NOT NULL,
  vin VARCHAR(17) UNIQUE NULL,
  brand VARCHAR(100),
  model VARCHAR(100),
  year INT,
  engine VARCHAR(100),
  fuel_type VARCHAR(50),
  transmission VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id),
  INDEX (plate),
  INDEX (vin)
);

-- Migrar vehículos existentes desde orders (si los hay) antes de quitar columnas
SET @has_plate = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'plate'
);

SET @sql = IF(@has_plate > 0,
  'INSERT IGNORE INTO vehicles (customer_id, plate, brand, model, year)
     SELECT customer_id, plate, vehicle_brand, vehicle_model, vehicle_year
     FROM orders WHERE plate IS NOT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Agregar vehicle_id + contacto de entrega a orders
SET @has_vehicle_id = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'vehicle_id'
);
SET @sql = IF(@has_vehicle_id = 0,
  'ALTER TABLE orders ADD COLUMN vehicle_id INT NULL AFTER customer_id,
     ADD COLUMN dropoff_name VARCHAR(255) NULL AFTER notes,
     ADD COLUMN dropoff_phone VARCHAR(20) NULL AFTER dropoff_name,
     ADD CONSTRAINT fk_orders_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Poblar vehicle_id en orders existentes desde la placa migrada
SET @sql = IF(@has_plate > 0,
  'UPDATE orders o INNER JOIN vehicles v ON v.plate = o.plate SET o.vehicle_id = v.id WHERE o.vehicle_id IS NULL',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Quitar columnas viejas de orders (ahora viven en vehicles)
SET @sql = IF(@has_plate > 0,
  'ALTER TABLE orders
     DROP COLUMN plate,
     DROP COLUMN vehicle_brand,
     DROP COLUMN vehicle_model,
     DROP COLUMN vehicle_year',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
