# Database notes

`database/schema.sql` then `database/seed.sql`, imported in that order through phpMyAdmin or the `mysql` client. Schema drops and recreates `sk_monitoring`, so never run it against a database holding real data.

26 tables, 50 foreign keys, 17 check constraints. Validated: every foreign key points at a column that exists, and every seed row matches its column count.

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

## Known seed gap

Only six barangays are seeded, taken from the prototype screens. Replace with the complete official list of Nasipit barangays before the demo.
