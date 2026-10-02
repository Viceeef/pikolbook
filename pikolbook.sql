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
    /*ENGINE=InnoDB - default, transaction-safe storage engine for MySQL that provides ACID 
    compliance, row-level locking, and foreign key support.*/

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

-- ANALYTICS VIEWS
-- 1. Periodic Reporting (Yearly & Monthly Revenue/Booking Summary)
CREATE OR REPLACE VIEW view_periodic_reporting AS
SELECT 
    YEAR(booking_date) AS report_year,
    MONTH(booking_date) AS report_month,
    MONTHNAME(booking_date) AS month_name,
    COUNT(id) AS total_bookings,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_bookings,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_bookings,
    SUM(CASE WHEN payment_status = 'paid' THEN amount_paid ELSE 0 END) AS total_revenue
FROM bookings
GROUP BY YEAR(booking_date), MONTH(booking_date);

-- 2. Resource Analytics (Bookings & Revenue per Court per Year & Month)
CREATE OR REPLACE VIEW view_analytics_by_resource AS
SELECT 
    YEAR(b.booking_date) AS report_year,
    MONTH(b.booking_date) AS report_month,
    MONTHNAME(b.booking_date) AS month_name,
    c.id AS court_id,
    c.name AS court_name,
    COUNT(b.id) AS total_bookings,
    SUM(CASE WHEN b.payment_status = 'paid' THEN b.amount_paid ELSE 0 END) AS total_revenue
FROM bookings b
JOIN courts c ON b.court_id = c.id
WHERE b.status != 'cancelled'
GROUP BY YEAR(b.booking_date), MONTH(b.booking_date), c.id;

-- 3. Client Analytics (Bookings & Expenditure per Client per Year & Month)
CREATE OR REPLACE VIEW view_analytics_by_client AS
SELECT 
    YEAR(b.booking_date) AS report_year,
    MONTH(b.booking_date) AS report_month,
    MONTHNAME(b.booking_date) AS month_name,
    cl.id AS client_id,
    cl.full_name AS client_name,
    cl.phone AS client_phone,
    COUNT(b.id) AS total_bookings,
    SUM(b.amount_paid) AS total_spent
FROM bookings b
JOIN clients cl ON b.client_id = cl.id
WHERE b.status != 'cancelled'
GROUP BY YEAR(b.booking_date), MONTH(b.booking_date), cl.id;

-- PHP will later validate times, amounts, permissions and booking conflicts.
-- Passwords must be generated with PHP password_hash(), never plain text.
-- Payment information is recorded manually. No online payment is processed.