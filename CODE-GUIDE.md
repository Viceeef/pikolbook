# Pikolbook: understand and explain the code

This guide explains the actual PHP/MySQL application in this folder. Read it with the files open in VS Code. You already know HTML/CSS, so most examples focus on PHP, JavaScript, and the database. The goal is to explain what the code really does, including its limits, rather than memorize a presentation script.

## 1. Start with the whole system

Pikolbook is a court-booking system used by Admin and Staff. Clients are customers whose details are recorded; they do not have login accounts. There is no public signup page.

There are three places where work happens:

| Place | What runs there | Responsibility |
| --- | --- | --- |
| Browser | HTML, CSS, `script.js` | Display forms, respond to clicks, show dialogs, filter visible rows, preview prices. |
| XAMPP Apache/PHP | The `.php` files | Check login and permissions, validate submitted values, query MySQL, generate HTML. |
| MySQL/MariaDB | Database `pikolbook_db` | Store accounts, customers, courts, reservations, maintenance, and refund history. |

The browser does not run PHP. When you open `http://localhost/pikolbook/clients.php`, Apache runs that PHP file first. PHP reads the database and produces HTML. The browser receives that generated HTML, then CSS styles it and JavaScript adds interactions.

The usual flow is:

1. Browser requests a PHP page.
2. PHP loads shared functions and the database connection.
3. PHP checks the signed-in user and queries the needed records.
4. PHP loops through records to generate HTML.
5. JavaScript works with that HTML in the browser.
6. Submitting a form sends named inputs back to PHP.
7. PHP validates again, saves if valid, then redirects to a fresh page.

A `.php` file can contain normal HTML. Inside `<?php ... ?>`, PHP runs instructions. Outside those tags, the HTML is sent to the browser. The indented layout does not turn the PHP file into an HTML-only file.

## 2. How the files connect

| File | Connects to | Main purpose |
| --- | --- | --- |
| `config.php` | MySQL | Connection settings and three database helpers. |
| `functions.php` | `config.php` | Sessions, login checks, validation, reusable form fields, availability, refunds. |
| `layout.php` | Functions already loaded by the page | Shared header/footer, booking form, calendar, booking details, cancellation/refund dialogs. |
| `index.php` | `functions.php`, then `script.js`/`style.css` | Login and logout. |
| `dashboard.php` | Functions, layout, database | Today's summary and quick schedule; opens the shared booking form. |
| `bookings.php` | Functions, layout, database | Saves bookings and handles complete/cancel/refund; displays list/calendar. |
| `resources.php` | Functions, layout, database | Court details and maintenance; Admin controls changes. |
| `clients.php` | Functions, layout, database | Customer list, edit, archive/delete. |
| `staff.php` | Functions, layout, database | Admin manages Staff accounts and resets their passwords. |
| `profile.php` | Functions, layout, database | Current user's own details and optional password change. |
| `reports.php` | Functions, layout, database | Periodic, resource, and client reports. |
| `script.js` | Elements and `data-*` attributes in generated HTML | Browser interactions. It does not connect to MySQL. |
| `style.css` | Class names and element IDs | Design, phone prefix alignment, responsive layout, print rules. |
| `pikolbook.sql` | Imported through phpMyAdmin | Creates tables, views, and missing starter accounts/courts. |

Most pages begin with:

```php
require 'functions.php';
$user = signed_in();
```

`require` loads another file. Because `functions.php` requires `config.php`, the page receives the database connection too. `signed_in()` returns the current account as an associative array, such as `$user['id']` and `$user['role']`.

Later, the page loads `layout.php` and calls:

```php
page_header($title, $page, $user);
```

That outputs the sidebar, top bar, and opening `<main>`. The page outputs its own content. `page_footer()` closes the common layout and outputs shared feedback/confirmation dialogs. Keeping shared markup in functions avoids repeating the same header and forms in many pages.

Both Login and the shared page header load `style.css` and `script.js`. Their URLs include `filemtime(...)`, the file's last modification time. This changes the URL when an asset changes and helps prevent the browser using an old version. The browser-tab icon uses `img/login_logo.png`.

## 3. PHP syntax you will see repeatedly

### Variables, arrays, and keys

