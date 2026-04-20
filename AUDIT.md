# Stack Blueprint — Conversion Audit & Architecture Gap Analysis

> **Single Source of Truth** | Last updated: 2026-04-10
>
> Covers: `nexus-landing.html` → V2 JSON/CSS output audit | Architecture article gaps | Broader edge-case coverage guided by article §14

Update this file every time a bug is fixed or a new edge case is handled. Mark resolved items ✅ FIXED.

---

## Part 1: HTML Source → V2 Output Audit

### 1.1 Section Inventory — What the HTML Contains vs What V2 Output Has

The HTML body contains these top-level blocks in order:

| # | HTML Element / Comment | Role | V2 JSON | Status |
|---|---|---|---|---|
| 1 | `<div id="cursor">` | Custom cursor overlay | SKIP → recreated in Global Setup | ✅ Handled |
| 2 | `<canvas id="bg-canvas">` | Particle canvas background | Should be → Global Setup | ❌ **MISSING** |
| 3 | `<nav id="navbar">` | Navigation bar | HTML widget | ✅ Present |
| 4 | `<section class="hero">` | Hero section | Native container | ✅ Present (partial) |
| 5 | `<div class="marquee-wrap">` | Scrolling ticker strip | — | ❌ **COMPLETELY MISSING** |
| 6 | `<div class="stats">` | Animated stat counters (×4) | — | ❌ **COMPLETELY MISSING** |
| 7 | `<section class="features">` | Bento grid features | Native container | ⚠️ Partial (spans & sub-visuals missing) |
| 8 | `<section class="process">` | How It Works steps | Native container | ⚠️ Wrong content, 9 steps instead of 4 |
| 9 | `<section class="testimonials">` | Testimonial cards (×3) | — | ❌ Cards missing entirely |
| 10 | `<section class="pricing">` | Pricing cards (×3) | Native container | ⚠️ Empty content, wrong prices |
| 11 | `<div class="cta-section">` | Full-bleed CTA | — | ❌ **COMPLETELY MISSING** |
| 12 | `<footer>` | Site footer | Native container | ✅ Present (minor issues) |
| 13 | `<script>` block | Cursor + Canvas + Reveal JS | → Global Setup | ⚠️ Canvas injection incomplete |

---

### 1.2 Bug Catalogue

---

#### BUG-01 · `<canvas id="bg-canvas">` — Canvas Element Not Injected into Global Setup

**Severity: CRITICAL**
**Article reference: §12 Canvas Injection, §24**

The source HTML has an explicit `<canvas id="bg-canvas">` element at body level, and the `<script>` block drives a full particle system drawing to it. The architecture article mandates:

> "The canvas must be injected into `document.body` via `document.createElement('canvas') + document.body.appendChild()`. The Global Setup widget includes the `document.createElement('canvas') + document.body.appendChild()` pattern."

**What happened:** The `<canvas>` element was classified as `SKIP` (correct — it should not appear as a widget inline). However the Global Setup widget was NOT built to inject the canvas via JS. The particle system JS (`bg-canvas`, `ctx.getContext('2d')`, the animation loop, `drawLines()` etc.) was either skipped or not included in the Global Setup output.

**Expected output in Global Setup:**
```html
<script>
(function(){
  // CANVAS INJECTION — must be first
  var canvas = document.createElement('canvas');
  canvas.id = 'bg-canvas';
  canvas.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;z-index:0;pointer-events:none;';
  document.body.appendChild(canvas);
  // ... full particle system JS here ...
})();
</script>
```

**Root cause:** Pass 6 (Global Setup Synthesis) didn't correctly detect the canvas element in Pass 1's animation inventory and therefore did not include the canvas injection pattern.

---

#### BUG-02 · `<div class="marquee-wrap">` — Entirely Missing

**Severity: HIGH**
**Article reference: §3 Stage 1, §7 Pass 1 "section boundary detection"**

The marquee strip between Hero and Stats is a top-level `<div>` (not a `<section>`). It is completely absent from the JSON. Not even an HTML widget fallback was generated.

**Root cause:** The segmenter only recognises `<section>`, `<header>`, `<footer>`, `<main>`, `<article>` as section boundaries. The article explicitly states:

> "section boundary detection — top-level `<section>`, `<header>`, `<footer>`, and **major `<div>` blocks with full-width layout**"

Any `<div>` that is a direct child of `<body>` (or 1 level deep) with `width: 100%` or `overflow: hidden` should be treated as a section.

**Expected output:** An `html` widgetType containing the full `.marquee-wrap` markup with companion CSS for the `@keyframes marquee-scroll` animation.

---

#### BUG-03 · `<div class="stats">` — Animated Counter Section Entirely Missing

**Severity: HIGH**

Same root cause as BUG-02. The stats section is a full-width `<div class="stats">` with 4 `stat-cell` children using `data-target` attributes and a JS `IntersectionObserver`-driven counter animation. Should have been classified `HTML_WIDGET_ANIMATED` at minimum. Was never reached by the segmenter.

