PIKOLBOOK - MONTHLY BLOCKING AND CALENDAR UPDATE

INSTALL OR UPDATE IN XAMPP
1. Back up your current project folder and export pikolbook_db in phpMyAdmin.
2. Copy the PHP, CSS, JS and SQL files from this pikolbook folder into
   C:\xampp\htdocs\pikolbook. Keep your repository's Git files in place.
   Keep your own merchant QR image and any customized database settings.
3. Start Apache and MySQL. In http://localhost/phpmyadmin/, import the new
   pikolbook.sql. This is REQUIRED for the new booking_details table.
   Do not delete your database. Existing bookings and clients are preserved.
   Importing again keeps existing passwords, phones and court rates.
4. Check config.php if your database connection differs from XAMPP defaults.
5. Open http://localhost/pikolbook/ and press Ctrl+F5 to reload CSS and JS.

ACCOUNTS
New databases get these accounts directly from SQL, with password phpadmin:
  admin@pikolbook.com   Admin
  mwf@pikolbook.com     Staff
  tth@pikolbook.com     Staff
Existing accounts keep their current passwords after import.
There is no sign-up or setup page. MWF/TTH are account labels, not day limits.
The MySQL connection uses root with an empty password by default. phpadmin
is the website password, not the MySQL root password.

MONTHLY BLOCKING
Open New booking, select a client and choose Monthly block.
Choose the first date, court, start time and duration. The same weekday and
hours repeat every 7 days until the end of that calendar month.
For a full month, choose the first occurrence of the weekday in that month.
A later first date reserves only the remaining occurrences. No past dates
are included automatically, skipped, or silently moved to a different month.
Example: four Tuesdays at 5-7 PM cost 4 x 2 x PHP 300 = PHP 2,400.
The form lists the dates and total before saving. Verify the single full
payment and enter its reference. Each date gets one booking and its share
of the amount, linked by a monthly-block code. Reports do not double-count.
If even one date is unavailable, nothing in that submission is saved.
Admin can reschedule a single future occurrence within the same month at
the same price. Shared payment details remain fixed. To change its price,
cancel the relevant occurrence and create a new booking/payment record.

CALENDAR
Bookings & schedule has List view and Calendar view, following the wireframe.
Choose a month and court. Each day shows booked and available court-hours,
plus maintenance hours where relevant. All courts adds their hours together.
Click a day to see hourly availability. Available opens the booking form
with that court, date and time. Booked links to the filtered booking list.
Past hours and maintenance are not offered as available. Cancelled bookings
release their slots. Calendar availability is checked again when saving.

CANCELLATIONS AND REFUNDS
The example image did not specify one exact rule. This update uses:
  At least 24 hours before start: 100% refund eligibility.
  From 3 to under 24 hours before start: 50% refund eligibility.
  Under 3 hours, already started or no-show: 0% refund eligibility.
All timing uses Asia/Manila, including the database connection.
Select Cancel, review the dates and refund amount, and enter a reason.
For a monthly block, choose one date or all future confirmed dates in it.
The rule applies separately to each occurrence's share of the payment.
If the refund changes while the form is open, it asks you to review again.
Confirm cancellation immediately releases the selected slots and records
the reason, time, user and refund due. It does not send money.
After sending a refund outside the website, choose Record refund for that
date and enter the actual transaction reference. The full amount due for
that date is recorded. A refund cannot be recorded twice. Use one refund
transaction per date; transaction references cannot be reused.
Cancelled bookings and earlier refunded records cannot be edited.
Completed bookings cannot be cancelled. Complete is available only after
an entire reserved time has ended. There is no booking delete action.
Old cancellations without recorded policy details keep their old history;
the new policy is not applied retroactively to them.

REPORTS
Your teammate's three report tabs and month/year filters are retained.
Filters apply to Periodic reporting. Resource/client reports cover all dates.
Net received = paid booking amounts minus refunds recorded as actually paid.
Pending refunds are shown separately. Amounts are grouped by booking date,
so a refund changes the net total for the original booking's period.
The dashboard's Net receipts today is payments dated today minus refunds
recorded today. It can differ from reports grouped by booking date.
Print works on the selected report. Invalid reversed date ranges show an error.

STAFF AND PASSWORDS
The users table already has a phone column. Staff forms and Edit profile
save and display it. Seeded accounts have no invented phone numbers; enter
their actual numbers using Edit staff or Edit profile.
Edit staff changes name, email, phone and active status, keeping the password.
New staff requires an initial password. Staff changes their own password in
Edit profile using their current password and matching new passwords.
Leaving the new password blank keeps it while updating other profile data.
Admin has a separate Reset password action for forgotten staff passwords;
it requires the Admin's own password. Previous sessions are rejected after
a password change. The current user's own profile session is refreshed.
Password eye buttons work on login and password forms.
Passwords are hashed in MySQL; they are never displayed or stored as plain text.

BOOKING FEEDBACK AND PAYMENT
Failures open a popup above the booking form with Edit and try again or
Discard form. Safe entries are retained; payment verification must be checked
again after an unsuccessful save. Success also opens a confirmation popup.
New bookings and reschedules require manually verified full payment.
For an existing standalone record, correcting payment to Unpaid requires
amount 0 and clears its payment reference/date/method. Refunds use the
cancellation flow. Staff cannot change a reservation's schedule.
The server checks overlap, maintenance, dates, duration, payment references,
roles and CSRF tokens. A transaction and shared database lock protect saves.
There is no payment API. Payments use real GCash QRPH or Bank QRPH outside
the app. Add the real venue QR as img/qrph.png when available.

FILES
All 11 PHP files, style.css, script.js, pikolbook.sql and notes are in this
folder. Only images are in img/. No framework, build process or external API.
FILE-GUIDE.txt explains each file and the booking/cancellation code flow.
Original Week 1 reference PDFs, notes and logos are retained.
