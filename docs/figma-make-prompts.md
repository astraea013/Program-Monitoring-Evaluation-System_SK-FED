# FIGMA MAKE PROMPTS — REVISED PROTOTYPE SCREENS

Four screens. Paste **Prompt 0** first in a new Figma Make session so the design system is set, then paste the screen prompts one at a time in the same session.

The KK Member evaluation screen is excluded as requested.

---

## PROMPT 0 — DESIGN SYSTEM (paste this first)

```
I am building a web dashboard for a Sangguniang Kabataan (Philippine youth council)
monitoring and evaluation system. Set up this design system and reuse it for every
screen I ask for after this. Do not generate a screen yet — just hold these rules.

FONTS
- UI text: Archivo. Fallback Inter.
- IDs, numbers, timestamps and small uppercase labels: IBM Plex Mono.

COLOURS
- Deep navy #10214B — sidebar background, headings, body text
- Blue #1B5AC4 — primary buttons, active tabs, progress fills, record IDs
- Amber #F5B60D — logo, active nav item text and its left bar, warning fills
- Off-white #F7F8FB — page background
- White #FFFFFF — cards, tables, panels
- Gray #5A6478 — secondary text and small labels
- Green #1E7D4F — success
- Red #C8322B — destructive
- Border #E3E7EF — all card and table borders

LAYOUT (every screen, 1200px wide)
- Fixed left sidebar, 200px, background #10214B.
  Top of sidebar: amber sun icon, then "SK Federation" in white bold 13px, and
  "Monitoring & Evaluation System" under it in 9px #9FB0D0.
  Nav items 12.5px #CDD8EC with a small glyph on the left, 9px vertical padding.
  The active nav item has background #19305F, amber text, bold, and a 3px amber
  left border.
- Main area background #F7F8FB.
- A white top bar with a bottom border: on the left a breadcrumb in IBM Plex Mono,
  uppercase, 10.5px, letter-spacing 0.14em, colour #5A6478; on the right a plain
  "Sign out" link.
- Page body padding 20px 24px.

COMPONENTS
- Page header: title 23px bold navy; under it a subtitle in IBM Plex Mono 11.5px
  #5A6478. A status pill or buttons sit on the right of the header.
- Pill: radius 999px, uppercase, IBM Plex Mono 9.5px, letter-spacing 0.1em,
  padding 5px 10px. Variants — amber #FDF3D6 on #8A6A05, blue #E4EDFC on #164AA3,
  green #DFF2E8 on #14613C, red #FBE4E3 on #9B2621, gray #ECEFF5 on #4A5468.
- Info banner: background #EAF1FD, radius 5px, padding 11px 14px, a 6px blue dot
  on the left, text 12px #1C3566.
- Stat card: white, 1px #E3E7EF border, radius 6px, padding 13px 15px. Label 11px
  #5A6478 on top, value 24px bold under it. Four of these in a row with 14px gaps.
- Tabs: a row with a 1px bottom border, items 12.5px #5A6478, the active one blue
  #1B5AC4, semibold, with a 2px blue bottom border.
- List row card: white, 1px #E3E7EF border, radius 5px, and a 3px coloured left
  border (amber = pending, green = approved, red = returned, blue = neutral).
  Inside: a record ID in IBM Plex Mono 10.5px blue with a status pill beside it,
  a 14.5px bold title, then 11.5px #5A6478 detail lines. On the far right a small
  timestamp above a button.
- Table: white, 1px border, radius 6px. Header row background #FAFBFD, header text
  IBM Plex Mono 9.5px uppercase letter-spacing 0.09em #5A6478. Cells 12.5px with
  #F0F3F8 row separators. Money columns right-aligned in IBM Plex Mono.
- Progress bar: 6px tall, track #E8ECF3, radius 999px, fill blue, amber, or green.
- Buttons: primary = blue fill, white text, 12px semibold, radius 5px, padding
  8px 15px. Ghost = white fill, #BFD2F2 border, blue text. Destructive = white
  fill, #EFC3C1 border, red text.
- Right detail panel: 330px wide, white card, 1px border, radius 6px. Title 14px
  bold, a mono subtitle under it, then key-value rows (label left 12px, value right
  in IBM Plex Mono). Section labels inside are IBM Plex Mono 9.5px uppercase
  letter-spacing 0.1em #5A6478. A note box inside is background #FAFBFD with a
  1px border, radius 5px, text 11.5px.
- Main content and right panel sit in a two-column grid, 1fr and 330px, 18px gap.

Use Philippine peso (₱) for all money. Keep it clean and flat — no gradients,
no drop shadows on cards, no rounded "app" look. This is a government youth
council system, so it should read as orderly and plain.
```