---

#### BUG-04 · `<div class="cta-section">` — Full-Bleed CTA Entirely Missing

**Severity: HIGH**

The acid-green CTA section (`div.cta-section reveal`) between Pricing and Footer — "Ready to build something extraordinary?" — is a `<div>`, not a `<section>`, and was never picked up by the segmenter. Should become a native container with `background_color: #c8ff00`, heading widget, text widget, and two button widgets, with companion CSS for the `::before` watermark text pseudo-element.

---

#### BUG-05 · Testimonials Section — Section Present, 0 Cards Rendered

**Severity: HIGH**

The testimonials `<section>` is present in source (lines 995–1035) with 3 `testi-card` divs, each containing: a blockquote paragraph, an avatar div with initials, the author name, and the author role. The JSON output contains a section header labelled `— SOCIAL PROOF` + the heading "Teams that moved fast chose NEXUS." — but **zero testimonial cards appear**.

The JSON at around line 2002 merges this into a container with `_element_id: "sb-pricing"` (naming collision) and places the first testimonial quote text incorrectly into the section description `sb-section-desc` widget instead of inside a card.

**Root cause (likely):** The `testi-grid` `<div>` was not recognised as a card grid container pattern. The 3 `testi-card` children were not classified. Their scroll-reveal animation class (`testi-card` observed by JS) may have triggered `HTML_WIDGET_ANIMATED` classification, but even that HTML widget doesn't appear — suggesting the grid was silently dropped.

**Expected output:** 3 native card containers per testi-card, each containing: `text-editor` (quote with `<strong>` preserved), an `html` widget for the avatar circle with initials, and two `text-editor` or `html` widgets for name and role.

---

#### BUG-06 · Process Section — 9 Steps Output, 4 Steps in Source + Duplicated Content

**Severity: HIGH**

Source HTML has exactly 4 `process-step` divs (01–04). V2 JSON produces 9 steps (01–09), with every step duplicated except the last of each group:

| JSON Step | Content | Correct? |
|---|---|---|
| 01 | "Connect your stack" | ✅ |
| 02 | "Connect your stack" (duplicate) | ❌ |
| 03 | "Connect your stack" (duplicate) | ❌ |
| 04 | "Define your logic" | ✅ |
| 05 | "Define your logic" (duplicate) | ❌ |
| 06 | "Deploy with confidence" | ✅ |
| 07 | "Deploy with confidence" (duplicate) | ❌ |
| 08 | "Scale automatically" | ✅ |
| 09 | "Scale automatically" (duplicate) | ❌ |

**Root cause:** The recursive tree walker in the JSON assembler (Pass 7) visits children twice — once at the parent (`process-steps`) level during the parent's `assembleNode` call, and again when it assembles child containers. The article's `assembleNode` pseudocode (§7 Pass 7) is explicit: children are mapped inside the parent's `buildContainer()` call and should not be visited again.

---

#### BUG-07 · Pricing Cards — Empty Content; Template Default Values Leaked

**Severity: HIGH**

Source HTML has 3 pricing cards with:
- Plan names: `STARTER`, `GROWTH`, `ENTERPRISE`
- Prices: `$0`, `$149`, `CUSTOM`
- Periods: "Free forever — no credit card", "per month · billed annually", "Volume pricing · white glove"
- Feature lists: 5–6 `<li>` items per card
- CTA buttons: "GET STARTED FREE", "START 14-DAY TRIAL", "CONTACT SALES"
- Featured badge on Growth card: "MOST POPULAR"

V2 JSON output:
- All plan name divs: empty
- All price amounts: `$40` (template default)
- All price periods: empty
- All button text: `""` (empty)
- Feature lists: completely absent from all 3 cards
- Featured badge: absent from Growth card

**Root cause:** Template library pattern matched the pricing card structure but did not extract actual content from the source. The `$40` default leaked from the template scaffold. Content injection is broken. (See article §17 Workaround 3.)

---

#### BUG-08 · `<div class="process-visual reveal visible">` — Orbital Animation Missing from Process Section

**Severity: HIGH**
**Article reference: §12, §7 Pass 3 animation inventory**

The process section's right column contains `.process-visual reveal visible` with 3 nested `.orb-large` rings and a `.orb-center` and 4 `.orbit-node` elements. This entire visual is **not rendered** in the V2 JSON output, despite the fact that the orbital visual widget (`sb-orbital-widget`) does appear — but it is placed as a direct child of the `sb-process-grid` container, not as the right column of a 2-column layout. Additionally, the `.reveal.visible` pre-revealed class is not being passed through — meaning the animation may still run but the initial state may be wrong.

The article is explicit: elements with CSS `animation:` or JS must be classified as `HTML_WIDGET_ANIMATED` — which is correct here — but the orbital visual was omitted from the rendered grid-row's right column in layout context.

---

#### BUG-09 · Hero `<h1>` — Inline `<em>` and `<span class="acid-word">` Content Stripped

