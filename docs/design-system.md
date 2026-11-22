# Design system

The prototype screens were built, reviewed and placed in the manuscript as Figures 20 to 47. The UI is approved. Build to these tokens; do not restyle.

## Colour

| Token | Hex | Use |
|---|---|---|
| Katipunan Indigo | `#10214B` | Sidebar background, headings, body text |
| Tarpaulin Blue | `#1B5AC4` | Primary buttons, active tabs, progress fills, record IDs |
| Sun Ray | `#F5B60D` | Logo, active nav item text and its left bar, warning fills |
| Bayanihan White | `#F7F8FB` | Page background |
| White | `#FFFFFF` | Cards, tables, panels |
| Sampaguita Gray | `#5A6478` | Secondary text, small labels |
| Green | `#1E7D4F` | Success |
| Alert Red | `#C8322B` | Destructive actions, notification count badge |
| Border | `#E3E7EF` | All card and table borders |

## Type

- UI: **Archivo**, fallback Inter, fallback system sans
- IDs, money, timestamps, small uppercase labels: **IBM Plex Mono**

## Layout

Frame width 1200px. Sidebar exactly 200px, fixed. Right detail panel exactly 330px with an 18px gap from the main column. Page body padding 20px 24px.

**Sidebar** — `#10214B`. Amber sun icon at top, "SK Federation" white bold 13px, "Monitoring & Evaluation System" 9px `#9FB0D0` beneath. Nav items 12.5px `#CDD8EC`, 9px vertical padding. Active item: background `#19305F`, amber text, bold, 3px amber left border.

**Navigation is grouped** under small headings in IBM Plex Mono, 8.5px, uppercase, letter-spacing 0.14em, `#7F90B4`, not clickable. Dashboard sits alone at the top with no heading.

| Role | Groups |
|---|---|
| SK Barangay Official | Members (Verify Members, Youth Profiling) · Programs (Programs, Calendar, QR Attendance, Participation) · Resources (Inventory, Budget) · Content & Reports (Documents, KK Member Content, Reports) |
| SK Federation | Monitoring (Youth Profiling, Programs, Calendar, Participation) · Budget & Reports (Budget, Reports) · Content (Documents, KK Member Content) |
| KK Member | My Activities (Calendar, Scan QR Code, My Attendance, My Evaluations, My Certificates) · Requests (Request Equipment) · Information (Announcements, Reports & Documents, Feedback) |

The SK Federation sidebar has no Inventory and no QR Attendance. The KK sidebar has no Budget. These absences are the access model made visible; keep them.

**Top bar** — white, bottom border. Breadcrumb left in IBM Plex Mono, uppercase, 10.5px, letter-spacing 0.14em, `#5A6478`. Right side: a **Notifications button** then a plain "Sign out" link, 14px apart. The button is white with a 1px `#E3E7EF` border, radius 6px, padding 6px 11px, 12px navy text, a glyph on the left, and a red count badge on the right in white IBM Plex Mono 9.5px. Notifications never appears in the sidebar.

## Components

**Pill** — radius 999px, uppercase, IBM Plex Mono 9.5px, letter-spacing 0.1em, padding 5px 10px. Amber `#FDF3D6`/`#8A6A05`, blue `#E4EDFC`/`#164AA3`, green `#DFF2E8`/`#14613C`, red `#FBE4E3`/`#9B2621`, gray `#ECEFF5`/`#4A5468`.

**Info banner** — background `#EAF1FD`, radius 5px, padding 11px 14px, 6px blue dot on the left, text 12px `#1C3566`.

**Stat card** — white, 1px border, radius 6px, padding 13px 15px. Label 11px gray above, value 24px bold below. Four across with 14px gaps.

**Tabs** — row with a 1px bottom border. Items 12.5px gray; active is blue, semibold, with a 2px blue bottom border.

**List row card** — white, 1px border, radius 5px, 3px coloured left border: amber pending, green approved, red returned, blue neutral. Record ID in mono 10.5px blue with a status pill beside it, 14.5px bold title, 11.5px gray detail lines, and on the right a small timestamp above a button.

**Table** — white, 1px border, radius 6px. Header background `#FAFBFD`, header text IBM Plex Mono 9.5px uppercase letter-spacing 0.09em gray. Cells 12.5px, row separators `#F0F3F8`. Money columns right-aligned in mono.

**Progress bar** — 6px tall, track `#E8ECF3`, radius 999px, fill blue, amber or green.

**Buttons** — primary: blue fill, white text, 12px semibold, radius 5px, padding 8px 15px. Ghost: white, `#BFD2F2` border, blue text. Destructive: white, `#EFC3C1` border, red text.

**Right detail panel** — 330px white card. Title 14px bold, mono subtitle, key-value rows (label left 12px, value right in mono). Section labels inside are IBM Plex Mono 9.5px uppercase letter-spacing 0.1em gray. Note box inside: `#FAFBFD` background, 1px border, radius 5px, 11.5px text.

## Tone

Flat and orderly. No gradients, no drop shadows on cards, no large corner radii. Peso sign on all money. This is a government youth council system, not a consumer app.