---

## PROMPT 1 — SK BARANGAY OFFICIAL, BUDGET

```
Build the "Budget" screen for the SK Barangay Official role.

Sidebar nav, with Budget active:
Dashboard, Youth Profiling, Programs, Calendar, QR Attendance, Participation,
Documents, KK Member Content, Inventory, Budget, Notifications (with a small amber
dot on the right), Reports, Verify Members.

Breadcrumb: BUDGET

Header: title "Budget", subtitle "Barangay 1 (Apagan) · CY 2026 · Proposal and
utilisation", and an amber pill on the right reading "1 PROPOSAL PENDING".

Info banner: "Budget records are visible to this barangay's SK officials and to the
SK Federation only. Katipunan ng Kabataan members have no access to them."

Four stat cards: Proposed ₱250,000 · Approved by Federation ₱235,000 ·
Utilised ₱181,500 · Balance ₱53,500.

Tabs: Proposals (active), Programme Budgets, Expenses.

Three list rows, each showing the proposal lifecycle:

1. Blue left border. ID "BPR-2026-002" with an amber PENDING pill.
   Title "Supplemental Budget Proposal — 2nd Semester 2026".
   Details: "Proposed ₱95,000 · Covers 3 programs · Submitted to SK Federation" and
   "Attachments: Proposal letter, Itemised breakdown, Council resolution".
   Right: "Submitted Oct 21, 2026" and a ghost button "View submission".

2. Green left border. ID "BPR-2026-001" with a green APPROVED pill.
   Title "Annual Budget Proposal — CY 2026".
   Details: "Proposed ₱155,000 · Approved ₱140,000 · Deducted from federation
   allocation" and "Remarks: Approved with reduction on representation expense."
   Right: "Approved Jan 18, 2026" and a ghost button "View submission".

3. Red left border. ID "BPR-2025-004" with a red RETURNED pill.
   Title "Supplemental Budget Proposal — Sports Fest".
   Details: "Proposed ₱60,000 · Returned for revision" and "Remarks: Attach the
   itemised breakdown and the council resolution."
   Right: "Returned Nov 12, 2025" and a primary button "Revise and resubmit".

Below this a two-column section.

Left column, small label "PROGRAMME BUDGETS — COMPLETION STATUS", then a table with
columns PROGRAM, ALLOCATED, UTILISED, BALANCE, STATUS. Each program cell shows the
name in semibold with its code under it in mono. The STATUS cell holds a pill and a
small grey note under it:
- Health & Wellness Campaign / PRG-001 — 45,000 / 38,200 / 6,800 — amber INCOMPLETE
  — "Balance for medical supplies not yet delivered."
- Digital Literacy Workshop / PRG-002 — 30,000 / 30,000 / 0 — green COMPLETE —
  "Fully utilised and liquidated."
- Livelihood Training — Ube Processing / PRG-003 — 60,000 / 55,300 / 4,700 —
  amber INCOMPLETE — "Awaiting receipt from supplier."
- Environmental Clean-up Drive / PRG-004 — 15,000 / 15,000 / 0 — green COMPLETE —
  "No remaining balance."
- Sports Fest 2026 / PRG-005 — 90,000 / 43,000 / 47,000 — gray ONGOING —
  "Activity still in progress."

Right panel: title "Health & Wellness Campaign", subtitle "PRG-001 · Barangay 1
(Apagan)". Key-value rows Allocated ₱45,000, Utilised ₱38,200, Balance ₱6,800, then
an amber progress bar at 85% with "85% utilised" in mono under it. Then a section
"COMPLETION STATUS" with an amber INCOMPLETE pill. Then "NOTE ON REMAINING AMOUNT"
with a note box reading "₱6,800 is held for medical supplies ordered on Sep 30 but
not yet delivered. To be utilised once the supplier releases the items." Then
"SUPPORTING DOCUMENTS" listing two file chips, "Proposal letter.pdf 214 KB" and
"Itemised breakdown.xlsx 38 KB". A full-width primary button "Record expense" at
the bottom.
```

---

## PROMPT 2 — SK FEDERATION, BUDGET ALLOCATION AND APPROVAL