**Severity: MEDIUM**
**Article reference: §14 Edge Case 10 (exact match)**

Source headline:
```html
<h1 class="hero-headline">
  Build<br>
  <em>workflows</em><br>
  that <span class="acid-word">think.</span>
</h1>
```

V2 JSON heading widget `title`: `"Build\n    workflows\n    that think."` — all HTML markup stripped. The companion CSS correctly defines:
- `.sb-hero-headline em { ... -webkit-text-stroke: 1px ...; color: transparent; }`
- `.sb-hero-headline .acid-word { color: #c8ff00; }`

But since the markup is gone, these rules are unreachable.

**Article mitigation (§14 Edge Case 10):**
> "If the inline styling is limited to `<em>` and simple `<span>` with colour changes, generate the Heading widget with the full HTML as the title value (Elementor's Heading widget accepts HTML in the title field)."

**Expected title value:** `"Build<br><em>workflows</em><br>that <span class=\"acid-word\">think.</span>"`

---

#### BUG-10 · Bento Grid — Column/Row Spans Not Applied

**Severity: MEDIUM**
**Article reference: §14 Edge Case 5**

Source CSS uses asymmetric grid spans on bento cards:
- `.card-a` → `grid-column: span 5; grid-row: span 5`
- `.card-b` → `grid-column: span 4; grid-row: span 3`
- `.card-c` → `grid-column: span 3; grid-row: span 3`
- `.card-d` → `grid-column: span 4; grid-row: span 4`
- `.card-e` → `grid-column: span 3; grid-row: span 4`
- `.card-f` → `grid-column: span 5; grid-row: span 3`

V2 JSON uses `grid_columns_fr: "1fr 1fr 1fr"` (3 equal columns) with no `grid_row_start / grid_row_end / grid_column_start / grid_column_end` on any child. The asymmetric bento layout collapses to a uniform grid.

**Article mitigation:** CSS resolver detects `grid-column: span N`, converts to explicit `grid_row_start: 1, grid_row_end: N+1`. The JSON assembler writes these to the child container settings.

---

#### BUG-11 · Bento Card Visuals — Sub-Components Dropped

**Severity: MEDIUM**

Card A (`.card-a`) contains `.card-a-visual` with two `.orb` divs and a `.terminal` block (terminal CLI animation). Card D (`.card-d`) contains `.pipeline` with 4 progress bars. Both sub-components were dropped — they do not appear in the respective card containers in V2 JSON.

**Expected fix (article §13 Hybrid Detection):** When a native card container has child elements with animation indicators, those children fall through to `HTML_WIDGET_COMPLEX` and are preserved as an `html` widget sibling inside the card container — not silently dropped.

---

#### BUG-12 · Google Fonts URL — Unresolved CSS Variables

**Severity: MEDIUM**
**Article reference: §12 Font Loading Strategy**

The Global Setup widget Google Fonts `<link>` URL contains literal `var(--font-body)`, `var(--font-display)`, `var(--font-mono)` strings instead of resolved values. This is an invalid URL.

**Article requirement:**
> "Collect all `font-family` values from the resolved style map. For each font family, collect all `font-weight` and `font-style` values used. Check if the font is available on Google Fonts."

CSS custom property resolution must run **before** the font URL is assembled. Pass 1 must resolve `:root { --font-display: 'Syne'; }` etc. and provide a resolved font map to Pass 6.

---

#### BUG-13 · Duplicate `_element_id: "sb-pricing"` — Two Containers with Same ID

**Severity: MEDIUM**
**Article reference: §7 Pass 9 Validation**

Two top-level containers have `_element_id: "sb-pricing"`: the mislabelled testimonials container and the actual pricing container. This causes broken anchor navigation (`#pricing`) and CSS `#sb-pricing` targeting on the wrong element.

**Article requirement (Pass 9):** Scan all `_element_id` values after assembly. Detect duplicates. Auto-repair by regenerating one.

---

#### BUG-14 · CSS Class Map in Companion CSS Header — Incomplete and Incorrect

**Severity: LOW**

The companion CSS header class map (lines 10–18 of the `.css` file) lists:
- `.sb-pricing` twice ❌
- `.sb-testimonials` — not listed ❌
- `.sb-marquee` — not listed ❌
- `.sb-stats` — not listed ❌
- `.sb-cta` — not listed ❌

The class map should reflect every section-level container and major widget class in the output. It is the user's primary reference for applying classes in the Elementor editor.

---

#### BUG-15 · Footer Column Titles — "PRODUCT" Duplicated Twice

**Severity: LOW**

The source footer has 3 link columns: PRODUCT, DEVELOPERS, COMPANY. The V2 JSON footer has 4 nav columns (correct) but the second column is labelled "PRODUCT" instead of the expected second column title (the footer structure in source has PRODUCT, DEVELOPERS, COMPANY only — the 4th column in the JSON is an extra duplicate). Minor content extraction failure.

---

