# Build plan

Order matters: each stage depends on the one before it. Finish and test a stage before starting the next.

## Done

- **Database.** `schema.sql` and `seed.sql`, validated. See `docs/database.md`.
- **Stages 1–8.** Built from the Figma Make prototype and tested against the seed accounts. The Stage 1 test passes: each seed account lands on its own dashboard, `juan.cruz` is refused while pending, and another role's URL returns 403.

## Stage 1 — Foundation

- `config/database.php` — PDO connection, `ERRMODE_EXCEPTION`, `utf8mb4`, emulated prepares off.
- `config/config.php` — base URL, upload paths, app name, session settings.
- `includes/auth.php` — `login()`, `logout()`, `current_user()`, `require_login()`, `require_role()`, `require_barangay_access()`.
- `includes/helpers.php` — escaping, peso formatting, date formatting, CSRF token issue and check, flash messages.
- `includes/header.php`, `includes/sidebar.php`, `includes/footer.php` — role-aware shell with the grouped navigation and the notifications button.
- `assets/css/style.css` — the design tokens from `docs/design-system.md`.
- `index.php` login, `logout.php`, `register.php`.

**Test before moving on:** each of the six seed accounts logs in and lands on the right dashboard; `juan.cruz` is refused because the account is pending; typing another role's URL is refused.

## Stage 2 — Administrator

User accounts, role assignment including designating barangay chairmen, barangay records, system settings, audit log viewer. Audit logging should be a helper called from every write path, so wire it here.

## Stage 3 — Members and profiles

KK registration with barangay selection. Barangay-side verification queue: approve or reject, writing `verification_status`, `verified_by`, `date_verified`. Youth profile CRUD, scoped to the official's barangay. Federation read-only view across barangays.

## Stage 4 — Programs, activities, calendar

Programs and activities CRUD with `program_code` generation. Calendar view. QR token generation on activity creation and a printable QR page. Federation monitoring views.

## Stage 5 — Attendance, evaluation, e-certificate

The chain, in this order, because each step depends on the last:

1. Scan endpoint: validate `qr_token`, confirm `is_open`, confirm the member is logged in and verified, resolve `profile_id`, insert attendance.
2. Redirect into that activity's evaluation form.
3. Save responses and answers.
4. Generate the certificate automatically in the same transaction, with a unique `certificate_no`.

Also: manual attendance encoding for walk-ins, the member's own attendance history, the member's own evaluation results, and the barangay's view of issued certificates.

## Stage 6 — Inventory and equipment requests

Item CRUD for the barangay. KK request form against available quantity. Barangay approve/decline. Approval must, in one transaction, insert the transaction row, increase `quantity_released`, and recompute `stock_status` against `reorder_level`. Returns reverse it.

`quantity_on_hand` is the total the barangay holds and does **not** change on issue. Available = `quantity_on_hand − quantity_released`. An earlier version of this line also decreased `quantity_on_hand`, which counts each issue twice and contradicts the seed data.

## Stage 7 — Budget

Federation enters the allocation. Barangay prepares a proposal, uploads attachments linked via `documents.document_id`, submits. Federation approves (deducting from `remaining_amount` in one transaction) or returns with remarks. Barangay records expenses, which recompute `program_budgets.utilised_amount`. Completion status and balance note per program budget. Monthly and annual reports with variance and utilisation rate.

Enforce server-side that no KK member can reach any budget route.

## Stage 8 — Content, notifications, reports, dashboards

Announcements, document repository, feedback with the anonymous option, notification creation on the events that warrant it (registration verified, request acted on, proposal approved or returned), report generation and export, and the role dashboards.

## Stage 9 — Hardening before defense

- [x] Confirm every page calls the RBAC guard. (Every file in `admin/`, `federation/`, `barangay/`, `member/` calls `require_role()`.)
- [x] Attempt cross-barangay access by URL editing and confirm it fails. (Tested: profile edit, inventory approval, expense recording, verification photos.)
- [x] Confirm no plaintext passwords anywhere, including logs.
- [x] Confirm uploads reject non-permitted types and cannot execute. (Extension + MIME check, random names, `uploads/.htaccess` denies all direct access.)
- [ ] Replace all seed passwords.
- [~] Replace the barangay list with the official one. (Now the 19 barangays from the prototype; confirm spellings against the municipal list.)
- [ ] Check the system against `docs/features.md` line by line, and against the manuscript's scope.
- [ ] Test on XAMPP on Windows, and scan a printed QR code with a real phone over the LAN.

## Evaluation instrument

Chapter 2 commits to a **four-point** scale, 1.0 to 4.0, with ISO/IEC 25010 covering functional suitability, performance efficiency, usability and reliability, plus the Technology Acceptance Model for perceived usefulness and perceived ease of use. If evaluation forms are built into the system, match that scale.