```
Build the "Budget Allocation & Approval" screen for the SK Federation role.

Sidebar nav, with Budget active:
Dashboard, Youth Profiling, Programs, Calendar, Participation, Documents,
KK Member Content, Budget, Notifications (amber dot), Reports.
Note there is no Inventory and no Verify Members for this role.

Breadcrumb: BUDGET

Header: title "Budget Allocation & Approval", subtitle "Pederasyon ng mga
Sangguniang Kabataan · CY 2026", amber pill on the right "3 PROPOSALS FOR REVIEW".

Info banner: "An approved proposal is deducted from the federation allocation.
Approval records the amount against the allocation and does not itself release funds."

Four stat cards: Allocation for the Period ₱1,500,000 · Approved to Barangays
₱1,085,000 · Remaining Allocation ₱415,000 · Barangays Reporting 11 of 14, where
"of 14" is smaller and grey. Directly under the stat row put a blue progress bar
at 72%.

Tabs: Pending Proposals (active), Acted Upon, Allocation Setup, Utilisation by Barangay.

Three list rows with amber left borders, each with an amber PENDING pill, and on the
right a timestamp with a primary "Approve" button and a destructive "Return" button
side by side:

1. BPR-2026-002 — "Barangay 1 (Apagan) — Supplemental, 2nd Semester" —
   "Proposed ₱95,000 · Covers 3 programs · Submitted by M. Santos, SK Chairperson" /
   "Attachments: Proposal letter.pdf · Itemised breakdown.xlsx · Council resolution.pdf"
   — Submitted Oct 21, 2026
2. BPR-2026-009 — "Camagong — Annual Budget Proposal" — "Proposed ₱148,000 ·
   Covers 5 programs · Submitted by J. Deluta, SK Chairperson" / "Attachments:
   Proposal letter.pdf · Itemised breakdown.xlsx" — Submitted Oct 19, 2026
3. BPR-2026-011 — "Talisay — Supplemental, Livelihood Training" — "Proposed ₱72,000 ·
   Covers 1 program · Submitted by G. Fabre, SK Chairperson" / "Attachments:
   Proposal letter.pdf" — Submitted Oct 17, 2026

Below, a two-column section.

Left column, label "UTILISATION BY BARANGAY", then a table with columns BARANGAY,
APPROVED, UTILISED, BALANCE, PROGRESS. The PROGRESS cell holds a thin progress bar
with the percentage in mono under it:
- Barangay 1 (Apagan) — 140,000 / 118,500 / 21,500 — blue 85%
- Camagong — 120,000 / 74,000 / 46,000 — blue 62%
- Talisay — 135,000 / 130,200 / 4,800 — green 96%
- Culit — 98,000 / 31,000 / 67,000 — amber 32%
- Triangulo — 155,000 / 102,400 / 52,600 — blue 66%

Right panel: title "BPR-2026-002", subtitle "Barangay 1 (Apagan) · Supplemental".
Key-value rows: Proposed ₱95,000, Remaining allocation ₱415,000, If approved
₱320,000. Then "ATTACHED DOCUMENTS" with three file chips — Proposal letter.pdf
214 KB, Itemised breakdown.xlsx 38 KB, Council resolution.pdf 96 KB. Then "REMARKS"
with an empty note box whose placeholder reads "Required when a proposal is
returned." Then a full-width primary button "Approve proposal" and under it a
full-width destructive button "Return for revision".

The "If approved ₱320,000" row matters — it shows the deduction before the officer
commits to it.
```

---

## PROMPT 3 — SK BARANGAY OFFICIAL, PROGRAMS AND ACTIVITIES

```
Build the "Programs & Activities" screen for the SK Barangay Official role.

Sidebar nav with Programs active, same items as the Budget screen.

Breadcrumb: PROGRAMS

Header: title "Programs & Activities", subtitle "CBYDP 2026-2028 · ABYIP 2026
alignment", and a primary button "+ New Program" on the right.

Two-column layout.

Left column, a table with columns PROGRAM NAME, CENTRE, SCHEDULE, BUDGET, BUDGET
STATUS. Program names are semibold with their code in mono underneath. BUDGET is
right-aligned mono. BUDGET STATUS holds a pill with a small grey line under it:
- Health & Wellness Campaign / PRG-001 — Health — Jul 5, 2026 — 45,000 —
  amber INCOMPLETE, "₱6,800 left"
- Digital Literacy Workshop / PRG-002 — Education — Jul 12, 2026 — 30,000 —
  green COMPLETE, "Fully utilised"
- Livelihood Training — Ube Processing / PRG-003 — Economic Empowerment —
  Jun 28, 2026 — 60,000 — amber INCOMPLETE, "₱4,700 left"
- Environmental Clean-up Drive / PRG-004 — Environment — Jul 8, 2026 — 15,000 —
  green COMPLETE, "No balance"
- Sports Fest 2026 / PRG-005 — Sports & Recreation — Jul 20-22, 2026 — 90,000 —
  gray ONGOING, "₱47,000 left"

Right panel, top to bottom:
- A green COMPLETED pill
- Title "Health & Wellness Campaign", subtitle "PRG-001 · Barangay 1 (Apagan)"
- Label "PLAN ALIGNMENT" then three pills in a row: gray "2026-2028",
  blue "ABYIP 2026", gray "HEALTH"
- Label "BUDGET" then key-value rows Allocated ₱45,000, Utilised ₱38,200,
  Balance ₱6,800, then an amber progress bar at 85% with "85% utilised" under it
- Label "BUDGET COMPLETION" then an amber INCOMPLETE pill
- Label "NOTE ON REMAINING AMOUNT" then a note box: "₱6,800 held for medical
  supplies ordered Sep 30, not yet delivered."
- Label "TARGET DEMOGRAPHIC" then two small stat boxes side by side, 60 Target and
  54 Reached, with a green progress bar at 90% and "90% of target reached" under it
- A full-width primary button "View Full Report"
```

