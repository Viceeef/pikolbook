PIKOLBOOK - WORKING PHP / MYSQL VERSION

UPDATING YOUR WORKING COPY
1. Keep your database. You do not need to import SQL or run setup again.
2. Rename C:\xampp\htdocs\pikolbook to pikolbook-old as a backup.
3. Extract this ZIP and put its new pikolbook folder in C:\xampp\htdocs\.
   Replace the project folder; do not merge old PHP/HTML files into it.
4. If you customized the database settings, enter the same host, port,
   database, username and password values at the top of the new config.php.
   Do not copy the entire old config.php; the new one also connects to MySQL.
5. Open http://localhost/pikolbook/ and log in with your existing account.
6. Press Ctrl+F5 once so the browser loads the updated CSS and JavaScript.

Your MySQL records are outside the project folder and are not deleted by
replacing the PHP files. The SQL table structure is unchanged; existing
one-hour bookings remain valid.

FIRST INSTALLATION ONLY (WINDOWS XAMPP)
1. Extract the ZIP into C:\xampp\htdocs\ so you have a pikolbook folder.
2. Start Apache and MySQL in XAMPP Control Panel.
3. Open http://localhost/phpmyadmin/. Click Import, choose pikolbook.sql
   from this folder and click Go. The SQL creates pikolbook_db, the five
   original tables, analytics views and Court 1 through Court 4.
4. Open http://localhost/pikolbook/setup.php.
5. Choose an Admin email and three passwords (8 to 72 characters):
     Admin: your chosen email (default admin@pikolbook.test)
     MWF Staff: mwf@pikolbook.test
     TTHS Staff: tths@pikolbook.test
6. Log in at http://localhost/pikolbook/ with those credentials.

Always open PHP through localhost, not by double-clicking a PHP file.
If Apache uses port 8080, use http://localhost:8080/pikolbook/ instead.
Setup will not overwrite existing accounts. The previous browser preview
password is not automatically assigned to the real MySQL accounts.

WHAT CHANGED IN THIS UPDATE
- Start time and duration replace the fixed one-hour slot choice.
- Choose whole-hour durations from 1 to 15 hours, ending by midnight.
- The form shows end time and total price at PHP 300 per hour.
- Longer bookings are checked across their full time range on the server.
- Admin can resize a confirmed booking after recording the correct payment.
- Staff can change payment details but cannot change time or duration.
- Selected sidebar tabs keep the same font size and weight.
- Filter / Clear / All dates / Print report match the input height.
- 23 PHP files were merged into 12 without adding a framework.
- FILE-GUIDE.txt explains every remaining file and the merged files.

SIMPLE FILE ORGANIZATION
All PHP, CSS, JS, SQL and reference files are together in pikolbook/.
Only images are in img/. There are no mockups/, docs/, needed/,
admin/, staff/, assets/ or week subfolders.

DATABASE SETTINGS
config.php uses the local XAMPP defaults:
  Host: 127.0.0.1
  Port: 3306
  Database: pikolbook_db
  Username: root
  Password: empty
Change only those values if your local configuration differs.

BOOKING AND PAYMENT RULES
- Four starting courts; PHP 300 per court per hour; open 9 AM to midnight.
- Duration is a whole number of hours. No bookings extend past midnight.
- At 11 PM, only one remaining hour is offered; it ends the next day at 12 AM.
- No past bookings or bookings on archived/blocked courts.
- Overlapping ranges are rejected. Adjacent bookings are allowed.
- Client creation and booking creation happen in one database transaction.
- A new reservation needs verified full payment matching rate times duration.
- Payments use GCash QRPH or Bank QRPH outside this website.
- A payment reference cannot be reused for another booking.
- Existing payment records may be corrected to unpaid/refunded as requested.
  This does not automatically cancel a booking or perform a refund.
- Changing start time, court or duration updates the displayed amount and
  clears the verification checkbox so payment can be checked again.
- Changing a paid reservation's duration requires the new full amount.
  The website does not collect additional money or issue money back.
- Cancel/delete booking releases its time and retains its payment/history.
- A database lock and transaction protect simultaneous booking submissions.

PERMISSIONS AND HISTORY
Admin manages resources and Staff accounts, reschedules/resizes bookings,
cancels bookings and edits payment details.
Staff views resources, creates/cancels bookings, edits payment details,
edits/deletes clients and edits only their own profile.
Both can view reports. New clients are added only while making a booking.
Unused clients/courts/Staff may be deleted. Referenced ones are archived
or deactivated. Cancel/reschedule future bookings before court maintenance.
MWF/TTHS are account labels rather than weekday login restrictions.
Sessions expire after 30 minutes without activity.

MERCHANT QRPH IMAGE
Put the venue's real merchant QR image in img/qrph.png when supplied.
The form displays it automatically. A missing image is clearly identified.
There is no fake QR or payment gateway.

INCLUDED WEEK 1 REFERENCES
Original logos and theme, original database tables/views, Week 1 notes,
wireframes and design reference PDFs are retained in this package.
The old disabled HTML pages and duplicate mockups were replaced by PHP.
The current README and FILE-GUIDE take priority over historical Week 1 notes.

VERIFICATION
The merged version was tested using PHP 8.3, MariaDB 10.11 and a browser.
Login/logout, CRUD, profile changes, reports and server role checks passed.
Duration tests covered price calculation, stored end times, overlaps,
adjacent bookings, resize conflicts, insufficient payment and midnight.
Existing one-hour records stayed compatible. The filter controls' heights
and alignment and sidebar font consistency were checked in the browser.
Desktop and mobile layouts were visually inspected.
Windows XAMPP still uses your own local settings and database accounts.