### 1.3 Overall Conversion Accuracy (V2 Output vs Source HTML)

| Section | Source | Output | Score |
|---|---|---|---|
| Global Setup (fonts, cursor) | ✅ | ⚠️ canvas missing | 60% |
| Canvas background | ✅ | ❌ | 0% |
| Navigation | ✅ | ✅ | 100% |
| Hero (container + actions) | ✅ | ⚠️ em/acid-word stripped | 80% |
| **Marquee strip** | ✅ | ❌ | **0%** |
| **Stats counters** | ✅ | ❌ | **0%** |
| Features bento (cards) | ✅ | ⚠️ spans lost, sub-visuals missing | 50% |
| Process section | ✅ | ⚠️ 9 steps output, wrong text | 30% |
| **Testimonials cards** | ✅ | ❌ (header only) | **10%** |
| Pricing cards | ✅ | ⚠️ empty content, `$40` default | 20% |
| **CTA section** | ✅ | ❌ | **0%** |
| Footer | ✅ | ⚠️ duplicate col title | 85% |
| **TOTAL** | **12 areas** | **5 fully correct / 7 missing or wrong** | **~38%** |

---

## Part 2: Architecture Gap Analysis — Article vs Current Codebase

For each gap, the article section is cited so that future implementation can reference the exact specification.

---

### GAP-01 · Section Segmenter — Top-Level `<div>` Blocks Not Detected as Sections

**Article §7 Pass 1, §3 Stage 1, §6 Classification rule 13**

The article defines section boundary detection as covering `<section>`, `<header>`, `<footer>` AND "major `<div>` blocks with full-width layout". The current segmenter only picks up semantic HTML5 elements.

**Fixes BUGs:** 01 (canvas context), 02 (marquee), 03 (stats), 04 (CTA)

**Implementation spec:**
A top-level `<div>` (direct child of `<body>` or within 1 nesting level from `<body>`) must be registered as a section boundary if it meets any of:
- CSS: `width: 100%` or `min-width: 100vw`
- CSS: `position: relative; overflow: hidden` (typical full-bleed section pattern)
- Class name matches the section-level role vocabulary: `marquee`, `stats`, `cta`, `ticker`, `banner`, etc.
- HTML comment immediately before it matches `<!-- SECTION: ... -->` or `<!-- [NAME] -->`
- Contains `data-section` or `data-block` attributes

---

### GAP-02 · Recursive Tree Walker — Double-Visit Bug

**Article §7 Pass 7 `assembleNode()` pseudocode**

The article's `assembleNode()` maps children inside the parent's `buildContainer()` call. Children must not be walked again independently. The current implementation visits children at two levels, producing N×2 results for any repeated sibling pattern.

**Fixes BUGs:** 06 (9 steps instead of 4)

**Implementation spec:**
Each element must be visited exactly once. Introduce a `visited` set or use index-based iteration where children are claimed by their parent during assembly and cannot be re-processed.

---

### GAP-03 · Template Library — Scaffold Built, Source Content Not Injected

**Article §17 Workaround 3**

> "When the segmenter identifies a section that matches a known pattern... use the template library as the starting point and **populate it with the actual content and styles from the input HTML**."

The current template library creates the card scaffold (containers, widget sockets) but does not extract real content from the matched HTML nodes. Template default values (`$40`, empty strings) leak into the output.

**Fixes BUGs:** 07 (pricing content), 05 (testimonial cards)

**Implementation spec per card type:**

*Pricing card extraction:*
- Plan name → text of `.price-plan` or `h3/h4` inside card
- Price amount → inner HTML of `.price-amount` (preserving `<sup>$</sup>`)
- Period → text of `.price-period`
- Features → all `<li>` children of `.price-features`
- CTA text → button/`<a>` text content
- Featured flag → presence of `.featured` class or `.price-badge` child

*Testimonial card extraction:*
- Quote → inner HTML of `.testi-quote` / `<p class="...">`  (preserve `<strong>`)
- Avatar initials → text of `.testi-avatar`
- Name → text of `.testi-name`
- Role → text of `.testi-role`

---

### GAP-04 · Canvas Element — Not Detected in Pass 1 Animation Inventory / Not Injected in Pass 6

**Article §7 Pass 1, §12 Canvas Injection, §24**

Pass 1 must detect: `<canvas>` elements, `getContext('2d')` / `getContext('webgl')` calls in JS, and `document.createElement('canvas')` patterns. Any of these → `HTML_WIDGET_CANVAS` classification. Pass 6 must then generate the canvas injection pattern in the Global Setup.

The article states:
> "If Pass 1 found a `<canvas>` element or any JS creating a canvas context with `position: fixed`, the Global Setup widget will include the `document.createElement('canvas') + document.body.appendChild()` pattern."

**Fixes BUGs:** 01 (canvas missing from Global Setup)