```php
$name = 'Seth';
$clients = rows('SELECT * FROM clients ORDER BY full_name');
```

`$` starts a variable. `$clients` is an array of records. One record is another array with column names as keys:

```php
$client['full_name'];
$client['phone'];
```

`$client['phone']` means “the phone column from this client's record.” It is not a separate database query.

### GET and POST

`$_GET` contains URL parameters. `clients.php?edit=7` gives PHP `$_GET['edit']` with value `7`. This lets PHP find record 7 and open the edit form. Visiting that URL does not save an edit.

`$_POST` contains fields submitted by a form using `method="post"`. The HTML input's `name` determines the PHP key:

```html
<input id="full_name" name="full_name" type="text">
```

```php
$name = $_POST['full_name'];
```

The `id` connects a label or JavaScript selector to the element. The `name` connects the form value to PHP. They often match, but they serve different purposes.

### Default values and type conversion

```php
$id = (int) ($_POST['id'] ?? 0);
```

`??` uses a fallback when a value is missing or null. `(int)` converts the value to an integer. This is appropriate for database IDs, but not phone numbers: phone numbers are text because they contain `+63` and are not used in arithmetic.

`===` compares both value and type. `!==` means not strictly equal. `&&` means AND; `||` means OR. `!` means NOT. The ternary expression `$condition ? $yes : $no` picks between two values.

### Printing values and loops

```php
<?= e($client['full_name']) ?>
```

`<?= ... ?>` prints an expression. It is shorthand for `<?php echo ...; ?>`. `e()` escapes special HTML characters so a name is displayed as text rather than interpreted as markup.

```php
<?php foreach ($clients as $client): ?>
    <tr>
        <td><?= e($client['full_name']) ?></td>
    </tr>
<?php endforeach; ?>
```

`foreach` visits every client in the array. Each pass uses `$client` for the current record and outputs one table row. `:` and `endforeach;` are PHP's alternative loop syntax, useful when mixing PHP and HTML. `if (...): ... endif;` works the same way for conditional HTML.

### Functions, return, and output

A function groups reusable instructions. `phone_input()` returns a validated phone string. `phone_field()` outputs HTML for a form. A returned value can be assigned to a variable; output is sent into the page.

`field()` outputs a labeled input. Password fields also include the eye button. This is why Reset Password, Edit Profile, and New Staff use the same password behavior. `phone_field()` outputs a separate `+63` prefix next to the input. The explanatory phone paragraph has been removed; validation remains.

### Exceptions and redirects

```php
try {
    // Validate and save.
} catch (Exception $error) {
    // Roll back and show a useful error.
}
```

`throw new Exception(...)` stops the normal save path when a check fails. `catch` handles that failure. `go('bookings.php')` sends a redirect header and exits. Success normally redirects after saving so refreshing the page does not resubmit the same POST.

## 4. Database tables and relationships

The database has six tables. Each `id` is a primary key: a unique identifier for one row. A foreign key stores the identifier of a row in another table.

| Table | Important columns | What one row means |
| --- | --- | --- |
| `users` | `id`, `full_name`, `email`, `password_hash`, `role`, `phone`, `is_active` | One Admin/Staff account. |
| `clients` | `id`, `full_name`, `email`, `phone`, `is_active` | One customer. |
| `courts` | `id`, `name`, `description`, `hourly_rate`, `opening_time`, `closing_time`, `is_active` | One court/resource. |
| `court_blocks` | `id`, `court_id`, `start_at`, `end_at`, `reason` | One maintenance period for a court. |
| `bookings` | `id`, `court_id`, `client_id`, `created_by`, `booking_date`, `start_time`, `end_time`, `status`, payment columns | One reservation for one date. |
| `booking_details` | `booking_id`, `series_code`, cancellation and refund columns, `handled_by` | Optional extra information for one booking. |

`bookings.court_id` points to `courts.id`. `bookings.client_id` points to `clients.id`. `created_by` points to the user who made the booking; `payment_updated_by` identifies the user recording payment details. `booking_details.handled_by` identifies the user handling cancellation/refund information.

`booking_details.booking_id` is both its primary key and a foreign key to `bookings.id`. A booking can have at most one details row. A normal booking may have none until it is cancelled. A monthly booking gets one immediately to store its `series_code`.

