# Web-Based Program Monitoring and Evaluation System for the Sangguniang Kabataan Federation

Undergraduate capstone, BS Information Systems, Saint Michael College of Caraga. Built for the SK Federation of Nasipit, Agusan del Norte.

PHP + MySQL on Apache, served through XAMPP. No framework.

## Setup

1. Install XAMPP. Start **Apache** and **MySQL**.
2. Copy this folder into `xampp/htdocs/` so it sits at `xampp/htdocs/sk-capstone/`.
3. Open phpMyAdmin at `http://localhost/phpmyadmin`.
4. Import `database/schema.sql`. It drops and recreates the `sk_monitoring` database, so do not run it against anything you need to keep.
5. Import `database/seed.sql`.
6. Open `http://localhost/sk-capstone/`.

Default local credentials assume MySQL user `root` with an empty password, the XAMPP default. If yours differ, create `config/local.php` (git-ignored) and override them there, for example `define('DB_PASS', 'secret');`. See the example at the bottom of `config/config.php`.

Sign in with a username, an email address, or a KK ID (for example `KK-2026-00001` for Maria Santos).

### Testing the QR code on a phone

The QR code encodes the address the official's browser used. If the official opens the system as `http://localhost/...`, a phone cannot reach that address. Open it with the laptop's LAN address instead, for example `http://192.168.1.10/sk-capstone/`, with the phone on the same Wi-Fi. Phones only allow the in-page camera scanner on HTTPS or localhost. Over plain HTTP, members scan the poster with their phone's own camera app, which opens the link directly.

### Offline use

The QR generator and the camera scanner are stored in `assets/js/vendor/`, so attendance works without internet. Google Fonts falls back to system fonts when offline.

## Test accounts

| Username | Password | Role |
|---|---|---|
| `admin` | `Admin@123` | Administrator |
| `fed.pres` | `Fed@123` | SK Federation |
| `brgy1.chair` | `Brgy@123` | SK Barangay Official, Barangay 1 |
| `camagong.chair` | `Brgy@123` | SK Barangay Official, Camagong |
| `maria.santos` | `Member@123` | KK Member, verified |
| `juan.cruz` | `Member@123` | KK Member, pending verification |

These are development credentials committed to the repository on purpose, so the system can be demonstrated. Replace every one of them before the defense, and before the system is exposed to any network beyond localhost.

## Documentation

| File | Contents |
|---|---|
| `CLAUDE.md` | Context for Claude Code: stack, roles, the decisions that are easy to get wrong |
| `docs/features.md` | What each role can do, from the manuscript |
| `docs/database.md` | Schema notes, the rules the constraints enforce, query gotchas |
| `docs/design-system.md` | Colours, type, layout, components. The UI is approved — build to this |
| `docs/build-plan.md` | Build order, stage by stage |
| `docs/manuscript-state.md` | Revision history, corrected errors, open issues |
| `docs/prototype-reference.html` | The approved prototype screens, openable in a browser |
| `docs/figma-make-prompts.md` | Prompts used to generate the Figma screens |
| `docs/manuscript/` | Manuscript extracts and the latest `.docx` |

## Status

Stages 1 to 8 of `docs/build-plan.md` are built: all four roles, from the Figma Make prototype, in plain PHP with PDO, MySQL, HTML, CSS and JavaScript. Stage 9 (hardening before defense) is partly done; see the checklist there.

## Code layout

| Path | Contents |
|---|---|
| `index.php`, `register.php`, `logout.php` | Sign in (username, email or KK ID), KK registration with ID and selfie, sign out |
| `account.php`, `notifications.php`, `file.php` | Profile and password, notifications for every role, access-checked document download |
| `admin/` `federation/` `barangay/` `member/` | Role pages. Each calls `require_role()` before any output |
| `includes/pages/` | Pages shared by the federation and barangay roles: youth, programs, calendar, participation, documents, member content, reports, budget report. Each role has its own thin wrapper that does the role check |
| `includes/auth.php` | Login, session, `require_role()`, `require_barangay_access()` |
| `includes/domain.php` | Business rules: uploads, stock status, utilisation, evaluation forms, summaries |
| `includes/helpers.php` | Escaping, money, CSRF, flash messages, audit log, notifications, CSV export |
| `assets/` | `css/style.css` (design tokens), `js/app.js`, `js/vendor/` (QR libraries), logo |
