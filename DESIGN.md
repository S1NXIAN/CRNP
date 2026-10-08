---
name: Crates N' Plates
description: A warm, plainspoken diner management system — flat ledger surfaces, one accent, tabular money.
colors:
  accent: "#c8102e"
  accent-ink: "#ffffff"
  chalk: "#16181d"
  board: "#21242b"
  ink: "#f5f6f8"
  ink-muted: "#a9adb6"
  line: "#32363f"
  open: "#2f9e44"
  chalk-light: "#f4f5f7"
  board-light: "#ffffff"
  ink-light: "#16181d"
  ink-muted-light: "#5a5f6b"
  line-light: "#d7dae0"
typography:
  display:
    fontFamily: "system-ui, -apple-system, Segoe UI, Roboto, sans-serif"
    fontSize: "2.5rem"
    fontWeight: 700
    lineHeight: 1.1
    letterSpacing: "0.12em"
  headline:
    fontSize: "1.5rem"
    fontWeight: 600
    lineHeight: 1.25
  title:
    fontSize: "1.25rem"
    fontWeight: 600
    lineHeight: 1.3
  body:
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontSize: "0.8125rem"
    fontWeight: 400
    lineHeight: 1.4
    letterSpacing: "0.04em"
rounded:
  sm: "6px"
  md: "12px"
  lg: "20px"
  pill: "999px"
spacing:
  "1": "4px"
  "2": "8px"
  "3": "16px"
  "4": "24px"
  "5": "40px"
components:
  button-primary:
    backgroundColor: "{colors.accent}"
    textColor: "{colors.accent-ink}"
    rounded: "{rounded.md}"
    padding: "8px 16px"
    height: "44px"
  google-button:
    backgroundColor: "{colors.board}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    padding: "8px 16px"
    height: "44px"
  field-input:
    backgroundColor: "{colors.chalk}"
    textColor: "{colors.ink}"
    rounded: "{rounded.sm}"
    padding: "8px 16px"
    height: "44px"
  category-chip:
    backgroundColor: "{colors.board}"
    textColor: "{colors.ink}"
    rounded: "{rounded.pill}"
    padding: "4px 16px"
    height: "44px"
  category-chip-active:
    backgroundColor: "{colors.accent}"
    textColor: "{colors.accent-ink}"
    rounded: "{rounded.pill}"
    padding: "4px 16px"
    height: "44px"
  product-card:
    backgroundColor: "{colors.board}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
    padding: "16px"
  tag-promo:
    backgroundColor: "{colors.accent}"
    textColor: "{colors.accent-ink}"
    rounded: "{rounded.sm}"
    padding: "0 8px"
  tag-top:
    backgroundColor: "{colors.chalk}"
    textColor: "{colors.accent}"
    rounded: "{rounded.sm}"
    padding: "0 8px"
---

# Design System: Crates N' Plates

## Overview

**Creative North Star: "The Counter Ledger"**

Crates N' Plates looks like a diner's order pad and receipt made rigorous. The
surface is warm and plainspoken — a neighbourhood diner's voice carried straight
into the interface — but it is disciplined by a bookkeeper's columns: money is
tabular, labels are small and set in uppercase, and every figure the system can
compute it computes, so the screen never asks a person to do arithmetic. The
system spans one web app with four very different jobs (a customer ordering on a
phone, a cashier at a shared register, a cook glancing at a wall screen, and an
owner in the back office), so the visual system stays deliberately quiet and
consistent rather than expressive: it earns trust by being legible under service
light and identical from screen to screen.

Depth is tonal, not shadowed. The page sits on a near-black chalk surface, cards
and panels lift onto a slightly lighter board surface, and a 1px line does the
rest of the separating. There is effectively no shadow vocabulary — the one
exception is an inset ring marking a late kitchen ticket — so hierarchy comes
from surface layering, borders, and spacing, never from drop shadows. The feel
is **refined and restrained**: quiet surfaces, precise alignment, no flourish.
Controls are honest and unfussy rather than decorative.

Color is almost entirely neutral; a single warm red accent does all the
signalling. It is used **wherever it matters** — the primary action, the active
nav item, a promo tag, and every exception the diner must notice (a late ticket,
a refunds-owed badge, a flagged payment hold, a NEW badge). Because it is the
only hue in the system (bar one green "open" dot), each appearance reads as
emphasis rather than decoration. Typography is the system UI stack: no webfont
is loaded, so the app renders instantly and matches each device's native feel.
Two motifs carry the brand more than any single color: **uppercase, letter-spaced
micro-labels** (order codes, column headers, step counters) and **tabular
numerals on every monetary figure**, which keep the ledger columns aligned.