Several bookings can share the same `series_code`. They are the separate weekly dates in one paid monthly block. A series code groups them; it does not replace each booking's own ID.

**Important distinction:** paid weekly “blocking” uses `bookings` plus `booking_details`. `court_blocks` is for maintenance, not customer payments.

### What each page reads and changes

| Action/page | Reads | Inserts/updates/deletes |
| --- | --- | --- |
| Login | `users` by email and active status | No account write; creates session state. |
| Dashboard | `bookings`, `booking_details`, `courts`, `court_blocks`, `clients` | None directly; its booking form posts to `bookings.php`. |
| New booking, new client | `courts`, `court_blocks`, existing `bookings`, payment references | Inserts `clients`, inserts `bookings`; monthly mode also inserts `booking_details`. |
| New booking, existing client | Same availability/payment data plus selected active `clients` row | Inserts `bookings`; optional monthly `booking_details`. |
| Edit booking | Selected `bookings`/`booking_details`, court/availability/payment data | Updates `bookings`. |
| Complete booking | Selected booking and its end time | Updates `bookings.status`. |
| Cancel booking | Selected booking or series dates, payment shares | Updates `bookings.status`; inserts/updates `booking_details`. |
| Record refund | Cancellation/refund details and existing refund references | Updates `booking_details` refund fields. |
| Resources | `courts`, `court_blocks`, relevant `bookings` | Inserts/updates `courts`, replaces its master-override maintenance block; archives or deletes where allowed. |
| Clients | `clients`; checks booking references | Updates `clients`; archives referenced rows, deletes unreferenced rows. |
| Staff | Staff rows in `users`; checks booking/refund references | Inserts/updates Staff `users`; resets hash, deactivates or deletes where allowed. |
| Edit Profile | Current signed-in `users` row | Updates own name/email/phone and optional password hash. |
| Reports | `bookings`, `booking_details`, `courts`, `clients` | No writes. |

SQL creates three reporting views too. A view is a saved SELECT, not another table of copied bookings. The current `reports.php` reads the underlying tables and computes totals in PHP; it does not query these views. Do not claim the page uses them.

## 5. How database queries work

`config.php` opens a `mysqli` connection. It sets UTF-8 encoding and Philippine time. Connection settings are host, port, database name, username, and password. On XAMPP, the supplied database is `pikolbook_db`; it is not a database hosted by Vercel.

The helper functions are:

| Helper | Result | Use |
| --- | --- | --- |
| `query()` | Prepared statement after execution | INSERT, UPDATE, DELETE, or a statement used by another helper. |
| `rows()` | Array of associative rows | Lists, schedules, and report data. |
| `one()` | First row, or `null` | One account, selected booking, or aggregate result. |

Example from client editing:

```php
query('UPDATE clients SET full_name=?,phone=?,email=?,is_active=? WHERE id=?', 'sssii', [
    $name,
    $phone,
    $email,
    ($_POST['is_active'] ?? '1') === '1' ? 1 : 0,
    $id,
]);
```

Each `?` receives one value from the array in the same order. In `sssii`, `s` means string and `i` means integer. `d` means a decimal/float. Binding values separately helps prevent user input from becoming SQL instructions. The helper's `...$values` passes the array's entries as separate function arguments.

For combined booking lists, SQL uses aliases and joins:

```sql
FROM bookings b
JOIN courts c ON c.id = b.court_id
JOIN clients cl ON cl.id = b.client_id
LEFT JOIN booking_details d ON d.booking_id = b.id
```

`b`, `c`, `cl`, and `d` are short table names within this query. JOIN gets the matching court/client names. LEFT JOIN keeps a booking even when it has no optional details row. Those optional fields may be null, so PHP uses defaults such as `$booking['refunded_amount'] ?? 0`.

The booking list uses `ORDER BY b.id DESC`: higher/newer IDs first. This is creation order, not play order. Dashboard orders today's records by start time; the calendar lays out dates and hourly slots.

## 6. Login, sessions, and permissions

1. Login form POSTs email/password to `index.php`.
2. PHP selects an active account by email from `users`.
3. `password_verify()` compares the entered password against `password_hash`.
4. On success, PHP regenerates the session ID and stores `user_id`, `auth_hash`, `last_activity`, and a form token in `$_SESSION`.
5. PHP redirects to Dashboard.

