# Pikolbook — Week 1

A simple pickleball court booking system using HTML, CSS, PHP and MySQL in XAMPP. JavaScript will be used only for small interface interactions. No frameworks, APIs, online checkout or payment gateway.

## Current milestone: requirements and design

This package contains four static design mockups, the supplied wireframes, requirements, a proposed database design and team assignments. Navigation between mockups works. Forms are disabled and all displayed records are samples. No login, database connection or booking operation has been implemented.

## View in XAMPP (Windows)

1. Extract this ZIP.
2. Copy the inner `pikolbook` folder to `C:\xampp\htdocs\` (or your own XAMPP installation's `htdocs` folder).
3. Open XAMPP Control Panel and start **Apache**.
4. Open `http://localhost/pikolbook/` in your browser.
5. Select any of the four design previews.

Apache's default port is assumed. If your Apache uses port 8080, use `http://localhost:8080/pikolbook/`. You may also open `index.html` directly for this static milestone. MySQL is not needed yet.

## First-checking materials

- `docs/requirements.md`: scope, features, permissions, business rules and acceptance criteria.
- `docs/team-and-milestones.md`: responsibilities, planned file structure and task board.
- `docs/database-plan.md`: proposed tables and Week 3 database setup steps.
- `docs/supplied-wireframes.pdf`: original screen layouts supplied by the group.
- `mockups/`: four visual studies based on the wireframes.
- `assets/css/mockups.css`: separate styling for Angelique's design review.
- `assets/images/`: supplied logos, unchanged.

Read the proposed decisions in the requirements document as a team. Record actual contributions in the task board; assignments do not claim that someone has already completed the work.

## Stop after this milestone

Week 2 will build the page layouts and small JavaScript interactions. Week 3 will add PHP, the runnable SQL schema, database connection, authentication and CRUD. Week 4 covers presentation and defense. Proceed only after the user's explicit command for the next milestone.

No SQL needs to be run in Week 1. The eventual SQL will be supplied as a separate `database/pikolbook.sql` file with phpMyAdmin instructions, when Week 3 is authorized.

## Reference notes

The final-project PDF uses “gym” once and contains inconsistent dates for Weeks 2–4. This project follows the pickleball scope and milestone order, without guessing revised deadlines. The branding follows “Pikolbook” in the supplied logo and wireframes.

The four PHP lecture decks informed the plan: basic PHP files and variables; GET/POST forms; sessions and cookies; mysqli with a local MySQL database. The backend will add essential password hashing, prepared statements and validation while keeping ordinary, readable PHP files.

## Verification

Local HTML links and image/CSS paths were checked, and all mockup form controls are disabled. Browser rendering could not be verified in the build environment because a browser was unavailable. Review the mockups in Chrome or Edge through the XAMPP URL above. PHP and MySQL tests do not apply to this static design milestone.
