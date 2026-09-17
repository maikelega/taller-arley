-- Taller Arley — Schema Base (Fase 1)
-- Run this first to set up the database

SET FOREIGN_KEY_CHECKS=0;

-- Roles
CREATE TABLE IF NOT EXISTS roles (
  id INT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE
);

INSERT INTO roles (id, name) VALUES
(1, 'admin'),
(2, 'mechanic'),
(3, 'client');

-- Usuarios
CREATE TABLE IF NOT EXISTS users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(255) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(255) NOT NULL,
  phone VARCHAR(20),
  role_id INT NOT NULL,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- Clientes
CREATE TABLE IF NOT EXISTS customers (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  phone VARCHAR(20),
  email VARCHAR(255),
  address TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Estados de OT
CREATE TABLE IF NOT EXISTS order_statuses (
  id INT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE
);

INSERT INTO order_statuses (id, name) VALUES
(1, 'Recibido'),
(2, 'En Diagnóstico'),
(3, 'Presupuesto Pendiente'),
(4, 'Presupuesto Aprobado'),
(5, 'En Reparación'),
(6, 'Calidad'),
(7, 'Listo para Entrega'),
(8, 'Cerrado');

-- Órdenes de Trabajo
CREATE TABLE IF NOT EXISTS orders (
  id INT PRIMARY KEY AUTO_INCREMENT,
  customer_id INT NOT NULL,
  plate VARCHAR(20) UNIQUE NOT NULL,
  reception_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  status_id INT DEFAULT 1,
  estimated_completion DATE,
  actual_completion DATE,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id),
  FOREIGN KEY (status_id) REFERENCES order_statuses(id),
  INDEX (plate),
  INDEX (status_id),
  INDEX (customer_id)
);

-- Fotos de OT
CREATE TABLE IF NOT EXISTS order_photos (
  id INT PRIMARY KEY AUTO_INCREMENT,
  order_id INT NOT NULL,
  photo_path VARCHAR(500) NOT NULL,
  photo_type ENUM('entry', 'progress', 'exit') DEFAULT 'entry',
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  INDEX (order_id)
);

-- Diagnóstico Visual Inicial (DVI)
CREATE TABLE IF NOT EXISTS order_dvi (
  id INT PRIMARY KEY AUTO_INCREMENT,
  order_id INT NOT NULL,
  category ENUM('mechanic', 'electric'),
  severity ENUM('green', 'yellow', 'red'),
  notes TEXT,
  estimated_hours DECIMAL(5, 1),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  INDEX (order_id)
);

-- Presupuestos
CREATE TABLE IF NOT EXISTS order_budgets (
  id INT PRIMARY KEY AUTO_INCREMENT,
  order_id INT NOT NULL UNIQUE,
  total DECIMAL(10, 2),
  approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  approved_by INT,
  approved_date DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (approved_by) REFERENCES users(id),
  INDEX (approval_status)
);

-- Líneas de Presupuesto
CREATE TABLE IF NOT EXISTS budget_lines (
  id INT PRIMARY KEY AUTO_INCREMENT,
  budget_id INT NOT NULL,
  description VARCHAR(255),
  qty INT DEFAULT 1,
  unit_cost DECIMAL(10, 2),
  total DECIMAL(10, 2),
  approved BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (budget_id) REFERENCES order_budgets(id) ON DELETE CASCADE,
  INDEX (budget_id)
);

-- Citas
CREATE TABLE IF NOT EXISTS order_appointments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  order_id INT NOT NULL UNIQUE,
  requested_date DATE,
  confirmed_date DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- Registro de Trabajo
CREATE TABLE IF NOT EXISTS work_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  order_id INT NOT NULL,
  mechanic_id INT NOT NULL,
  start_time DATETIME,
  end_time DATETIME,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (mechanic_id) REFERENCES users(id),
  INDEX (order_id),
  INDEX (mechanic_id)
);

-- QC (Calidad)
CREATE TABLE IF NOT EXISTS order_qc (
  id INT PRIMARY KEY AUTO_INCREMENT,
  order_id INT NOT NULL UNIQUE,
  reviewed_by INT,
  status ENUM('approved', 'rejected') DEFAULT 'approved',
  notes TEXT,
  reviewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by) REFERENCES users(id),
  INDEX (order_id)
);

-- Tokens de Portal Cliente
CREATE TABLE IF NOT EXISTS client_tokens (
  id INT PRIMARY KEY AUTO_INCREMENT,
  order_id INT NOT NULL,
  token VARCHAR(255) UNIQUE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME,
  is_active BOOLEAN DEFAULT TRUE,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  INDEX (token),
  INDEX (order_id)
);

-- Notificaciones
CREATE TABLE IF NOT EXISTS order_notifications (
  id INT PRIMARY KEY AUTO_INCREMENT,
  order_id INT NOT NULL,
  notification_type VARCHAR(50),
  sent_date DATETIME,
  status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
  error_message TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  INDEX (order_id),
  INDEX (status)
);

-- Sesiones
CREATE TABLE IF NOT EXISTS sessions (
  id VARCHAR(255) PRIMARY KEY,
  user_id INT,
  ip_address VARCHAR(45),
  user_agent TEXT,
  expires_at DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX (user_id),
  INDEX (expires_at)
);

SET FOREIGN_KEY_CHECKS=1;
