# Skill-Infused Pipeline Architecture: Bringing the Native Converter to AI Parity and Beyond

## A Deep Systems Design Document for the HTML-to-Elementor Conversion Plugin v2

---

## Table of Contents

1. [The Parity Problem — Why Native Falls Short and How to Close the Gap](#parity)
2. [What "Skills" Mean in This Context](#skills-concept)
3. [The Skill Module System — Architecture and Interface](#skill-architecture)
4. [AI Converter: Per-Pipeline Skill Injection](#ai-skills)
   - Pass 1: Design Intelligence Skill
   - Pass 2: Layout Architecture Skill
   - Pass 3: Content Strategy Skill
   - Pass 4: CSS Cascade Mastery Skill
   - Pass 5: Naming Systems Skill
   - Pass 6: Animation and Effects Skill
   - Pass 7: Elementor Schema Mastery Skill
   - Pass 8: CSS Architecture Skill
   - Pass 9: Quality Assurance Skill
5. [Native Converter: Expert-Trained Skill Modules](#native-skills)
   - Visual Fingerprinting Engine
   - Design Pattern Recognition System
   - Typography Scale Detector
   - Spacing System Analyser
   - Color Harmony Classifier
   - Component Boundary Detector
   - Interactive State Modeller
   - Editability Prediction Engine
   - Semantic Graph Analyser
   - Responsive Intent Inferrer
6. [Cross-Cutting Improvements to All Passes](#cross-cutting)
7. [The Pattern Library — Native AI](#pattern-library)
8. [Confidence Scoring and Fallback Chains](#confidence)
9. [The Skill Handoff Protocol — How Passes Share Knowledge](#handoff)
10. [Robustness Engineering — Making Each Pass Failure-Safe](#robustness)
11. [The Offline Learning Loop — Getting Better Without AI](#offline-learning)
12. [Edge Case Hardening — Expanded Catalogue](#edge-cases)
13. [Performance Architecture — Speed Without Sacrificing Quality](#performance)
14. [The Correction Feedback Loop — Closing the Human-in-the-Loop Gap](#correction-loop)
15. [Benchmark Targets — Defining "Parity"](#benchmarks)
16. [Implementation Roadmap](#roadmap)

---

## 1. The Parity Problem — Why Native Falls Short and How to Close the Gap {#parity}

The previous architecture document established that the native converter would achieve roughly 80% quality on semantic HTML, 60% on utility-class HTML, and 40% on obfuscated HTML. Those numbers are acceptable for a first version but not for a production tool that aspires to be the best in class. The AI converter, given good preprocessing and prompting, can consistently achieve 90%+ on all input types because it brings semantic understanding — the ability to reason about design intent rather than just parse syntax.

To close this gap without requiring an AI API, the native converter must become something more than a rule engine. It must be a knowledge-dense system where every pipeline stage operates at the level of a domain expert. The key insight driving this entire upgrade is:

**A rule engine is only as good as the rules someone thought to write. An expert system is as good as the domain knowledge it encodes, combined with the ability to reason about novel cases using first principles.**

The difference between these two is not academic. A rule engine handles the cases its author anticipated. An expert system handles those plus the cases that can be reasoned about from deeper principles. The native converter must become an expert system.

There are three techniques available to achieve this without AI inference:

**1. Skill modules** — Discrete, deeply trained knowledge packages applied at each pipeline stage, encoding expert-level heuristics built from exhaustive cataloguing of real-world cases.

**2. Pattern library matching** — A growing library of known design patterns with fuzzy matching, so common structures are recognised at recognition speed rather than being reconstructed from first principles every time.

**3. Confidence-gated fallback chains** — Instead of a single classification attempt, each stage runs multiple classification strategies in order of confidence, falling through to progressively more conservative (but reliable) options until a threshold is met.

The combination of these three produces a native converter that, for the design patterns it has seen, is faster and more consistent than the AI converter (deterministic, no API latency, no token cost), and for novel patterns falls back gracefully rather than catastrophically.

---

## 2. What "Skills" Mean in This Context {#skills-concept}

In Claude's own architecture, skills are specialised knowledge modules — structured expert documents that contain not just facts but reasoning patterns, heuristics, examples, edge cases, and decision frameworks for a specific domain. When Claude reads a SKILL.md file, it does not just absorb facts — it absorbs a way of thinking about a domain.

For this plugin, a Skill means:

**For the AI converter:** A structured prompt module injected into the system prompt for a specific pipeline stage. It encodes the expert knowledge that Claude needs to perform that stage at the level of a specialist — not a generalist who knows a bit about everything, but a practitioner who knows that domain deeply. Each pass gets its own Skill injected, replacing generic instructions with expert-level guidance.

**For the native converter:** A compiled knowledge module — a PHP/JS class or module that encodes the same expert knowledge as machine-executable rules, decision trees, weighted heuristics, lookup tables, and pattern matchers. The "skill" is the depth and accuracy of those rules, built by exhaustively studying the domain the way an expert would.

The critical difference from the previous architecture: previously, each pipeline pass had a function that did its job. Now, each pipeline pass has a Skill module that makes it a domain expert, plus the function that applies that expertise. The Skill is the knowledge layer. The function is the execution layer.

---

## 3. The Skill Module System — Architecture and Interface {#skill-architecture}

Every Skill module, regardless of whether it is used by the AI or native converter, implements a common interface:

```
SkillModule {
  name: string
  version: string
  domain: string
  
  // For AI converter: returns prompt text to inject
  getSystemPrompt(): string
  getExamples(): Example[]
  getConstraints(): Constraint[]
  
  // For native converter: returns executable logic
  getHeuristics(): Heuristic[]
  getPatternLibrary(): Pattern[]
  getDecisionTree(): DecisionNode
  getFallbackChain(): FallbackStrategy[]
  
  // Shared
  getConfidenceThresholds(): ThresholdMap
  getQualityChecks(): QualityCheck[]
  getKnownEdgeCases(): EdgeCase[]
}
```

This shared interface matters for three reasons:

First, it enables the AI and native converters to use the same Skill definitions as their source of truth. When you update a Skill's knowledge (add a new edge case, improve a heuristic), both converters benefit.

Second, it makes Skills versioned and testable independently of the pipeline. You can update the Layout Architecture Skill without touching the pipeline code, and run the skill's own test suite to verify the change did not regress any known cases.

Third, it creates a clear separation between knowledge (Skills) and execution (pipeline passes). The pipeline knows how to apply expertise; the Skill knows what the expertise is.

### Skill Confidence Interface

Every Skill must return not just a result but a confidence score (0.0–1.0) for that result. The pipeline uses this score to decide whether to accept the result, try the next strategy in the fallback chain, or escalate to a higher-cost approach.

```
SkillResult {
  result: any
  confidence: float (0.0–1.0)
  reasoning: string[]     // human-readable explanation of the decision
  warnings: string[]      // concerns that did not block the decision
  alternatives: {         // other classifications considered, with scores
    result: any
    confidence: float
  }[]
}
```

The `reasoning` array is important. It feeds the conversion report shown to users, making the plugin's decisions transparent and auditable. It also feeds the correction feedback loop (Section 14) — users who disagree with a classification can see why it was made and provide targeted corrections.

---

## 4. AI Converter: Per-Pipeline Skill Injection {#ai-skills}

For the AI converter, each pipeline pass that calls the Claude API receives a tailored system prompt that injects the relevant Skill. The previous architecture used a single monolithic system prompt for the entire conversion. This upgrade replaces that with per-pass Skill injection, giving Claude exactly the specialist knowledge needed for each stage.

### Pass 1: Design Intelligence Skill

**Domain:** Design systems, brand identity, visual design principles, design token architecture.

**What this Skill teaches Claude:**

The Design Intelligence Skill enables Claude to read a design not as a document but as an intentional system. It knows that colours in a well-designed prototype are not arbitrary — they follow a palette logic (primary, secondary, accent, background, surface, text, muted text, border). It knows that typography is not arbitrary — it follows a scale (display, heading, subheading, body, caption, label). It knows that spacing is not arbitrary — it usually follows a grid (4px, 8px, multiples thereof).

```
DESIGN INTELLIGENCE SKILL v1.0
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

You are a senior design systems architect with 10 years of experience 
building and auditing brand design systems. Your task is to extract the 
complete design system from the provided HTML prototype.

COLOUR SYSTEM EXTRACTION
─────────────────────────
Identify the colour roles, not just the values:
- Background (darkest or lightest surface, used for page bg)
- Surface (slightly lighter/darker than background, used for cards)
- Surface Elevated (cards that sit above surface)
- Primary Text (main content text)
- Secondary Text (muted/supporting text, typically 40-60% opacity)
- Hint Text (placeholder/tertiary text, typically 20-35% opacity)
- Accent / Primary Action (CTA buttons, highlighted elements, links)
- Accent Secondary (secondary interactive elements)
- Border / Stroke (dividers, card borders, typically low opacity)
- Success / Warning / Error (if present)

Look for: CSS custom properties on :root, repeated colour values, 
rgba with consistent alpha values, colour relationships (accent at 
different opacities = surface, border).

Produce: { role: hex/rgba, ... } design token map.

TYPOGRAPHY SCALE EXTRACTION  
────────────────────────────
Identify the type hierarchy:
- Display (largest, hero headlines, weight 700-900)
- Heading L (section titles, weight 700-800)
- Heading M (card titles, weight 600-700)
- Heading S (step titles, widget labels, weight 500-600)
- Body L (lead paragraphs, large body text)
- Body M (standard body text)
- Body S (captions, footnotes)
- Label / Tag (uppercase, letter-spaced, often mono)
- Mono (code, terminal, technical labels)

For each: font-family, font-weight, font-size, line-height, 
letter-spacing, colour role. Note if the same font-family is used 
across multiple levels (common) or if distinct families are used per level.

SPACING SYSTEM EXTRACTION
──────────────────────────
Identify the base spacing unit:
- Check padding/margin values for common multiples
- 4px base: values will be 4, 8, 12, 16, 24, 32, 40, 48, 60, 80...
- 8px base: values will be 8, 16, 24, 32, 40, 48, 64, 80, 96...
- Custom: document the actual values used
Note section-level vs component-level vs inline spacing patterns.

DESIGN PATTERN IDENTIFICATION
──────────────────────────────
Identify which of these named patterns are present:
Hero (eyebrow + headline + sub + CTA), Bento Grid, Stats Row,
Process Steps, Testimonial Cards, Pricing Tiers, Full-Bleed CTA,
Marquee Strip, Fixed Nav, Card Grid, Two-Column Feature, 
Icon Feature List, FAQ Accordion, Timeline, Team Grid

For each pattern found: note its section boundary, primary colours used,
typographic levels used, interactive states.

OUTPUT FORMAT
──────────────
Return a structured JSON design system object. Do not guess values — 
only include values explicitly present in the CSS. Flag any ambiguous 
values with a confidence score.
```

**Why this matters:** When Pass 7 (JSON Assembly) needs to know what colour to set as the button background, it does not have to re-parse the entire CSS. It reads from the design system object produced by Pass 1. Every downstream pass benefits from the design intelligence extracted here.

---

### Pass 2: Layout Architecture Skill

**Domain:** CSS Grid, CSS Flexbox, responsive layout, Elementor container model.

```
LAYOUT ARCHITECTURE SKILL v1.0
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

You are a CSS layout architect who has implemented hundreds of 
complex grid and flexbox layouts and has deep knowledge of how 
CSS layout specifications map to Elementor's container system.

ELEMENTOR CONTAINER MODEL — EXPERT KNOWLEDGE
─────────────────────────────────────────────
Elementor has three container types in v3.x+:

1. FLEX CONTAINER (default)
   Use for: row/column layouts, hero sections, card interiors, 
   any layout where children need flex alignment
   Key settings: flex_direction, justify_content, align_items, 
   flex_wrap, gap (column + row separately)
   
2. GRID CONTAINER (container_type: "grid")  
   Use for: any CSS Grid layout — bento grids, card grids, 
   footer columns, feature icon grids
   Key settings: grid_columns_fr (e.g. "1fr 1fr 1fr"), 
   grid_rows_fr (usually "auto"), gap
   Child settings: grid_column_start, grid_column_end, 
   grid_row_start, grid_row_end
   Critical: Elementor Grid uses 1-based indexing for start/end.
   "span 3" starting at column 1 = grid_column_start:1, 
   grid_column_end:4 (NOT 3, because CSS Grid end is exclusive)
   
3. NESTED INNER CONTAINER (isInner: true)
   Any container inside another container.
   Must have isInner: true in JSON.
   Elementor renders these differently from top-level containers.

LAYOUT PATTERN → ELEMENTOR MAPPING
────────────────────────────────────
display: flex, flex-direction: row → FLEX CONTAINER, direction: row
display: flex, flex-direction: column → FLEX CONTAINER, direction: column
display: flex (default, no direction) → FLEX CONTAINER, direction: row
display: grid, simple columns → GRID CONTAINER
display: grid, with named areas → GRID CONTAINER + explicit child placement
display: block → FLEX CONTAINER, direction: column (Elementor has no block)
display: inline-flex → FLEX CONTAINER (drop inline, Elementor is always block-level)
position: absolute children → do NOT map position to Elementor; use companion CSS
float layouts → convert to FLEX CONTAINER (floats are legacy, Elementor ignores them)

CSS GRID PLACEMENT RULES
─────────────────────────
When you see grid-column: span N on a child:
  grid_column_start: [auto or explicit start]
  grid_column_end: [start + N] (Elementor uses exclusive end values)

When you see grid-column: A / B:
  grid_column_start: A
  grid_column_end: B

When you see grid-column: A / span N:
  grid_column_start: A
  grid_column_end: A + N

When you see grid-row: span N on a child:
  Same pattern for row start/end.

COMMON LAYOUT FAILURES TO AVOID
─────────────────────────────────
- Do NOT create a flex container for every <div>. Only create containers
  where the div has meaningful layout children (2+).
- Do NOT use GRID CONTAINER for a simple 2-column div — use FLEX ROW instead
  unless the content requires explicit cell sizing with fr units.
- Do NOT nest containers more than 4 levels deep. Flatten where possible.
- Single-child containers: if a container has only one child, consider
  whether the container is necessary. Often the container exists only
  to add padding — move the padding to the child widget settings instead.
- Auto height vs min-height: only add min_height to a container when the
  design clearly requires it (hero sections, visual panels). Do not add
  min_height to every container.

RESPONSIVE LAYOUT INTENT
─────────────────────────
For every FLEX ROW container, state explicitly:
- Should it stack to column on tablet? (default: yes for side-by-side 
  content, no for nav items, tag rows, button groups)
- Should it stack to column on mobile? (default: yes for almost everything)
For every GRID CONTAINER, state:
- Column count on tablet (usually half desktop, min 2)
- Column count on mobile (usually 1)
```

---

### Pass 3: Content Strategy Skill

**Domain:** UX writing, information architecture, content editability, client communication.

```
CONTENT STRATEGY SKILL v1.0
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

You are a UX content strategist with experience running content audits 
for agency clients. You know exactly which content on a page a client 
will want to edit and which they will never touch.

EDITABILITY CLASSIFICATION SYSTEM
───────────────────────────────────
Score each content element 0-10 for how likely a non-developer client 
is to want to edit it after the site launches:

10 — MUST BE NATIVE WIDGET
  Hero headlines, hero subheadings, CTA button text, section titles,
  body paragraphs, testimonial quotes, person names and titles,
  pricing plan names and amounts, pricing feature lists, CTA section
  headlines, footer company description. Clients ask to change these
  on day 2 of every project.

7-9 — SHOULD BE NATIVE WIDGET
  Step titles and descriptions, feature card titles and body text,
  stat labels (but not animated numbers), eyebrow/tag text,
  blog post titles and excerpts if present. Clients change these
  during the first month.

4-6 — EITHER IS ACCEPTABLE
  Static stat numbers (non-animated), author initial avatars,
  nav link labels (unless they link to pages the client manages),
  footer column headings. Some clients care, some don't.

1-3 — HTML WIDGET PREFERRED
  Animated counter numbers, terminal/code block content,
  pipeline step labels inside visual components, orbital node icons,
  badge text positioned absolutely over cards ("MOST POPULAR").
  Almost no client will ever ask to change these.

0 — ALWAYS HTML WIDGET
  Any animated element, canvas content, cursor, particle system,
  orbital rings, marquee text (it should be in a config variable
  in the widget, not exposed to clients), blinking cursors,
  SVG illustrations.

SPECIAL CASES
─────────────
Hero headline with styled spans (italic outline text, accent word):
  Classify as HEADING WIDGET with HTML in the title field.
  Companion CSS handles the styling of em and span elements.
  Client edits the text, the styles are preserved by the class.
  
Marquee content:
  Make it an HTML widget BUT put the actual text items in a JavaScript
  array at the top of the widget so a developer can update them
  without understanding the animation code.

Pricing badge ("MOST POPULAR"):
  HTML widget inside the pricing card container.
  Position absolute in companion CSS.
  Note in conversion report: "Badge text can be edited in the 
  HTML widget for this card."

CONTENT STRUCTURE PATTERNS
───────────────────────────
Recognise these standard content structures and map them correctly:

EYEBROW + TITLE + DESCRIPTION pattern:
  eyebrow → Text Editor widget (class: [p]-section-tag)
  title → Heading widget h2 (class: [p]-section-title)  
  description → Text Editor widget (class: [p]-section-desc)
  Wrap in a container with class [p]-section-header

CARD pattern (title + body + optional visual + optional CTA):
  container → FLEX COLUMN inner container (class: [p]-card)
  title → Heading h3 (class: [p]-card-title)
  body → Text Editor (class: [p]-card-body)
  visual → HTML widget (class: [p]-card-visual)
  CTA → Button widget OR Text Editor with link

PROCESS STEP pattern (number + title + description):
  container → FLEX ROW inner container (class: [p]-process-step)
  number → HTML widget div (class: [p]-step-num) [editability 3]
  content wrapper → FLEX COLUMN inner container
  title → Heading h4 (class: [p]-step-title)
  description → Text Editor (class: [p]-step-desc)

TESTIMONIAL pattern (quote + author):
  container → FLEX COLUMN inner container (class: [p]-testi-card)
  quote → Text Editor (class: [p]-testi-quote)
  author block → HTML widget (class: [p]-testi-author) 
    [avatar circle + name + role, editability 4, 
     but name/role are in the HTML so a developer can update]
```

---

### Pass 4: CSS Cascade Mastery Skill

**Domain:** CSS specificity, inheritance, custom properties, computed values, the cascade.

```
CSS CASCADE MASTERY SKILL v1.0
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

You are a CSS architect who has written the CSS for thousands of 
production websites. You understand the cascade at specification level.

SPECIFICITY CALCULATION
────────────────────────
Calculate specificity as [id, class, type]:
- ID selector (#foo): [1,0,0]
- Class selector (.foo), attribute ([foo]), pseudo-class (:hover): [0,1,0]
- Type selector (div), pseudo-element (::before): [0,0,1]
- Inline style: [1,0,0,0] (wins over all)
- !important: override all specificity

When multiple rules target the same element, the highest specificity wins.
For equal specificity, the later rule in source order wins.

PROPERTY CLASSIFICATION FOR ELEMENTOR
───────────────────────────────────────
ELEMENTOR PANEL SETTING (translate directly):
  font-family, font-weight, font-size (px), line-height,
  letter-spacing, color (text), background-color (solid/rgba),
  padding (all sides), margin (all sides), border (all sides),
  border-radius (uniform), width, max-width, min-height,
  display:flex direction/justify/align, gap

COMPANION CSS (cannot express in Elementor panel):
  font-size with clamp() — extract min as panel fallback, 
    put full clamp() in companion CSS under element's class
  color: transparent — companion CSS
  -webkit-text-stroke — companion CSS
  background: linear-gradient, radial-gradient — companion CSS
  background: repeating-*, background-image — companion CSS
  box-shadow — companion CSS
  text-shadow — companion CSS
  filter, backdrop-filter — companion CSS
  overflow (hidden/visible/auto) — companion CSS
  position (relative/absolute/fixed/sticky) — companion CSS
  z-index — companion CSS
  transform, transition, animation — companion CSS
  ::before, ::after content — companion CSS
  :hover, :focus states — companion CSS
  CSS custom properties on non-:root elements — companion CSS
  mix-blend-mode — companion CSS
  clip-path, mask — companion CSS
  pointer-events: none — companion CSS
  user-select: none — companion CSS

HTML WIDGET INTERNAL STYLE (belongs with the component):
  @keyframes animations
  requestAnimationFrame JS
  canvas API calls
  IntersectionObserver JS
  Any property that only makes sense attached to its animated element

CUSTOM PROPERTY RESOLUTION
────────────────────────────
When you encounter var(--token-name):
1. Look up --token-name in the :root custom properties
2. If found: substitute the resolved value
3. If not found in :root: look for the nearest ancestor element 
   that declares --token-name
4. If still not found: use the fallback value if provided (var(--x, fallback))
5. If no fallback: use the property's initial value and flag as warning

SHORTHAND EXPANSION RULES
──────────────────────────
padding: A → top:A, right:A, bottom:A, left:A
padding: A B → top:A, right:B, bottom:A, left:B  
padding: A B C → top:A, right:B, bottom:C, left:B
padding: A B C D → top:A, right:B, bottom:C, left:D

margin: same pattern

border: W S C → width:W, style:S, color:C (all sides)
border-radius: A → all corners A
border-radius: A B → top-left+bottom-right:A, top-right+bottom-left:B
border-radius: A B C D → TL:A, TR:B, BR:C, BL:D

font: [style] [weight] size/[line-height] family
  e.g., font: 800 120px/0.92 'Syne', sans-serif
  → weight:800, size:120px, line-height:0.92, family:Syne

RGBA OPACITY HANDLING
──────────────────────
rgba(R,G,B,0.03) → Use as Elementor background_color directly.
  Elementor accepts rgba strings. Do not convert to hex.
rgba(R,G,B,0.1) as border-color → Use as Elementor border_color directly.
rgba(255,255,255,0.5) → Elementor accepts this format.
Note: Elementor's color picker in the UI shows hex but accepts rgba in JSON.
```

---

### Pass 5: Naming Systems Skill

**Domain:** CSS naming conventions, BEM, semantic HTML, accessibility.

```
NAMING SYSTEMS SKILL v1.0
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

You are a front-end architect who has audited and standardised CSS 
naming systems across large multi-team projects. You produce class 
names that are semantic, predictable, collision-safe, and maintainable.

CLASS NAME GENERATION RULES
────────────────────────────
Given a project prefix P and an element E in section S:

Top-level section container: [P]-[S]
Section header row: [P]-section-header OR [P]-[S]-header
Section title heading: [P]-section-title OR [P]-[S]-title
Section tag/eyebrow: [P]-section-tag OR [P]-[S]-tag  
Section description: [P]-section-desc OR [P]-[S]-desc
Section body content area: [P]-[S]-body

Inner layout containers:
  [P]-[S]-row (flex row)
  [P]-[S]-grid (grid container)
  [P]-[S]-col (flex column, when multiple columns)
  [P]-[S]-actions (button group container)
  [P]-[S]-bottom (bottom row in a section)
  [P]-[S]-inner (generic inner wrapper)

Card/item containers:
  [P]-[S]-card (generic card)
  [P]-[S]-item (list item)
  [P]-[S]-cell (grid cell)

Widget-level classes — EVERY WIDGET GETS ONE:
  [P]-[S]-headline (hero main heading)
  [P]-[S]-sub (hero subtitle)
  [P]-[S]-title (section-level h2)
  [P]-[S]-card-title (card-level h3)
  [P]-[S]-step-title (process step heading)
  [P]-[S]-body-text (body paragraph)
  [P]-[S]-card-body (card body paragraph)
  [P]-[S]-step-desc (process step description)
  [P]-[S]-tag (eyebrow label widget)
  [P]-[S]-author (testimonial author widget)
  [P]-[S]-quote (testimonial quote widget)
  [P]-[S]-amount (pricing amount)
  [P]-[S]-plan (pricing plan name)
  [P]-[S]-period (pricing billing period)
  [P]-[S]-feats (pricing features list)
  
Button classes (shared across sections):
  [P]-btn-primary (main acid/brand CTA)
  [P]-btn-secondary (secondary CTA)
  [P]-btn-ghost (underline/transparent CTA)
  [P]-btn-dark (dark fill CTA, used in CTA sections)
  [P]-btn-outline-dark (outline on light background)
  [P]-btn-price (pricing card CTA, transparent)
  [P]-btn-price-featured (featured pricing card CTA)

State classes (added by JS):
  [P]-reveal (hidden initially, animated in on scroll)
  [P]-visible (added by IntersectionObserver)
  [P]-d1, [P]-d2, [P]-d3 (stagger delays 0.1s, 0.2s, 0.3s)
  [P]-scrolled (added to nav on scroll)
  [P]-counted (added to stat cells when counter starts)
  [P]-active (generic active state)
  [P]-loaded (page/section fully loaded)

HTML widget-specific classes:
  [P]-[S]-visual (visual/diagram HTML widget)
  [P]-[S]-terminal (terminal/code HTML widget)
  [P]-[S]-pipeline (pipeline bars HTML widget)
  [P]-[S]-orbital (orbital/ring animation HTML widget)
  [P]-[S]-counter (animated counter HTML widget)
  [P]-[S]-marquee (marquee strip HTML widget)
  [P]-global-setup (the first HTML widget, global setup)
  [P]-nav (fixed navigation HTML widget)

ELEMENT ID GENERATION
──────────────────────
Only top-level section containers get element IDs (for anchor navigation).
Pattern: [P]-[section-name]
Examples: nx-hero, nx-features, nx-process, nx-testimonials, 
  nx-pricing, nx-cta, nx-footer

Do NOT give element IDs to inner containers or widgets.
Multiple IDs on a page create specificity and accessibility issues.

PREFIX CONFLICT CHECK
──────────────────────
Avoid these prefixes — they are commonly used by WordPress themes/plugins:
  wp-, wc-, elementor-, el-, e-, et-, divi-, avada-, be-, 
  vc-, wpb-, fusion-, cs-, gp-

Safe single-letter prefixes: j-, k-, m-, n-, q-, r-, u-, v-, x-, y-, z-
Safe 2-3 letter prefixes derived from brand: use initials of the project name
```

---

### Pass 6: Animation and Effects Skill

**Domain:** CSS animations, JavaScript animation patterns, canvas API, performance, browser compatibility.

```
ANIMATION AND EFFECTS SKILL v1.0
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

You are a creative developer specialising in web animation who has 
implemented particle systems, scroll animations, canvas effects, 
and CSS micro-interactions across hundreds of projects.

ANIMATION CLASSIFICATION
─────────────────────────
BODY-LEVEL INJECTION REQUIRED (use document.body.appendChild):
  - Any <canvas> element used as a background effect
  - Any element that must render below ALL page content
  - Custom cursor elements (must be above all content but outside 
    Elementor's stacking context)

The reason: Elementor containers establish stacking contexts via 
position:relative. Any position:fixed element inside a stacking context 
is fixed relative to that context, not the viewport. The only reliable 
solution is to inject outside Elementor's DOM entirely.

CANVAS SETUP PATTERN (mandatory in this plugin):
  const c = document.createElement('canvas');
  c.id = '[P]-bg-canvas';
  c.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;'
    + 'z-index:0;pointer-events:none;opacity:[opacity];';
  document.body.appendChild(c);
  
  // CRITICAL: Elementor sections must sit above canvas
  // Add to companion CSS: body .e-con, body .elementor-section { 
  //   position:relative; z-index:2; }
  // And body::after (noise overlay) at z-index:1

PARTICLE SYSTEM SPECIFICATION
───────────────────────────────
Standard particle system for landing pages:
  Count: 100-150 (130 is ideal — visible but not heavy)
  Colour split: 70% white, 30% accent colour (use design token)
  Size: Math.random() * 1.5 + 0.5 (0.5–2px)
  Opacity: Math.random() * 0.4 + 0.1 (0.1–0.5)
  Velocity: (Math.random() - 0.5) * 0.3 (slow drift)
  Connection: draw lines when distance < 100px
  Line opacity: (1 - distance/100) * 0.08 (very subtle)
  Line width: 0.5px
  Line colour: accent colour
  Reset: when particle crosses any viewport edge

PERFORMANCE RULES:
  - Use requestAnimationFrame (never setInterval for animation)
  - Clear canvas with clearRect, not fillRect (no fill = transparent)
  - Keep globalAlpha outside the particle loop when possible
  - Only draw connection lines for O(n²/2) pairs — skip the inner half
  - Target 60fps. If particle count is too high for device, reduce count
    not quality (prefer 80 smooth particles over 200 choppy ones)
  - Add resize listener but debounce it (16ms) to avoid thrash

SCROLL REVEAL PATTERN (IntersectionObserver):
  const observer = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add('[P]-visible');
        // Don't unobserve — allows re-animation if user scrolls back
        // (optional: observer.unobserve(e.target) to only animate once)
      }
    });
  }, { 
    threshold: 0.12,     // 12% of element in view triggers
    rootMargin: '0px'    // no offset
  });
  
  // Must run after DOMContentLoaded AND handle Elementor's late rendering
  function initReveal() {
    document.querySelectorAll('.[P]-reveal').forEach(el => 
      observer.observe(el));
  }
  
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initReveal);
  } else {
    initReveal();
  }
  // Also listen for Elementor's frontend init for dynamically added widgets
  window.addEventListener('elementor/frontend/init', initReveal);

CURSOR PATTERN:
  Must inject into body. Must use RAF loop, not mousemove-only update.
  Dot: immediate position (no lag), 8px, accent colour
  Ring: lagging position (lerp by 0.12), 36px, accent colour at 50% opacity
  Ring hover state: enlarge to 56px, add accent fill at 8% opacity
  CSS: body:has(a:hover) #[P]-cursor-ring, 
       body:has(button:hover) #[P]-cursor-ring { enlarged state }

STAT COUNTER PATTERN:
  Use IntersectionObserver (separate from reveal observer, threshold 0.3)
  On entry: animate count from 0 to target over 1.8 seconds
  For decimals: use toFixed(N) during animation
  Add [P]-counted class for CSS ::before bar animation (progress line)
  Mark data-done on counter span to prevent re-triggering

NAV SCROLL PATTERN:
  Listen on window scroll
  Toggle [P]-scrolled class at scrollY > 60
  [P]-scrolled styles: rgba(bg, 0.85) background, backdrop-filter: blur(20px),
    border-bottom: 1px solid rgba(255,255,255,0.1), padding reduction
  Use passive: true on the event listener for scroll performance

CSS ANIMATIONS — WHAT GOES IN COMPANION CSS VS HTML WIDGET:
  @keyframes used globally (reveal, pulse, blink, status): companion CSS
  @keyframes used only inside one HTML widget: inside that widget's <style>
  transition on hover states: companion CSS (targeting the widget class)
  animation on decorative elements inside HTML widget: inside widget <style>
```

---

### Pass 7: Elementor Schema Mastery Skill

**Domain:** Elementor's internal widget schema, settings keys, data types, version compatibility.

```
ELEMENTOR SCHEMA MASTERY SKILL v1.0
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

You are an Elementor core contributor who knows every widget type, 
every settings key, and every breaking change in Elementor's history.

WIDGET TYPE REFERENCE — FREE VERSION ONLY
──────────────────────────────────────────
ALLOWED widget types (Free, all 3.x versions):
  heading, text-editor, button, image, html, divider, spacer,
  icon-list, icon, image-box, text, video, google_maps,
  wp-widget-[post-type], inner-section (legacy only)

PRO-ONLY (do NOT generate these):
  counter, countdown, progress, testimonial, slides, tabs, accordion,
  toggle, alert, call-to-action, media-carousel, reviews, portfolio,
  flip-box, price-table, price-list, animated-headline, hotspot,
  lottie, form, nav-menu (the Pro version; basic HTML nav is fine)

WIDGET SETTINGS SCHEMA — EXACT KEY NAMES
──────────────────────────────────────────

HEADING WIDGET:
{
  "title": "HTML string (can contain <em>, <span>, <br> but not block tags)",
  "header_size": "h1|h2|h3|h4|h5|h6",
  "align": "left|center|right|justify",
  "title_color": "hex or rgba string",
  "typography_typography": "custom",
  "typography_font_family": "string",
  "typography_font_weight": "100|200|300|400|500|600|700|800|900|normal|bold",
  "typography_font_style": "normal|italic|oblique",
  "typography_font_size": { "unit": "px|em|rem|vw", "size": number },
  "typography_line_height": { "unit": "px|em", "size": number },
  "typography_letter_spacing": { "unit": "px|em", "size": number },
  "_css_classes": "space-separated class string",
  "_element_id": "string (no spaces, no special chars)"
}

TEXT-EDITOR WIDGET:
{
  "editor": "HTML string (full HTML including <p>, <strong>, <a> etc)",
  "_css_classes": "...",
  "_element_id": ""
}

BUTTON WIDGET:
{
  "text": "plain text (no HTML)",
  "link": { "url": "string", "is_external": false, "nofollow": false },
  "align": "left|center|right|justify",
  "background_color": "hex or rgba",
  "button_text_color": "hex or rgba",
  "border_border": "none|solid|double|dotted|dashed|groove",
  "border_color": "hex or rgba",
  "border_width": { "top": "N", "right": "N", "bottom": "N", "left": "N" },
  "border_radius": { "unit": "px", "top": N, "right": N, "bottom": N, "left": N },
  "padding": { "unit": "px", "top": "N", "right": "N", "bottom": "N", "left": "N", "isLinked": false },
  "typography_typography": "custom",
  "typography_font_family": "...",
  "typography_font_weight": "...",
  "typography_font_size": { "unit": "px", "size": N },
  "typography_letter_spacing": { "unit": "em", "size": N },
  "_css_classes": "...",
  "_element_id": ""
}

HTML WIDGET:
{
  "html": "complete HTML string including <style> and <script> blocks",
  "_css_classes": "...",
  "_element_id": ""
}

ICON-LIST WIDGET:
{
  "icon_list": [
    { "text": "link text", "link": { "url": "#" }, "selected_icon": { "value": "" } }
  ],
  "layout": "traditional|inline",
  "space_between": { "unit": "px", "size": N },
  "_css_classes": "...",
  "_element_id": ""
}

CONTAINER SETTINGS:
{
  "flex_direction": "row|column|row-reverse|column-reverse",
  "flex_wrap": "nowrap|wrap|wrap-reverse",
  "justify_content": "flex-start|flex-end|center|space-between|space-around|space-evenly",
  "align_items": "flex-start|flex-end|center|stretch|baseline",
  "gap": { "unit": "px", "size": N, "column": N, "row": N },
  "background_background": "classic|gradient|image|video|slideshow",
  "background_color": "hex or rgba",
  "padding": { "unit": "px", "top": "N", "right": "N", "bottom": "N", "left": "N", "isLinked": false },
  "margin": { "unit": "px", "top": "N", "right": "N", "bottom": "N", "left": "N", "isLinked": false },
  "min_height": { "unit": "px|vh|em", "size": N },
  "min_height_type": "min-height",
  "overflow": "default|hidden|auto|scroll",
  "border_border": "none|solid|...",
  "border_color": "...",
  "border_width": { "top": "N", "right": "N", "bottom": "N", "left": "N" },
  "border_radius": { "unit": "px", "top": N, "right": N, "bottom": N, "left": N },
  "_css_classes": "...",
  "_element_id": "",
  "custom_css": "CSS string (Elementor Pro only — note in report if used)"
}

GRID CONTAINER ADDITIONAL SETTINGS:
{
  "container_type": "grid",
  "grid_columns_fr": "Nfr Nfr Nfr (space-separated fr values)",
  "grid_rows_fr": "auto (almost always)",
  "gap": { "unit": "px", "size": N, "column": N, "row": N }
}

GRID CHILD PLACEMENT SETTINGS (on inner containers inside a grid):
{
  "grid_column_start": N,
  "grid_column_end": N,  (exclusive — one more than the last column occupied)
  "grid_row_start": N,
  "grid_row_end": N
}

COMMON MISTAKES TO AVOID:
  ✗ "typography_font_size": 16  (wrong — must be object)
  ✓ "typography_font_size": { "unit": "px", "size": 16 }
  
  ✗ "padding": "60px 40px"  (wrong — must be object)
  ✓ "padding": { "unit": "px", "top": "60", "right": "40", "bottom": "60", "left": "40", "isLinked": false }
  
  ✗ "border_width": "1px"  (wrong — must be object with sides)
  ✓ "border_width": { "top": "1", "right": "1", "bottom": "1", "left": "1" }
  
  ✗ Using widgetType: "counter" (Pro only)
  ✓ Using widgetType: "html" with count-up script inside
  
  ✗ isInner: false on a nested container
  ✓ isInner: true on ALL containers inside other containers
  
  ✗ Duplicate id values
  ✓ Every id is a unique 8-character alphanumeric string
```

---

### Pass 8: CSS Architecture Skill

**Domain:** CSS methodologies, cascade architecture, maintainable CSS, specificity management.

```
CSS ARCHITECTURE SKILL v1.0
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

You are a CSS architect who has written and maintained large-scale 
stylesheets for agency projects. You produce CSS that is readable, 
maintainable, conflict-safe, and well-documented.

COMPANION CSS FILE STRUCTURE
─────────────────────────────
Always produce the companion CSS in this exact order:

1. FILE HEADER — explains what the file is, how to add it, and the 
   class map. The class map is the most important part of the header.
   Every class used in the JSON must be listed:
   Format: .classname → what element, which section, where in Elementor
   
2. DESIGN TOKENS — :root block with CSS custom properties.
   Include every colour, font family, and spacing value from Pass 1.
   Use descriptive names: --color-bg, --color-text, --color-accent,
   --font-display, --font-body, --font-mono, --stroke

3. PAGE-LEVEL OVERRIDES — body, .elementor-page overrides for 
   background colour (with !important to beat theme), font family.
   The critical z-index stack:
     body .e-con, body .elementor-section { position:relative; z-index:2; }
     body::after { z-index:1; } /* noise overlay */
     #[P]-bg-canvas { z-index:0; } /* particle canvas */

4. UTILITY CLASSES — scroll reveal, stagger, custom scrollbar.
   These are generic and apply across all sections.

5. PER-SECTION STYLES — one clearly labelled subsection per page section.
   Within each subsection, order: containers, headings, body text, 
   buttons, HTML widget internals, hover states, pseudo-elements.

6. RESPONSIVE BREAKPOINTS — at the end, clearly labelled.
   Always produce both 1024px (tablet) and 768px (mobile) blocks.
   Within each block, group rules by section.

SPECIFICITY MANAGEMENT RULES
──────────────────────────────
Use class selectors exclusively. Never use ID selectors (#) in 
companion CSS — they have too much specificity and create maintenance issues.
Never use !important except for page-level background overrides.
Never use descendant selectors deeper than 3 levels.
Never use type selectors on their own (h2 { }) — always qualify with class.

GOOD: .nx-hero-headline .elementor-heading-title { }
BAD:  #nx-hero h1 { }  (ID + type = specificity nightmare)
BAD:  h1 { }           (too broad, will bleed into other sections)

HOVER STATE PATTERNS
──────────────────────
Cards: 
  .nx-[section]-card {
    transition: background .3s, border-color .3s, transform .3s;
  }
  .nx-[section]-card:hover {
    background: [slightly lighter surface] !important;
    border-color: rgba([accent RGB], 0.25) !important;
    transform: translateY(-3px);
  }

Buttons:
  .nx-btn-primary .elementor-button {
    transition: background .2s, transform .2s !important;
  }
  .nx-btn-primary .elementor-button:hover {
    background: [lighter accent] !important;
    transform: translateY(-2px);
  }

Process steps:
  .nx-process-step:hover .nx-step-num { color: [accent] !important; }
  .nx-process-step:hover .nx-step-title .elementor-heading-title { 
    color: [accent]; 
  }

Footer links:
  .nx-footer-link { transition: color .2s; }
  .nx-footer-link:hover { color: [text-primary] !important; }

PSEUDO-ELEMENT PATTERNS
────────────────────────
Eyebrow tag before-line:
  .nx-section-tag::before {
    content: '';
    display: inline-block;
    width: 40px; height: 1px;
    background: [accent];
    margin-right: 12px;
    vertical-align: middle;
  }

CTA watermark (ghost text):
  .nx-cta { position: relative; overflow: hidden; }
  .nx-cta > * { position: relative; z-index: 1; }
  .nx-cta::before {
    content: '[BRAND NAME]';
    position: absolute; right: -20px; top: 50%;
    transform: translateY(-50%);
    font-family: var(--font-display); font-weight: 800;
    font-size: 200px; color: rgba(0,0,0,0.06);
    letter-spacing: -8px; pointer-events: none;
    line-height: 1; white-space: nowrap; z-index: 0;
  }

Stat cell progress line (::before on [P]-counted):
  .nx-stat-cell::before {
    content: ''; position: absolute;
    top: 0; left: 0; right: 0; height: 2px;
    background: linear-gradient(90deg, [accent] 0%, transparent 100%);
    transform: scaleX(0); transform-origin: left;
    transition: transform .8s ease;
  }
  .nx-stat-cell.[P]-counted::before { transform: scaleX(1); }

RESPONSIVE CSS PATTERNS
────────────────────────
Always include these as minimum responsive rules:

@media (max-width: 1024px) {
  .nx-hero-headline .elementor-heading-title {
    font-size: clamp(48px, 8vw, 100px) !important;
    letter-spacing: -2px !important;
  }
  .nx-hero-bottom, .nx-process-grid {
    flex-direction: column !important;
  }
  .nx-section-desc .elementor-widget-text-editor p {
    text-align: left;
  }
  .nx-bento-grid { 
    grid-template-columns: 1fr 1fr !important; 
  }
}

@media (max-width: 768px) {
  /* all sections horizontal padding floor */
  [class*="nx-"][class*="-section"],
  .nx-hero, .nx-features, .nx-process,
  .nx-testimonials, .nx-pricing, .nx-footer {
    padding-left: 24px !important;
    padding-right: 24px !important;
  }
  .nx-testi-grid, .nx-pricing-grid {
    flex-direction: column !important;
  }
  .nx-bento-grid {
    grid-template-columns: 1fr !important;
  }
  .nx-bento-grid > * {
    grid-column: 1 / -1 !important;
    grid-row: auto !important;
  }
  .nx-footer-grid {
    grid-template-columns: 1fr 1fr !important;
  }
  .nx-stats-grid {
    grid-template-columns: repeat(2, 1fr) !important;
  }
  .nx-cta {
    padding: 60px 32px !important;
    margin-left: 16px !important;
    margin-right: 16px !important;
  }
  .nx-cta::before { font-size: 100px !important; }
}
```

---

### Pass 9: Quality Assurance Skill

```
QUALITY ASSURANCE SKILL v1.0
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

You are a QA engineer who has reviewed hundreds of Elementor templates 
for correctness, completeness, and import safety.

CRITICAL CHECKS (failures block output):
  □ JSON.parse() succeeds with no errors
  □ All id values are unique (no duplicates in the template)
  □ All elType values are either "container" or "widget"
  □ All widgetType values are in the Free widget whitelist
  □ All containers have an "elements" array (even if empty [])
  □ No isInner:false on a container nested inside another container
  □ All _css_classes values are strings (not null, not array)
  □ All padding/margin/gap/font_size values are objects with correct keys
  □ Template has at least one container in the content array
  □ Global Setup widget is the first element
  □ Nav HTML widget is the second element (if fixed nav present)

WARNING CHECKS (do not block output, include in report):
  □ Any widget missing _css_classes
  □ Any container missing _element_id on a top-level section
  □ Hero section missing min_height setting
  □ Pricing section: is the featured card visually distinct?
  □ Any button widget with empty link.url ("#" is acceptable)
  □ Any heading widget with empty title
  □ Particle canvas script uses document.body.appendChild (not inline canvas)
  □ Companion CSS contains class map header
  □ Companion CSS contains @media (max-width: 1024px) block
  □ Companion CSS contains @media (max-width: 768px) block
  □ All classes in _css_classes appear in companion CSS as selectors
  □ No CSS custom property in companion CSS references undefined variable

AUTO-REPAIR CAPABILITIES:
  Duplicate ID → generate new unique ID for the duplicate
  Missing elements array → add []
  String padding → convert "60px" to object form
  Null _css_classes → convert to ""
  Whitespace in _element_id → replace with hyphens
  Pro widget type → replace with nearest Free equivalent + add report note
```

---

## 5. Native Converter: Expert-Trained Skill Modules {#native-skills}

For the native converter, Skills are not prompt modules — they are deeply trained PHP/JS modules encoding the same expert knowledge as hard rules, weighted heuristics, pattern libraries, and decision trees. The goal is to encode what a domain expert knows into executable logic that runs without inference.

### Skill Module 1: Visual Fingerprinting Engine

**What it does:** Identifies which high-level design patterns are present in the HTML by analysing the combination of structural, stylistic, and content signals — essentially "fingerprinting" sections.

**Training approach:** Build a database of design patterns, each described by a weighted set of signals. A pattern match returns a confidence score based on how many signals match and with what weight.

```php
class VisualFingerprintEngine {

  private array $patterns = [];

  public function __construct() {
    $this->loadPatternLibrary();
  }

  private function loadPatternLibrary(): void {
    // HERO PATTERN
    $this->patterns['hero'] = new DesignPattern([
      'signals' => [
        // Structural signals
        Signal::structural('is_first_significant_section', weight: 0.9),
        Signal::structural('min_height_gte_80vh', weight: 0.85),
        Signal::structural('has_heading_and_button', weight: 0.8),
        Signal::structural('flex_direction_column', weight: 0.5),
        Signal::structural('justify_content_flex_end_or_center', weight: 0.6),
        
        // Typographic signals
        Signal::typographic('largest_font_on_page', weight: 0.9),
        Signal::typographic('font_size_gte_64px', weight: 0.8),
        Signal::typographic('has_h1_tag', weight: 0.85),
        Signal::typographic('has_eyebrow_element', weight: 0.7),
        // eyebrow = small all-caps element immediately before main heading
        
        // Content signals
        Signal::content('has_cta_button', weight: 0.75),
        Signal::content('has_subtitle_paragraph', weight: 0.7),
        Signal::content('word_count_lt_100', weight: 0.5),
        // heroes have less text than feature sections
        
        // Visual signals
        Signal::visual('full_viewport_width', weight: 0.8),
        Signal::visual('has_background_colour_or_image', weight: 0.6),
        Signal::visual('no_border', weight: 0.4),
      ],
      'exclusions' => [
        // Cannot be hero if these are true
        Signal::structural('has_grid_children', weight: 1.0),
        // has grid children = probably features, not hero
        Signal::content('has_price_amount', weight: 1.0),
        // has price = pricing, not hero
      ],
      'minimum_confidence' => 0.65
    ]);

    // BENTO GRID PATTERN
    $this->patterns['bento_grid'] = new DesignPattern([
      'signals' => [
        Signal::structural('display_grid', weight: 0.95),
        Signal::structural('has_4_or_more_children', weight: 0.8),
        Signal::structural('children_have_different_sizes', weight: 0.9),
        Signal::structural('children_have_border', weight: 0.7),
        Signal::structural('children_have_background', weight: 0.7),
        Signal::typographic('children_have_heading', weight: 0.75),
        Signal::typographic('children_have_body_text', weight: 0.7),
        Signal::content('feature_or_capability_language', weight: 0.6),
        // "AI Workflow", "Security", "Monitoring" etc
      ],
      'minimum_confidence' => 0.7
    ]);

    // PRICING TIER PATTERN  
    $this->patterns['pricing'] = new DesignPattern([
      'signals' => [
        Signal::structural('has_2_to_4_similar_siblings', weight: 0.8),
        Signal::content('has_currency_symbol', weight: 0.95),
        Signal::content('has_price_number', weight: 0.95),
        Signal::content('has_feature_list', weight: 0.8),
        Signal::content('has_cta_button', weight: 0.7),
        Signal::structural('children_have_equal_or_similar_layout', weight: 0.7),
        Signal::visual('one_child_visually_distinct', weight: 0.75),
        // featured plan has different background
      ],
      'minimum_confidence' => 0.75
    ]);

    // TESTIMONIAL PATTERN
    $this->patterns['testimonials'] = new DesignPattern([
      'signals' => [
        Signal::structural('has_3_similar_siblings', weight: 0.8),
        Signal::content('has_quotation_marks', weight: 0.9),
        Signal::content('has_person_name', weight: 0.8),
        Signal::content('has_job_title', weight: 0.75),
        Signal::content('has_company_name', weight: 0.7),
        Signal::structural('children_have_border', weight: 0.6),
        Signal::visual('has_avatar_circle', weight: 0.65),
      ],
      'minimum_confidence' => 0.7
    ]);

    // ... (PROCESS_STEPS, STATS_ROW, MARQUEE_STRIP, FIXED_NAV,
    //      CTA_SECTION, FOOTER patterns)
  }

  public function identify(DOMElement $section, StyleMap $styles): PatternResult {
    $scores = [];
    foreach ($this->patterns as $name => $pattern) {
      $score = $pattern->evaluate($section, $styles);
      if ($score >= $pattern->minimumConfidence) {
        $scores[$name] = $score;
      }
    }
    arsort($scores);
    $topPattern = array_key_first($scores);
    return new PatternResult(
      pattern: $topPattern ?? 'unknown',
      confidence: $scores[$topPattern] ?? 0.0,
      alternatives: array_slice($scores, 1, 3, preserve_keys: true)
    );
  }
}
```

**Edge cases handled by this module:**

When two patterns have similar confidence scores (within 0.05 of each other), the module returns both as candidates and the pipeline applies the fallback chain — choosing the simpler, safer pattern when uncertain.

When no pattern exceeds the minimum confidence threshold, the module returns `unknown` and the section is processed with the generic container rules rather than the optimised pattern-specific rules.

---

### Skill Module 2: Design Pattern Recognition System

A complementary system to Visual Fingerprinting that works on sub-component level (individual cards, steps, buttons) rather than section level. Recognises micro-patterns:

```php
class ComponentRecogniser {

  // Recognises eyebrow + heading + description combos
  // Recognises card structures (border + bg + heading + text + optional CTA)
  // Recognises process step structures (number + title + desc)
  // Recognises stat cell structures (large number + small label)
  // Recognises testimonial author blocks (avatar + name + role)
  // Recognises pricing feature lists (icon/arrow + text, repeated)
  // Recognises navigation link lists (anchor tags, horizontal layout)
  // Recognises button groups (2+ adjacent button-like elements)
  
  public function recogniseComponent(DOMElement $el, StyleMap $styles): ComponentType {
    // Run through component recognition checks in order of specificity
    // Most specific patterns checked first (pricing feature list before generic list)
    // Return ComponentType with confidence score
  }
}
```

---

### Skill Module 3: Typography Scale Detector

One of the most powerful signals for correct widget classification and companion CSS generation is knowing the design's typographic scale. If you know the design uses a 1.333 (perfect fourth) scale with a base of 16px, you can verify that a `48px` element is a Display size (3 steps up from base) and a `12px` element is a Label size (1 step down).

```php
class TypographyScaleDetector {

  // Collect all font-size values from the CSS
  // Sort them ascending
  // Calculate ratios between adjacent sizes
  // Detect if ratios are consistent (within 0.05 tolerance)
  
  // Common modular scales to test against:
  private array $knownScales = [
    'minor-second'   => 1.067,
    'major-second'   => 1.125,
    'minor-third'    => 1.200,
    'major-third'    => 1.250,
    'perfect-fourth' => 1.333,
    'augmented-fourth' => 1.414,
    'perfect-fifth'  => 1.500,
    'golden-ratio'   => 1.618,
    // Also test for CSS Fluid (clamp-based) and arbitrary scales
  ];

  public function detect(array $fontSizes): ScaleResult {
    // If consistent modular scale found:
    //   - Label each font size with its role in the hierarchy
    //   - Display (largest) → hero headline
    //   - H1 level → section title
    //   - H2 level → card title  
    //   - H3 level → step title
    //   - Body → body text
    //   - Small → caption, tag, label
    
    // If no consistent scale:
    //   - Group by approximate size ranges
    //   - Apply heuristic role assignment based on size + weight combination
  }
  
  public function getRoleForSize(float $px, float $weight): string {
    // Combines size AND weight in the role decision
    // 60px + weight 800 → Display
    // 60px + weight 400 → Large decorative number (stat)
    // 16px + weight 700 → Button label
    // 11px + weight 400 + uppercase + letter-spacing → Tag/Eyebrow
    // 11px + weight 400 + monospace → Code/Terminal label
  }
}
```

---

### Skill Module 4: Spacing System Analyser

```php
class SpacingSystemAnalyser {

  // Collect all padding and margin values (individual sides, not shorthand)
  // Find the GCD (greatest common divisor) of all spacing values
  // Common bases: 4, 5, 6, 8, 10
  
  // If GCD is 4 or 8: confirm by checking all values are multiples
  // If confirmed: generate spacing tokens:
  //   base: 4, xs: 8, sm: 12, md: 16, lg: 24, xl: 32, 2xl: 48, 3xl: 64...
  
  // This information feeds:
  //   1. Companion CSS :root variables (--space-xs, --space-sm etc)
  //   2. Responsive scaling (tablet: 80%, mobile: 60% of each token)
  //   3. Elementor settings (padding/margin values as clean multiples)
  //   4. Quality check (values that are NOT multiples of the base are flagged
  //      as potential one-offs or mistakes)
  
  public function analyse(array $spacingValues): SpacingSystem {
    // Returns: base, tokens, scale type (4px/8px/custom), 
    //          outliers (values not fitting the system)
  }
  
  public function generateResponsiveScale(SpacingSystem $system): array {
    // Returns: [
    //   'desktop' => [...token values...],
    //   'tablet'  => [...token values * 0.75...],
    //   'mobile'  => [...token values * 0.5, min 24px for horizontal...]
    // ]
  }
}
```

---

### Skill Module 5: Color Harmony Classifier

```php
class ColorHarmonyClassifier {

  // Input: array of all colour values found in CSS
  // Output: classified colour roles
  
  public function classify(array $colours): ColourSystem {
    // Step 1: Convert all to HSL for analysis
    // Step 2: Find the darkest value → likely background
    // Step 3: Find the lightest value → likely text on dark bg
    // Step 4: Find the most saturated value → likely accent
    
    // Step 5: Cluster by luminance:
    //   L < 15%  → background tier
    //   L 15-35% → surface tier (cards, sections)
    //   L 35-55% → mid-tones (borders, muted elements)
    //   L 55-80% → secondary text, icons
    //   L > 80%  → primary text, headings
    
    // Step 6: Check alpha values:
    //   rgba(*, *, *, 0.03-0.1) → surface overlay / card bg
    //   rgba(*, *, *, 0.1-0.2) → border / stroke
    //   rgba(*, *, *, 0.4-0.6) → muted text
    //   rgba(*, *, *, 0.8-1.0) → primary content
    
    // Step 7: Identify the accent:
    //   High saturation + used on buttons = Primary Action
    //   High saturation + used on small labels = Secondary Accent
    //   Low saturation + used as border = Stroke
    
    // Returns: { background, surface, accent, textPrimary, textSecondary,
    //            textMuted, border, accentSecondary }
  }
  
  public function detectScheme(ColourSystem $cs): string {
    // Returns: 'dark', 'light', 'high-contrast-dark', 'high-contrast-light'
    // Dark scheme: background L < 15%
    // This affects companion CSS (will hover states lighten or darken?)
    // And affects what default colours to use for unnamed elements
  }
}
```

---

### Skill Module 6: Component Boundary Detector

One of the hardest problems in parsing arbitrary HTML is knowing where one component ends and another begins. The boundary detector uses a combination of signals to identify component-level groupings:

```php
class ComponentBoundaryDetector {

  // A component boundary is detected when:
  //
  // HARD BOUNDARIES (always separate components):
  //   - Different background-color between siblings
  //   - Border on one side but not the other
  //   - Significant vertical gap (gap > 40px between siblings)
  //   - Semantic HTML boundary (<section>, <article>, <aside>)
  //
  // SOFT BOUNDARIES (suggest separate components, need 2+ signals):
  //   - Change in primary font family between siblings
  //   - Change in primary colour scheme between siblings
  //   - Significant change in DOM depth between siblings
  //   - Grid area boundary (grid-column changes)
  //
  // NON-BOUNDARIES (these are internal layout helpers, not components):
  //   - flex row that is a direct child of a clearly identified component
  //   - Single-column layout div that has only one meaningful child
  //   - Wrapper div that matches parent width exactly and has no bg/border
  
  public function detectBoundaries(DOMNodeList $siblings, StyleMap $styles): array {
    // Returns array of component groupings from the sibling list
    // Each grouping = list of DOM elements that belong to the same component
  }
  
  // Special case: card grids
  // When a grid container has 3+ children with identical structure,
  // each child is its own component (card), not part of one large component
  public function isCardGrid(DOMElement $el, StyleMap $styles): bool {
    // Returns true if el is a grid with 3+ structurally-similar children
  }
}
```

---

### Skill Module 7: Interactive State Modeller

Understands hover, focus, and active states — the CSS rules that only apply in interactive states — and routes them correctly:

```php
class InteractiveStateModeller {

  public function extractInteractiveStates(
    string $elementClass, 
    CSSRuleSet $rules
  ): InteractiveStateMap {
    
    // Collect all :hover, :focus, :active, :focus-visible rules
    // for selectors matching the element's class
    
    // For each interactive state, classify:
    //   ELEMENTOR_HOVER_ANIMATION → if it is a transform or opacity change
    //     → recommend Elementor Pro Motion Effects
    //     → OR generate companion CSS hover rules
    //   CSS_TRANSITION → transition: property duration timing
    //     → always companion CSS (Elementor panel has no transitions)
    //   CSS_HOVER_COLOR → color/background change on hover
    //     → companion CSS targeting widget's CSS class
    //   JS_DEPENDENT → state applied by JS class toggle
    //     → document in HTML widget script, companion CSS for the toggled class
    
    // Generate companion CSS rules for all non-Elementor states
    // Flag Elementor Pro Motion Effects as optional enhancement
  }
  
  // Special handling for parent:hover affecting child
  // e.g., .card:hover .card-title → { color: accent }
  // This cannot be expressed in Elementor's per-widget hover settings
  // Must go in companion CSS as: .nx-card:hover .nx-card-title .elementor-heading-title { }
  public function detectParentChildHover(CSSRuleSet $rules): array {
    // Returns array of parent-child hover relationships
    // Each with: parent class, child class, affected properties
  }
}
```

---

### Skill Module 8: Editability Prediction Engine

A trained classifier that predicts the editability score of each element using a combination of signals. This is the native equivalent of the Content Strategy Skill for the AI converter:

```php
class EditabilityPredictor {

  public function predict(DOMElement $el, StyleMap $styles, array $context): float {
    $score = 5.0; // start neutral
    
    // STRONG UPWARD SIGNALS (client will want to edit)
    if ($this->isHeading($el)) $score += 3.5;
    if ($this->isBodyParagraph($el)) $score += 3.0;
    if ($this->isButtonLabel($el)) $score += 3.5;
    if ($this->isTestimonialQuote($el)) $score += 3.5;
    if ($this->isPricingFeatureListItem($el)) $score += 3.0;
    if ($this->isPersonName($el)) $score += 3.0;
    if ($this->isSectionTitle($el)) $score += 3.5;
    if ($this->isEyebrowTag($el)) $score += 2.0;
    
    // DOWNWARD SIGNALS (client will not want to edit)
    if ($this->hasAnimation($el, $styles)) $score -= 4.0;
    if ($this->isDecorativeSymbol($el)) $score -= 4.0;
    if ($this->isInitialsAvatar($el)) $score -= 3.0;
    if ($this->isProgressBar($el)) $score -= 4.5;
    if ($this->isTerminalOrCode($el)) $score -= 3.0;
    if ($this->isOrbitalOrRing($el)) $score -= 5.0;
    if ($this->isMarqueeItem($el)) $score -= 3.5;
    if ($this->isPositionedAbsolute($el, $styles)) $score -= 2.0;
    if ($this->textLengthLt5Chars($el)) $score -= 2.0; // single chars, icons
    if ($this->hasBlendMode($el, $styles)) $score -= 3.0;
    
    // Context signals
    if ($context['parent_is_animated']) $score -= 2.0;
    if ($context['inside_card_grid'] && $this->isCardTitle($el)) $score += 2.0;
    if ($context['is_repeated_sibling_N_times'] > 3) $score += 1.5;
    // repeated structures are usually client-editable items
    
    return max(0.0, min(10.0, $score));
  }
}
```

---

### Skill Module 9: Semantic Graph Analyser

Goes beyond parent-child DOM relationships to understand semantic relationships between elements — things like "this heading and this paragraph are conceptually paired" or "these three cards are siblings in a list":

```php
class SemanticGraphAnalyser {

  // BUILD SEMANTIC GRAPH
  // Nodes: elements classified as having semantic role
  // Edges: relationships between nodes
  
  // RELATIONSHIP TYPES:
  //   LABELS → element A is a label for element B
  //     (eyebrow text LABELS section title)
  //     (step number LABELS step title)
  //   DESCRIBES → element A describes element B
  //     (subtitle DESCRIBES hero headline)
  //     (body text DESCRIBES card title)
  //   IS_ACTION_FOR → element A is a CTA for section B
  //     (button IS_ACTION_FOR hero)
  //     (price card button IS_ACTION_FOR pricing section)
  //   ILLUSTRATES → element A visually represents concept B
  //     (orbital animation ILLUSTRATES the process section)
  //     (terminal widget ILLUSTRATES the AI orchestration feature)
  //   SIBLING_OF → elements are conceptually parallel
  //     (testimonial card SIBLING_OF other testimonial cards)
  //     (pricing tier SIBLING_OF other pricing tiers)
  
  // WHY THIS MATTERS:
  // Semantic relationships inform container nesting decisions.
  // An eyebrow + heading + description that LABEL and DESCRIBE each other
  // should be in a FLEX_COLUMN container with tight gap.
  // Two elements with SIBLING_OF relationship should be in the same
  // grid or flex row, not split across containers.
  
  // Also informs companion CSS naming:
  // If element A LABELS element B, they get sibling CSS classes
  // that can be used together in hover rules:
  // .nx-process-step:hover .nx-step-num (LABELS) .nx-step-title
}
```

---

### Skill Module 10: Responsive Intent Inferrer

Extracts responsive intent from the design even when media queries are absent:

```php
class ResponsiveIntentInferrer {

  // If no @media queries exist in the CSS, infer responsive intent:
  
  // ALWAYS STACK ON MOBILE:
  //   Any FLEX_ROW container wider than 600px
  //   Any grid with 3+ columns
  //   Hero bottom row (sub + CTAs)
  //   Process grid (steps + visual)
  
  // USUALLY STACK ON MOBILE:
  //   Footer grid (2+ columns)
  //   Any pricing or testimonial grid
  
  // USUALLY KEEP ON MOBILE:
  //   Button groups (2 buttons side by side)
  //   Tag/eyebrow rows
  //   Navigation links (hide on mobile, show hamburger)
  //   Stats grid: 4→2 columns (2x2 on mobile, not single column)
  
  // FONT SIZE INTENT:
  //   If font-size uses clamp(): extract min/preferred/max,
  //     use preferred for desktop setting, min for mobile
  //   If font-size is px: generate clamp() for companion CSS
  //     using the detected typography scale to infer max size
  
  // PADDING INTENT:
  //   Desktop section padding: use as-is
  //   Tablet: multiply by 0.75 (floor: 40px vertical, 40px horizontal)
  //   Mobile: multiply by 0.5 (floor: 40px vertical, 24px horizontal)
  
  public function inferBreakpoints(SectionInventory $sections): ResponsiveConfig {
    // Returns per-section responsive rules for companion CSS
  }
}
```

---

## 6. Cross-Cutting Improvements to All Passes {#cross-cutting}

Beyond the per-pass Skills, several improvements apply across the entire pipeline regardless of which pass they occur in:

### Shared Context Object

All passes read from and write to a single `ConversionContext` object that travels through the pipeline. This eliminates the problem of later passes not having access to earlier passes' decisions:

```php
class ConversionContext {
  public DesignSystem $designSystem;      // Pass 1: colours, fonts, spacing
  public SectionInventory $sections;      // Pass 1: section list
  public LayoutMap $layouts;             // Pass 2: layout decisions
  public ClassificationMap $classes;     // Pass 3: widget classifications
  public StyleMap $resolvedStyles;       // Pass 4: computed styles per element
  public ClassMap $cssClasses;           // Pass 5: CSS class assignments
  public AnimationInventory $animations; // Pass 1/6: animation inventory
  public string $prefix;                 // Shared: project prefix
  public array $conversionLog;          // Shared: decisions + reasoning
  public array $warnings;               // Shared: warnings for report
  public array $companionCSSRules;       // Accumulated: companion CSS
  
  // Immutable after each pass writes its section
  // Downstream passes can read but not modify upstream results
}
```

### Decision Logging

Every classification decision made by any pass is logged with its reasoning:

```php
$context->log(
  pass: 'pass3_classification',
  element: $elementId,
  decision: 'HTML_WIDGET_ANIMATED',
  confidence: 0.87,
  reasoning: [
    'Element has animation: pulse 4s infinite in resolved styles',
    'Animation is on the element itself, not just on hover',
    'No meaningful text content that client would need to edit',
    'Pattern matches: decorative orbital ring component'
  ],
  alternatives: [
    ['decision' => 'DECORATIVE', 'confidence' => 0.45],
    ['decision' => 'FLEX_COLUMN', 'confidence' => 0.30]
  ]
);
```

This log feeds the user-facing conversion report and the correction feedback loop.

### Confidence Cascades

Every decision in every pass has a confidence score. When confidence is below the threshold for that decision type, the pass does not guess — it escalates through the fallback chain:

```
Confidence ≥ 0.85 → Use this classification, high confidence
Confidence 0.65–0.85 → Use this classification, mark as medium confidence, suggest review
Confidence 0.40–0.65 → Use most conservative safe option (HTML_WIDGET_COMPLEX)
Confidence < 0.40 → Use HTML_WIDGET_COMPLEX, add prominent report warning
```

The "most conservative safe option" principle: when uncertain, HTML widgets preserve the original appearance at the cost of editability. Native widgets preserve editability at the cost of appearance. For a conversion tool, preserving appearance is the safer default — the user can always manually move an HTML widget to native later, but they cannot fix a broken visual without knowing what it should look like.

---

## 7. The Pattern Library — Native AI {#pattern-library}

The pattern library is the closest the native converter gets to AI intelligence. It is a database of known design patterns, each described by:

- A structural schema (what containers/widgets the pattern uses in Elementor)
- A set of required signals (must be present for the pattern to match)
- A set of optional signals (increase confidence if present)
- A set of exclusion signals (invalidate the pattern if present)
- Template JSON with placeholder fields (filled with actual content on match)
- Companion CSS template (with class names that match the template JSON)
- Known edge cases for this pattern

The NEXUS template's sections form the seed library. Every additional design that goes through the plugin and is reviewed/corrected by a user adds to the library (with consent and anonymisation).

### Fuzzy Pattern Matching

Exact pattern matching is too brittle. Real-world designs are variations on themes, not exact copies. The pattern library uses fuzzy matching:

```php
class FuzzyPatternMatcher {

  public function match(SectionAnalysis $section): array {
    $candidates = [];
    
    foreach ($this->patternLibrary as $pattern) {
      // Score = weighted sum of matching signals
      $score = 0.0;
      $maxScore = 0.0;
      
      foreach ($pattern->requiredSignals as $signal) {
        $maxScore += $signal->weight;
        if ($section->hasSignal($signal->name)) {
          $score += $signal->weight;
        } else {
          // Missing required signal: heavy penalty
          $score -= $signal->weight * 0.5;
        }
      }
      
      foreach ($pattern->optionalSignals as $signal) {
        $maxScore += $signal->weight * 0.5;
        if ($section->hasSignal($signal->name)) {
          $score += $signal->weight * 0.5;
        }
      }
      
      foreach ($pattern->exclusionSignals as $signal) {
        if ($section->hasSignal($signal->name)) {
          $score = 0.0; // immediate disqualification
          break;
        }
      }
      
      $normalised = $score / $maxScore;
      if ($normalised >= $pattern->minimumConfidence) {
        $candidates[] = ['pattern' => $pattern, 'score' => $normalised];
      }
    }
    
    usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);
    return $candidates;
  }
}
```

---

## 8. Confidence Scoring and Fallback Chains {#confidence}

The fallback chain defines what happens when primary classification fails. Each classification type has its own chain:

### Widget Type Fallback Chain

```
HEADING attempt → confidence threshold 0.75
  ↓ (if below threshold)
TEXT_EDITOR attempt → confidence threshold 0.65
  ↓ (if below threshold)
HTML_WIDGET_COMPLEX (no confidence required — safe fallback)
```

### Container Type Fallback Chain

```
GRID_CONTAINER attempt → confidence threshold 0.80
  ↓ (if below threshold)
FLEX_ROW attempt → confidence threshold 0.70
  ↓ (if below threshold)
FLEX_COLUMN attempt → confidence threshold 0.60
  ↓ (if below threshold)
FLEX_COLUMN with default settings (always succeeds)
```

### Pattern Match Fallback Chain

```
Exact pattern match (confidence ≥ 0.85) → use pattern template
  ↓ (if below threshold)
Partial pattern match (confidence 0.65–0.85) → use pattern template 
  for structure, re-derive settings from actual CSS
  ↓ (if below threshold)
No pattern match → derive structure from layout analysis alone
  ↓ (if layout analysis confidence < 0.65)
HTML_WIDGET_COMPLEX with prominent report warning
```

---

## 9. The Skill Handoff Protocol — How Passes Share Knowledge {#handoff}

Pass N needs to know what Pass N-1 decided, and sometimes what Pass N-3 decided. The handoff protocol defines what each pass writes to the ConversionContext and what the next pass is expected to read:

```
Pass 1 (Document Intelligence) WRITES:
  context.designSystem ← colours, fonts, spacing tokens
  context.sections ← section list with boundaries
  context.animations ← animation inventory
  context.prefix ← detected or user-provided prefix

Pass 2 (Layout Analysis) READS: sections, designSystem
Pass 2 WRITES:
  context.layouts ← layout type + settings per section and container

Pass 3 (Content Classification) READS: sections, layouts, animations
Pass 3 WRITES:
  context.classifications ← widget type + confidence + editability per element
  context.patternMatches ← pattern library matches per section

Pass 4 (Style Resolution) READS: classifications, designSystem
Pass 4 WRITES:
  context.resolvedStyles ← Elementor settings + companion CSS rules per element
  context.companionCSSRules ← accumulated (appended, not replaced)

Pass 5 (Class Generation) READS: sections, classifications, layouts
Pass 5 WRITES:
  context.cssClasses ← class name + element ID per element
  
Pass 6 (Global Setup) READS: designSystem, animations, cssClasses, prefix
Pass 6 WRITES:
  context.globalSetupHTML ← complete HTML widget content

Pass 7 (JSON Assembly) READS: everything (all context fields)
Pass 7 WRITES:
  context.templateJSON ← raw Elementor JSON object

Pass 8 (Companion CSS) READS: cssClasses, companionCSSRules, designSystem,
                                layouts, patternMatches
Pass 8 WRITES:
  context.companionCSS ← complete CSS string

Pass 9 (Validation) READS: templateJSON, companionCSS
Pass 9 WRITES:
  context.validationReport ← errors, warnings, repairs
  context.finalTemplateJSON ← validated (and repaired) JSON
  context.finalCompanionCSS ← validated CSS
```

---

## 10. Robustness Engineering — Making Each Pass Failure-Safe {#robustness}

Every pass must be failure-safe. A pass that throws an unhandled exception should not crash the entire conversion — it should log the failure, fall back to the safe default for every element it was processing, and continue. The user receives a conversion report noting what failed and what was substituted.

```php
abstract class PipelinePass {

  abstract protected function execute(
    ConversionContext $context
  ): ConversionContext;

  public function run(ConversionContext $context): ConversionContext {
    try {
      return $this->execute($context);
    } catch (SkillException $e) {
      // Skill module failed — use fallback, continue
      $context->warn("Pass {$this->name}: Skill module failed: {$e->getMessage()}. Using fallback.");
      return $this->runFallback($context);
    } catch (ParseException $e) {
      // Input parsing failed — mark all elements in this pass as HTML_WIDGET_COMPLEX
      $context->warn("Pass {$this->name}: Parse error: {$e->getMessage()}. All affected elements → HTML widget.");
      return $this->markAllAsHtmlWidget($context);
    } catch (\Throwable $e) {
      // Unexpected failure — log, return context unchanged
      $context->error("Pass {$this->name}: Unexpected error: {$e->getMessage()}. Pass skipped.");
      return $context;
    }
  }
  
  // Fallback: every element → HTML_WIDGET_COMPLEX
  // This is always safe — the HTML widget preserves the original appearance
  abstract protected function runFallback(ConversionContext $context): ConversionContext;
}
```

### Input Sanitisation

Before any pass runs, the HTML input is sanitised:

```php
class InputSanitiser {
  public function sanitise(string $html): SanitisedInput {
    // 1. Parse with lenient HTML5 parser (handle malformed HTML)
    // 2. Remove: <script type="application/ld+json"> (JSON-LD, irrelevant)
    // 3. Remove: <script src="*analytics*|*gtag*|*fbq*|*hotjar*"> (tracking)
    // 4. Keep: <script> blocks that contain animation/interaction logic
    //    (detect by checking for canvas API, RAF, IntersectionObserver,
    //     addEventListener, document.querySelector patterns)
    // 5. Inline external CSS: fetch stylesheets referenced in <link> tags
    //    (only if the URL is from the same origin or a CDN)
    // 6. Flag: external images (src starting with http) → note for manual replacement
    // 7. Flag: font imports in CSS (@import url('fonts.googleapis...')) → extract
    // 8. Normalise: convert <b> to <strong>, <i> to <em> (semantic HTML)
    // 9. Size check: if > 150KB HTML, warn user and suggest section-by-section mode
    
    return new SanitisedInput(
      html: $cleanHtml,
      flags: $flags,       // issues found during sanitisation
      externalFonts: $fonts,
      externalImages: $images
    );
  }
}
```

---

## 11. The Offline Learning Loop — Getting Better Without AI {#offline-learning}

The native converter can improve over time even without an AI engine, through a structured learning loop:

### Step 1: Conversion + Review

When a user imports a template and makes corrections in Elementor (changing widget types, adding classes, adjusting layouts), the plugin can detect these post-import changes if the user opts in to a "help improve the plugin" programme.

### Step 2: Diff Analysis

The plugin compares the generated JSON against the corrected JSON. Each difference is a learning signal:

```
Generated: widgetType "html" (HTML_WIDGET_COMPLEX, confidence 0.55)
Corrected: widgetType "heading" with specific class and typography settings
Learning signal: This element pattern → HEADING_WIDGET, confidence calibration needed
```

### Step 3: Pattern Library Update

When enough learning signals for the same pattern accumulate (configurable threshold, e.g., 10 users made the same correction), the pattern library is updated:

```
New signal added to [card-title] pattern:
  Signal: text_length_lt_60_chars + parent_has_border + font_size_gte_18px
  → increases HEADING_WIDGET confidence by 0.15
```

### Step 4: Distribution

Pattern library updates are distributed as plugin updates. Users who enable automatic minor updates receive improved conversion quality without any action. The pattern library version is tracked separately from the plugin version so library-only updates can be lightweight.

### Step 5: Community Patterns

Advanced: allow users to submit named patterns ("I keep seeing this testimonial-with-video layout") that become first-class entries in the pattern library. These are reviewed and tested before distribution.

---

## 12. Edge Case Hardening — Expanded Catalogue {#edge-cases}

Beyond the cases in the previous article, the Skill modules enable handling of these additional edge cases:

### EC-11: Designs with a dark/light mode toggle

**Signals:** Two colour scheme definitions, JS that toggles a class on `<body>`, CSS that uses `[data-theme="dark"]` or `.dark-mode` selectors.

**Handling:** The Colour Harmony Classifier detects two palettes. The primary palette (the one active by default based on the initial HTML) is used for the Elementor template. The secondary palette is noted in the conversion report with a suggestion to use Elementor Pro's Global Colours to create a toggle, or to use a third-party dark mode plugin.

### EC-12: Designs with sticky/parallax sections

**Signals:** `position: sticky`, `background-attachment: fixed`, JS scroll event listeners that modify `transform` or `top` values.

**Handling:** `position: sticky` on section elements → companion CSS, add note that Elementor Pro's Sticky feature achieves the same effect natively. `background-attachment: fixed` (parallax) → companion CSS. JS parallax → HTML widget with the JS, companion CSS targeting that widget's class.

### EC-13: Designs with CSS custom properties that are dynamically set by JS

**Signals:** JS that calls `document.documentElement.style.setProperty('--some-var', value)`.

**Handling:** The animation inventory flags these as JS-controlled custom properties. The CSS that uses these properties is moved to the Global Setup HTML widget as part of its JS, rather than to the static companion CSS. A comment in the companion CSS notes: "The following properties are controlled by JavaScript — see Global Setup widget."

### EC-14: Designs that use `grid-template-areas`

**Signals:** `grid-template-areas` in the container's CSS, `grid-area` on children.

**Handling:** The Layout Architecture Skill translates named grid areas to explicit `grid_column_start/end` and `grid_row_start/end` values. The named areas map is logged in the conversion report for reference.

### EC-15: Designs with very long pages (10+ sections)

**For AI converter:** Multi-turn conversation mode (described in the previous article) becomes mandatory. The Document Intelligence pass runs first as a separate API call to establish the design system, then sections are processed 3–4 at a time.

**For native converter:** All passes still run on the complete document, but section processing is done in parallel where passes allow it (passes 3, 4, and 5 can process sections independently once passes 1 and 2 are complete).

### EC-16: Designs where the same visual component appears multiple times

**Signals:** DOM subtrees that are structurally and stylistically identical (testimonial cards, pricing tiers, feature cards).

**Handling:** The pattern library matcher identifies the first instance and classifies it. All subsequent identical instances are classified the same way by reference, not by re-running the full classification. This is not just a performance optimisation — it ensures consistency. All three testimonial cards will have identical class naming patterns, which the companion CSS can target with a single rule.

### EC-17: Designs using `clip-path` for decorative shapes

**Signals:** `clip-path: polygon(...)` or `clip-path: path(...)` on elements.

**Handling:** Always companion CSS. The polygon/path values are passed through unchanged. Elementor has no panel equivalent for clip-path. The element itself may still be classified as a native container or widget — the clip-path is an additional style applied on top, not a reason to make the whole element an HTML widget.

### EC-18: Designs with form elements

**Signals:** `<form>`, `<input>`, `<textarea>`, `<select>`.

**Handling:** Forms require PHP/backend logic to work. The plugin classifies the form as HTML_WIDGET_COMPLEX and preserves the original HTML. The conversion report includes a note: "This form element was preserved as an HTML widget. For a functional form, replace it with Elementor Free's (limited) HTML embed or a form plugin widget. The form's visual styling is preserved in the companion CSS."

---

## 13. Performance Architecture — Speed Without Sacrificing Quality {#performance}

A 9-pass pipeline with multiple skill modules running on each pass sounds slow. In practice, well-designed passes with appropriate caching can process a standard landing page (10–15 sections) in under 3 seconds on typical server hardware.

### Lazy Evaluation

Not every element needs every skill module to run. The cascade is:
1. Pattern library match (fast lookup)
2. Visual fingerprinting (medium — needs style resolution)
3. Widget decision tree (medium — rule-based)
4. Full style resolution (slow — needs CSS cascade calculation)
5. HTML widget assembly (trivial — just copy the HTML)

Most elements will be resolved at step 1 or 2. Only ambiguous elements escalate to steps 3 and 4. HTML widgets skip step 4 entirely.

### CSS Specificity Cache

The most expensive operation is CSS cascade resolution. Build a selector → specificity cache so that the same selector is not parsed more than once:

```php
class SpecificityCache {
  private array $cache = [];
  
  public function getSpecificity(string $selector): Specificity {
    if (!isset($this->cache[$selector])) {
      $this->cache[$selector] = $this->calculate($selector);
    }
    return $this->cache[$selector];
  }
}
```

### Pass Parallelisation

Passes 3, 4, and 5 can run in parallel per section once passes 1 and 2 are complete. In a PHP environment, this can be done via forked processes. In Node.js, via Promise.all() over sections. The time saving on a 15-section page is significant (roughly 60% of the sequential time).

---

## 14. The Correction Feedback Loop — Closing the Human-in-the-Loop Gap {#correction-loop}

The correction feedback loop is the feature that most raises the ceiling on native converter quality, because it allows the system to be improved by every user who interacts with it.

### In-Plugin Correction Mode

After import, the plugin offers a "Review Conversion" mode in the WordPress admin that displays:

1. The conversion confidence report (which elements had low confidence)
2. A section-by-section list of decisions made with reasoning (from the decision log)
3. Editable fields: for each low-confidence decision, the user can select the correct widget type from a dropdown

When the user submits corrections:
1. The plugin re-runs passes 7–9 with the corrected classifications
2. A new JSON and CSS are generated
3. The corrections are logged (with opt-in) for the learning loop

### AI-Assisted Correction

When the user has Claude API access and a correction is submitted, the plugin can send the corrected element to Claude with a targeted prompt:

```
The following HTML element was classified as [original classification] 
with confidence [score]. The user has indicated the correct classification 
is [correct classification]. 

Generate the optimal Elementor JSON settings for this element as 
[correct classification], using these design tokens: [tokens].
Apply CSS class [class from Pass 5].
```

This targeted use of the AI for corrections is much more token-efficient than a full re-conversion, yet it applies AI-quality settings to the specific elements that need it.

---

## 15. Benchmark Targets — Defining "Parity" {#benchmarks}

To know when the native converter reaches parity with the AI converter, we need measurable targets:

| Metric | Current Native | Target Native | AI Converter |
|---|---|---|---|
| Structural accuracy (semantic HTML) | 78% | 92% | 93% |
| Widget type accuracy (semantic HTML) | 72% | 90% | 91% |
| Style fidelity (20 sample elements) | 65% | 85% | 87% |
| CSS class coverage | 84% | 99% | 99% |
| JSON validity (first output) | 94% | 99.5% | 97% |
| Utility-class HTML accuracy | 52% | 75% | 82% |
| Processing time (10-section page) | ~8s | <3s | 15–45s (API) |
| Offline capability | Yes | Yes | No |
| Deterministic output | Yes | Yes | No |
| Cost per conversion | $0 | $0 | $0.02–0.15 |

The native converter's target exceeds the AI converter on: JSON validity (deterministic validation vs AI's occasional schema errors), processing speed (no API latency), offline capability, determinism, and cost. It should trail slightly on ambiguous input types (utility-class HTML, obfuscated HTML) where the AI's semantic reasoning genuinely outperforms rules.

**The hybrid strategy for reaching parity:** Use the pattern library for 70% of cases (common design patterns where the library match is high confidence). Use the full skill-enhanced pipeline for the remaining 30% (novel patterns, unusual structures). Use AI-assisted correction for any element that fails the confidence threshold after the full pipeline. This three-tier approach produces AI-quality results for the majority of inputs with zero API cost.

---

## 16. Implementation Roadmap {#roadmap}

### Phase 1 — Foundation (Weeks 1–6)
Implement the 9-pass pipeline with basic skill rules. Ship the shared ConversionContext, decision logging, confidence scoring, and fallback chains. Pattern library starts with the NEXUS template's 11 sections as seed data. Target: 75% accuracy on semantic HTML.

### Phase 2 — Skill Module Integration (Weeks 7–12)
Implement all 10 native Skill modules with full training. Integrate the AI converter's per-pass Skill prompts. Implement the fuzzy pattern matcher. Target: 85% accuracy on semantic HTML, 65% on utility-class HTML.

### Phase 3 — Robustness and Edge Cases (Weeks 13–18)
Implement input sanitisation, parallel pass execution, specificity cache, and the full 18-case edge case catalogue. Implement the correction feedback loop. Target: 90% on semantic HTML, 72% on utility-class HTML.

### Phase 4 — Learning Loop and Community (Weeks 19–24)
Launch the opt-in learning programme. Implement pattern library updates via plugin updates. Open the community pattern submission system. Target: 92% on semantic HTML, 78% on utility-class HTML, and improving continuously.

---

*This document represents the v2 architecture specification for the HTML-to-Elementor conversion plugin, incorporating Skill module design, per-pass expert knowledge injection, native AI equivalence strategy, and the full technical thinking behind making a deterministic system perform at the level of a trained AI model for the most common design patterns it encounters.*

---

**Suggested tags:** Plugin architecture, Elementor conversion engine, skill modules, native AI parity, HTML parsing pipeline, design pattern recognition, CSS cascade resolver, agentic pipeline

**Suggested categories:** Plugin Development, Systems Architecture, AI Engineering, WordPress