A session holds server-side state across requests. The browser keeps the session-ID cookie; it does not receive the password hash as a login field. Login does not add a new `users` row.

Passwords are stored as hashes using `password_hash(..., PASSWORD_DEFAULT)`. They are not stored as readable text and cannot be displayed back to Staff. `password_verify()` checks them; it does not decrypt them.

Every protected page calls `signed_in()`. This verifies the active account still exists, checks its hash against the session's remembered hash, and updates the activity time. After 30 minutes without a protected-page request, the session expires. A password reset changes the hash, so an old session is rejected on its next protected request.

`admin_only($user)` blocks Staff from Admin-only operations even if they manually type a URL. Hiding a sidebar link is just the visual part; PHP enforces permission. Staff can create bookings, work with clients, complete/cancel, record payments/refunds, view reports, and edit their own profile. Only Admin changes court records, manages Staff, and changes a booking's schedule.

`csrf_field()` outputs a hidden random form token. `check_post()` compares the submitted token with the session token before changing data. It is not a password, database ID, or payment reference.

## 7. Trace a new booking from the form to MySQL

Open `layout.php` → `booking_modal()`, then `bookings.php`.

1. PHP loads active clients and courts into the form's choices. Court options include `data-rate` for the JS price preview.
2. User chooses existing/new client, court, date, start hour, duration, and single/monthly booking.
3. JavaScript previews the end time, weekly dates, and total. It checks inputs before submission.
4. The form POSTs to `bookings.php`, including `court_id`, `date`, `hour`, `duration`, `repeat`, client fields/ID, payment fields, notes, and `csrf`.
5. PHP checks session, token, action, date/time values, and payment details. The trusted final price comes from `courts.hourly_rate`, not the browser's preview.
6. For new bookings PHP calls `available()` for each requested date. It rejects past times, inactive courts, outside-hours reservations, maintenance overlaps, and booking overlaps.
7. New-client mode inserts `full_name`, normalized `phone`, and `email` into `clients`, then uses `mysqli_insert_id($db)` as `client_id`. Existing mode verifies the selected active client instead of inserting another.
8. PHP inserts each reservation into `bookings` with the selected court/client IDs and signed-in creator's ID.
9. A monthly series also gets `booking_details` rows with the same generated series code.
10. Commit saves everything; PHP redirects with a success message. An error rolls everything back and restores safe form values for retry.

`return_page` is checked against a limited choice: Dashboard or Bookings. It allows the shared form to return to where it was opened.

### Availability and overlap

The overlap rule is:

```text
existing start < requested end
AND existing end > requested start
```

5–7 PM conflicts with 6–8 PM. 7–9 PM does not overlap 5–7 PM because the old end equals the new start. Duration checks cover the entire booking, not only its first hour.

`booking_end()` handles an end time of midnight: `00:00:00` after the start belongs to the next date. Cancelled bookings do not block availability. PHP checks again when saving, even if the calendar showed a slot as free earlier.

### Transactions and the lock

`begin_write()` gets a named MySQL lock and begins a transaction. The lock makes another save wait briefly while a change is checked and saved. A transaction groups statements into one unit. `end_write(true)` commits; `end_write(false)` rolls back, then releases the lock.

Without this grouping, a client could be inserted and left behind after a failed reservation, or only some dates in a monthly series could be saved. The shared lock also protects against two users checking the same free slot and both saving it simultaneously. The lock waits up to five seconds, then asks the user to retry.

### Monthly blocking example

At ₱300/hour, Tuesdays 5–7 PM cost ₱600 per date. If the first chosen date has four Tuesdays remaining in that same month, the preview and server total are ₱2,400. `repeat_dates()` adds seven days repeatedly and stops at month end. Dates earlier than the first selected date are not included.

There are four `bookings` rows, each with ₱600 total and ₱600 amount paid, all sharing the actual full-payment reference and one series code through their details rows. Reports count four dates and sum ₱2,400 once through those shares. The full ₱2,400 is not duplicated in every booking.

## 8. Payments, cancellation, completion, and refunds

