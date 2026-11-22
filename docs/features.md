# Feature specification

Taken from the manuscript's Scope and Limitations (1.4), Definition of Terms (1.5), use case diagram (Figure 4), activity diagrams (Figures 5 to 8) and sequence diagrams (Figures 9 to 12).

The system covers ten functions: youth profiling, programs and activities, calendar scheduling, attendance, activity evaluation with automatic e-certificates, a digital repository, barangay inventory with stock status, budget monitoring, dashboards, and reports with notifications and analytics.

---

## Administrator

- Manage user accounts: create, update, deactivate.
- Assign roles, including designating the SK Chairman of each barangay.
- Manage barangay records.
- Manage system-wide settings.
- View audit logs.

Explicitly **not** the Administrator's job: verifying KK registrations, encoding youth profiles, encoding activity records.

## SK Federation

- Dashboard summarising all barangays.
- View youth profiles across barangays (read-only; barangays encode them).
- Monitor programs and activities encoded by each barangay.
- Monitor attendance and evaluation results across barangays.
- Manage the federation document repository.
- Post announcements, publish accomplishments, reply to feedback.
- **Budget:** enter the allocation for the period; review pending proposals with their attachments; approve (deducting the approved amount from the allocation) or return with remarks; monitor utilisation and remaining balance per barangay.
- Generate and export consolidated reports.

The federation holds **no inventory**.

## SK Barangay Official

Access is limited to the official's own barangay throughout.

- Dashboard for one barangay.
- **Verify KK registrations** for that barangay: confirm residency and age, then activate or reject. The account is inactive until approved.
- Maintain youth profiles for that barangay.
- Create and encode programs and activities; set date, time and venue on the calendar.
- **Generate the QR code** for each activity; display or print it.
- Build the evaluation form and its questions for an activity.
- Monitor attendance and view issued e-certificates.
- **Inventory:** maintain items with total quantity, quantity issued, quantity available and stock status (available / low stock / out of stock). Act on equipment requests; an approval deducts the issued quantity and updates status automatically; record returns.
- **Budget:** prepare a proposal, upload supporting documents to the repository, submit to the federation, revise and resubmit if returned. Once approved, record expenses per program and activity; the system computes utilised and balance. Mark each program budget complete or incomplete with a note on the remaining amount. Generate monthly and annual budget reports.
- Post announcements, upload documents, reply to feedback, maintain accomplishment reports, generate reports.

## KK Member

- **Register** and select a barangay; wait for that barangay's chairman to verify. No login until verified.
- View announcements, the calendar of activities, and published accomplishments.
- Maintain own profile.
- **Scan the activity QR code** while logged in, which records attendance, opens the evaluation form, and issues the e-certificate on submission.
- View own attendance history and eligibility for succeeding programs.
- **View and review own evaluation results** — the ratings and answers previously submitted.
- View own e-certificates.
- View barangay inventory availability and **request equipment**; follow the action taken on the request.
- Submit comments and suggestions, optionally **anonymously** (identity hidden from officials when so chosen).

KK members have **no budget access of any kind**.

---

## Cross-cutting

**Notifications** appear as a button in the top bar with an unread count, not as a sidebar item. The manuscript's ERD omits a notifications table; the schema here adds one, and Figure 13 should be updated to match.

**Dashboards** show summaries scoped to the viewer's role and barangay.

**Reports** are generated from stored records and can be exported or printed. No AI interpretation.

## Stated limitations — do not build past these

- One SK Federation only. No integration with DILG, NYC, COA or DBM systems.
- Budget approval records an amount against the allocation; it does not release funds, produce official financial statements, or replace the barangay treasurer's books of accounts.
- Inventory does not handle procurement, depreciation, write-off, or liquidation.
- The e-certificate certifies participation only, not performance.
- Registration is limited to residents aged fifteen to thirty.
- Web application only. It runs in a mobile browser but is not a native mobile app, and does not work offline.