**Key Characteristics:**
- Flat tonal layering: chalk base → board raised → 1px border; essentially no shadows.
- One warm red accent, used wherever the eye must go next or an exception lives; the only other hue is a green "open" status dot.
- Tabular numerals on every money figure; uppercase letter-spaced labels on every micro-label.
- System UI font stack, no webfont; dark-first with a full `prefers-color-scheme: light` mirror.
- Pills and soft radii (6 / 12 / 20px) over hard corners; touch targets floored at 44px.
- Motion is minimal and only ever transform/opacity, always disabled under `prefers-reduced-motion`.

## Colors

A near-monochrome ledger of chalk, board, and ink, broken by exactly one warm
red. Every value below is a `--color-*` custom property on `:root`, and each has
a light-mode counterpart swapped in by `@media (prefers-color-scheme: light)`.

### Primary
- **Accent Red** (#c8102e / `--color-brand`): the single accent, and the only
  chromatic color in the UI. Carries the primary button, the active nav item and
  active chip, promo and Top-3 tags, the 2px payment frame and flagged-hold
  border, the kitchen NEW/LATE badges and the red 12-minute age timer, the
  refunds-owed badge, error text, and focus outlines. It appears wherever
  emphasis is needed — action or exception alike — so its meaning is always
  "look here".
- **Accent Ink** (#ffffff / `--color-brand-ink`): the text and icon color placed
  on Accent Red fills — buttons, tags, banners, badges. It is always white,
  because Accent Red is dark enough to carry it in both themes.

### Neutral
- **Chalk** (#16181d / `--color-surface`): the base page surface — the dark
  diner wall the ledger sits on. Light mode: **Chalk Light** (#f4f5f7).
- **Board** (#21242b / `--color-surface-raised`): the raised panel surface —
  cards, panels, tables, the register header, tiles at rest. Light mode:
  **Board Light** (#ffffff).
- **Ink** (#f5f6f8 / `--color-text`): primary text. Light mode: **Ink Light**
  (#16181d).
- **Ink Muted** (#a9adb6 / `--color-text-muted`): secondary text, hints,
  placeholders, strike-through prices, table headers. Light mode: **Ink Muted
  Light** (#5a5f6b).
- **Line** (#32363f / `--color-border`): every 1px border, divider, table rule,
  and input stroke. Light mode: **Line Light** (#d7dae0).
- **Open Green** (#2f9e44): a single-purpose hue — the "open now" status dot on
  the store badge only. It is not a token and never fills anything else.

### Named Rules
**The One Accent Rule.** There is exactly one accent hue in the system (Accent
Red) plus one functional green dot. No second brand color is introduced; new
emphasis reuses Accent Red rather than adding a hue.

**The Every-Exception Rule.** Accent Red is not decoration and not confined to
"primary" roles: it marks the primary action *and* every exception the diner
must notice (late, refunds owed, flagged hold, NEW). If a state needs attention,
it goes red — it does not get a new color.

## Typography

**Display Font:** system UI stack — `system-ui, -apple-system, "Segoe UI",
Roboto, sans-serif` (with generic `sans-serif` fallback)
**Body Font:** the same stack
**Label/Mono Font:** none — micro-labels reuse the body stack with uppercase and
letter-spacing; money uses tabular figures rather than a mono face.

**Character:** one system stack, no webfont, so the app paints instantly and
inherits each device's native texture. Personality comes from scale, weight, and
letter-spacing — not from a typeface. Small labels are set uppercase and
letter-spaced to read as ledger column heads; large identifiers (order codes)
are the opposite: big, bold, and widely tracked.

### Hierarchy
- **Display** (700, 2.5rem, line-height 1.1, letter-spacing 0.12em): the order
  code on the customer tracker — the single biggest element in the app, read
  from across a counter.
- **Headline** (600, 1.5rem): screen titles (cart, queue, register actions,
  kitchen board, sign-in title).
- **Title** (600, 1.25rem): section titles, menu group titles, product names in
  the detail view, KPI values.
- **Body** (400, 1rem, line-height 1.5): all running text; long copy caps at
  ~60ch (`.site-main__copy`, descriptions, settings copy).
- **Label** (400–600, 0.8125rem, letter-spacing 0.04em, uppercase): column
  headers, field labels, step counters, "cashier on duty" — the ledger's column
  heads.

### Named Rules
**The Tabular Money Rule.** Every monetary figure — prices, totals, change,
receipt lines, KPI values, kitchen quantities — sets `font-variant-numeric:
tabular-nums`. Digits align in columns; a computed number never jitters.

**The Ledger-Label Rule.** Any label under ~1rem that names a column, field, or
metric is uppercase with 0.04em letter-spacing. Order codes and large
identifiers invert this: large, bold, 0.08–0.12em tracking, never uppercase.

## Layout

A mobile-first, single-column flow that widens into at most two columns for
task surfaces. The main public container (`.site-main`) is capped at **960px**
and centered with 40px vertical / 24px horizontal padding; interior panels cap
themselves narrower still (forms 32–40rem, the receipt 24rem, the tracker 34rem,
long copy 60ch). Content grids are `auto-fit`/`auto-fill` with `minmax` so they
reflow without breakpoints: product grid `minmax(220px, 1fr)`, tiles
`minmax(9rem, 1fr)`, rentals/occupancy `minmax(16rem, 1fr)`.

The spacing rhythm is a five-step scale — **4 / 8 / 16 / 24 / 40px** — used for
every gap, padding, and margin; there are no arbitrary spacing values. Density is
**comfortable** on the public site and cashier POS, and **compact** only on
read-only glanceable surfaces (kitchen board, admin tables), and never below the
tap floor.

Named breakpoints, in order of appearance: **800px** (walk-in POS splits into a
3fr / 2fr two-column register), **720px** (receipt/product-detail print-ish
widening), **60rem** (kitchen lanes split 2fr / 1fr), plus `prefers-color-scheme:
light`, `prefers-reduced-motion: reduce`, and a `print` block for receipts. Reflow
is verified down to **320px** (WCAG 1.4.10); overflow uses "Show more", never
silent truncation.

## Elevation & Depth

Flat, tonal, and border-led. The system uses **no drop shadows** as a depth
signal: a surface is either the base **Chalk** or the raised **Board**, and a 1px
**Line** border does the separating. Hierarchy is expressed by moving a panel up
one tonal step (chalk → board) and by spacing, not by shadow. The only
shadow-shaped rule in the entire stylesheet is an *inset* ring on a late kitchen
ticket (`box-shadow: inset 0 0 0 1px var(--color-brand)`), which is a state cue,
not an elevation. Dashed borders mark two "empty/provisional" cases
(`.empty-state`, `.promo-preview`), and a 2px solid Accent Red border marks two
"attention" containers (`.payment-frame`, `.tracker__hold`).

### Named Rules
**The Flat-By-Default Rule.** Surfaces are flat at rest and in every state.
Never add a drop shadow to imply elevation — raise the surface tone (chalk →
board) and let the 1px line hold the edge.

**The Border-Over-Shadow Rule.** When something must feel lifted or focused, the
answer is a border color change (to Accent Red) or an `outline` — never a shadow
or a glow.

## Shapes

A soft, rounded form language: a three-step radius scale — **6px (sm)**, **12px
(md)**, **20px (lg)** — plus a full **pill (999px)**. Small elements (inputs,
tags, chips-of-text, thumbs) take `sm`; the default control and panel radius is
`md`; large containers (cards, sections, the receipt, the payment frame) take
`lg`. Pills are reserved for inherently round affordances: category chips,
favorite hearts, steppers, and the store badge. Nothing in the system uses a
square corner, and there is no border-radius: 0 anywhere.

Borders are hairline (1px, `Line`) by default; **2px** borders are reserved for
the two attention containers (payment frame, flagged hold) and the kitchen
ticket card; **dashed** 1px borders mark empty or provisional surfaces. Images
use `aspect-ratio: 4 / 3` with `object-fit: cover` and inherit the container
radius; the one hard-edged surface is the white GCash QR, which is served
untouched inside its red frame.

## Components

The feel throughout is **refined and restrained** — quiet, precise, unfussy.
Every control is a real element with a 44px minimum height on touch surfaces,
and every interactive state is carried by a border/color change, never a shadow.

### Buttons
- **Shape:** medium radius (12px) for primary and secondary; the pill (999px) is
  reserved for chips, steppers, and the favorite toggle.
- **Primary:** Accent Red fill, white text, 600 weight, `8px 16px` padding,
  44px min-height. One per view where a primary action exists.
- **Secondary / Google:** Board fill, Ink text, 1px Line border, 12px radius,
  600 weight. On hover/focus the border shifts to Accent Red (the only hover
  treatment on a button).
- **Link button:** no fill or border, Accent Red text, 24px min-height — used for
  in-flow actions like "Sign out".
- **Disabled:** `opacity: 0.55` with `cursor: not-allowed` (the place-order gate).
- **Toggle button:** Board fill at rest; when `aria-pressed="true"` the border
  and text both shift to Accent Red (a state, not a filled button).

### Chips
- **Style:** category chips are pills — Board fill, Ink text, 1px Line border,
  44px min-height, `4px 16px` padding.
- **Selected:** the active chip flips to an Accent Red fill with white text and
  600 weight (`.category-chip--active`).
- **Choice chips** (`.chip`): hidden radio + styled span; the checked span takes
  the Accent Red fill and the keyboard focus shows a 2px Accent Red outline with
  a 2px offset.

### Cards / Containers
- **Corner Style:** large radius (20px) for cards, sections, receipts, and the
  payment frame; medium (12px) for smaller panels, tiles, and rows.
- **Background:** **Board** (#21242b) for every raised card/panel; the base
  **Chalk** shows through the page and inside inputs.
- **Shadow Strategy:** none — see Elevation & Depth. Flat by default.
- **Border:** 1px Line; 2px Accent Red only for attention containers.
- **Internal Padding:** from the spacing scale — 16px typical, 24px for larger
  panels, 40px for hero/empty states.

### Inputs / Fields
- **Style:** Chalk fill, Ink text, 1px Line stroke, small radius (6px), `8px 16px`
  padding, 44px min-height.
- **Focus:** the field border shifts to Accent Red; the choice-chip pattern uses
  a 2px Accent Red outline with 2px offset.
- **Error:** `.field__error` and `.notice--error` render in Accent Red (the error
  border also shifts to Accent Red).

### Navigation
- **Style:** a plain flex row of labeled text links (never an unlabeled icon),
  always visible. Links are Ink, 600 weight, 44px min-height; the current page
  takes Accent Red via `aria-current="page"`. The admin and cashier registers use
  the same rule inside `.register-header` / `.site-nav`, with the cashier's
  "on duty" name pinned right.
- **Mobile:** the nav wraps rather than collapsing into a hamburger; the logo
  always sits on its Board chip so the white-on-transparent artwork stays legible.

### Signature Components
- **Order-code display** (`.tracker__code`, `.walkin-head__code`,
  `.kitchen-card__code`): the brand's most distinctive motif — a large, bold,
  widely-tracked code (2–2.5rem, 0.08–0.12em) sitting under a small uppercase
  label. It appears identically on the customer tracker, the walk-in register,
  the receipt, and the kitchen ticket, tying every surface together.
- **Branded GCash frame** (`.payment-frame`): a 2px Accent Red bordered Board
  panel holding the untouched white QR (max 20rem) and the copyable GCash number
  in tabular figures — the artwork is never re-rendered.
- **Kitchen ticket** (`.kitchen-card`): a 2px-bordered Board card with the code,
  per-line quantities in bold tabular figures, an age timer that turns Accent Red
  at 12 minutes, a NEW badge that flashes opacity-only five times then holds
  steady, and a dimmed (55% opacity) LATER variant for not-yet-cookable tickets.

## Do's and Don'ts

### Do:
- **Do** keep the surface stack to Chalk → Board and separate with a 1px Line.
- **Do** set `font-variant-numeric: tabular-nums` on every monetary figure.
- **Do** set micro-labels (columns, fields, metrics) uppercase with 0.04em
  tracking, and order codes large, bold, and 0.08–0.12em tracked.
- **Do** use Accent Red for both the primary action and every exception (late,
  refunds owed, flagged hold, NEW); it is the only accent.
- **Do** floor touch targets at 44px on cashier/kitchen surfaces and never below
  24px anywhere (WCAG 2.5.8).
- **Do** confine motion to `transform`/`opacity` and disable it under
  `prefers-reduced-motion: reduce`.
- **Do** carry the radius scale 6 / 12 / 20px plus pills — no square corners.

### Don't:
- **Don't** add a drop shadow to imply elevation; raise the surface tone instead.
- **Don't** introduce a second brand hue — the only non-red color is the
  single-purpose green "open" dot (#2f9e44).
- **Don't** use Accent Red as a large background fill for decoration; it is for
  emphasis and exceptions (the announcement banner is the one sanctioned large
  fill).
- **Don't** load a webfont or add a mono face — the system stack plus tabular
  figures is the whole type system.
- **Don't** re-render, recolor, or re-export the logo (`public/images/logo.png`,
  white-on-transparent, always on its Board chip) or the GCash QR (always the
  official white image inside the red frame).
- **Don't** put two same-weight, same-style buttons in one visual group — the
  hierarchy comes from size, contrast, and grouping.
- **Don't** hide navigation behind an unlabeled icon; chrome stays visible and
  labeled.
