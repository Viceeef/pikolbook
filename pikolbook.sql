-- Pikolbook starter database for XAMPP.
-- Import this file in phpMyAdmin. It creates empty tables, not working logins.
-- No existing tables or records are deleted.

CREATE DATABASE IF NOT EXISTS pikolbook_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pikolbook_db;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
  phone VARCHAR(30),
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS clients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(150),
  phone VARCHAR(30) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS courts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  description TEXT,
  hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  opening_time TIME NOT NULL,
  closing_time TIME NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS court_blocks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  court_id INT NOT NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  reason VARCHAR(255) NOT NULL,
  FOREIGN KEY (court_id) REFERENCES courts(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  court_id INT NOT NULL,
  client_id INT NOT NULL,
  created_by INT NOT NULL,
  booking_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  status ENUM('confirmed', 'completed', 'cancelled') NOT NULL DEFAULT 'confirmed',
  total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  payment_status ENUM('unpaid', 'paid', 'refunded') NOT NULL DEFAULT 'unpaid',
  amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  payment_method VARCHAR(50),
  payment_reference VARCHAR(100),
  paid_at DATETIME,
  payment_notes TEXT,
  payment_updated_by INT,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (court_id) REFERENCES courts(id),
  FOREIGN KEY (client_id) REFERENCES clients(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (payment_updated_by) REFERENCES users(id),
  INDEX court_schedule (court_id, booking_date, status)
) ENGINE=InnoDB;

-- PHP will later validate times, amounts, permissions and booking conflicts.
-- Passwords must be generated with PHP password_hash(), never plain text.
-- Payment information is recorded manually. No online payment is processed.
