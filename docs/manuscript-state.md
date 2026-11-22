# Manuscript state and open issues

The manuscript passed proposal defense. Several revisions have been applied since. This records where it stands and what is still outstanding, because the system must match it.

## Revision history

**Proposal defense revisions**
1. Budget monitoring added as a feature. Two literature review sections removed: *Approaches to System Development and Software Quality Assessment* and *Access Control, Security, and Data Privacy*. A new *Budget Monitoring Systems* section added with references [73] to [77]. Eight orphaned references deleted, so [1] to [72] kept their numbers and nothing needed renumbering. Final count: **77 references**.
2. Scope, limitations, objectives and Definition of Terms aligned to the budget feature.
3. Budget made invisible to KK members, and the literature review's gap statement rewritten so it no longer claims a gap the system does not close.

**Adviser revisions, November**
1. APA 7 formatting applied to all five data tables: bold table number on its own line, italic title below, horizontal rules only, no vertical lines, headings not bold, cells single-spaced.
2. Definition of Terms expanded from 10 terms to 33.

## Errors found and corrected

- Scale ranges in Tables 3, 4 and 5 read `2.50-3.0` and `1.50-2.0`, leaving gaps where a mean of 3.2 or 2.3 fell into no band. Corrected to `2.50-3.49` and `1.50-2.49`.
- "Functional **Sustainability**" corrected to "Functional **Suitability**" in the table title and the List of Tables. The adviser's own note repeated the error.
- "KKK" appeared twice in the Definition of Terms. Corrected to "KK".
- The closing summary of the literature review was fused into a deleted section with corrupted text, and had to be split out and repaired.
- A mis-citation in the inventory section pointed at the wrong reference.
- Tables 3 to 5 drew a full grid from Word's TableGrid style with no borders of their own, so the APA fix needed an explicit override.

## Open issues

**Figure 13 (ERD) has no notifications table.** The schema adds one. The ERD must be updated or the diagram will not match the system.

**Figure captions are not APA.** They read "Figure 47. Title" in italics. APA 7 wants **Figure 47** bold on one line with the italic title below, as the tables now have. The adviser asked about tables only, so figures were left alone.

**System Evaluation names only the ISO model**, but the scope and Definition of Terms still commit to the Technology Acceptance Model as the second approach. Reconcile.

**Prototype screenshots include browser chrome and the Windows taskbar**, with the figma.com URL visible. Crop them, or re-shoot with F11 fullscreen. A panelist who sees the URL may ask whether the system exists.

**"Utilized" / "Utilization"** in the Figma screens versus **"utilised" / "utilisation"** in the manuscript. Pick one.

**Figure numbering.** The last prototype figure is numbered 37, duplicating the SK Barangay Official Budget figure. It should be 47. "MKK Member Request Equipment" has a stray M.

**Barangay list.** Only six barangays are seeded, taken from the prototype. The official Nasipit list is needed.

**No KK screens exist for announcements, notifications or feedback**, though all three are in the scope and the use case diagram. *(The built system has all three; the prototype figures still lack them.)*

**Identity verification at registration is not in the manuscript.** The prototype, and so the built system, asks for an ID photo and a selfie, which only the barangay chairman can view (each view is audited) and which are deleted once the chairman acts. Either describe this in Scope, Data Privacy and Definition of Terms, or remove it from the system. A panelist may ask how photos of minors are protected.

**Figure 13 (ERD) needs the columns added during the build.** See "Added while building from the prototype" in `docs/database.md` (chairman designation, youth profile fields, settings table and others).

**Activity evaluation uses a 1–5 scale; Chapter 2's instrument is 4-point.** These are different instruments (activity feedback vs. the ISO 25010/TAM system evaluation), but the manuscript should say so explicitly.

## Key references for the budget argument

- **[73] Duque (2025)** — fiscal management of the SK across all 76 barangays of Tarlac City. Budgeting was the weakest area at 2.29. Publishing the approved budget and submitting the annual budget-versus-actual report were rated **never complied with**. This is the strongest link between the literature and the budget reporting feature.
- **[74] Liwanag (2024)** — barangay budget utilisation, 30 barangays, Concepcion, Tarlac. Spending consistently below budget; SK Fund use has widened beyond sports.
- **[75] Dela Peña et al. (2024)** — Kawó Banwa, a budget monitoring information system for urban barangays, San Jose, Occidental Mindoro.
- **[76] Kambuaya et al. (2026)** — budget information systems raise accountability and fund utilisation, PLS-SEM, N=50.
- **[77] Santolamazza, Mattei & Grandis (2025)** — Rome participatory budgeting; the monitoring stage after approval was never provided for.
- **[43] Deduro et al. (2025)** — "The Skeynected", the closest precedent: SK project monitoring and youth profiling for LYDO Tupi, South Cotabato. Covers monitoring and profiling only, with no attendance capture, no activity evaluation, no document repository, no inventory and no KK member accounts.

## Research gap as argued

Philippine barangay systems centre on residents, clearances and certificates, and almost all serve a single barangay. BarangayConnect [36] explicitly names non-integration with higher government systems as a limitation. An SK assessment found youth profiling, databases and public disclosure rated lowest of all indicators. For budget specifically: no reviewed study examines budget monitoring across a federation where many councils draw on separate funds and report to one body, and none keeps the budget record in the same system as the programs, activities and inventory the spending is charged against.
