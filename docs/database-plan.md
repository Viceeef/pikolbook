# Proposed database — design only

Database name: `pikolbook_db`. Target: the MySQL-compatible database supplied with XAMPP, using InnoDB and utf8mb4. The schema is planned here for review; no database is required for the first checking.

| Table | Proposed fields | Purpose |
|---|---|---|
| users | id, full_name, email (unique), password_hash, role, phone, is_active, created_at | Admin and Staff accounts; role restricted to admin/staff |
| clients | id, full_name, email (optional), phone, is_active, created_at | Customers for whom staff make bookings |
| courts | id, name (unique), description, hourly_rate, opening_time, closing_time, is_active | Court resource details |
| court_blocks | id, court_id, start_at, end_at, reason | Maintenance and other unavailable periods |
| bookings | id, court_id, client_id, created_by, booking_date, start_time, end_time, status, total_amount, notes, created_at, updated_at | Reservations and their historical price |
| booking_payments | id, booking_id (unique), status, amount_paid, method, reference, paid_at, notes, updated_by, updated_at | One simple offline-payment record per booking |

IDs use integer auto-increment primary keys. Foreign keys link court_blocks to courts; bookings to courts, clients and the creating user; booking_payments to bookings and the updating user. Use restrictive deletion for referenced history. Use DECIMAL(10,2) for money, DATE/TIME for the booking schedule, and VARCHAR(255) for password hashes. Staff profile data lives in users; do not create a duplicate staff login table.

The application records times in Asia/Manila consistently. Add a booking index on court_id, booking_date and status for schedule checks. Availability is derived from court hours, blocks and active bookings; do not store a misleading single “available now” field.

Payment fields describe a manual record, not a transaction processor. A cancelled paid booking remains paid until staff record the result of an offline refund. No card number, CVV, API key or gateway token is stored.

## XAMPP database steps for Week 3

These steps are a future checklist. The exact runnable SQL will be delivered when Week 3 is authorized.

1. Start Apache and MySQL in XAMPP Control Panel.
2. Visit http://localhost/phpmyadmin/.
3. Open the SQL tab and run the supplied `database/pikolbook.sql`, or use Import to select that file. The file will create `pikolbook_db` and its tables.
4. Set local credentials in `config/database.php`: typically host `localhost`, user `root`, empty password for an unmodified local XAMPP installation, database `pikolbook_db`. Use your actual password if configured.
5. Create the first Admin through a one-time local setup process that hashes the password in PHP. Disable that setup after use; do not distribute a known working admin password.
6. Check login, sample records, booking validation, CRUD and report results through http://localhost/pikolbook/.

Do not add database setup or functioning login to the Week 1 submission. No production hosting or external service is required.