**Implementation spec for Global Setup canvas block:**
```js
// CANVAS — injected at body level for correct z-index stacking
var sbCanvas = document.createElement('canvas');
sbCanvas.id = 'sb-bg-canvas';
sbCanvas.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;z-index:0;pointer-events:none;';
document.body.insertBefore(sbCanvas, document.body.firstChild);
```
The particle system JS must then reference `document.getElementById('sb-bg-canvas')` using the prefixed ID, not the original `bg-canvas`.

---

### GAP-05 · Heading Widget Builder — Strips Inner HTML from Mixed-Content Headings

**Article §14 Edge Case 10**

Full quote from article:
> "Detect headings with mixed inline elements. If the inline styling is limited to `<em>` (italic) and simple `<span>` with colour changes, generate the Heading widget with the full HTML as the title value."

The heading widget builder currently calls `$element->textContent` (or equivalent), stripping all markup.

**Fixes BUGs:** 09 (hero headline em + acid-word)

**Implementation spec:**
```php
// In heading widget builder:
$innerHtml = $element->innerHTML(); // preserve markup
$hasComplexInline = $this->hasComplexInlineEffects($element); // check for transforms, animations, gradients

if ($hasComplexInline) {
    return $this->buildHtmlWidget($element->outerHTML(), $settings, $cssClass);
} elseif ($this->hasMixedInlineContent($element)) {
    // em, strong, simple span — pass innerHTML as title
    $settings['title'] = $innerHtml;
} else {
    $settings['title'] = trim($element->textContent());
}
```

---

### GAP-06 · CSS Custom Property Resolution — Not Run Before Font URL Builder

**Article §12 Font Loading Strategy, §6 CSS Parser Requirements**

The article requires resolving `var(--font-display)` from `:root` declarations before building the Google Fonts URL. Custom property resolution must be part of Pass 1 (or early Pass 4) and the resolved values passed to Pass 6.

**Fixes BUGs:** 12 (invalid Fonts URL)

**Implementation spec:**
1. CSS parser collects all `:root { --var: value }` declarations into `$tokenMap`
2. Additionally collect from element-scoped custom properties (§14 Edge Case 6)
3. When any value references `var(--name)`, look up `$tokenMap['--name']` and substitute
4. Pass resolved `$fontMap = ['Syne', 'DM Sans', 'Space Mono']` to Pass 6 font URL builder

---

### GAP-07 · Bento Grid Span Mapping — `grid-column/row: span N` Not Translated

**Article §14 Edge Case 5**

The article specifies: CSS parser detects `grid-column: span N` on grid children. Values are converted:
- `span 5` → `grid_row_start: 1, grid_row_end: 6`
- `2 / span 3` → `grid_row_start: 2, grid_row_end: 5`

These are then written to the child container's Elementor settings JSON.

**Fixes BUGs:** 10 (bento grid layout collapsed)

**Note from article:** The editor may not visually honour explicit grid placements in the drag interface, but the rendered frontend will be correct.

---

### GAP-08 · Animated Sub-Components Inside Native Cards — Silently Dropped

**Article §13 Hybrid Detection, §6 Classification**

The article's key insight:
> "The hybrid boundary should be drawn at the component level, not the section level. A pricing section can have native containers for the grid + native widgets for the content + an HTML widget for the badge (absolute-positioned decorative)."

When a native container (bento card, pricing card) has child elements with animation indicators, those children must not be silently dropped. They should fall through to `HTML_WIDGET_COMPLEX` and be appended as an `html` widget inside the card's `elements` array.

**Fixes BUGs:** 08 (process visual), 11 (card-a-visual, pipeline bars)

---

### GAP-09 · Pass 9 Validation — Duplicate `_element_id` Not Caught

**Article §7 Pass 9**

After JSON assembly, validate all `_element_id` values. Detect duplicates. Auto-repair: regenerate or suffix the duplicate with a counter.

**Fixes BUGs:** 13 (two containers with `sb-pricing`)

---

### GAP-10 · Testimonial Card Grid — No Template Library Pattern

**Article §17 Workaround 3, §17 Template Library Matching highest-ROI feature**

The testimonial card grid (`testi-grid` + 3× `testi-card`) has no pattern entry in the template library. Without a match, the classifier cannot determine that `testi-grid` is a 3-column flex-row container and each `testi-card` is a flex-column card child.

**Fixes BUGs:** 05

**Add to template library:** Detect `testimonials` / `testi` / `reviews` / `social-proof` section vocabulary, match `N × card-like children`, produce a grid container + N card containers.

---

### GAP-11 · `isInner` Flag — May Be Incorrectly Set on Nested Containers

**Article §11 The `isInner` Flag**

> "`isInner: true` for containers nested inside other containers. The top-level sections have `isInner: false`. Everything nested inside has `isInner: true`. Getting this wrong causes layout rendering issues in the editor."

The V2 output shows some inner containers with `isInner: false` (e.g., footer grid sub-container at line 2685 has `isInner: true` — correct — but the `elements` within have `isInner: false`). Audit all nested containers for `isInner` correctness.

---

### GAP-12 · CSS Shorthand Expansion — Not Consistently Applied

