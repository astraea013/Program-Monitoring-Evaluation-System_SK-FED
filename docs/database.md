# Database notes

`database/schema.sql` then `database/seed.sql`, imported in that order through phpMyAdmin or the `mysql` client. Schema drops and recreates `sk_monitoring`, so never run it against a database holding real data.

27 tables (the 26 of the ERD plus `settings`). Validated: every foreign key points at a column that exists, every seed row matches its column count, and every stored derived value agrees with its source rows.

## Added while building from the prototype

These columns and the `settings` table are not in Figure 13 and must be added to the ERD.

| Table | Column(s) | Why |
|---|---|---|
| `barangays` | `chairman_id` → `users` | The Administrator designates one SK Chairman per barangay; only that user verifies KK registrations |
| `users` | `rejection_reason` | Shown to the applicant when a registration is rejected |
| `youth_profiles` | `youth_org`, `school_status`, `is_pwd`, `attended_assembly` | Youth Profile section of the registration form and masterlist |
| `youth_profiles` | `id_document_type`, `id_document_path`, `selfie_path`, `consent_date` | Identity verification at registration. Both paths are set to NULL and the files deleted once the chairman acts |
| `evaluation_questions` | `section` | Groups questions into Program Quality, Implementer Quality, Perceived Benefits and Suggestions |
| `equipment_requests` | `date_needed` | "Needed by" date on the request form |
| `documents` | `is_public` | Published to the KK member portal |
| `settings` | key/value | System name, municipality, audit retention, maintenance mode, eligibility minimum |

## Constraints that encode business rules

Several rules from the manuscript are enforced in the schema rather than only in PHP. Do not work around them; if one blocks you, the application logic is probably wrong.

| Constraint | Rule it enforces |
|---|---|
| `activities.qr_token` UNIQUE | One QR code identifies exactly one activity |
| `attendance` UNIQUE `(activity_id, profile_id)` | A member cannot be recorded twice for one activity |
| `evaluation_responses` UNIQUE `(form_id, profile_id)` | One evaluation submission per member per activity |
| `certificates.response_id` UNIQUE | Exactly one e-certificate per evaluation |
| `evaluation_forms.activity_id` UNIQUE | One evaluation form per activity |
| `chk_prop_status` | Proposal status limited to draft / pending / approved / returned |
| `chk_pb_status` | Program budget limited to complete / incomplete / ongoing |
| `chk_item_status` | Stock status limited to available / low stock / out of stock |
| `chk_users_status` | Account status limited to pending / verified / rejected |
| `chk_answer_rating` | Ratings between 1 and 5 |

## Table map

**Accounts and people** — `roles`, `barangays`, `users`, `youth_profiles`
**Programs** — `programs`, `activities`, `accomplishments`
**Attendance and evaluation** — `attendance`, `evaluation_forms`, `evaluation_questions`, `evaluation_responses`, `evaluation_answers`, `certificates`
**Inventory** — `inventory_items`, `equipment_requests`, `inventory_transactions`
**Budget** — `budget_allocations`, `budget_proposals`, `program_budgets`, `budget_expenses`
**Content and system** — `documents`, `announcements`, `feedback`, `notifications`, `reports`, `audit_logs`

## Things worth knowing before writing queries

**`users` vs `youth_profiles` are separate.** A `youth_profile` can exist without a `user` account, because barangay officials encode youth who have not registered. Attendance, evaluations, certificates, feedback and equipment requests all reference `profile_id`, never `user_id`. When a logged-in KK member acts, resolve their `profile_id` from `youth_profiles.user_id` first.

**`users.verified_by` is self-referential**, pointing at the SK Barangay Chairman's `user_id`.

**Barangay scoping.** `users.barangay_id` is NULL for Administrator and SK Federation, and set for barangay officials and KK members. Every barangay-scoped query must filter on it. Do not rely on the session value alone for writes — confirm the record belongs to that barangay in the WHERE clause.

**The budget chain** runs `budget_allocations` → `budget_proposals` → `program_budgets` → `budget_expenses`. Approving a proposal must, in one transaction: set `prop_status` to approved, set `approved_amount`, `reviewed_by` and `date_reviewed`, and subtract the approved amount from `budget_allocations.remaining_amount`.

**`budget_proposals.document_id`** links a proposal to its uploaded attachment in `documents`. This is the answer to "how do you know which file belongs to which proposal", and a panelist may well ask.

**Derived values are stored, not computed on the fly**: `inventory_items.quantity_released`, `inventory_items.stock_status`, `program_budgets.utilised_amount`, `budget_allocations.remaining_amount`. Every write path that affects one must update it in the same transaction, or the screens will disagree with each other.

**Inventory:** `quantity_on_hand` is the total held, `quantity_released` is what is out on loan, and available is the difference. Issuing raises `quantity_released` only.

**KK ID** is not stored. It is `KK-<year encoded>-<profile_id padded to 5>`, e.g. `KK-2026-00001`.

**`notifications` is not in the manuscript's ERD (Figure 13).** It is needed by the scope, the use case diagram and every prototype screen. Figure 13 must be updated to match before final submission.

## Test accounts

| Username | Password | Role |
|---|---|---|
| `admin` | `Admin@123` | Administrator |
| `fed.pres` | `Fed@123` | SK Federation |
| `brgy1.chair` | `Brgy@123` | SK Barangay Official, Barangay 1 |
| `camagong.chair` | `Brgy@123` | SK Barangay Official, Camagong |
| `maria.santos` | `Member@123` | KK Member, verified |
| `juan.cruz` | `Member@123` | KK Member, pending verification |

`juan.cruz` exists so the verification flow can be demonstrated. Change all of these before the defense.

## Seed notes

- 19 barangays, from the prototype's Barangays & Settings screen. Confirm spellings against the official municipal list.
- `brgy1.chair` and `camagong.chair` are the designated chairmen of their barangays.
- Maria Santos has attended two activities and holds one e-certificate, so the member screens have data.
- The budget amounts were adjusted so they reconcile. The prototype figures (Figures 25 and 37) did not: their program lines exceeded the approved amount, and the remaining allocation did not equal total minus approved.