---

## PROMPT 4 — SK BARANGAY OFFICIAL, BUDGET REPORT

```
Build the "Budget Report" screen for the SK Barangay Official role.

Sidebar nav with Budget active, same items as the Budget screen.

Breadcrumb: BUDGET › REPORTS

Header: title "Budget Report", subtitle "Barangay 1 (Apagan) · Statement of budget
against actual spending". On the right, a ghost button "Export PDF" and a primary
button "Generate report".

Info banner: "The annual report compares the approved budget against the actual
spending for the whole year. The Sangguniang Kabataan is required to submit and
publish this comparison."

Tabs: Monthly, Annual (active), Per Program.

Under the tabs a thin filter row: the mono label "PERIOD", a blue pill "CY 2026",
a gray pill "JAN - DEC", then the mono label "GENERATED" and the text
"Oct 28, 2026 · 9:14 PM" in small grey mono.

Four stat cards: Approved Budget ₱235,000 · Actual Spending ₱181,500 ·
Unutilised ₱53,500 · Utilisation Rate 77.2% where the percent sign is smaller.

Two-column layout.

Left column, label "MONTHLY BREAKDOWN — CY 2026", then a table with columns MONTH,
BUDGET, ACTUAL, VARIANCE, UTILISATION. Money columns right-aligned in mono. The
UTILISATION cell holds a thin progress bar with the percentage in mono under it:
- January 18,000 / 12,400 / 5,600 — amber 69%
- February 18,000 / 17,100 / 900 — green 95%
- March 20,000 / 19,800 / 200 — green 99%
- April 15,000 / 8,250 / 6,750 — amber 55%
- May 22,000 / 21,300 / 700 — green 97%
- June 30,000 / 27,650 / 2,350 — blue 92%
- July 52,000 / 48,100 / 3,900 — blue 93%
- August 14,000 / 11,200 / 2,800 — amber 80%
- September 16,000 / 9,700 / 6,300 — amber 61%
- October 30,000 / 6,000 / 24,000 — amber 20%
Then a final TOTAL row with a #FAFBFD background, "TOTAL" in semibold with
"Jan - Oct 2026" in mono under it, bold figures 235,000 / 181,500 / 53,500, and a
blue bar at 77.2%.

Right panel: title "Report Settings", subtitle "Barangay 1 (Apagan)".
- Label "REPORT TYPE" then two pills, gray "MONTHLY" and blue "ANNUAL"
- Label "COVERAGE" then key-value rows Period CY 2026, Programs covered 5,
  Expense entries 47
- Label "SUMMARY" then key-value rows Highest utilisation "Mar — 99%", Lowest
  utilisation "Oct — 20%", Programs complete "2 of 5"
- Label "NOTE ON UNUTILISED AMOUNT" then a note box: "₱53,500 remains unutilised.
  ₱47,000 is committed to Sports Fest 2026, which is still in progress. ₱6,500 is
  held for deliveries not yet received."
- Label "SUBMISSION" then an amber pill "NOT YET SUBMITTED"
- A full-width primary button "Submit to SK Federation" and under it a full-width
  ghost button "Export as PDF"
```

---

## IF FIGMA MAKE DRIFTS

It tends to add rounded corners, shadows and bright colours on its own. If that
happens, follow up with:

```
Remove all drop shadows from the cards and tables. Keep corner radius at 5 or 6px,
never more. Use only the colours in the design system — no purple, teal or gradient
fills. Keep the sidebar a flat #10214B with no shadow. Money values and record IDs
must be IBM Plex Mono, not the UI font.
```

If the sidebar comes out too wide or the layout breaks at 1200px:

```
Set the frame width to 1200px. The sidebar is exactly 200px and does not scroll.
The main area fills the rest. The right detail panel is exactly 330px with an
18px gap from the main column.
```