**Article §6 CSS Parser Requirements**

> "Shorthand expansion. `padding: 60px 40px` must be expanded to individual top/right/bottom/left values. `border: 1px solid rgba(...)` must be split into border-width, border-style, border-color."

CSS shorthands must always be expanded before mapping to Elementor settings. Partial expansion (only top/bottom from `padding: T R B L`) causes incorrect spacing.

---

### GAP-13 · Clamp Values — Not Moved to Companion CSS with Fallback

**Article §6 CSS Parser Requirements**

> "`clamp(min, preferred, max)` cannot be set in Elementor's typography panel. Extract the min value as the Elementor setting and move the `clamp()` expression to companion CSS."

The hero headline uses `font-size: clamp(64px, 9vw, 140px)`. Elementor JSON should receive `120` (max) or `64` (min, safer) as the fixed fallback. The `clamp()` rule goes to companion CSS targeting `.sb-hero-headline .elementor-heading-title`.

---

### GAP-14 · Scroll Reveal Observer — Not Initialised After Elementor `frontend/init`

**Article §12 Scroll Reveal Observer Timing**

> "The observer should re-query for `.nx-reveal` elements after Elementor's `elementor/frontend/init` event fires."

The Global Setup scroll reveal JS must listen for `elementorFrontend.hooks.addAction('frontend/element_ready/global', ...)` or the `elementor/frontend/init` window event to catch dynamically-inserted widgets.

---

### GAP-15 · Multi-Page / Tab-Panel HTML — Hidden Sections Silently Skipped, No Report

**Article §14 Edge Case 3**

Elements with `display: none` toggled by JS are currently silently skipped with no user notification. The article requires flagging them in a conversion report and offering the user a choice.

---

### GAP-16 · Tailwind / Utility-First CSS — No Fallback Classification Path

**Article §14 Edge Case 1, §8 CSS Class Detection Case C**

When class names carry no semantic information (Tailwind utility classes), the engine must fall back to style-based classification:
- Very large font-size + heading tag + near top of document → HERO_HEADING
- `display: grid` + similar children structure → CARD_GRID
- Small font + all-caps + letter-spacing → TAG_LABEL

No such style-based fallback currently exists in the classifier.

---

### GAP-17 · No Pre-Conversion HTML Audit / Validation Report

**Article §17 Workaround 1**

Before conversion the plugin should surface: framework signatures (Tailwind, Bootstrap, GSAP), external CSS that cannot be inlined, Elementor HTML signatures (needs extraction mode), minification detection, and missing `:root` variables.

---

### GAP-18 · Editability Scoring System — Not Implemented

**Article §13 Editability Scoring**

The article defines a 0–10 editability score to drive the native vs HTML widget decision at the leaf element level. Score ≥ 7 → native widget. Score < 4 → HTML widget. Score 4–6 → tiebreaker rule. This ensures granular, component-level hybrid decisions rather than all-or-nothing section casting.

---

### GAP-19 · Complex Inline Button Children — Not Handled

**Article §6 Classification, §10 Widget Decision Tree**

> "Exception: if `<a>` contains non-text/non-span children → HTML_WIDGET_COMPLEX"

Buttons that contain nested `<span>` for arrow icons, or multiple child elements (icon + label), must be checked: if the `<a>` or `<button>` has block-level children or JS event bindings → `HTML_WIDGET_COMPLEX`. Currently all `<a>` with button styling → `BUTTON_WIDGET` regardless.

---

### GAP-20 · SVG Inline Elements — No Classification Rule

**Article §14 Edge Case 4**

Inline `<svg>` elements need classification:
- `aria-hidden="true"` → `HTML_WIDGET` (decorative)
- Only content of its element → `HTML_WIDGET` (illustration), flagged in report
- Inside a `<button>` or `<a>` → part of the Button widget HTML
- `<title>` or `<desc>` accessible text → `HTML_WIDGET` with text noted in report

No SVG classification rules currently exist.

---

### GAP-21 · GSAP / Complex Animation Library Detection — No Detection or Warning

**Article §14 Edge Case 7**

Detect GSAP imports (`<script src="*gsap*">` or `import gsap from`). Flag all elements where `gsap.` / `TweenLite.` / `TimelineMax.` is called. Classify as `HTML_WIDGET_ANIMATED`. Add conversion report warning: "GSAP animations detected — WordPress page must have GSAP loaded globally."

---

### GAP-22 · mix-blend-mode Detection — Not Moved to Companion CSS with Warning

**Article §14 Edge Case 8**

`mix-blend-mode` on text or elements creates effects with no Elementor panel equivalent. Must be detected and moved to companion CSS. Add report warning: "mix-blend-mode may not display in Elementor editor preview."

---

### GAP-23 · Elementor HTML Extraction Mode — Not Implemented

**Article §14 Edge Case 9**

