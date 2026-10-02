# Week 1 requirements

## Purpose and users

Pikolbook helps venue personnel manage pickleball courts, client records and reservations. The resource is a court. There are two system roles: **Admin** and **Staff**. Clients are booking records, not a third login role in the supplied design. Staff enter bookings on clients' behalf.

The website records offline payment information. It does not collect money or card details, redirect to a payment service, or call payment APIs.

## Required modules

| Module | Planned behavior | Completion evidence in Week 3 |
|---|---|---|
| Accounts | Sign in/out, protected pages, staff account creation and own-profile update | Invalid login fails; role checks prevent unauthorized actions |
| Courts/resources | Add, view, edit and remove unused courts; set availability and maintenance blocks | Unavailable courts cannot be booked |
| Bookings | Search, create, view, reschedule, cancel and remove eligible records; list and calendar views | Overlapping bookings rejected on create and edit |
| Clients | Add, search, view, edit and remove unused client records | Required fields validated; referenced clients retained |
| Staff records | Add, view, edit and deactivate staff accounts; remove unused accounts | Unique email and safe account permissions enforced |
| Offline payment records | Set amount, status, method, reference, paid date and notes | Payment change persists; no online payment transaction occurs |
| Reports/history | Date range, bookings per court and bookings per client | Correct filtered results, including clearly marked cancellations |

CRUD means Create, Read, Update and Delete. For referenced records, deactivate/archive instead of deleting history. Hard deletion is planned only for unused courts/clients/staff and eligible mistaken bookings; the exact booking deletion rule needs instructor confirmation. Reports read underlying CRUD records and do not need a separate editable reports table.

## Proposed permissions

| Action | Admin | Staff |
|---|---|---|
| Login, logout and edit own profile | Yes | Yes |
| View courts and availability | Yes | Yes |
| Create/edit/remove courts and set blocked times | Yes | No, based on wireframes |
| Create/edit/cancel bookings and record offline payment | Yes | Yes |
| Manage client records | Yes | Yes |
| Manage other staff records | Yes | Clarify before backend work |
| View date/court/client booking reports | Yes | Yes |
| Assign Admin role | Yes | No |

The specification explicitly lists staff-record CRUD under Staff, but the supplied Staff wireframe omits staff management. This is unresolved: confirm with the instructor before implementing access rules. Staff must never be able to grant themselves Admin access.

## Proposed booking rules

1. One booking reserves one court for one client on one day. Recurring and overnight reservations are outside the initial scope.
2. Client, court, date, start and end times are required. End must be after start. New bookings cannot start in the past. Opening hours remain a venue setting to confirm.
3. An active booking conflicts when `existing_start < requested_end` and `existing_end > requested_start` on the same court and date. Adjacent bookings are allowed. Ignore the current booking when editing it.
4. Cancelled bookings release their time. Maintenance blocks and inactive courts prevent booking.
5. Enforce the conflict check in PHP. Serialize writes for the same court in a database transaction in Week 3 so two simultaneous submissions cannot both reserve the same time.
6. Proposed booking statuses: Confirmed, Completed and Cancelled. Payment status is independent: Unpaid, Paid or Refunded. Cancelling a paid booking does not automatically issue or record a refund.
7. One payment record per booking keeps the initial project small. Partial payments, installments and automatic refunds are outside scope. A reference and note can record a refund handled outside the website.
8. The example PHP 300 rate, client names, counts and dates in the mockups are illustrative, not real venue information.

## Interface design

Use the supplied green logos, dark green sidebar, pale neutral background and clear tables. Use Arial/system fonts locally, without external services. Use labels on form fields, readable status words and visible keyboard focus. Adapt to smaller screens; wide tables may scroll horizontally.

The four current mockups show login, Admin dashboard, Staff dashboard and booking creation. The original wireframe PDF supplies resource forms, list/calendar bookings, clients, staff, reports and profile layouts for Week 2.

Two suggested design simplifications are explicit: “Remember me” becomes “Remember email” (no persistent login token), and “Forgot Password?” becomes contact-admin help (no email API). If password reset is required, implement an admin-assisted local reset in Week 3. Never store passwords in cookies.

## Backend approach for Week 3

Use plain PHP pages, small shared includes, mysqli prepared statements, GET for searching and POST for changes. Use PHP sessions with session ID regeneration after login, password_hash/password_verify, server-side role checks, CSRF tokens for changes, and htmlspecialchars for output. Validate every form again in PHP. No MVC framework, ORM, package manager, build pipeline or external API is required.

## Final acceptance checklist (not implemented in Week 1)

- [ ] Admin and Staff logins work; logged-out visitors cannot open protected pages.
- [ ] Record CRUD works and foreign-key history is preserved.
- [ ] Valid bookings save; conflicts, blocked times and invalid dates fail clearly.
- [ ] Editing, cancellation, back-to-back slots and simultaneous requests behave correctly.
- [ ] Offline payment changes save independently of booking status.
- [ ] Filters and date/court/client reports match stored records.
- [ ] Logout clears access; passwords are hashed; submitted SQL-like text is harmless.
- [ ] Forms, navigation and tables remain usable on mobile and desktop.

## Decisions to confirm before relevant milestones

- Instructor-approved calendar dates (the source dates are inconsistent).
- Staff-record CRUD permissions versus the Staff wireframe.
- Staff self-registration: proposed registration creates Staff only and requires Admin activation; confirm whether admin-created accounts alone meet the requirement.
- Real court names, hourly prices, opening hours and minimum booking duration.
- Whether archive/cancellation meets the instructor's delete criterion for historical bookings.
