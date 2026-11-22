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

Default local credentials assume MySQL user `root` with an empty password, the XAMPP default. Change them in `config/database.php` if yours differ.

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

Database complete and validated. Application code not yet started. See `docs/build-plan.md`.