The application records manual QRPH payments. It does not contact GCash/a bank, charge a customer, or prove a transaction succeeded. Staff/Admin verifies the actual external transaction, enters its reference and paid time, and confirms the verification checkbox. A real merchant QR image is optional at `img/qrph.png`; the project does not generate a fake payment QR.

New bookings require full payment. PHP checks the amount, allowed method, nonempty reference, reference reuse, verification checkbox, and a valid paid time that is not in the future. Standalone payment edits can correct an old booking to unpaid with amount zero; rescheduling requires full payment. Shared monthly payment fields cannot be independently changed for one occurrence.

`status` is reservation status: confirmed, completed, cancelled. `payment_status` is a separate stored payment state. Cancelling does not itself send a refund. In this app, recording the new cancellation refund updates `booking_details`; the original paid booking remains available for historical calculations.

Completion requires a confirmed booking whose whole reserved time has ended. Cancellation requires a reason. A series cancellation affects future confirmed occurrences, not every historical date with the code.

Current refund policy in `refund_quote()`:

| Time remaining before start | Refund calculated from recorded paid amount |
| --- | --- |
| At least 24 hours | 100% |
| At least 3 hours, less than 24 hours | 50% |
| Less than 3 hours, already started, or past | 0% |

This is the implemented project policy; it is not a rule supplied by a bank. The server recomputes it at confirmation. If time crossed a cutoff or the affected dates changed, the preview must be reviewed again.

Cancellation stores the timestamp, reason, `refund_due`, and handler in `booking_details`, and changes `bookings.status` to cancelled. This releases availability immediately. Refund money is sent outside the system. The Record Refund form then requires confirmation and a reference; PHP records `refunded_amount`, `refunded_at`, `refund_reference`, and handler. It rejects duplicate references and already-recorded refunds.

Example: a paid ₱600 booking is cancelled with 10 hours remaining. Refund due is ₱300. Before refund is recorded, net received remains ₱600 and pending refund is ₱300. After recording payment of the refund, net received is ₱300 and pending refund is zero.

## 9. Other pages: what happens behind each button

### Resources

Court creation inserts name/description, fixed starter rate ₱300, hours 9 AM–midnight, and active status into `courts`. Editing changes name/description/status. Maintenance dates are stored in `court_blocks`, not in the court description. The page replaces the court's master-override maintenance record when that setting is changed.

It checks reservations before deactivating/deleting a court or adding overlapping maintenance. A referenced court is archived instead of destroying booking history. An unreferenced court can be removed, along with its maintenance rows. A current “Available” label describes the court's active/maintenance condition, not a promise that every future time slot is free.

### Clients

New clients are created only together with a booking. Editing updates their name, email, normalized phone, and active status. Delete checks whether any bookings use the client ID: referenced clients are archived; unused ones are deleted. An archived client can be reactivated through Edit. Their historical bookings stay connected to the same ID.

### Staff and Edit Profile

Staff creation inserts a `users` row with role `staff`, a hashed initial password, phone, email, name, and status. Editing Staff details does not change their password.

Admin reset is a separate form. It checks Admin's current password, confirms the two new Staff passwords match and are 8–72 characters, then updates only the chosen Staff account's hash. It does not need the Staff member's old password. Staff cannot open this Admin page.

Edit Profile uses the session user's ID, not a posted target ID. It checks that user's current password before saving details. Leave both new-password fields blank to keep the password. To change it, both must match and meet the length rule. Successful change updates the session's remembered hash and regenerates the session ID. Phone numbers come from `users.phone`; initially missing values are not fabricated.

### Calendar and booking details

PHP loads relevant bookings and maintenance once, then `calendar_slot()` checks those arrays to classify court-hour cells. It does not run a fresh SQL query for every cell. Month navigation and day selection use GET URLs. Available-slot buttons carry date/hour/court `data-*` values; JS uses them to prefill the shared booking form.

A booked name links with `info=bookingID`. `booking_details_modal()` reads that exact booking plus names/payment/refund data and displays a read-only summary. Opening the summary does not modify the reservation. Close preserves the selected calendar context.

### Reports and Dashboard

Periodic reporting applies its month/year bounds in PHP/SQL. Resource/client reports use all loaded booking records and group by the corresponding foreign-key ID. `report_totals()` loops through records and counts statuses, net received, and pending refunds. Reports include cancelled/completed history.