If uploaded HTML was copied from Elementor's rendered output, it contains Elementor class signatures (`elementor-section`, `e-con`, `elementor-widget-*`, `data-element_type`). Offer "Elementor HTML extraction mode" to read data attributes directly rather than inferring structure.

---

### GAP-24 · Partial Conversion with Guidance Comments — Not Implemented

**Article §17 Workaround 4**

When the engine has low confidence about a section, instead of producing poor JSON, generate an HTML widget containing the original HTML with an inline guidance comment block explaining the structure and suggesting how to rebuild it natively. This is far more useful than a broken native widget.

---

### GAP-25 · Complex Component Types Not Covered by Current Decision Tree

**Not yet in article — derived from edge cases seen in wild and the article's general principles**

The following component archetypes appear commonly in real-world HTML prototypes and are not yet covered by the engine's decision tree or template library:

| Component | Detection Signal | Recommended Elementor Strategy |
|---|---|---|
| **Carousel / Slider** | `overflow: hidden` wrapper + multiple same-width children + JS `translate/scroll` | HTML widget (Elementor Pro has a carousel widget; free → HTML widget with library JS) |
| **Accordion / FAQ** | `<details>/<summary>` or `<div>` with JS toggle + `height: 0; overflow: hidden` | HTML widget (Elementor Pro: Accordion; free → HTML widget with inline toggle JS) |
| **Tab panels** | Multiple `.tab-panel` with `display: none` toggled by JS | Each tab → separate HTML widget; tab nav → HTML widget with JS |
| **Modal / Lightbox** | `position: fixed; z-index: 9999` + `display: none` toggle | SKIP from main template; add as popup suggestion in report |
| **Sticky navigation** | `position: sticky; top: 0` | Native container with companion CSS `position: sticky` |
| **Split-screen layout** | Two `50vw` children, one fixed image one scrollable text | FLEX_ROW container with 2 inner containers; image → Image widget |
| **Parallax sections** | `background-attachment: fixed` or JS scroll transform | Native container; `background-attachment: fixed` → companion CSS with warning |
| **Video background** | `<video autoplay muted loop>` inside a section | HTML widget (video element) with companion CSS for positioning |
| **Lottie animation** | `<lottie-player>` or `<script src="*lottie*">` | HTML widget; add report warning about Lottie JS dependency |
| **Countdown timer** | JS `setInterval` with date-based target | HTML widget (`HTML_WIDGET_ANIMATED`); report that timer resets on page load |
| **Progress bars (dynamic)** | JS-driven `width` changes on bar fills | HTML widget with the JS preserved |
| **Star ratings** | `★` or SVG star elements in a row | HTML widget (simple enough but not a standard Elementor Free widget) |
| **Map embeds** | `<iframe src="*maps.google*">` or `<div id="map">` with JS map init | HTML widget (or Elementor Pro Map widget); add placeholder notice |
| **Code snippets with highlight.js** | `<pre><code class="language-*">` | HTML widget; preserve syntax highlighting JS in Global Setup |
| **Notification bar / Banner** | Full-width `position: fixed/sticky; top: 0` strip, usually dismissible | HTML widget (SKIP main layout, add guidance comment) |
| **Cookie consent** | JS-generated overlay | SKIP + report notice |

---

## Part 3: Prioritised Fix Roadmap

### P0 — Critical (Core Output Broken)

| ID | Fix | Article Ref | Fixes Bugs |
|---|---|---|---|
| F-01 | Extend segmenter to detect top-level `<div>` sections | §7 Pass 1, §3 Stage 1 | BUG-02, 03, 04 |
| F-02 | Fix recursive tree walker double-visit | §7 Pass 7 | BUG-06 |
| F-03 | Content extraction for all template library patterns (pricing, testimonials) | §17 Workaround 3 | BUG-05, 07 |
| F-04 | Canvas detection in Pass 1 + canvas injection in Pass 6 Global Setup | §7 Pass 1, §12 | BUG-01 |

### P1 — High (Major Output Quality)

| ID | Fix | Article Ref | Fixes Bugs |
|---|---|---|---|
| F-05 | Resolve CSS custom properties before font URL builder | §12, §6 | BUG-12 |
| F-06 | Heading widget: pass inner HTML for mixed-content headings | §14 Edge Case 10 | BUG-09 |
| F-07 | Preserve animated sub-components as HTML widgets inside native card containers | §13 Hybrid Detection | BUG-08, 11 |
| F-08 | Pass 9: duplicate `_element_id` detector and auto-repair | §7 Pass 9 | BUG-13 |
| F-09 | Add testimonial card grid to template library | §17 Workaround 3 | BUG-05 |
| F-10 | Bento grid: map `grid-column/row: span N` to Elementor child settings | §14 Edge Case 5 | BUG-10 |

### P2 — Medium (Architecture Robustness)

