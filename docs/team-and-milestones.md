# Team plan and milestones

Assignments below describe planned responsibility, not fabricated completed contributions. Each member should review, adapt and explain their own work.

| Member | Responsibility | Main planned files |
|---|---|---|
| Angelique So | Design, colors, spacing, responsiveness and visual review | assets/css/style.css; assets/css/forms.css; assets/images/ |
| Aldrei Paz | Database, authentication, PHP validation and CRUD handlers | database/pikolbook.sql; config/database.php; includes/auth.php; actions/ |
| Seth Somoza | HTML pages, small JS interactions, shared layout, backend integration and overall coding | index.php; dashboard.php; resources.php; bookings.php; clients.php; staff.php; reports.php; profile.php; includes/header.php; includes/sidebar.php; includes/footer.php; assets/js/main.js |

## Milestones

| Week | Scope | Checking materials | Progress gate |
|---|---|---|---|
| 1 | Requirement analysis and design | Requirements, supplied wireframes, visual mockups, database plan, assignments | Current package; team review remains |
| 2 | Frontend development | Complete HTML/CSS layouts, responsive pages, small JS interactions; sample data only | Start only on user's command |
| 3 | Backend, integration and testing | XAMPP/MySQL setup, SQL, PHP login, roles, CRUD, booking conflict prevention and reports | Start only on user's command |
| 4 | Presentation and defense | Demo script, screenshots, test results, role explanations and final submission | Start only on user's command |

Week 1 is September 21–26 in the specification. Week 2 says “September 28 to May 3,” Week 3 says “September 5 to 9,” and Week 4 says “September 12 to 16.” Keep the sequence and confirm actual deadlines with the instructor.

## Simple project-management setup

Use this Markdown board as the initial project tool. Edit the status column as work moves from To do → In progress → Review → Done. Store the shared project folder in the team's chosen shared drive or repository. Do not overwrite another member's files without coordinating.

| Task | Assignee | Week | Status |
|---|---|---|---|
| Review branding and four visual mockups | Angelique | 1 | To do |
| Review tables, relationships and required fields | Aldrei | 1 | To do |
| Reconcile wireframes, scope and planned files | Seth | 1 | To do |
| Confirm requirements conflicts with instructor | All | 1 | To do |
| Agree final page styling and responsive behavior | Angelique | 2 | To do |
| Build all HTML pages and shared navigation | Seth | 2 | To do |
| Review frontend fields for backend readiness | Aldrei | 2 | To do |
| Add form toggles, simple filters and calendar view | Seth | 2 | To do |
| Provide SQL, database connection and authentication | Aldrei | 3 | To do |
| Add CRUD handlers, role checks and booking rules | Aldrei + Seth | 3 | To do |
| Integrate pages with database and reports | Seth | 3 | To do |
| Check styling, errors and responsiveness | Angelique | 3 | To do |
| Run functional tests and prepare demonstration | All | 3–4 | To do |

## Planned separation of files

The paths above are the future application layout. They are intentionally not filled with working backend code in Week 1. Keep database credentials in one configuration file. Share header/sidebar/footer with PHP includes after integration; do not duplicate backend logic across screens. Use readable filenames such as actions/save_booking.php, actions/cancel_booking.php, actions/save_client.php and actions/save_resource.php.

Seth integrates reviewed contributions. Angelique reviews the resulting appearance. Aldrei reviews database access and validation. Keep a brief actual-contribution log for the peer rating, including date, member, files changed and work completed.

## Week 1 review checklist

- [ ] Open all four mockups and compare with supplied wireframes.
- [ ] Agree that clients are records and Admin/Staff are the two login roles.
- [ ] Confirm offline payment recording only.
- [ ] Review pending decisions in requirements.md.
- [ ] Assign each task and record actual contributions.
- [ ] Request Week 2 only when the team is ready.
