-- Centro Automotriz Arley — PATCH: mejoras a orden de trabajo
-- Cédula cliente, datos de vehículo (color/motor/km/combustible),
-- placa opcional (VIN como alternativa), reparaciones solicitadas,
-- ángulo de fotos de inspección.
-- Idempotente.

-- 1) Cédula de identidad del cliente
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'cedula'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE customers ADD COLUMN cedula VARCHAR(20) NULL AFTER name',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Datos adicionales de vehículo: color, kilometraje
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles' AND COLUMN_NAME = 'color'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE vehicles ADD COLUMN color VARCHAR(50) NULL AFTER year,
     ADD COLUMN mileage INT NULL AFTER color',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3) Placa deja de ser obligatoria — el vehículo se identifica por placa
-- O vin (al menos uno). MySQL permite múltiples NULL en columna UNIQUE,
-- así que no rompe la restricción de unicidad de placas reales.
SET @is_not_null = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles'
    AND COLUMN_NAME = 'plate' AND IS_NULLABLE = 'NO'
);
SET @sql = IF(@is_not_null > 0,
  'ALTER TABLE vehicles MODIFY COLUMN plate VARCHAR(20) NULL',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4) Reparaciones solicitadas por el cliente al momento de recepción
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'requested_repairs'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE orders ADD COLUMN requested_repairs TEXT NULL AFTER dropoff_phone',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5) Ángulo de la foto de inspección (frente, atrás, laterales, techo,
-- interior, las 4 llantas, o "extra" para fotos libres sin ángulo fijo)
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_photos' AND COLUMN_NAME = 'angle'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE order_photos ADD COLUMN angle VARCHAR(30) NULL AFTER photo_type',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