| ID | Fix | Article Ref | Fixes Bugs/Gaps |
|---|---|---|---|
| F-11 | CSS clamp → companion CSS with fixed-px fallback to JSON | §6 CSS Parser | GAP-13 |
| F-12 | CSS shorthand expansion (padding, border, margin) | §6 CSS Parser | GAP-12 |
| F-13 | Scroll reveal observer re-query after Elementor `frontend/init` | §12 | GAP-14 |
| F-14 | Complex button children → HTML_WIDGET_COMPLEX exception | §10 Widget Tree | GAP-19 |
| F-15 | SVG classification rules | §14 Edge Case 4 | GAP-20 |
| F-16 | Update companion CSS class map header to reflect all output sections | — | BUG-14 |
| F-17 | Remove CSS Prefix manual input from admin UI; auto-detect on upload | §8 Prefix Detection | — |

### P3 — Architecture Features (Article-Specified, Not Yet Built)

| ID | Feature | Article Ref |
|---|---|---|
| F-18 | Tailwind/utility-CSS fallback classification via style heuristics | §14 Edge Case 1, §8 Case C |
| F-19 | Pre-conversion HTML audit / validation report | §17 Workaround 1 |
| F-20 | Editability scoring system (0–10) | §13 |
| F-21 | GSAP / animation library detection + warnings | §14 Edge Case 7 |
| F-22 | mix-blend-mode detection → companion CSS + warning | §14 Edge Case 8 |
| F-23 | Multi-page / tab-panel section opt-in | §14 Edge Case 3 |
| F-24 | Partial conversion with guidance comment blocks | §17 Workaround 4 |
| F-25 | Elementor HTML extraction mode | §14 Edge Case 9 |
| F-26 | Complex component decision tree (carousel, accordion, modal, etc.) | §10, GAP-25 |
| F-27 | Responsive JSON settings (`_tablet`, `_mobile` keys) | §20 |
| F-28 | Chunked/multi-turn AI conversion for large HTML files | §5 |
| F-29 | Interactive correction mode (post-conversion re-classification UI) | §17 Workaround 2 |
| F-30 | Confidence scoring on Pass 3 classifications | §7 Pass 3 |

---

## Quick Reference: Source → V2 Output Section Map

```
nexus-landing.html body                  V2 JSON
──────────────────────────────────       ──────────────────────────────────
<style> (fonts, vars, animations)    →   Widget: sb-global-setup (html) ✅ (partial)
<div id="cursor">                    →   SKIP → recreated in Global Setup ✅
<canvas id="bg-canvas">             →   ❌ NOT injected in Global Setup
<nav id="navbar">                    →   Widget: sb-nav-widget (html) ✅
<section class="hero">               →   Container: sb-hero ✅
  .hero-eyebrow                      →     Widget: html ✅
  h1.hero-headline                   →     Widget: heading ⚠️ (em/span stripped)
  .hero-bottom                       →     Container: sb-hero-bottom ✅
    .hero-sub                        →       Widget: text-editor ✅
    .hero-actions                    →       Container ✅
      .btn-primary                   →         Widget: button ✅
      .btn-ghost                     →         Widget: text-editor ✅
<div class="marquee-wrap">           →   ❌ NOT IN OUTPUT
<div class="stats">                  →   ❌ NOT IN OUTPUT
<section class="features" id="features">
  .section-header                    →   Container: sb-section-header ✅
  .bento (CSS grid, 6 cards)         →   Container: sb-bento-grid ⚠️ (no spans)
    .card-a (+ .card-a-visual orbs)  →     Container: sb-bento-card-a ⚠️ (visual missing)
    .card-b                          →     Container: sb-bento-card-b ✅
    .card-c                          →     Container: sb-bento-card-c ✅
    .card-d (+ .pipeline bars)       →     Container: sb-bento-card-d ⚠️ (pipeline missing)
    .card-e                          →     Container: sb-bento-card-e ✅
    .card-f                          →     Container: sb-bento-card-f ✅
<section class="process" id="process">
  .section-header                    →   Container: sb-section-header ✅
  .process-grid                      →   Container: sb-process-grid ⚠️
    .process-steps 4×                →     ❌ → 9 steps, wrong/duplicate text
    .process-visual.reveal.visible   →     Widget: sb-orbital-widget ⚠️ (wrong column)
<section class="testimonials">
  .section-header                    →   ⚠️ Header only, mislabelled as sb-pricing
  .testi-grid 3× .testi-card        →   ❌ CARDS MISSING
<section class="pricing" id="pricing">
  .section-header                    →   Container: sb-pricing (2nd copy) ⚠️
  .pricing-grid 3× .price-card      →     ⚠️ 3 empty scaffolds, $40 defaults, no features
<div class="cta-section reveal">     →   ❌ NOT IN OUTPUT
<footer>
  .footer-top (4-col grid)           →   Grid: sb-footer-top ✅
  .footer-bottom (copy + status)     →   Widget: html ✅
<script> (cursor + canvas + reveal)  →   ⚠️ Cursor JS ✅, Canvas JS ❌, Reveal JS ✅
```

---

*Last updated: 2026-04-10. Update this file and mark items ✅ FIXED as fixes are applied.*
