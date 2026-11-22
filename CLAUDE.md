# CLAUDE.md

Context for Claude Code working on this repository.

## What this is

An undergraduate capstone system: a **Web-Based Program Monitoring and Evaluation System for the Sangguniang Kabataan Federation**, built for the SK Federation of Nasipit, Agusan del Norte, Philippines.

The author is a BS Information Systems student at Saint Michael College of Caraga. The manuscript has already passed proposal defense; the system is now being built to match it. **The manuscript is the specification.** When code and manuscript disagree, that is a defect — either fix the code or tell the author which manuscript sentence needs changing. Do not silently diverge.

## Technology stack — fixed, do not substitute

Table 1 of the manuscript (Software Platforms, Development Environments, and Tools) commits the project to:

- **PHP** (procedural / light OOP, PDO for database access) — PHP 8.x
- **MySQL / MariaDB**
- **Apache**, served through **XAMPP**
- **HTML, CSS, JavaScript** on the front end
- Any modern browser; mobile browsers must work, because KK members scan QR codes on phones

There is **no framework**. Do not introduce Laravel, CodeIgniter, Composer dependencies, Node tooling, or a build step. A panelist will open this in XAMPP and expect `htdocs/sk-capstone/` to just run. Adding a framework also contradicts a table in a defended manuscript.

Client-side libraries loaded from a CDN are acceptable where they save real work (a QR scanning library, a charting library), but keep them few and pin exact versions.

## Roles

Four, and the boundaries between them matter more than the features:

| Role | Scope |
|---|---|
| Administrator | Accounts, roles, barangay records, system settings, audit logs. **Does not verify KK registrations.** |
| SK Federation | Consolidates across all barangays. Enters the budget allocation and approves or returns barangay budget proposals. **Holds no inventory of its own.** |
| SK Barangay Official | One barangay only. Verifies KK registrations for that barangay, encodes programs and activities, generates QR codes, keeps the barangay inventory, prepares budget proposals, records expenses. |
| KK Member | Registers, scans QR codes, answers evaluations, receives e-certificates, requests equipment, views own evaluation results. **Has no access to budget records whatsoever.** |

## Decisions that are easy to get wrong

These were adviser- or stakeholder-mandated and several reverse an earlier design. Re-deriving them from intuition will produce the wrong system.

1. **The SK Barangay Chairman verifies KK registrations, not the Administrator.** An account stays inactive until the chairman of the barangay the applicant selected approves it.
2. **Inventory belongs to the barangay, not the federation.** The federation has no inventory module. KK members may view availability and request items; only barangay officials change stock.
3. **There is no AI feature.** An earlier revision added an "AI that interprets the data" to report generation. It was removed. Do not reintroduce it anywhere — not in dashboards, not in reports.
4. **E-certificates generate automatically** on submission of an evaluation. There is no approval queue and no pending state.
5. **Budget runs as a proposal-and-approval cycle.** The federation enters an allocation for the period. A barangay prepares a proposal, uploads supporting documents to the repository, and submits it. The federation approves or returns it. An approved amount is deducted from the allocation. Returned proposals are revised and resubmitted.
6. **Each program budget carries a completion status** (complete / incomplete / ongoing) **and a free-text note explaining the remaining amount.** This was specifically requested by the SK Federation president.
7. **Budget monitoring produces monthly and annual reports** comparing approved budget against actual spending, with variance and utilisation rate.
8. **KK members cannot see budget.** This is stated in the scope and the limitations. Keep budget out of the KK navigation and enforce it server-side, not just by hiding links.

## QR attendance semantics

Get this right or the whole attendance chain is wrong:

- The QR code identifies the **activity**, not the person.
- The SK Barangay Official generates and displays or prints the code.
- A KK member must be **logged in** before scanning, or the attendance cannot be attributed.
- Scan validates the token and that the activity is open, then records attendance, then opens that activity's evaluation form, then issues the e-certificate once the evaluation is submitted.
- Youth who attend without a registered account are **encoded manually** by an officer.

## Repository layout

```
/                      login (index.php), logout, register
config/                db connection, app config
includes/              auth, RBAC guard, helpers, domain rules, header/sidebar/footer partials
includes/pages/        pages shared by federation + barangay; role dirs hold thin wrappers
assets/css, assets/js  design system and client scripts; assets/js/vendor = QR libraries (offline)
admin/                 Administrator pages
federation/            SK Federation pages
barangay/              SK Barangay Official pages
member/                KK Member pages
uploads/documents/     repository files
uploads/proposals/     budget proposal attachments
uploads/verification/  KK registration ID + selfie photos (deleted once the chairman acts)
database/              schema.sql, seed.sql
docs/                  specification extracted from the manuscript
```

Keep role pages in their own directory. Every page in a role directory must call the RBAC guard before emitting output.

## Working rules

- **Read `docs/` before building a feature.** `docs/features.md` holds the per-role behaviour the manuscript commits to; `docs/database.md` explains the schema's non-obvious constraints.
- **Prepared statements only.** Every query goes through PDO with bound parameters. This is a government youth system holding minors' personal data, and the manuscript cites RA 10173 (Data Privacy Act).
- **Authorise on the server.** A barangay official must not reach another barangay's records by editing a URL. Check ownership in the query, not only in the navigation.
- **Passwords** use `password_hash()` / `password_verify()`. Never store or log a plaintext password.
- **Uploads** must be validated by extension and MIME type, given generated filenames, and stored outside the web root where possible. If they must live under `uploads/`, ship a `.htaccess` that prevents PHP execution there.
- Peso amounts are `DECIMAL(12,2)`. Never use floats for money.
- Follow the existing design tokens in `docs/design-system.md`. The prototype screens were approved; do not restyle them.

## Implementation notes

- **Inventory quantities.** `quantity_on_hand` is the barangay's total holding; `quantity_released` is what is out on loan; available = on hand − released. Approval raises `quantity_released`, a return lowers it. The seed data was written this way.
- **KK ID** (`KK-2026-00001`) is derived from `youth_profiles.profile_id` and the year encoded. It is not stored. Members can sign in with it.
- **SK Chairman** is `barangays.chairman_id`, set by the Administrator. Only that user can approve or reject the barangay's registrations.
- **Money** is never added in PHP. Sums and comparisons run in SQL on DECIMAL columns (`CAST(? AS DECIMAL(12,2))`). `money_input()` validates form input as a string.
- **QR token** is random hex. `member/scan.php` also accepts the seed's older `ACT1-…` style tokens.
- **Evaluation scale** for activity evaluations is 1–5, matching the schema check and the prototype. The 4-point scale in Chapter 2 is for the system's own ISO 25010/TAM evaluation, not for activity forms.

## Status

Stages 1–8 of `docs/build-plan.md` are built and tested against the seed data. Stage 9 hardening is partly done (see the checklist there).
