PIKOLBOOK - WEEK 1

Files
  index.html             Home / preview links
  login.html             Login design
  admin-dashboard.html   Admin design
  staff-dashboard.html   Staff design
  new-booking.html       Booking form design
  style.css              All styles
  pikolbook.sql          Starter database
  img/                   Your two logos

HTML and CSS are in the same folder. Later PHP and JS files will also
be in this folder, with separate files for each page or action.

OPEN THE WEBSITE
1. Copy the pikolbook folder into C:\xampp\htdocs\.
2. Start Apache in XAMPP.
3. Open http://localhost/pikolbook/.
   If Apache uses port 8080, open http://localhost:8080/pikolbook/.

CREATE THE DATABASE
1. Start Apache and MySQL in XAMPP.
2. Open http://localhost/phpmyadmin/.
3. Click Import at the top.
4. Choose pikolbook.sql from this folder.
5. Click Go (or Import).
6. Look for pikolbook_db on the left. Refresh if needed.

You should see five empty tables:
  users        Admin and Staff accounts
  clients      Client details
  courts       Court details and opening hours
  court_blocks Maintenance / unavailable times
  bookings     Reservations and offline payment details

The SQL creates the database itself. You do not need a database/ folder.
You can also open pikolbook.sql in a text editor, copy all the code,
and run it in phpMyAdmin's SQL tab.

No sample login is included. Password hashes will be created through PHP
when we add working accounts. Do not enter plain passwords in the table.
The SQL does not delete existing records or change existing table layouts.
Use it for a new pikolbook_db; it is not a migration for a different schema.

WHAT WORKS NOW
Links between the preview pages work. Forms are disabled. The numbers,
names and PHP 300 price are examples. Importing the SQL does not connect
the pages to the database. No real bookings or payments happen yet.

TEAM
Angelique So: CSS, colors, spacing and design.
Aldrei Paz: SQL, database connection, login and backend validation.
Seth Somoza: HTML pages, JS when needed, backend integration and general coding.
These are assignments; record each person's actual work as you go.

WEEKS
1. Requirements and design (current).
2. Complete the frontend pages and simple interactions.
3. Connect PHP and MySQL, add login and CRUD, then test.
4. Prepare the presentation and defense.
We wait for your command before moving to the next week.
The starter SQL is included now because you requested it.

SCOPE
Two login roles: Admin and Staff. Clients are records, not login users.
Manage courts, clients, staff and bookings. Search available schedules,
reschedule/cancel bookings and view date/court/client reports.
Payment is outside the website; staff only record its status and details.
Basic HTML, CSS, PHP, MySQL and a little JS. No APIs or frameworks.

LATER RULES
PHP will check required fields, booking overlaps and unavailable courts.
Cancelled bookings free the slot. Back-to-back bookings are allowed.
Payment status is separate from booking status; cancellation does not
perform a refund. Keep referenced history instead of deleting it.
Use hashed passwords, prepared SQL statements and session role checks.

TO CONFIRM
The specification lets Staff manage staff records, but the Staff wireframe
has no Staff page. Confirm that permission before backend development.
Also confirm court prices, opening hours and the milestone dates, since
the specification has date typos. Keep using your original wireframe PDF.

CHECKS
Local links and image paths have been checked. Browser appearance and
SQL execution have not been tested here. Review the pages in your browser
and run the import in XAMPP; send any error text if something fails.

CSS
style.css now uses plain colors, class selectors, margins, padding,
borders, simple Grid columns and Flexbox from the supplied lessons.
One media query stacks the columns on smaller screens.
Each property is on its own line, with comments for each section.
The top label now only says Week 1 preview.

FULL WINDOW LAYOUT
The sidebar and login panels now fill the available browser height.
The login content is centered, and the home page uses the full width.
Only style.css changed. No database import is needed for this update.
100vh means the browser height; flex-grow fills the remaining space
below the Week 1 label. Small screens still stack the columns.