`net_received()` returns paid money minus refunds recorded as paid, with a minimum of zero. A refund merely due is shown as pending and is not yet subtracted. Periodic reports group by booking date. Dashboard net receipts instead uses the date payment was received and the date refunds were recorded. Therefore those two views can show different totals for the same day/month without being contradictory.

## 10. JavaScript: understand each section

JavaScript uses the DOM, the browser's representation of HTML elements. `querySelector()` finds one matching element, `querySelectorAll()` finds multiple, and `getElementById()` finds an ID. `addEventListener()` registers a function to run on an event. `textContent` reads/writes visible text safely. `className` selects CSS styling. `hidden` works on normal HTML elements; the SVG eye uses `toggleAttribute('hidden', ...)` explicitly.

`data-open="staff-dialog"` becomes `button.dataset.open`. The attribute is just a bridge between HTML and JS; it is not a database value unless the form separately submits it.

| Section of `script.js` | What it does | Saves data? |
| --- | --- | --- |
| Open/close buttons and `data-auto-open` | Calls dialog `showModal()`/`close()`; failed forms reopen. | No. |
| Filter-clear buttons | Resets one control and dispatches input/change events. | No. |
| Password toggles | Changes this field between password/text, changes eye SVG, updates accessibility labels. | No. |
| Confirmation forms | Prevents initial submit, remembers the form, asks confirmation, submits only after Confirm. | Only PHP saves after submission. |
| Generic table searches | Hides nonmatching rows and shows an empty-results row. | No. |
| Booking filters | Checks search/date/court/status against each row's text and dataset. | No. |
| Report tabs | Shows the selected section; shows month/year filters only for Periodic. | No. |
| Existing/new client mode | Changes visible fields, required flags, and disabled inputs. | No. |
| Client search in booking | Filters choices already loaded by PHP; clicking a result selects its ID. | No. |
| Maintenance selector | Shows dates and makes them required when maintenance is selected. | No. |
| Print | Opens year sections and calls `window.print()`. Print CSS hides the controls and other reports. | No. |
| Booking invalid/retry/cancel | Shows foreground feedback, focuses invalid input on retry, reloads on discard. | No. |
| Booking summary | Computes preview duration, dates, price; unchecks verification when schedule changes. | No. |
| Payment mode | Controls required/disabled fields and sets unpaid amount to zero. | No. |
| Calendar free-slot buttons | Prefills booking court/date/hour and opens dialog. | No. |
| Booking submit button | Disables Save and displays Saving to reduce double clicks. | PHP remains responsible for the save. |
| Phone input | Normalizes complete local/international numbers to national digits and sets custom validity. | PHP later saves normalized `+63` text. |

### Example: password eye

```js
const showing = input.type === 'password';
input.type = showing ? 'text' : 'password';
button.querySelector('.icon-eye').toggleAttribute('hidden', !showing);
button.querySelector('.icon-eye-off').toggleAttribute('hidden', showing);
```

Here `showing` means this click is about to reveal the password. When true, the open eye's hidden attribute is removed and the slashed eye's is added. Each button finds the input inside its own `.password-input`, so toggling Current Password does not reveal New Password too.

### Example: booking filters

A table row carries `data-date`, `data-court`, and `data-status` from PHP's database record. JS compares those with the selected controls. A row stays visible only when every selected condition matches. Empty filters mean “do not restrict this condition.” Hiding a row does not delete it or change SQL.

This is different from Periodic Report filtering: that form submits a GET request to `reports.php`, which queries the selected range. There are no `fetch()` calls or external APIs in this project.

### Example: phone validation

The visible field contains national digits; the adjacent `+63` is a span, not a submitted input. JS accepts complete forms such as `0917...`, `917...`, `63917...`, or `+63917...` and normalizes the field. PHP's `phone_input()` runs `normalize_phone()` again and stores `+639...`. Letters and incorrect numbers fail even if JS is bypassed.

`required`, `pattern`, and `setCustomValidity()` help the user in the browser. They are not enough for database safety: a person could manually POST data. PHP repeats validation and checks permissions, price, and availability.

## 11. Handling errors without losing data

