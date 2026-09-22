# CSS coding standards — 2026 research digest

All URLs accessed 2026-09-22. Sources are primary: W3C WCAG 2.2 Understanding documents and MDN, plus one company style guide explicitly labeled first-party where it is used. Project decisions cite `README.md` (§2 "Stack — decided", §3 "Design system") as their owner. Every rule below is checkable as written: a MUST/SHOULD/NEVER keyword, a numeric limit, a named pair, or a required structure.

Scope: plain CSS, zero build step, one `public/css/app.css`, design tokens + component classes (README §2). No preprocessor, no npm/Node toolchain, no animation library (README §2 "Explicitly rejected").

## 1. File, layers, formatting

- All project CSS MUST live in `public/css/app.css`; it MUST be the only project-authored stylesheet linked from layouts (README §2: "One `public/css/app.css`").
- The first statement of `app.css` MUST be `@layer tokens, base, components, utilities;`, and every rule MUST sit inside one of those four layers — `tokens` (custom-property declarations), `base` (element defaults), `components` (component classes), `utilities` (small overrides) (project structure; layer mechanics per [MDN: `@layer`](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/@layer)).
- Rules MUST be placed in the correct layer instead of relying on source order: once layer order is established, the first-declared layer has the lowest priority and the last the highest, and styles NOT in a layer always override layered styles — so an unlayered stray rule beats every component ([MDN: `@layer`](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/@layer)).
- Conflicts SHOULD be resolved by layer position, never by raising specificity or adding weight — layered order beats specificity, which "enables using simpler CSS selectors" ([MDN: `@layer`](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/@layer)).
- Indentation MUST be 2 spaces; tabs MUST NOT be used (first-party: [Google HTML/CSS Style Guide, Indentation](https://google.github.io/styleguide/htmlcssguide.html)).

## 2. Naming and casing

- Class names MUST match `^[a-z][a-z0-9]*(-[a-z0-9]+)*$` — all lowercase, words separated by hyphens only (first-party: [Google HTML/CSS Style Guide, "Separate words in class names by a hyphen"](https://google.github.io/styleguide/htmlcssguide.html)).
- Class names MUST be meaningful and as short as possible but as long as necessary — name purpose, not appearance (first-party: [Google HTML/CSS Style Guide, "Use meaningful or generic class names"](https://google.github.io/styleguide/htmlcssguide.html)):

  ```css
  /* Good */  .btn-checkout { … }
  /* Bad */   .btn-green-big { … }
  ```

- Classes MUST NOT be qualified with type selectors (`button.btn`); the rule targets the class alone (first-party: [Google HTML/CSS Style Guide, Type Selectors](https://google.github.io/styleguide/htmlcssguide.html)).
- Custom properties MUST begin with `--`, with words separated by hyphens (`--space-2` not `--space2`) ([MDN: Using CSS custom properties](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Cascading_variables/Using_custom_properties)).
- Every design-token custom property MUST carry exactly one group prefix from this fixed set: `--color-`, `--space-`, `--radius-`, `--shadow-`, `--font-`, `--duration-`, `--ease-`; a new token group MUST be added to this list before use (project token namespaces under README §2 "design tokens + component classes").
- Custom property names are case-sensitive — `--my-color` and `--My-color` are different properties — so every reference MUST match the declared case exactly, and mixing cases MUST NOT be relied upon to fall back ([MDN: Using CSS custom properties, "Custom property names are case sensitive"](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Cascading_variables/Using_custom_properties)).

## 3. Design tokens (custom properties)

- Global tokens MUST be declared once, on `:root`, so they are referenced globally; component-local tokens MUST be declared on that component's own class only when the value is genuinely scoped there ([MDN: Using CSS custom properties](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Cascading_variables/Using_custom_properties): "`:root` … so that it can be referenced globally").
- Component rules MUST consume colors, spacing, radii, fonts, shadows, durations, and easings via `var()`; a raw color literal (`#…`, `rgb(…)`) MUST appear only inside `tokens` declarations, NEVER in a component or base rule (README §2 tokens; README §5 roadmap item 7 dark-mode audit depends on one place to change; rationale per [MDN: Using CSS custom properties](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Cascading_variables/Using_custom_properties): repeated values otherwise need search-and-replace across the stylesheet).
- `var()` MUST NOT be used in media queries or container queries, in property names, or in selectors — only in property values ([MDN: Using CSS custom properties](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Cascading_variables/Using_custom_properties)).
- Because custom properties cascade and inherit from the parent, a token scoped to a child element MUST NOT be referenced by any ancestor ([MDN: Using CSS custom properties](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Cascading_variables/Using_custom_properties)).

## 4. Selectors and specificity

- ID selectors MUST NOT be used — they are reserved for anchors, and one ID (`1-0-0`) outweighs any number of classes (first-party: [Google HTML/CSS Style Guide, "Avoid ID selectors"](https://google.github.io/styleguide/htmlcssguide.html); weight columns per [MDN: Specificity](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Cascade/Specificity)).
- A component rule SHOULD be exactly one class selector, i.e. specificity `0-1-0`; selectors SHOULD keep specificity "down to a minimum" ([MDN: Specificity](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Cascade/Specificity)):

  ```css
  /* Good */  .ticket.is-late { … }   /* 0-2-0 */
  /* Bad */   #kitchen .list .ticket.late { … }  /* 1-3-0 */
  ```

- State and variant styling MUST be expressed with classes or pseudo-classes on the component class (`.btn:hover`, `.badge--promo`), never with an ancestor ID chain (derived from the three-column model, [MDN: Specificity](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Cascade/Specificity)).
- `!important` MUST NOT be used anywhere — it "break[s] the natural cascade of CSS"; override via a later layer instead (first-party: [Google HTML/CSS Style Guide, "Avoid using `!important` declarations"](https://google.github.io/styleguide/htmlcssguide.html); layer escape hatch per [MDN: `@layer`](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/@layer)).
- When grouping selectors where the shared part should add no weight, `:where()` SHOULD be used — `:where()` and its parameters count as `0-0-0` ([MDN: Specificity](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Cascade/Specificity)).

## 5. Motion: transitions vs. keyframes vs. Web Animations API

Decision table — one mechanism per situation (mechanism split from README §2 "Motion"; behavior per the cited MDN pages):

| Situation | Mechanism |
|---|---|
| Two-state property change on hover / focus / active / toggle | CSS `transition` ([MDN: Using CSS transitions](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Transitions/Using): transitions control the speed of changing CSS properties between states) |
| Multi-step or looping timeline authored in CSS (toast entrance) | `@keyframes` + `animation` ([MDN: Using CSS animations](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Animations/Using): keyframes mark start/end states and intermediate waypoints) |
| Choreography sequenced from JS across elements (badge bump, card stagger) | native WAAPI `element.animate()` ([MDN: Web Animations API](https://developer.mozilla.org/en-US/docs/Web/API/Web_Animations_API): `Element.animate()` creates and plays an `Animation` with playback controls) |

- An animation library MUST NOT be added; CSS + WAAPI covers all motion (README §2 "Explicitly rejected": Framer Motion / `motion` / any animation library).
- A `transition` MUST name each animated property explicitly; `transition: all` MUST NOT be used ([MDN: Using CSS transitions](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Transitions/Using): you decide which properties animate "by listing them explicitly").
- Animations SHOULD touch only `transform` and `opacity` (plus color) when the effect allows; properties that affect the box model SHOULD NOT be animated ([MDN: Using CSS animations](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Animations/Using): "changing any properties that impact the box model negatively impacts performance").
- Transitions MUST NOT animate to or from `auto`; the behavior is unspecified across engines ([MDN: Using CSS transitions](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Transitions/Using)).
- Entering/exiting elements hidden with `display: none` MUST use `transition-behavior: allow-discrete` together with `@starting-style`; that pair is the required structure for fade-in/out of `display` ([MDN: Using CSS transitions](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Transitions/Using)).
- Every duration and delay MUST use a `--duration-*` token and every easing a `--ease-*` token; literal `ms`/`s` values MUST NOT appear in component or motion rules (project token rule, README §2).
- All non-essential motion that moves or scales content MUST be reduced or removed under `@media (prefers-reduced-motion: reduce)` — fades in place of translation/scale are acceptable ([MDN: `prefers-reduced-motion`](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/@media/prefers-reduced-motion): the feature lets users request minimal non-essential motion; [MDN: Web Animations API](https://developer.mozilla.org/en-US/docs/Web/API/Web_Animations_API) applies the same note to JS-driven animation).
- The kitchen new-order flash MUST NEVER flash more than 3 times in any one-second period (WCAG 2.2 Level A, flashing content can trigger seizures) ([WCAG 2.3.1 Three Flashes or Below Threshold](https://www.w3.org/WAI/WCAG22/Understanding/three-flashes-or-below-threshold)).
- Blinking content (e.g., an attention blink) MUST stop by itself within 5 seconds or MUST provide a mechanism to pause, stop, or hide it ([WCAG 2.2.2 Pause, Stop, Hide](https://www.w3.org/WAI/WCAG22/Understanding/pause-stop-hide); sufficient techniques G11/G152/SCR22 and failure F112 are listed on that page).

## 6. Responsive layout: mobile-first

- Every layout MUST ship `<meta name="viewport" content="width=device-width, initial-scale=1.0">` (MDN's recommended setting: [MDN: `<meta name="viewport">`](https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/meta/name/viewport); first-party: [Google HTML/CSS Style Guide](https://google.github.io/styleguide/htmlcssguide.html) prescribes the same content value).
- Layouts MUST NOT disable user zoom: `user-scalable=no` and `maximum-scale` below `2` MUST NOT be set ([MDN: `<meta name="viewport">`](https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/meta/name/viewport): disabling zoom prevents low-vision users from reading content; WCAG requires at least 2× scaling).
- Mobile-first structure is a named pair: unadorned base rules MUST define the narrow/mobile layout, and widening MUST happen only through `min-width` media queries — `max-width` media queries MUST NOT be used (README §3 "Mobile-first breakpoints"; method per [MDN: Responsive web design](https://developer.mozilla.org/en-US/docs/Learn_web_development/Core/CSS_layout/Responsive_Design): single-column base, then "check for wider screens").

  ```css
  /* Good (mobile-first) */  @media (min-width: 48rem) { … }
  /* Bad (desktop-first) */  @media (max-width: 47.99rem) { … }
  ```

- Breakpoints MUST use relative units (`rem`), not device pixel widths, and MUST be placed where the content starts to look bad, not at a named device size ([MDN: Responsive web design](https://developer.mozilla.org/en-US/docs/Learn_web_development/Core/CSS_layout/Responsive_Design)).
- Content images MUST be fluid: `max-width: 100%` on the image, so they never force horizontal overflow ([MDN: Responsive web design](https://developer.mozilla.org/en-US/docs/Learn_web_development/Core/CSS_layout/Responsive_Design): fluid images "have their `max-width` property set to `100%`").
- New layout code SHOULD use flexbox or grid, which are responsive by default, instead of floats ([MDN: Responsive web design](https://developer.mozilla.org/en-US/docs/Learn_web_development/Core/CSS_layout/Responsive_Design)).

## 7. Accessibility (WCAG 2.2)

### 7.1 Focus

- Every keyboard-operable control MUST have a visible focus indicator, styled via `:focus-visible` ([WCAG 2.4.7 Focus Visible (Level AA)](https://www.w3.org/WAI/WCAG22/Understanding/focus-visible); sufficient technique C45 uses `:focus-visible`; also [MDN: `:focus-visible`](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/Selectors/:focus-visible): removing focus styles "makes keyboard navigation inaccessible for sighted users").
- `outline: none` / `outline: 0` MUST NOT remove the default indicator without a replacement visible at ≥3:1 contrast against adjacent colors ([MDN: `:focus-visible`](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/Selectors/:focus-visible) citing [WCAG 1.4.11 Non-Text Contrast](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast): the focus indicator must be at least 3:1).
- Focus styling SHOULD target `:focus-visible`, not `:focus`, so pointer clicks do not draw a ring while keyboard focus still shows one ([MDN: `:focus-visible`](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/Selectors/:focus-visible)).

### 7.2 Contrast and color

- Normal text MUST have a contrast ratio of ≥4.5:1 against its background; large-scale text (≥18 pt, ≈24 px, or 14 pt bold) MUST have ≥3:1 ([WCAG 1.4.3 Contrast (Minimum)](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum)).
- Computed contrast MUST NOT be rounded when checking thresholds — 4.499:1 fails 4.5:1 ([WCAG 1.4.3 Contrast (Minimum)](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum)).
- Non-text UI components (input borders, control states), focus indicators, and meaningful graphical objects MUST have ≥3:1 contrast against adjacent colors ([WCAG 1.4.11 Non-Text Contrast](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast)).
- State and meaning (error, promo, low stock, valid/invalid) MUST NOT be conveyed by color alone — each MUST also carry text or a shape/icon cue ([WCAG 1.4.1 Use of Color (Level A)](https://www.w3.org/WAI/WCAG22/Understanding/use-of-color); failure F81 on that page covers required/error fields identified by color only).
- The kitchen ticket's 12-minute "late" state MUST NOT be conveyed by red alone — the running numeric age MUST remain visible alongside the color change ([WCAG 1.4.1](https://www.w3.org/WAI/WCAG22/Understanding/use-of-color); project feature: README §3/§5 "age timers (red at 12 min)").

### 7.3 Reflow and text

- Every page MUST present content without loss of information or functionality and without scrolling in two dimensions at a width equivalent to 320 CSS px (≈1280 px viewport at 400% zoom), except content that genuinely requires 2D layout (maps, data tables, video) ([WCAG 1.4.10 Reflow](https://www.w3.org/WAI/WCAG22/Understanding/reflow)).
- Text containers MUST NOT clip or overlap text when a user overrides spacing to line height ≥1.5× font size, paragraph spacing ≥2× font size, letter spacing ≥0.12 em, and word spacing ≥0.16 em — i.e., no fixed-height boxes around copy (WCAG failure F104 is exactly this clipping/overlap) ([WCAG 1.4.12 Text Spacing](https://www.w3.org/WAI/WCAG22/Understanding/text-spacing)).

### 7.4 Touch targets

- Every pointer-input target MUST be at least 24×24 CSS px ([WCAG 2.5.8 Target Size (Minimum) (Level AA)](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum)).
- Cashier/POS touch targets MUST be ≥44 px (project rule, README §3 "cashier touch targets ≥44 px" — stricter than the 24 px WCAG floor; its spacing exception must not be needed on POS tiles).

## 8. Source list

- W3C, *Understanding WCAG 2.2* success criteria: [1.4.1 Use of Color](https://www.w3.org/WAI/WCAG22/Understanding/use-of-color), [1.4.3 Contrast (Minimum)](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum), [1.4.10 Reflow](https://www.w3.org/WAI/WCAG22/Understanding/reflow), [1.4.11 Non-Text Contrast](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast), [1.4.12 Text Spacing](https://www.w3.org/WAI/WCAG22/Understanding/text-spacing), [2.2.2 Pause, Stop, Hide](https://www.w3.org/WAI/WCAG22/Understanding/pause-stop-hide), [2.3.1 Three Flashes or Below Threshold](https://www.w3.org/WAI/WCAG22/Understanding/three-flashes-or-below-threshold), [2.4.7 Focus Visible](https://www.w3.org/WAI/WCAG22/Understanding/focus-visible), [2.5.8 Target Size (Minimum)](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum) (accessed 2026-09-22).
- MDN (W3C-adjacent web platform docs): [`@layer`](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/@layer), [Using CSS custom properties](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Cascading_variables/Using_custom_properties), [Specificity](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Cascade/Specificity), [Using CSS transitions](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Transitions/Using), [Using CSS animations](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Animations/Using), [Web Animations API](https://developer.mozilla.org/en-US/docs/Web/API/Web_Animations_API), [`prefers-reduced-motion`](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/@media/prefers-reduced-motion), [Responsive web design](https://developer.mozilla.org/en-US/docs/Learn_web_development/Core/CSS_layout/Responsive_Design), [`<meta name="viewport">`](https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/meta/name/viewport), [`:focus-visible`](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/Selectors/:focus-visible) (accessed 2026-09-22).
- First-party style guide (company-authored, used only for naming/formatting rules): [Google HTML/CSS Style Guide](https://google.github.io/styleguide/htmlcssguide.html) (accessed 2026-09-22).
- Project decisions: `README.md` §2 "Stack — decided" (plain CSS, one stylesheet, motion mechanism split, rejected libraries), §3 "Design system" (mobile-first, 44 px POS targets, kitchen display), §5 roadmap item 7 (dark-mode audit).
