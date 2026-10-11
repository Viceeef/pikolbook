-- Pikolbook starter database for XAMPP.
-- Import this file in phpMyAdmin. The three assigned accounts are ready immediately.
-- Account password: phpadmin (stored below as a bcrypt hash).
-- Reimporting preserves existing account passwords and phone numbers.
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

-- Monthly blocks and cancellation/refund history. Existing bookings need no changes.
CREATE TABLE IF NOT EXISTS booking_details (
  booking_id INT PRIMARY KEY,
  series_code VARCHAR(32),
  cancelled_at DATETIME,
  cancel_reason VARCHAR(500),
  refund_due DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  refunded_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  refunded_at DATETIME,
  refund_reference VARCHAR(100),
  handled_by INT,
  FOREIGN KEY (booking_id) REFERENCES bookings(id),
  FOREIGN KEY (handled_by) REFERENCES users(id),
  INDEX monthly_block (series_code)
) ENGINE=InnoDB;

-- Reports include all booking statuses. Revenue is paid money minus recorded refunds.
CREATE OR REPLACE VIEW view_periodic_reporting AS
SELECT YEAR(b.booking_date) AS report_year, MONTH(b.booking_date) AS report_month,
 MONTHNAME(b.booking_date) AS month_name, COUNT(b.id) AS total_bookings,
 SUM(b.status='completed') AS completed_bookings, SUM(b.status='cancelled') AS cancelled_bookings,
 SUM(CASE WHEN b.payment_status='paid' THEN GREATEST(0,b.amount_paid-COALESCE(d.refunded_amount,0)) ELSE 0 END) AS total_revenue
FROM bookings b LEFT JOIN booking_details d ON d.booking_id=b.id
GROUP BY YEAR(b.booking_date), MONTH(b.booking_date), MONTHNAME(b.booking_date);

CREATE OR REPLACE VIEW view_analytics_by_resource AS
SELECT YEAR(b.booking_date) AS report_year, MONTH(b.booking_date) AS report_month,
 MONTHNAME(b.booking_date) AS month_name, c.id AS court_id, c.name AS court_name,
 COUNT(b.id) AS total_bookings,
 SUM(CASE WHEN b.payment_status='paid' THEN GREATEST(0,b.amount_paid-COALESCE(d.refunded_amount,0)) ELSE 0 END) AS total_revenue
FROM bookings b JOIN courts c ON c.id=b.court_id LEFT JOIN booking_details d ON d.booking_id=b.id
GROUP BY YEAR(b.booking_date), MONTH(b.booking_date), MONTHNAME(b.booking_date), c.id, c.name;

CREATE OR REPLACE VIEW view_analytics_by_client AS
SELECT YEAR(b.booking_date) AS report_year, MONTH(b.booking_date) AS report_month,
 MONTHNAME(b.booking_date) AS month_name, cl.id AS client_id, cl.full_name AS client_name, cl.phone AS client_phone,
 COUNT(b.id) AS total_bookings,
 SUM(CASE WHEN b.payment_status='paid' THEN GREATEST(0,b.amount_paid-COALESCE(d.refunded_amount,0)) ELSE 0 END) AS total_spent
FROM bookings b JOIN clients cl ON cl.id=b.client_id LEFT JOIN booking_details d ON d.booking_id=b.id
GROUP BY YEAR(b.booking_date), MONTH(b.booking_date), MONTHNAME(b.booking_date), cl.id, cl.full_name, cl.phone;

-- COURTS
-- Add the four starting courts when missing.
INSERT INTO courts (name, description, hourly_rate, opening_time, closing_time)
SELECT 'Court 1', 'Pickleball court', 300.00, '09:00:00', '00:00:00'
WHERE NOT EXISTS (SELECT 1 FROM courts WHERE name = 'Court 1');

INSERT INTO courts (name, description, hourly_rate, opening_time, closing_time)
SELECT 'Court 2', 'Pickleball court', 300.00, '09:00:00', '00:00:00'
WHERE NOT EXISTS (SELECT 1 FROM courts WHERE name = 'Court 2');

INSERT INTO courts (name, description, hourly_rate, opening_time, closing_time)
SELECT 'Court 3', 'Pickleball court', 300.00, '09:00:00', '00:00:00'
WHERE NOT EXISTS (SELECT 1 FROM courts WHERE name = 'Court 3');

INSERT INTO courts (name, description, hourly_rate, opening_time, closing_time)
SELECT 'Court 4', 'Pickleball court', 300.00, '09:00:00', '00:00:00'
WHERE NOT EXISTS (SELECT 1 FROM courts WHERE name = 'Court 4');


-- ASSIGNED ACCOUNTS
-- Rename the old starter accounts without changing their IDs or booking history.
UPDATE users SET email = 'admin@pikolbook.com'
WHERE email = 'admin@pikolbook.test' AND NOT EXISTS (
  SELECT 1 FROM (SELECT email FROM users) AS assigned_users
  WHERE email = 'admin@pikolbook.com'
);

UPDATE users SET email = 'mwf@pikolbook.com'
WHERE email = 'mwf@pikolbook.test' AND NOT EXISTS (
  SELECT 1 FROM (SELECT email FROM users) AS assigned_users
  WHERE email = 'mwf@pikolbook.com'
);

UPDATE users SET email = 'tth@pikolbook.com'
WHERE email = 'tths@pikolbook.test' AND NOT EXISTS (
  SELECT 1 FROM (SELECT email FROM users) AS assigned_users
  WHERE email = 'tth@pikolbook.com'
);

-- Add the assigned accounts only when missing. Password for new accounts: phpadmin.
INSERT INTO users(full_name,email,password_hash,role,is_active)
SELECT 'Administrator', 'admin@pikolbook.com', '$2y$12$oki7pu9Z.vNORG1wJ7s9yuMMU0J8S8XBgLjb0lZTcfR9K8ev7LTZu', 'admin', 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email='admin@pikolbook.com');

INSERT INTO users(full_name,email,password_hash,role,is_active)
SELECT 'MWF Staff', 'mwf@pikolbook.com', '$2y$12$oki7pu9Z.vNORG1wJ7s9yuMMU0J8S8XBgLjb0lZTcfR9K8ev7LTZu', 'staff', 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email='mwf@pikolbook.com');

INSERT INTO users(full_name,email,password_hash,role,is_active)
SELECT 'TTH Staff', 'tth@pikolbook.com', '$2y$12$oki7pu9Z.vNORG1wJ7s9yuMMU0J8S8XBgLjb0lZTcfR9K8ev7LTZu', 'staff', 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email='tth@pikolbook.com');

