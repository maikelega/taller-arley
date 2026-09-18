-- Centro Automotriz Arley — PATCH: importación legacy de Mónica
-- Historial de facturas, segundo teléfono, catálogos de servicios.
-- Idempotente.

-- 1) Segundo teléfono (284 clientes de Mónica tienen 2 números en un
-- solo campo, separados por "/")
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'phone2'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE customers ADD COLUMN phone2 VARCHAR(20) NULL AFTER phone',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Marca el origen del cliente (migrado de Mónica vs. creado en el sistema nuevo)
-- y guarda el código legacy para poder re-cruzar si hace falta.
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'monica_cod_empre'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE customers ADD COLUMN monica_cod_empre VARCHAR(20) NULL UNIQUE AFTER cedula',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Historial de facturas de Mónica (solo lectura — referencia histórica,
-- no participa del flujo activo de Kanban/presupuesto)
CREATE TABLE IF NOT EXISTS legacy_invoices (
  id INT PRIMARY KEY AUTO_INCREMENT,
  customer_id INT NULL,
  monica_cod_empre VARCHAR(20) NULL,
  nro_fact INT NOT NULL,
  fecha DATE NULL,
  total DECIMAL(12,2) DEFAULT 0,
  monto_pagado DECIMAL(12,2) DEFAULT 0,
  saldo DECIMAL(12,2) DEFAULT 0,
  FOREIGN KEY (customer_id) REFERENCES customers(id),
  INDEX (customer_id),
  INDEX (nro_fact),
  INDEX (fecha)
);

CREATE TABLE IF NOT EXISTS legacy_invoice_lines (
  id INT PRIMARY KEY AUTO_INCREMENT,
  invoice_id INT NOT NULL,
  descripcion VARCHAR(255),
  cantidad DECIMAL(10,2) DEFAULT 1,
  precio_unitario DECIMAL(12,2) DEFAULT 0,
  FOREIGN KEY (invoice_id) REFERENCES legacy_invoices(id) ON DELETE CASCADE,
  INDEX (invoice_id)
);

-- 3) Catálogos de servicios (para autocompletar en el flujo de recepción
-- y reparación) — extraídos y clasificados desde el historial de Mónica
CREATE TABLE IF NOT EXISTS requested_repairs_catalog (
  id INT PRIMARY KEY AUTO_INCREMENT,
  descripcion VARCHAR(255) NOT NULL UNIQUE,
  veces_usado INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS work_done_catalog (
  id INT PRIMARY KEY AUTO_INCREMENT,
  descripcion VARCHAR(255) NOT NULL UNIQUE,
  veces_usado INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