`fail_form()` rolls back the write, stores safe draft values and an error in the session, and redirects to Edit or New. `fv()` retrieves those draft values when rebuilding the form. Passwords, the CSRF value, and the paid-verification checkbox are excluded from stored drafts.

Booking browser errors appear in the foreground dialog before submission. PHP booking failures reopen the booking form with the server error and retry/discard choices. Database exceptions are logged and replaced by a general duplicate/save message rather than exposing raw database internals to the user.

An empty report/search is different from a failed save. “No bookings match” means there are no records in the selected range; it does not prove the SQL query failed.

## 12. How to read/debug the project yourself

Read in this order: `index.php`, `config.php`, the top of `functions.php`, `profile.php`, `clients.php`, `staff.php`, `resources.php`, `bookings.php`, then the corresponding `layout.php` functions and `script.js`. Reports and Dashboard make more sense after understanding the tables and payment/refund fields.

For one button, answer these questions:

1. What HTML button/form is clicked? What are its `action`, `method`, and named inputs?
2. Does JavaScript only show something, or does it allow/stop form submission?
3. Which PHP file receives the request?
4. Which POST action branch runs? Which validations can reject it?
5. Which SELECT checks the record or permission?
6. Which INSERT/UPDATE/DELETE changes data? Which table/key is affected?
7. Is it inside a transaction? What happens on failure?
8. Where does PHP redirect, and which SELECT shows the fresh result?

If something looks wrong, inspect browser Console for JS errors and check that the latest CSS/JS loaded. If saving fails, read the foreground message and check the form values/selected IDs. In phpMyAdmin, look at the target table row, not just the page screenshot. Never manually change a password hash to plain text.

## 13. Defense questions with honest answers

**Why PHP and MySQL?** PHP processes forms and sessions on the server; MySQL stores relational records. They fit the XAMPP setup and the plain project structure used in class.

**Why mix PHP with HTML?** The HTML structure is fixed, while PHP supplies current names, records, statuses, and permissions from MySQL. Loops generate table rows and conditions generate the appropriate controls.

**Why shared functions instead of putting everything in one file?** Login checks, queries, form fields, and the booking popup are reused. Keeping them in two shared files avoids repeating and fixing the same code on every page. The app still has no framework or API folders.

**Does JavaScript save bookings?** No. It helps fill/check the form and previews the price. The POST reaches PHP, which verifies data and writes MySQL.

**How do you prevent double bookings?** The server checks interval overlap, maintenance, hours, and active court status, then saves inside the shared write lock and transaction.

**How does monthly blocking work?** PHP creates one booking per weekly date remaining in the selected month. A shared series code groups those dates, and each holds its share of the payment.

**Why archive instead of delete?** A booking still references the customer, court, or Staff ID. Archiving stops new use without breaking historical reports and foreign keys.

**How do you protect passwords?** Store hashes, verify with `password_verify()`, require the current password for Profile/Admin reset, and invalidate stale sessions after a hash change.

**Is payment automatic?** No. Staff verifies QRPH externally and records its details. The system records payments/refunds; it does not transfer money or connect to a bank API.

**Why do reports and Dashboard totals differ?** Reports group net amounts by booking date. Dashboard receipts/refunds use their actual recorded transaction dates.

**Can it run by uploading raw PHP to Vercel Drop?** Not as this plain XAMPP setup. It needs a PHP server and an accessible MySQL database. Local MySQL is not uploaded with the PHP files.

**Is formatting proof of who wrote the code?** No. Readable indentation helps review and explain it. The strongest defense is showing the actual control flow, SQL statements, validation checks, and limitations accurately. Explain assistance honestly if asked.

## 14. Practice walkthrough before presenting

Make a test client through a two-hour future booking. Show the resulting `clients` row, the `bookings` row, and how client/court/user IDs connect. Create a monthly block and show its grouped `booking_details` rows. Try an overlapping reservation and explain why it fails. Cancel a future booking, show `refund_due`, then explain why the amount is pending until a real refund is recorded. Finally, show that Staff cannot open Staff management and can only update their own Profile with their current password.

Use test data and avoid changing real payment/refund records merely for a demonstration. You should be able to explain both a successful action and the check that stops an invalid one.
