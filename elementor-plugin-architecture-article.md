# Building an HTML-to-Elementor Conversion Plugin: Architecture, Logic, and Deep Technical Thinking for Both AI-Powered and Offline Native Approaches

---

## Table of Contents

1. [Introduction and Scope](#intro)
2. [What the Conversion Problem Actually Is](#the-problem)
3. [How the Conversion Was Done Manually — The Full Internal Process](#manual-process)
4. [Plugin Architecture Overview](#architecture)
5. [Approach One: The Claude AI-Powered Pipeline](#claude-approach)
6. [Approach Two: The Offline Native Conversion Engine](#offline-approach)
7. [The Agentic Steps Model for Offline Conversion](#agentic)
8. [CSS Class Detection — The Central Hard Problem](#css-classes)
9. [HTML Structure Parsing — Mapping DOM to Elementor Widgets](#html-parsing)
10. [The Widget Decision Tree](#widget-tree)
11. [JSON Assembly — Building Valid Elementor Structure](#json-assembly)
12. [Handling the Global Setup — Canvas, Cursor, Fonts, Reveal](#global-setup)
13. [The Hybrid Detection Problem — When to Use Native vs HTML Widget](#hybrid-detection)
14. [Edge Cases Catalogue](#edge-cases)
15. [Limitations of the Claude AI Approach](#claude-limits)
16. [Limitations of the Offline Native Approach](#offline-limits)
17. [Workarounds and Mitigation Strategies for Both](#workarounds)
18. [The Two Template Versions — V1 vs V2 in Plugin Context](#two-versions)
19. [Companion CSS Generation Logic](#css-gen)
20. [Responsive Configuration Logic](#responsive-logic)
21. [Plugin UI and User Experience Design](#plugin-ui)
22. [Testing Strategy and Quality Metrics](#testing)
23. [Recommended Technology Stack](#tech-stack)
24. [Final Architecture Decision Summary](#summary)

---

## 1. Introduction and Scope {#intro}

This article documents the complete technical thinking behind a WordPress plugin that takes any custom HTML/CSS/JS web design and converts it into an importable Elementor JSON page template — automatically, without the user needing to understand JSON, Elementor's internal data model, or how widgets map to DOM nodes.

The plugin must support two distinct operational modes. The first uses the Claude API as its conversion intelligence — sending parsed HTML to Claude with a structured system prompt, receiving structured JSON back, validating it, and delivering it as a downloadable template file. The second works entirely offline, using a deterministic parsing engine, a trained rule set, and an agentic multi-pass processing pipeline to achieve the best possible conversion result without any external API dependency.

Both modes must produce the same two outputs: a valid Elementor JSON template file and a companion CSS file. Both must handle the same range of inputs — from tightly structured HTML with semantic class names to minified, framework-generated markup with auto-generated class names.

This article is not a quick overview. It is an attempt to think through every meaningful decision in this plugin's design — the parsing logic, the mapping rules, the agentic pipeline stages, the edge cases, the failure modes, and the workarounds. Much of what is documented here was discovered empirically during the manual conversion of the NEXUS SaaS landing page prototype, where the same decisions that a plugin must make automatically were made by hand and the consequences of each choice were observed directly.

---

## 2. What the Conversion Problem Actually Is {#the-problem}

Before writing a single line of plugin code, it is worth being precise about what "conversion" actually means in this context, because the naive understanding of the problem leads to the wrong architecture.

The naive understanding: "Parse HTML into a tree, map each node to an Elementor widget, emit JSON." This is what most people imagine when they think about this problem. It is wrong — or rather, it is incomplete in ways that will cause catastrophic failures on real-world HTML.

The actual problem has five distinct layers:

**Layer 1: Structural mapping.** What Elementor structural concept (container, grid container, inner container) corresponds to each HTML element? A `<section>` might be a top-level container. A `<div class="hero-bottom">` that uses flexbox might be an inner container. A `<div class="bento">` using CSS Grid might be a Grid container. The HTML node type alone does not tell you this — you need to read the CSS to understand the layout intent.

**Layer 2: Widget identification.** Within each structural container, which child nodes represent widgets? An `<h1>` is clearly a Heading widget. A `<button>` is clearly a Button widget. But what about a `<div class="stat-cell">` that contains a number and a label? Is that a Text Editor widget? A Counter widget? Two widgets in an inner container? An HTML widget? The answer depends on whether the content is static or animated, and whether it needs to be editable.

**Layer 3: Style extraction.** Elementor widgets have panel settings for typography, color, spacing, background, and border. For each widget, the plugin must extract the relevant CSS declarations from the stylesheet and translate them into Elementor's settings schema. A `font-size: clamp(64px, 9vw, 140px)` in CSS must become a typography setting. A `background: rgba(255,255,255,0.03)` must become a background color setting. But CSS can be applied via classes, IDs, inline styles, pseudo-classes, and inherited rules — the plugin must resolve the full cascade for each element.

**Layer 4: Effect classification.** Some CSS effects can be expressed in Elementor's panel. Some require custom CSS. Some require JavaScript and must become HTML widget content. Classifying each effect correctly — and deciding which layer it belongs to — is not a solved algorithmic problem. It requires semantic understanding of what the effect does.

**Layer 5: ID and class propagation.** Every Elementor widget and container needs meaningful CSS classes and element IDs populated in the Advanced tab. These are not in the original HTML in a form that maps cleanly to Elementor's `_css_classes` and `_element_id` fields. The plugin must generate them, preserve them consistently, and ensure the companion CSS uses the same class names.

A correct plugin architecture must address all five layers explicitly. A plugin that only addresses layers 1 and 2 will produce structurally valid JSON that looks nothing like the original design. A plugin that addresses all five will require significantly more sophisticated engineering.

---

## 3. How the Conversion Was Done Manually — The Full Internal Process {#manual-process}

Understanding how the manual conversion of the NEXUS prototype was done is essential for designing the plugin's logic, because the manual process was essentially the algorithm — and it can be made explicit.

### Stage 1: Section Inventory

Before touching any code, the prototype was read top to bottom and every distinct visual section was identified and named. This produced a list: Global Setup, Navigation, Hero, Marquee, Stats, Features (Bento), Process, Testimonials, Pricing, CTA, Footer.

For each section, three questions were answered:
- What is the primary layout type? (flex row, flex column, CSS grid)
- What content does it contain that must be editable?
- What effects does it use that cannot be reproduced natively in Elementor?

The answers to these three questions determined whether each section became a native container tree, an HTML widget, or a hybrid.

**Plugin implication:** Stage 1 is a structural segmentation pass. The plugin must identify top-level sections before attempting any widget-level mapping.

### Stage 2: CSS Resolution

For each section, the relevant CSS rules were read and the computed styles for key elements were noted. This meant reading `.hero-headline` and noting: font-family Syne, weight 800, size clamp(64px,9vw,140px), line-height 0.92, letter-spacing -3px. Then translating each property into its Elementor settings schema equivalent.

Some properties translated directly: `font-family` → `typography_font_family`, `font-weight` → `typography_font_weight`. Others required judgment: `clamp(64px, 9vw, 140px)` cannot be expressed in Elementor's typography panel (which takes a fixed pixel value), so it was moved to the companion CSS instead, and the Elementor JSON received a reasonable fixed value (120px) as a fallback.

Properties that have no Elementor equivalent — `-webkit-text-stroke`, `color: transparent` for outline text, `::before` and `::after` pseudo-elements, CSS custom properties, `animation` and `@keyframes` — were flagged as companion CSS content.

**Plugin implication:** The CSS resolver must parse the stylesheet, match rules to elements by selector specificity, and for each property decide: Elementor panel setting, companion CSS rule, or HTML widget internal style.

### Stage 3: Widget Mapping

With the section structure and computed styles known, each leaf-level content element was mapped to a widget type. The mapping was:
- `<h1>`, `<h2>`, `<h3>`, `<h4>` → Heading widget
- `<p>` text blocks → Text Editor widget
- `<a class="btn-...">`, `<button>` → Button widget (if simple) or Text Editor (if complex inline styling)
- `<img>` → Image widget
- `<ul>` with nav links → Icon List widget (in V2) or HTML widget (in V1)
- Animated components (counter divs, terminal blocks, pipeline bars, marquee) → HTML widget
- Canvas, cursor, particle system → HTML widget (body-injected JS)

For borderline cases — elements that were visually simple but had animated states — the decision was made in favour of HTML widgets to preserve the effect rather than risk losing it in a native widget that could not replicate the animation.

**Plugin implication:** The widget mapper must have a decision tree with clear rules for each HTML element type, modified by the presence of animation-related CSS and JS event bindings.

### Stage 4: ID and Class Generation

For every container and widget in the Elementor JSON tree, a CSS class name was generated following the `[prefix]-[section]-[element]` convention. Element IDs were generated for top-level sections. The companion CSS was then written using these same class names as selectors.

The prefix (`nx-`) was chosen to be short, memorable, and collision-safe. It was applied consistently to every element — not just sections, not just major containers, but every individual widget.

**Plugin implication:** The class generator must produce a prefix (from the project name or user input), apply the convention consistently to every node in the tree, and pass the class map to the companion CSS generator.

### Stage 5: Global Setup Assembly

The Global Setup HTML widget was assembled last, because its contents depend on knowing everything else in the template: which fonts are used, what the brand colors are (for CSS variables), what class names the scroll reveal system uses, and what effects need body-level injection.

The canvas script was written specifically to use `document.body.appendChild()` rather than inline canvas markup, based on the known Elementor stacking context problem.

**Plugin implication:** The Global Setup assembler runs after all other sections are processed and reads from the resolved token list (colors, fonts, class prefix).

### Stage 6: Validation and Repair

The assembled JSON was validated by running `JSON.parse()` against it. The companion CSS was reviewed for completeness. Several passes were needed to catch missing classes, inconsistent color values, and widget settings that used incorrect property names.

**Plugin implication:** The output validator must parse the JSON, check for structural completeness (every widget has required keys, every container has elements array, all IDs are unique), and optionally lint the companion CSS.

---

## 4. Plugin Architecture Overview {#architecture}

Based on the manual process documented above, the plugin can be structured as a pipeline with discrete, testable stages:

```
INPUT
  ↓
[HTML Parser] → DOM tree + inline styles
  ↓
[CSS Parser] → Resolved style map per element
  ↓
[JS Analyser] → Animation/interaction inventory
  ↓
[Segmenter] → Section list with layout types
  ↓
[Widget Mapper] → Widget decisions per element
  ↓
[Class Generator] → CSS class + ID assignments
  ↓
[Settings Extractor] → Elementor settings per widget
  ↓
[JSON Assembler] → Raw Elementor JSON tree
  ↓
[Global Setup Builder] → Global HTML widget content
  ↓
[Companion CSS Generator] → CSS file content
  ↓
[Validator] → JSON parse + structural checks
  ↓
OUTPUT: template.json + companion.css
```

Each stage is a discrete module. The Claude API approach replaces stages 2 through 8 with a single API call (though it still needs stage 1 for preprocessing and stages 9 through 11 for post-processing). The offline approach must implement all stages explicitly.

Both approaches share:
- The HTML Parser (stage 1)
- The Class Generator (stage 6, partially)
- The JSON Assembler shell (stage 8)
- The Global Setup Builder (stage 9)
- The Companion CSS Generator (stage 10)
- The Validator (stage 11)

The core intelligence — deciding what maps where — is what differs between approaches.

---

## 5. Approach One: The Claude AI-Powered Pipeline {#claude-approach}

### How It Works

The Claude API approach sends the preprocessed HTML, extracted CSS, and a comprehensive system prompt to the Claude API (claude-sonnet-4-6 or newer) and receives structured JSON back. The system prompt encodes all the conversion rules, naming conventions, widget decision logic, and output format requirements — essentially, it is the master conversion prompt from the tutorial article, translated into a machine-readable system instruction.

### Preprocessing Before the API Call

Before sending anything to Claude, the plugin must:

**1. Inline all CSS.** If the HTML references external stylesheets, fetch them and inline the computed styles as a `<style>` block. Claude cannot fetch external resources, so all styles must be present in the payload.

**2. Strip irrelevant content.** Remove `<script>` tags that are not relevant to layout or animation (analytics, tracking, CMS-injected code). Remove comments. Remove whitespace-only text nodes. This reduces token count.

**3. Extract design tokens.** Before the API call, programmatically extract CSS custom properties from `:root { ... }` declarations. These become the brand color and font list that primes Claude's understanding of the design system. Pass them explicitly in the prompt.

**4. Identify animated elements.** Scan the JavaScript for `IntersectionObserver`, `requestAnimationFrame`, `setInterval`, `addEventListener('mousemove')`, canvas contexts, and CSS animation/transition rules applied via JS. Flag these elements in the HTML (e.g., add a data attribute: `data-nx-type="animated"`) before sending.

**5. Size check.** Claude's context window is generous but not infinite. A large landing page with extensive inline CSS and JavaScript may exceed practical limits. If the payload exceeds approximately 80,000 characters, the plugin should split the page into sections and make multiple API calls, assembling the results.

### The System Prompt Strategy

The system prompt must be structured, not conversational. It should:
- State the output format requirement explicitly (valid JSON, specific schema)
- Provide the full widget type reference (heading, text-editor, button, html, image, icon-list, divider, spacer)
- State the naming convention with the project-specific prefix
- State the hybrid decision rules (what becomes native vs HTML widget)
- Include the Global Setup HTML widget specification
- Require the companion CSS as a second output
- Specify that all IDs must be unique and follow a naming pattern
- Require JSON.parse() valid output — no trailing commas, no comments in JSON

One critical design decision: **instruct Claude to return two JSON objects in a wrapper**, not raw JSON followed by CSS. A structure like:

```json
{
  "template": { ... elementor json ... },
  "companion_css": "... css string ..."
}
```

This makes programmatic extraction reliable. The plugin then splits the two outputs, saves them as separate files, and validates each independently.

### Multi-Turn Conversation for Large Pages

For pages too large for a single API call, the plugin should implement a multi-turn conversation:

**Turn 1:** Send the design token extraction and overall section inventory. Ask Claude to return only a section map — a JSON list of sections with their types, element counts, and suggested conversion approach. No full JSON yet.

**Turn 2 onwards:** Send each section's HTML and CSS individually, with the section map from Turn 1 as context. Ask Claude to convert that section only, using the prefix and conventions established in Turn 1.

**Final assembly:** Merge all section JSON objects into the final template, run the validator, generate the Global Setup and companion CSS from the accumulated token list.

This approach is slower and costs more API credits, but it is more reliable for complex designs because each section gets Claude's full attention rather than being one of many sections competing for context window space.

### Streaming and User Experience

The Claude API supports streaming responses. For a WordPress admin panel, streaming the JSON as it is generated gives the user feedback that something is happening. The plugin can display a progress indicator that updates as each section's JSON is confirmed valid.

However, streaming JSON is tricky — you cannot validate a partial JSON object. The recommended approach: stream to a buffer, display a "processing" animation, and only validate and deliver the file when the stream is complete.

### Cost and Rate Limiting

At the time of writing, a full landing page conversion (approximately 15,000–40,000 tokens input, 8,000–15,000 tokens output) costs fractions of a dollar per conversion. But in a plugin context with potentially many users, the hosting model matters:

**Option A: User provides their own API key.** Zero cost to the plugin developer. User manages their own Claude account. This is the simplest model and appropriate for a developer-facing plugin.

**Option B: Plugin developer provides API key via a SaaS wrapper.** Costs real money at scale. Requires a subscription model or credit system on the plugin side. More polished UX but more infrastructure.

**Option C: Hybrid.** Free tier uses the offline engine. Premium tier unlocks the Claude API conversion. This is commercially sensible and technically sound — the offline engine covers common cases and the AI handles complex ones.

---

## 6. Approach Two: The Offline Native Conversion Engine {#offline-approach}

The offline engine must replicate, deterministically, everything that Claude does intelligently. This is the harder engineering problem. Claude can infer that a `<div class="orb">` with `border-radius: 50%` and CSS animation is a decorative element that should become an HTML widget. A rule-based engine must be explicitly programmed with that inference.

The offline engine is best understood as a large, carefully designed decision tree applied at multiple levels of the DOM hierarchy. Every node in the HTML tree passes through the tree and receives a classification. The classifications then drive JSON generation.

### The Core Classification System

Every DOM node receives one of these primary classifications:

- **SECTION_CONTAINER** — top-level structural wrapper, becomes a top-level Elementor container
- **FLEX_ROW** — flex-direction row layout, becomes an inner container with flex row settings
- **FLEX_COLUMN** — flex-direction column layout, becomes an inner container with flex column settings
- **GRID_CONTAINER** — CSS grid layout, becomes an Elementor Grid container
- **HEADING_WIDGET** — `<h1>` through `<h6>` content, becomes a Heading widget
- **TEXT_WIDGET** — paragraph/text content, becomes a Text Editor widget
- **BUTTON_WIDGET** — `<a>` or `<button>` that is clearly a CTA, becomes a Button widget
- **IMAGE_WIDGET** — `<img>` tag, becomes an Image widget
- **LIST_WIDGET** — `<ul>` or `<ol>` with link items, becomes an Icon List widget
- **HTML_WIDGET_ANIMATED** — element with detected animation CSS/JS, becomes an HTML widget
- **HTML_WIDGET_COMPLEX** — element with layout too complex for native widgets, becomes an HTML widget
- **HTML_WIDGET_CANVAS** — `<canvas>` or canvas-creating JS, becomes a body-injected HTML widget
- **DECORATIVE** — element with no editable content and no children (pseudo-decorative divs, spacers), becomes a Spacer or Divider widget or is ignored
- **SKIP** — element that should not appear in the Elementor output (scripts, meta elements, hidden elements, cursor divs that will be recreated in Global Setup)

The classification algorithm applies these rules in order (first match wins):

1. If the element is `<canvas>` or creates a canvas → HTML_WIDGET_CANVAS
2. If the element has `position: fixed` and is not the main nav → HTML_WIDGET_ANIMATED
3. If the element has `animation:` or `@keyframes` targeted CSS → HTML_WIDGET_ANIMATED
4. If the element contains `<canvas>`, `<video>`, WebGL context → HTML_WIDGET_COMPLEX
5. If the element is `<h1>`–`<h6>` with only text content → HEADING_WIDGET
6. If the element is `<p>` with only text content → TEXT_WIDGET
7. If the element is `<a>` or `<button>` with button-like styling → BUTTON_WIDGET
8. If the element is `<img>` → IMAGE_WIDGET
9. If the element is `<ul>`/`<ol>` with `<li><a>` children → LIST_WIDGET
10. If the element has `display: grid` → GRID_CONTAINER
11. If the element has `display: flex` and `flex-direction: row` → FLEX_ROW
12. If the element has `display: flex` and `flex-direction: column` (default) → FLEX_COLUMN
13. If the element is `<section>`, `<header>`, `<footer>`, `<main>` → SECTION_CONTAINER
14. If the element is a `<div>` with only text children → TEXT_WIDGET
15. If the element is a `<div>` with mixed or complex children → FLEX_COLUMN (default container)
16. Anything else with no meaningful children → DECORATIVE

### CSS Parser Requirements

The offline engine's CSS parser must handle:

**Selector matching.** For a given DOM element, the parser must find all CSS rules whose selectors match that element and resolve the cascade (specificity ordering, `!important` flags). This is the most computationally expensive part of the offline engine.

**CSS custom properties.** Resolve `var(--color-primary)` by looking up the custom property value from the `:root` block or the nearest ancestor that defines it.

**Shorthand expansion.** `padding: 60px 40px` must be expanded to individual top/right/bottom/left values. `border: 1px solid rgba(255,255,255,0.1)` must be split into border-width, border-style, border-color.

**Calc expressions.** `calc()` values cannot be expressed in Elementor settings. Flag them for companion CSS.

**Clamp expressions.** `clamp(min, preferred, max)` cannot be set in Elementor's typography panel. Extract the min value as the Elementor setting and move the `clamp()` expression to companion CSS.

**Media query awareness.** The CSS parser must collect responsive rules (those inside `@media` blocks) separately from the base rules. These will feed the responsive configuration logic.

**What the CSS parser does not need to handle:** CSS animations (`@keyframes`, `animation:` property), CSS transitions (these go to companion CSS automatically), filter effects, backdrop-filter, SVG-specific properties, WebGL/canvas API properties.

### Style-to-Elementor Settings Translator

Once the CSS cascade is resolved for an element, the translator maps each property to its Elementor settings equivalent:

```
font-family → typography_font_family
font-weight → typography_font_weight
font-size (px only) → typography_font_size { unit: "px", size: N }
font-size (clamp) → companion CSS, fallback px to typography_font_size
letter-spacing (em) → typography_letter_spacing { unit: "em", size: N }
letter-spacing (px) → typography_letter_spacing { unit: "px", size: N }
line-height (unitless) → typography_line_height { unit: "em", size: N }
color → title_color (headings) or color (text editor)
background-color (solid) → background_background: "classic", background_color: hex
background-color (rgba) → background_background: "classic", background_color: rgba
padding (expanded) → padding { unit: "px", top, right, bottom, left, isLinked: false }
margin (expanded) → margin { unit: "px", top, right, bottom, left, isLinked: false }
gap → gap { unit: "px", size, column, row }
border → border_border, border_color, border_width object
border-radius (uniform) → border_radius { unit: "px", top/right/bottom/left all same }
min-height → min_height { unit: "vh" or "px", size: N }
```

Properties with no Elementor settings equivalent go into a companion CSS bucket for that element:

```
-webkit-text-stroke → companion CSS, targeting element's class
color: transparent → companion CSS
overflow: hidden → companion CSS
position (non-static) → companion CSS
z-index → companion CSS
box-shadow → companion CSS
filter/backdrop-filter → companion CSS
animation/transition → companion CSS
::before / ::after → companion CSS
```

---

## 7. The Agentic Steps Model for Offline Conversion {#agentic}

The single most important architectural decision for the offline engine is whether to use a single-pass conversion or a multi-pass agentic pipeline. The answer is unambiguously: **multi-pass is required** for production-quality output.

A single-pass engine reads the HTML once and emits JSON. It cannot look ahead to understand context. It cannot revise an early decision based on information found later. It produces acceptable output for simple, well-structured HTML and poor output for anything complex.

A multi-pass agentic pipeline runs a series of specialist passes over the same HTML/CSS input, each pass adding information to a shared context object. Each pass can read and use the output of previous passes. Later passes can revise early decisions. The final pass emits JSON from the fully-resolved context.

Here is the complete agentic pipeline for the offline engine:

### Pass 1: Document Intelligence

**What it does:** Reads the entire HTML document and builds a high-level understanding without yet making any mapping decisions.

**Outputs:**
- Design token list (CSS custom properties from `:root`)
- Font list (all `font-family` values found in CSS)
- Color palette (all unique hex/rgba values found in CSS)
- Animation inventory (all elements with `animation:`, `transition:`, JS event listeners, `IntersectionObserver`, `requestAnimationFrame`)
- Section boundary detection (top-level `<section>`, `<header>`, `<footer>`, and major `<div>` blocks with full-width layout)
- Complexity score per section (count of unique CSS rules, JS dependencies, DOM depth)

**Why it matters:** The section boundary detection in Pass 1 prevents a common failure in single-pass engines: deeply nested `<div>` trees where the engine cannot determine which `<div>` is a "section" vs a layout helper vs a card component. Pass 1 identifies the skeleton before anything else fills in the details.

### Pass 2: Layout Analysis

**What it does:** For each section identified in Pass 1, analyses the layout type.

**Outputs per section:**
- Primary layout: flex-row, flex-column, CSS grid, or block
- Grid definition if applicable: number of columns, `grid-template-columns` value, `grid-template-rows`, `gap`
- Grid child placement: for each child of a grid container, the `grid-column` and `grid-row` values
- Flex properties: `justify-content`, `align-items`, `flex-direction`, `gap`
- Nesting depth: how many levels of containers are needed
- Elementor container type recommendation: `container` (flex) or `container` with `container_type: "grid"`

**Why it matters:** Layout analysis must happen before widget mapping because the container type determines how children are positioned. A CSS Grid bento layout needs `grid_column_start/end` on each child. A flex row layout needs `width` percentages or `flex` values. Getting this wrong means the layout collapses regardless of how correct the widget-level content is.

### Pass 3: Content Classification

**What it does:** For each element within each section, applies the widget decision tree and assigns a primary classification.

**Inputs:** Pass 1 animation inventory, Pass 2 layout analysis.

**Outputs per element:**
- Primary classification (from the classification system above)
- Editability flag (true/false — will a client need to change this content?)
- Animation flag (true/false — does this element use CSS animation or JS?)
- Complexity flag (true/false — does this element use layout patterns Elementor cannot replicate natively?)
- Suggested widget type
- Confidence score (0–1, how confident the classifier is)

The confidence score is important. When the classifier is uncertain (confidence below 0.7), the element is flagged for the HTML Widget fallback. Uncertain classification that produces a native widget with wrong settings is worse than a clean HTML widget that just works.

**Edge case handling in Pass 3:**

Elements with very short text content inside a complex parent may be wrongly classified as TEXT_WIDGET when they are actually decorative labels. The rule: if the parent element has `position: absolute` or `position: relative` with transform applied, and the element's text content is fewer than 10 characters, classify as HTML_WIDGET_COMPLEX rather than attempting a native widget.

Elements that are `<a>` tags styled as buttons but containing `<span>` children for arrow icons or decorative elements should be classified as BUTTON_WIDGET if the total text content (stripping child elements) matches a CTA pattern, or HTML_WIDGET_COMPLEX if the visual treatment is too ornate for a native button.

### Pass 4: Style Resolution

**What it does:** For every element classified as a native widget type in Pass 3, resolves the full CSS cascade and translates properties to Elementor settings.

**This pass is expensive.** It must:
1. For each element, collect all matching CSS rules sorted by specificity
2. Apply the cascade (later rules override earlier ones; `!important` wins)
3. Resolve CSS custom property references
4. Expand shorthands
5. Classify each resolved property as: Elementor panel setting, companion CSS, or ignore

**Output:** A settings map per element: `{ elementor_settings: {...}, companion_css_rules: [...] }`

### Pass 5: Class and ID Generation

**What it does:** Assigns CSS class names and element IDs to every element that will become a container or widget in the JSON.

**Inputs:** Pass 3 classifications, Pass 1 section boundaries, user-provided project prefix.

**Naming logic:**
- Top-level section containers: `[prefix]-[section-name]` where section-name is inferred from the element's existing class names (see the CSS class detection section below)
- Inner containers: `[prefix]-[section-name]-[layout-role]` (e.g., `[prefix]-hero-bottom`, `[prefix]-pricing-grid`)
- Heading widgets: `[prefix]-[section]-[heading-role]` (e.g., `[prefix]-hero-headline`, `[prefix]-features-title`)
- Text editor widgets: `[prefix]-[section]-[content-role]` (e.g., `[prefix]-hero-sub`, `[prefix]-step-desc`)
- Button widgets: `[prefix]-btn-[variant]` (e.g., `[prefix]-btn-primary`, `[prefix]-btn-ghost`)
- HTML widgets: `[prefix]-[section]-[component]` (e.g., `[prefix]-hero-terminal`, `[prefix]-stats-counter`)
- Scroll reveal: add `[prefix]-reveal` to all major containers and cards, `[prefix]-d1/d2/d3` for staggered children

**Output:** An element-to-class-map: `{ element_id_in_dom: { css_class: "...", element_id: "..." } }`

### Pass 6: Global Setup Synthesis

**What it does:** Assembles the content of the Global Setup HTML widget using accumulated information from all previous passes.

**Inputs:**
- Pass 1 font list and color palette
- Pass 1 animation inventory (to know which JS effects need body-level injection)
- Pass 5 class prefix and scroll reveal class names
- Design tokens from CSS custom properties

**Output:** Complete HTML string for the Global Setup widget, including Google Fonts link tag, CSS variables, cursor HTML, canvas injection script, particle system, scroll reveal observer, and nav scroll listener.

**The canvas injection decision:** If Pass 1 found a `<canvas>` element or any JS creating a canvas context with `position: fixed`, the Global Setup widget will include the `document.createElement('canvas') + document.body.appendChild()` pattern. If no canvas was found, this is omitted.

### Pass 7: JSON Assembly

**What it does:** Walks the classified element tree and emits the Elementor JSON structure.

**Inputs:** All previous passes.

**Algorithm:**

```
function assembleNode(element, context):
  classification = context.classifications[element.id]
  settings = context.settings[element.id]
  cssClass = context.classMap[element.id]
  
  if classification == SECTION_CONTAINER:
    return buildContainer(element, settings, cssClass,
      children = element.children.map(child => assembleNode(child, context)))
  
  if classification == GRID_CONTAINER:
    return buildGridContainer(element, settings, cssClass,
      children = element.children.map(child => assembleNode(child, context)))
  
  if classification in [FLEX_ROW, FLEX_COLUMN]:
    return buildInnerContainer(element, settings, cssClass,
      children = element.children.map(child => assembleNode(child, context)))
  
  if classification == HEADING_WIDGET:
    return buildHeadingWidget(element, settings, cssClass)
  
  if classification == TEXT_WIDGET:
    return buildTextEditorWidget(element, settings, cssClass)
  
  if classification == BUTTON_WIDGET:
    return buildButtonWidget(element, settings, cssClass)
  
  if classification in [HTML_WIDGET_ANIMATED, HTML_WIDGET_COMPLEX]:
    return buildHtmlWidget(element.outerHTML, settings, cssClass)
  
  if classification == HTML_WIDGET_CANVAS:
    return null  // handled in Global Setup, not emitted here
  
  if classification == DECORATIVE:
    return buildSpacerOrNull(element)
  
  if classification == SKIP:
    return null
```

The recursive tree walk means that containers naturally contain their classified children. The tree structure of the JSON mirrors the section structure of the original HTML, which is what Elementor expects.

### Pass 8: Companion CSS Generation

**What it does:** Assembles the complete companion CSS file from all accumulated companion_css_rules and the class map.

**Structure of output:**
1. Header comment block with the full class map (Pass 5 output)
2. CSS tokens block (`:root` with all custom properties)
3. Page-level overrides (body background, cursor, z-index stack)
4. Utility classes (scroll reveal, stagger delays)
5. Per-element rules, in section order
6. Responsive breakpoints (@media blocks derived from the media query rules collected in Pass 4)

### Pass 9: Validation and Repair

**What it does:** Validates the assembled JSON and companion CSS and attempts automatic repair of common issues.

**JSON validation checks:**
- `JSON.parse()` succeeds
- All `id` values are unique (collision detection)
- All containers have an `elements` array (even if empty)
- All widgets have a `widgetType` key
- All `_css_classes` values are strings (not arrays, not null)
- All `padding`, `margin`, `gap`, `font_size` values are objects with the correct shape
- No duplicate `_element_id` values

**Auto-repair capabilities:**
- Regenerate duplicate IDs
- Add empty `elements: []` to any widget node that is missing it
- Coerce string padding values to object form
- Strip invalid characters from class names

**Output:** Final JSON and CSS, plus a repair report listing any issues found and fixed.

---

## 8. CSS Class Detection — The Central Hard Problem {#css-classes}

This deserves its own section because it is the most difficult problem the plugin faces and the one most likely to produce wrong results in edge cases.

When a user uploads their HTML, the CSS class names could be:

**Case A: Semantic and descriptive.** Classes like `.hero`, `.hero-headline`, `.bento-card`, `.process-step`, `.pricing-featured`. This is the ideal case. The offline engine can read these class names and infer section names, element roles, and naming hierarchy almost directly. Pass 5 class generation becomes simple: preserve the semantic meaning, apply the project prefix.

**Case B: BEM (Block Element Modifier) naming.** Classes like `.hero__headline--large`, `.pricing-card__feature-list`, `.btn--primary`. BEM is structured and parseable. The block name is the section, the element name is the role, the modifier is the variant. The engine can split on `__` and `--` to extract the hierarchy.

**Case C: Utility-first (Tailwind, Bootstrap).** Classes like `text-8xl font-black tracking-tight leading-none text-white`. These class names describe visual properties, not semantic roles. The engine cannot infer "this is the hero headline" from `text-8xl`. It must infer the role from the element's tag type, its position in the DOM, and its computed styles. This is significantly harder and less reliable.

**Case D: Auto-generated or obfuscated.** Classes like `.abc123`, `._3xHk2`, `.sc-jnlKLf`. These appear in CSS-in-JS frameworks (Styled Components, Emotion), CSS Modules with hashing enabled, or minified production builds. The class names carry zero semantic information. The offline engine must rely entirely on DOM structure, element type, and resolved styles to classify elements. This is the hardest case.

**Case E: Mixed.** The most common real-world case. Some classes are semantic (`.hero`, `.cta-button`), some are utility (`.flex`, `.gap-4`, `.text-center`), some are auto-generated (`.sc-abc123`). The engine must handle all three simultaneously for the same element.

### Detection Strategy Per Case

**For Cases A and B:** Extract the "role" from the class name using a role vocabulary lookup. Build a vocabulary of common semantic terms:

```
section-level role vocabulary:
hero, header, banner, above-fold, landing → HERO
features, capabilities, what-we-do, services → FEATURES
process, how-it-works, steps, workflow → PROCESS
testimonials, reviews, social-proof, clients → TESTIMONIALS  
pricing, plans, tiers, cost → PRICING
cta, call-to-action, conversion, get-started → CTA
footer, site-footer, page-footer → FOOTER

element-level role vocabulary:
headline, title, heading → HEADING role
subtitle, subheading, sub → SUB_HEADING role
body, description, copy, text, paragraph → TEXT role
button, cta, action, link → BUTTON role
card, item, feature → CARD role
tag, label, eyebrow, badge → TAG role
```

If a class name matches or contains any vocabulary term, the element inherits that role label. BEM blocks are first-class section identifiers.

**For Case C (Tailwind/Bootstrap):** The CSS parser resolves computed styles and the element is classified entirely by style characteristics:
- Very large font-size + full-width + near-top-of-document + heading tag → likely hero headline
- `display: grid` with multiple children of similar structure → likely a card grid
- Small font + all-caps + letter-spacing → likely a tag/label
- Full-viewport height + flex column justify-end → likely hero container

**For Case D (obfuscated):** Same as Case C but with additional heuristics based on DOM structure. Elements are classified by their structural role: first significant child of `<main>` is likely hero, `<footer>` is footer, elements with 3–6 similar siblings are likely cards.

**For Case E (mixed):** Apply A/B detection first. If no semantic class is found, fall through to C/D.

### Prefix Detection and Generation

When the user has a custom prefix (e.g., their HTML uses `.my-` or `.xyz-`), the plugin should detect and reuse it:

1. Find all class names in the HTML
2. Count the most common prefix substring (split at `-`)
3. If one prefix appears in more than 40% of class names, it is likely the project prefix
4. Offer to use the detected prefix or let the user override it

If no consistent prefix is found, generate one from the domain name, page title, or let the user input one. Default to a short alphanumeric string if nothing is available.

---

## 9. HTML Structure Parsing — Mapping DOM to Elementor Widgets {#html-parsing}

The HTML parser must produce a DOM tree augmented with resolved styles and classification labels. In a PHP WordPress plugin, this is done using PHP's `DOMDocument` class. In a JavaScript-based tool (Node.js plugin or browser extension), the native `DOMParser` API works well.

### Parser Requirements

**Handle malformed HTML.** Real-world HTML is rarely valid. The parser must use a lenient parser (PHP's `loadHTML` with `LIBXML_NOERROR` | `LIBXML_NOWARNING`, or an HTML5 parser like `html5-php` for PHP, or the browser's built-in parser for JS).

**Preserve source order.** Elementor's JSON is order-sensitive — widgets render in the order they appear in the `elements` array. The parser must preserve DOM source order.

**Extract inline styles.** Inline `style=""` attributes must be parsed and merged into the resolved style map, with highest specificity (they override class and tag rules).

**Handle conditional content.** Some HTML uses `display: none` for hidden states. The plugin should skip elements with `display: none` in their computed styles, as they do not render on the page.

**Handle template-specific patterns.** Some HTML prototypes use patterns like `<!-- SECTION: HERO -->` comments to mark section boundaries. The parser should look for these and use them as section boundary hints.

### The Depth Limit Problem

Elementor has a practical limit on container nesting depth — in the editor, deeply nested containers become difficult to select and the rendering can slow down. The plugin should:
- Flatten any HTML subtrees that are more than 4 levels deep where the intermediate levels add no visual structure
- Collapse single-child containers (a container containing only one container) into their child
- Move deeply nested decorative elements (absolutely positioned `::before` equivalents) to companion CSS

---

## 10. The Widget Decision Tree {#widget-tree}

The full decision tree for widget classification, incorporating all the edge cases discovered during the NEXUS build:

```
Is element a <canvas>?
→ YES: HTML_WIDGET_CANVAS (handled in Global Setup)

Is element the cursor overlay (#cursor, [id*="cursor"], 
  [class*="cursor"] with position:fixed)?
→ YES: SKIP (recreated in Global Setup)

Is element a fixed-position full-page background 
  (position:fixed + width:100% + height:100% + z-index < 0)?
→ YES: SKIP (recreated in Global Setup)

Is element a <script>, <style>, <link>, <meta>, <noscript>?
→ YES: SKIP

Does element have display:none or visibility:hidden 
  and no JS-toggled class?
→ YES: SKIP

Does element or its descendants use requestAnimationFrame, 
  setInterval, or CSS @keyframes?
→ YES: HTML_WIDGET_ANIMATED

Does element use CSS animation: or transition: on itself 
  (not children)?
→ YES (and it is a structural container): keep as native container, 
  move animation to companion CSS
→ YES (and it is a leaf element): HTML_WIDGET_ANIMATED

Is element a <h1>–<h6> with only text content 
  (no non-inline children)?
→ YES: HEADING_WIDGET

Is element a <p> with only text and inline elements?
→ YES: TEXT_WIDGET

Is element an <a> or <button> with button-like styling 
  (padding, background-color, no block children)?
→ YES: BUTTON_WIDGET
→ Exception: if <a> contains non-text/non-span children → HTML_WIDGET_COMPLEX

Is element an <img> with src attribute?
→ YES: IMAGE_WIDGET (note src for placeholder generation)

Is element a <ul> or <ol> where all <li> contain only <a> tags?
→ YES: LIST_WIDGET

Is element a <ul> or <ol> where <li> contain complex content?
→ YES: HTML_WIDGET_COMPLEX

Does element have display:grid?
→ YES: GRID_CONTAINER
  → sub-classify each direct child with grid placement settings

Does element have display:flex?
→ YES: flex-direction determines FLEX_ROW or FLEX_COLUMN

Is element <section>, <header>, <footer>, <main>, <article>?
→ YES: SECTION_CONTAINER

Is element a <div> that has the characteristics of a card 
  (similar siblings, border, background, padding, 
  children include heading + text)?
→ YES: FLEX_COLUMN (inner container, classified as card)
  → apply nx-[section]-card class

Is element a <div> with only text content?
→ YES: TEXT_WIDGET

Is element a <div> with only heading tag children?
→ YES: HEADING_WIDGET (use the heading tag's level)

Is element a <div> with position:absolute or transform applied 
  to text content that is < 10 characters?
→ YES: HTML_WIDGET_COMPLEX (decorative label)

Is element a <div> with children that include both text and 
  animated/complex elements?
→ YES: HTML_WIDGET_COMPLEX

Default for <div> with multiple heterogeneous children:
→ FLEX_COLUMN (native container, let children be classified individually)

Default for any unhandled element type:
→ HTML_WIDGET_COMPLEX
```

---

## 11. JSON Assembly — Building Valid Elementor Structure {#json-assembly}

### ID Generation

Every container and widget in Elementor JSON must have a unique `id` field. Elementor uses 8-character alphanumeric IDs. The plugin must:

1. Generate IDs that are globally unique within the template
2. Be deterministic when the same input is processed twice (so re-running the conversion on the same HTML produces the same IDs, enabling diffs)
3. Avoid collisions with any IDs already in the WordPress database (which Elementor stores in post meta)

**Recommended approach:** Generate IDs as `md5(section_name + element_type + index).substring(0, 8)`. This is deterministic and has very low collision probability for typical page sizes.

### Settings Object Construction

Every widget type has required settings keys. Missing keys cause Elementor to use defaults or throw errors. The plugin must know the required keys for each widget type:

**Heading widget minimum settings:**
```json
{
  "title": "...",
  "header_size": "h1",
  "_css_classes": "...",
  "_element_id": "...",
  "typography_typography": "custom",
  "typography_font_family": "...",
  "typography_font_weight": "...",
  "typography_font_size": { "unit": "px", "size": 0 },
  "title_color": "..."
}
```

**Text Editor widget minimum settings:**
```json
{
  "editor": "<p>...</p>",
  "_css_classes": "...",
  "_element_id": ""
}
```

**Button widget minimum settings:**
```json
{
  "text": "...",
  "link": { "url": "#", "is_external": false, "nofollow": false },
  "_css_classes": "...",
  "background_color": "...",
  "button_text_color": "...",
  "typography_typography": "custom",
  "border_radius": { "unit": "px", "top": 0, "right": 0, "bottom": 0, "left": 0 }
}
```

**HTML widget minimum settings:**
```json
{
  "html": "...",
  "_css_classes": "...",
  "_element_id": ""
}
```

**Container minimum settings:**
```json
{
  "flex_direction": "column",
  "background_background": "classic",
  "background_color": "...",
  "padding": { "unit": "px", "top": "0", "right": "0", "bottom": "0", "left": "0", "isLinked": false },
  "_css_classes": "...",
  "_element_id": ""
}
```

**Grid container additional settings:**
```json
{
  "container_type": "grid",
  "grid_columns_fr": "...",
  "gap": { "unit": "px", "size": 16, "column": 16, "row": 16 }
}
```

### The `isInner` Flag

Elementor uses `isInner: true` for containers nested inside other containers. The rule: the top-level sections (direct children of the template root) have `isInner: false`. Everything nested inside has `isInner: true`. Getting this wrong causes layout rendering issues in the editor.

---

## 12. Handling the Global Setup — Canvas, Cursor, Fonts, Reveal {#global-setup}

The Global Setup widget is the most important and most complex HTML widget in any template generated by this plugin. It must be assembled in Pass 6 using information gathered across all previous passes.

### Font Loading Strategy

The plugin must detect all `font-family` values used in the design and generate a single Google Fonts `<link>` tag that loads all required font families with all required weights. The Google Fonts URL format is:

```
https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap
```

The plugin must:
1. Collect all `font-family` values from the resolved style map
2. For each font family, collect all `font-weight` and `font-style` values used
3. Check if the font is available on Google Fonts (can use a bundled list of GF families)
4. If not on Google Fonts, flag for manual self-hosted font setup and add a comment in the companion CSS
5. Generate the concatenated URL

### CSS Variables Block

The `:root` block in the Global Setup's `<style>` tag must include:
- All brand colors from the design token list
- All font family names
- The stroke/border color as an rgba variable
- A `--prefix` variable containing the class prefix (useful for JS that generates dynamic class names)

### Canvas Injection — Why Body-Level Matters

As established earlier, the canvas must be injected into `document.body`. But there is another subtlety: the canvas must be injected before the Elementor containers render, otherwise it briefly appears on top of content before z-index settles. The solution: inject the canvas as the very first operation in the IIFE, before any other setup code runs.

Additionally: the canvas must have `z-index: 0`, all Elementor containers must have `position: relative; z-index: 2` (applied via the companion CSS `body .elementor-section, body .e-con { position: relative; z-index: 2; }`), and the noise overlay on `body::after` must have `z-index: 1`. This three-level z-index stack (canvas:0 → noise:1 → content:2) ensures the correct visual layering.

### Scroll Reveal Observer — Timing Considerations

The IntersectionObserver must be initialized after `DOMContentLoaded` because Elementor may dynamically insert some widgets after the initial HTML parse. The IIFE should check `document.readyState` and either run immediately (if `'interactive'` or `'complete'`) or wait for `DOMContentLoaded`.

Additionally, the observer should re-query for `.nx-reveal` elements after Elementor's `elementor/frontend/init` event fires (which can be listened for via `window.addEventListener`) to catch any widgets added dynamically by Elementor's popup or sticky functionality.

---

## 13. The Hybrid Detection Problem — When to Use Native vs HTML Widget {#hybrid-detection}

One of the hardest decisions the offline engine must make is the native vs HTML widget boundary. Making the wrong call in either direction has consequences:

**Too aggressive with native widgets:** Visual effects are lost. A card with a gradient border that used `::before` for the gradient effect becomes a native container with no visible border effect. The page looks worse than the original.

**Too aggressive with HTML widgets:** Everything becomes an HTML blob. Editability is zero. The whole point of native widgets is lost.

The key insight from building NEXUS V1 and V2 is: **the hybrid boundary should be drawn at the component level, not the section level**. A pricing section can have:
- Native containers for the overall grid structure (editable)
- Native heading widgets for the plan name (editable)
- Native text-editor widgets for the price amount (editable)
- Native button widgets for the CTA (editable)
- An HTML widget for the badge ("MOST POPULAR" with absolute positioning) (visual only)
- The companion CSS handling hover effects, border colors, and feature list arrow styling

This means the plugin must be able to split a single HTML component — a pricing card — into both native widgets (the editable content) and HTML widget fragments (the non-editable decorative elements), and compose them together inside a single native container.

### Editability Scoring

The offline engine should assign an editability score (0–10) to each leaf element:

- `10` — Must be natively editable. Pure text content (heading, paragraph, button label). Clients will definitely need to change this.
- `7–9` — Should be editable. Feature list items, testimonial quotes, pricing amounts. Likely to need updating.
- `4–6` — Could go either way. Tag labels, stat numbers (if static). Editability is nice but not critical.
- `1–3` — Unlikely to need editing. Author initials in avatar circles, decorative diamond bullets, pipe step labels.
- `0` — Never editable. Canvas effects, cursor rings, orbital rings, blinking cursors, progress bar fill animations.

Score ≥ 7 → native widget. Score < 4 → HTML widget. Score 4–6 → apply the editability tiebreaker: if the element is at the same DOM depth as other editable content in the same card, make it native for consistency.

---

## 14. Edge Cases Catalogue {#edge-cases}

### Edge Case 1: User uploads a CSS framework prototype (Tailwind)

**What happens:** Class names carry no semantic information. Every element has 10+ utility classes. The section segmentation cannot use class names.

**Mitigation:** The engine falls back entirely to structural and style-based classification. `<section>` boundaries are honoured. Elements are classified by tag type, DOM position, and computed styles. The output will be structurally correct but class names for the companion CSS will be generic (generated from tag type and position rather than semantic role). Quality will be acceptable for simple layouts, lower for complex grids.

**Enhancement:** Pre-process Tailwind HTML with a Tailwind CSS extractor that resolves all utility class values to a computed style object, then treats the result as inline CSS. This removes the framework dependency and produces a clean computed-styles-only input for the main engine.

### Edge Case 2: User uploads minified/production HTML

**What happens:** All class names are obfuscated (`._3xHk`, `.sc-abc`). No meaningful class names exist anywhere.

**Mitigation:** Full style-based classification with structural heuristics. The companion CSS class names will be entirely AI-generated based on element type and position (`.px-hero-heading-1`, `.px-section-2-card-3`). The template will function correctly but will not be easy to maintain.

**Enhancement:** Offer a "deobfuscation pass" where the user provides the original source HTML (before minification) alongside the minified version. The engine matches elements by structure and transfers the semantic class names.

### Edge Case 3: User uploads a multi-page HTML file (tabs, show/hide sections)

**What happens:** Some sections have `display: none` and are toggled by JavaScript. The engine's SKIP rule will omit these.

**Mitigation:** Detect `display: none` elements that have JS event listeners or are referenced by JS that modifies `display` or `visibility`. Flag these in a report. Ask the user whether to include them as separate Elementor sections or to omit them. If included, generate them as separate containers with the reveal animation class, which can be re-purposed as a section toggle.

### Edge Case 4: User uploads HTML with SVG illustrations

**What happens:** Inline `<svg>` elements may be decorative or content. A decorative SVG background texture should become an HTML widget. A content SVG illustration should ideally become an Image widget (after export to PNG/SVG file). An SVG icon inside a button should stay with the button.

**Mitigation:**
- SVG with `aria-hidden="true"` or `role="presentation"` → HTML widget (decorative)
- SVG as the only content of an element → HTML widget (illustration), flag in report for possible image export
- SVG with `<title>` or `<desc>` accessible text → HTML widget with the accessible text noted in the report
- SVG inside a `<button>` or `<a>` → part of the Button widget HTML, keep inline

### Edge Case 5: User uploads HTML with CSS Grid and explicit row spans

**What happens:** The bento grid in NEXUS uses `grid-row: span 5` etc. Elementor's Grid container supports explicit `grid_row_start` and `grid_row_end`. The engine should detect these.

**Mitigation:** The CSS parser detects `grid-column` and `grid-row` on grid children. Values like `span 5` are converted to `grid_row_start: 1, grid_row_end: 6` (adding 1 for end). Values like `2 / span 3` are converted to `grid_row_start: 2, grid_row_end: 5`. The Elementor JSON receives the explicit placement values. **Known limitation:** Elementor's editor visual representation of the grid may not honour all explicit placements in the drag-and-drop interface, but the rendered frontend will be correct.

### Edge Case 6: User uploads HTML with CSS custom properties but no `:root` declaration

**What happens:** Custom properties are declared on specific elements (`.hero { --accent: #c8ff00; }`) rather than `:root`. The design token extractor misses them.

**Mitigation:** The CSS parser must collect custom property declarations from all selectors, not just `:root`. Build a complete custom property map: `{ selector: { property: value } }`. When resolving `var(--accent)` on an element, walk up the selector specificity chain to find the nearest ancestor that declares `--accent`.

### Edge Case 7: User uploads HTML with GSAP or complex JS animation libraries

**What happens:** GSAP animations cannot be replicated in Elementor in any form. The JS is not portable. Elements with GSAP animations applied will look correct at their initial state but will not animate.

**Mitigation:** Detect GSAP imports (either `<script src="*gsap*">` or `import gsap from`). Flag all elements where `gsap.` or `TweenLite.` or `TimelineMax.` is called. Classify all flagged elements as HTML_WIDGET_ANIMATED and include the original JS in the widget. Add a prominent warning in the conversion report: "GSAP animations detected. These are included as HTML widget scripts. The WordPress page must have GSAP loaded globally for these to function. Consider converting to CSS animations for better portability."

### Edge Case 8: User uploads HTML with a design that uses `mix-blend-mode` for text effects

**What happens:** `mix-blend-mode: multiply` or `mix-blend-mode: screen` on text elements creates compositing effects that have no Elementor equivalent.

**Mitigation:** Detect `mix-blend-mode` in resolved styles. Move to companion CSS. Add warning: "mix-blend-mode effects require the companion CSS to be loaded and may not display correctly in the Elementor editor preview. Test on published frontend."

### Edge Case 9: User uploads HTML that has already been partially built in Elementor (copy-paste from Elementor's preview HTML)

**What happens:** The HTML contains Elementor's wrapper divs (`elementor-section`, `elementor-column`, `elementor-widget-container`), data attributes (`data-id`, `data-element_type`), and widget class patterns. This is a very different input from a clean prototype.

**Mitigation:** Detect Elementor's class signatures (`elementor-section`, `e-con`, `elementor-widget-*`). If detected, offer to run in "Elementor HTML extraction mode" where the engine reads the data attributes directly rather than inferring widget types from structure. This is a simpler problem than general HTML conversion and can produce much more accurate output.

### Edge Case 10: Hero headline with mixed content (text + styled spans)

**What happens:** `<h1>Build<br><em>workflows</em><br>that <span class="acid-word">think.</span></h1>` — the headline contains inline elements with different styles. A standard Heading widget in Elementor cannot apply different styles to different words.

**Mitigation:** Detect headings with mixed inline elements. If the inline styling is limited to `<em>` (italic) and simple `<span>` with colour changes, generate the Heading widget with the full HTML as the title value (Elementor's Heading widget accepts HTML in the title field). If the inline styling is more complex (transforms, animations, gradients), fall back to Text Editor widget or HTML widget. Include companion CSS rules targeting `.[prefix]-hero-headline em` and `.[prefix]-hero-headline .acid-word` for the special styling.

---

## 15. Limitations of the Claude AI Approach {#claude-limits}

### Context Window and Token Cost

A full landing page with extensive CSS and JS can approach 50,000 input tokens. At current pricing this is manageable, but for a multi-page site being converted section by section, costs accumulate. The plugin must implement smart preprocessing to minimise token usage:
- Strip comments and whitespace aggressively
- Remove duplicate CSS rules
- Inline only the CSS that applies to elements being converted
- Strip analytics, tracking, and CMS-specific scripts entirely

### Non-Determinism

Claude will not produce identical output for identical inputs every time. The JSON structure, class names, and settings values may vary between runs. This is a significant problem for a plugin where users expect consistent, predictable output. Mitigation: use temperature=0 in API calls to maximise determinism. Use structured output prompting (asking for strict JSON format) to minimise variation in output shape.

### JSON Validity

Claude can produce invalid JSON — unclosed brackets, trailing commas, comments inside JSON objects. The plugin must always validate with `JSON.parse()` and handle parse errors gracefully. For recoverable errors (trailing commas, single-line comments), the plugin can apply automatic repair before re-parsing. For unrecoverable errors, the plugin must re-call the API with an error-correction prompt.

### Schema Correctness

Claude may generate settings keys that do not exist in the target Elementor version, or use incorrect value formats (wrong unit types, string values where objects are expected). The plugin must validate the settings of each widget type against a known schema. A bundled JSON schema for each Elementor widget type, updated to match the installed Elementor version, allows validation and auto-repair of settings errors.

### Hallucinated Widget Types

Claude may invent widget type names that do not exist in Elementor Free (e.g., using `counter` or `animated-headline` which are Pro-only widgets, or inventing `stat-card` which does not exist at all). The plugin must whitelist permitted widget types and replace any unrecognised type with the nearest appropriate Free widget.

### API Availability

The Claude API requires internet access. In environments without reliable internet (local development, certain corporate networks, restricted hosting), the AI approach fails completely. The offline engine must be available as a fallback.

---

## 16. Limitations of the Offline Native Approach {#offline-limits}

### Cannot Understand Design Intent

The offline engine works on explicit syntax — HTML structure, CSS rules, DOM relationships. It cannot infer what a design is trying to achieve. Claude can look at a `<div class="orb">` with `border-radius: 50%`, blur, and a slow CSS animation and understand "this is a decorative glowing sphere, it should be an HTML widget." The offline engine sees: `display: block`, `border-radius: 50%`, `filter: blur(30px)`, `animation: pulse 4s`, classifies it as HTML_WIDGET_ANIMATED, and moves on. The result is the same in this case — but for less obvious elements, the offline engine will make wrong calls that Claude would handle correctly.

### CSS Selector Complexity

Full CSS cascade resolution — handling descendant selectors, sibling selectors, pseudo-classes, specificity calculations, `!important` flags, and inherited properties — is a non-trivial engineering problem. A simplified CSS resolver that only handles class selectors and tag selectors will fail on any well-written stylesheet that uses complex selectors. A full CSS resolver adds significant complexity and maintenance burden to the plugin.

The mitigation: implement CSS specificity correctly but scope the resolver to only the selectors present in the input HTML. Do not attempt to parse or resolve universal styles or complex pseudo-class chains unless they appear in the input.

### Grid Layout Limitations

Elementor's Grid container has specific rules about how grid children are placed and how auto-placement interacts with explicit placement. The offline engine may generate valid JSON with explicit placement settings that Elementor then overrides with its own auto-placement logic. The visual result on the frontend will be correct (CSS Grid is respected by browsers regardless of Elementor's logic), but the editor representation may look wrong.

This is acceptable for most use cases but should be clearly documented.

### Responsive Configuration

The offline engine can read `@media` queries from the CSS and generate responsive CSS rules for the companion CSS file. However, adding responsive settings directly to the Elementor JSON widget settings (the `_tablet` and `_mobile` responsive keys) requires knowing the exact internal key names for every responsive property in every widget type. These keys are not publicly documented and have changed between Elementor versions. The offline engine should generate responsive CSS in the companion file and add a note in the report recommending which settings to adjust in the Elementor responsive panel.

### New Elementor Features

The offline engine's widget type list, settings schema, and container model must be kept up to date with Elementor's releases. Elementor 3.x introduced the Flexbox Container as a replacement for the legacy Section/Column system. A future version may introduce new container types or deprecate current widget settings. The plugin must be versioned against Elementor releases and updated accordingly.

---

## 17. Workarounds and Mitigation Strategies for Both {#workarounds}

### Workaround 1: Pre-Validation of Input HTML

Before running either conversion pipeline, validate the input HTML against a set of quality checks:
- Is this valid HTML5? (Warn if not, but proceed)
- Are all CSS classes referenced in HTML present in the stylesheet?
- Are there obvious framework signatures (Tailwind, Bootstrap, CSS-in-JS)?
- Is the HTML from a rendered Elementor page (needs extraction mode)?
- Is the HTML minified? (Suggest deobfuscation)
- Are there external dependencies that cannot be inlined?

Surface these as a pre-conversion report so the user can decide whether to proceed or fix issues first.

### Workaround 2: Interactive Correction Mode

After conversion, present the user with a visual diff — the original HTML rendered alongside the Elementor template preview — and allow them to select elements that converted incorrectly and re-classify them manually. This is essentially a UI for overriding Pass 3 classifications. The user selects an element, chooses a classification (native heading, text editor, HTML widget, etc.), and the pipeline re-runs from Pass 4 onwards for that element.

For the Claude approach, the interactive correction mode sends the corrected element back to Claude with a feedback prompt: "The user has indicated this element should be a [widget type]. Please regenerate the settings for this element."

### Workaround 3: Template Library Matching

Bundle a library of known design patterns with their Elementor JSON equivalents. When the segmenter identifies a section that matches a known pattern (pricing grid, testimonial cards, bento features, hero with eyebrow/headline/sub/CTA), use the template library as the starting point and populate it with the actual content and styles from the input HTML. This produces higher-quality output for common patterns than either general-purpose approach can achieve.

The NEXUS template itself becomes the first entry in this library. Subsequent builds add more patterns.

### Workaround 4: Partial Conversion with Manual Completion Guidance

For sections that the engine has low confidence about (either below the classification threshold or containing patterns not in the decision tree), instead of producing poor-quality JSON, the engine produces an HTML widget containing the original HTML for that section, with a prominent comment block at the top explaining what it is and suggesting how to rebuild it natively:

```html
<!-- 
  NEXUS PLUGIN: This section could not be automatically converted.
  Reason: Contains GSAP animation with complex timeline.
  Suggestion: Rebuild as a native container with an HTML widget for
  the animation. The content structure is:
  - h3 heading: "AI Workflow Orchestration"
  - p text: "Chain LLMs, APIs..."
  - div.terminal: keep as HTML widget
-->
<div class="original-section">
  ... original HTML ...
</div>
```

This gives the user actionable information rather than a broken native widget.

### Workaround 5: Diffing and Re-Conversion

For the Claude approach, implement an automatic retry with diff. If the first conversion output fails validation or the settings schema check produces too many errors, automatically send a second API call with:
- The original HTML (same as before)
- The failed JSON output
- A list of specific errors found in validation
- An instruction to fix only the listed errors

This targeted error correction is more token-efficient than re-running the full conversion and resolves the majority of common errors in one pass.

---

## 18. The Two Template Versions — V1 vs V2 in Plugin Context {#two-versions}

The plugin should offer both output modes, explained to the user in plain terms:

**Output Mode A: Design Fidelity Mode (V1)**
Produces a template that looks as close as possible to the original design. Uses HTML widgets for any section where native Elementor containers would compromise the visual result. Best for designers and developers who will maintain the site. Editing content requires opening HTML widget source.

**Output Mode B: Editable Mode (V2)**
Produces a template where all text content, headings, and CTAs are in native Elementor widgets, editable from the panel without touching code. Uses Elementor Grid containers for grid layouts. Visual effects that cannot be replicated natively are preserved as HTML widgets. Best for client handoffs and sites where non-developers update content.

**The plugin UI for this choice** should not use technical language. Present it as:

> "How will this site be maintained?"
> ◉ I'll maintain it myself (prioritise visual accuracy)
> ◉ My client will update content (prioritise panel editability)
> ◉ Let the plugin decide based on complexity

The third option runs both pipelines and selects on a per-section basis: if a section has a complexity score above a threshold, use HTML widget mode for that section; otherwise use native widget mode.

---

## 19. Companion CSS Generation Logic {#css-gen}

The companion CSS generator runs after JSON assembly and has access to:
- The complete element-to-class map (Pass 5)
- All companion CSS rules collected during Pass 4 (style properties with no Elementor equivalent)
- The design token list (Pass 1)
- The section structure and widget types

### Structure of the Generated CSS

```
/* ── HEADER COMMENT BLOCK ── */
/* Complete class map: one line per class, section, and widget */
/* [p]-hero-headline → Heading widget, Hero section, Advanced tab CSS Classes */

/* ── DESIGN TOKENS ── */
:root { /* CSS custom properties */ }

/* ── PAGE OVERRIDES ── */
body, .elementor-page { /* background, font, etc */ }
body .elementor-section, body .e-con { position: relative; z-index: 2; }

/* ── UTILITY CLASSES ── */
.[p]-reveal { opacity: 0; transform: translateY(40px); ... }
.[p]-reveal.[p]-visible { opacity: 1; transform: translateY(0); }
.[p]-d1, .[p]-d2, .[p]-d3 { /* stagger delays */ }

/* ── SECTION: HERO ── */
/* One subsection per section, all rules grouped */

/* ── RESPONSIVE ── */
@media (max-width: 1024px) { ... }
@media (max-width: 768px) { ... }
```

### Deduplication

The same CSS property may appear multiple times — once from a class rule, once from an inherited rule, once from a shorthand expansion. The generator must deduplicate: for each selector, keep only the most specific (winning) value for each property.

### Rule Ordering

CSS rules must be ordered from least specific to most specific. Section-level rules before card-level rules before widget-level rules. This ensures that more specific overrides actually override rather than being overridden.

---

## 20. Responsive Configuration Logic {#responsive-logic}

The responsive logic runs in two parts: companion CSS media queries (generated automatically) and Elementor JSON responsive settings (generated on request, with caveats).

### Companion CSS Responsive Rules

The CSS parser collects all `@media` rules from the input CSS. For each responsive rule, it checks:
- Does the breakpoint match Elementor's standard breakpoints (1024px, 768px, 480px)?
- Does the rule target an element that has been classified as a native widget or container?

If yes to both: the responsive rule goes into the companion CSS file under the corresponding breakpoint. If yes to first but no to second (element is an HTML widget): the responsive rule goes inside the HTML widget's `<style>` block.

If the breakpoint does not match Elementor's standards, it is mapped to the nearest standard breakpoint with a note.

### Generating Mobile-First Defaults

If the input HTML has no responsive CSS, the generator produces a basic set of default responsive rules:

```css
@media (max-width: 1024px) {
  /* All flex-row containers stack to column */
  /* Padding values reduced by ~40% */
  /* Section desc text-align: left */
}

@media (max-width: 768px) {
  /* All sections get 24px horizontal padding */
  /* All grid containers go to single column */
  /* Font size floors applied */
}
```

These defaults are conservative and will need adjustment, but they prevent complete layout breakage on mobile. A note in the conversion report explains what was auto-generated and which sections need manual responsive tuning.

---

## 21. Plugin UI and User Experience Design {#plugin-ui}

The plugin's admin interface in WordPress should guide the user through the conversion process with clear states:

### Step 1: Upload
File upload area accepting `.html` files (single file with inline CSS/JS) or a `.zip` of HTML + CSS + assets. Drag-and-drop support. Size limit of 5MB (adequate for any single-page design). Inline validation: detect if uploaded file is valid HTML, show a preview.

### Step 2: Project Settings
- Project name (used for file naming and prefix generation)
- Custom prefix (auto-detected, user can override)
- Output mode (Design Fidelity vs Editable vs Auto)
- API mode toggle (Claude AI vs Offline Engine)
- If Claude AI: API key field (with link to get key), or toggle to use plugin-provided key if SaaS model

### Step 3: Conversion Progress
Progress display showing active pipeline stage. For the Claude approach: "Preprocessing... Sending to Claude API... Receiving response... Validating JSON..." For the offline approach: "Pass 1: Document Intelligence... Pass 2: Layout Analysis... Pass 3: Content Classification..." etc. Estimated time remaining. Cancellation button.

### Step 4: Results
Conversion quality report showing:
- Number of native widgets generated
- Number of HTML widgets generated
- Number of elements skipped
- Warnings (missing classes, unsupported effects, responsive configuration needed)
- Interactive diff preview (original HTML alongside Elementor template preview, if preview server available)

Download buttons: `.json` template file, `companion.css` file, both as a `.zip`.

### Step 5: Import Instructions
Inline guidance: "Import this JSON at Elementor → My Templates → Add New → Import Template. Then add the companion CSS at Elementor → Site Settings → Custom CSS. Remember to set your Global Colors and Fonts first."

---

## 22. Testing Strategy and Quality Metrics {#testing}

### Unit Tests

Every pipeline stage should have unit tests with a suite of test cases covering:
- Clean semantic HTML (baseline, should produce highest quality)
- Tailwind utility HTML (stress test for class name detection)
- Obfuscated class names (worst case)
- Complex nested grids (stress test for layout analysis)
- GSAP animations (stress test for animation detection)
- SVG-heavy HTML (stress test for SVG classification)
- Very large HTML (performance and token management)

### Quality Metrics

Define quantitative quality metrics to compare output against the original design:
- **Structural accuracy:** What percentage of top-level sections were correctly identified?
- **Widget type accuracy:** What percentage of elements received the correct widget classification?
- **Style fidelity:** For a sample of 20 elements, what percentage of CSS properties were correctly translated to Elementor settings?
- **Class coverage:** What percentage of widgets in the JSON have a non-empty `_css_classes` value?
- **JSON validity:** Does the output pass JSON.parse() without error?
- **Schema validity:** What percentage of widget settings objects are schema-valid?

### Regression Testing

Build a test HTML library that grows over time — one HTML file per design pattern encountered in the wild. Run the conversion pipeline against every file in the library on each code change and flag any regressions in the quality metrics.

---

## 23. Recommended Technology Stack {#tech-stack}

### WordPress Plugin Core
PHP 8.1+, WordPress 6.0+ hooks and APIs, namespaced code under a plugin prefix.

### HTML/CSS Parsing (PHP)
`html5-php` (masterminds/html5-php) for lenient HTML5 parsing in PHP. `sabberworm/php-css-parser` for CSS parsing and specificity calculation. Both are Composer packages.

### HTML/CSS Parsing (JavaScript alternative)
If building as a JavaScript-first plugin (with Node.js server or browser-based processing): `parse5` for HTML5 parsing, `css-tree` for CSS parsing, and `css-select` for selector matching.

### Claude API Integration
Anthropic's official PHP SDK (`anthropic/sdk`) or direct HTTP calls using WordPress's `wp_remote_post()`. Streaming responses via SSE (Server-Sent Events) for the progress UI.

### JSON Schema Validation
`opis/json-schema` (PHP) for validating generated JSON against Elementor widget schemas. Bundle a schema file per Elementor version.

### Frontend Admin UI
Vanilla JS or Vue 3 for the plugin admin interface. No heavy frameworks — this is an admin UI, not a SaaS app. `CodeMirror 6` for the interactive diff/correction UI.

### File Handling
PHP's `ZipArchive` for handling `.zip` uploads and creating downloadable asset packages. WordPress's `WP_Filesystem` for secure file operations.

---

## 24. Final Architecture Decision Summary {#summary}

After all of the above analysis, here are the key architectural decisions and the reasoning behind each:

**Use a multi-pass agentic pipeline for the offline engine, not single-pass.** The quality improvement from having each pass build on the last is too significant to sacrifice for implementation simplicity.

**Implement both approaches in the same plugin, not two separate plugins.** They share preprocessing, validation, Global Setup generation, and companion CSS generation. The core intelligence (Claude API vs deterministic rule engine) is swappable at one stage of the pipeline.

**Offline engine output quality: aim for 80% on semantic HTML, 60% on utility-class HTML, 40% on obfuscated HTML.** Being honest about these numbers helps set user expectations and guides where engineering effort should be invested.

**Claude AI approach should use a system prompt based on the master conversion prompt from the tutorial**, extended with structured output requirements and error correction instructions.

**The hybrid boundary (native widget vs HTML widget) should be drawn at the component level, not the section level.** This is the single most impactful design decision for producing usable output.

**The Global Setup HTML widget must inject the canvas and cursor into `document.body` via JavaScript, not place them in the widget's own HTML markup.** Failure to do this will result in the particle canvas not displaying on a large proportion of Elementor-powered WordPress sites.

**All generated templates should default to Elementor Flexbox Container system (not legacy Section/Column).** Legacy support is not worth maintaining; the free version of Elementor 3.x+ uses the new system.

**The companion CSS file is as important as the JSON.** Any plugin that delivers JSON without the companion CSS is delivering an incomplete product. The two must always be delivered together and documented as a pair.

**Interactive correction mode is a future feature, not a launch requirement.** Get the automated conversion working well first. Interactive correction adds significant UI complexity and should come in version 2.

**Template library matching is the highest-ROI feature for V1.** Common patterns (hero, pricing, testimonials, bento, footer) appear in the majority of landing pages. Matching these patterns and populating a known-good template with the actual content will produce better output than any general-purpose parser for the most common cases.

---

*This article represents the complete design specification for a production-quality HTML-to-Elementor conversion plugin as derived from hands-on experience building the NEXUS SaaS landing page template and its two Elementor conversion outputs. The approaches, limitations, and workarounds documented here reflect real decisions made during that build, extended and systematised for plugin implementation.*

---

**Suggested tags:** Elementor plugin development, HTML to Elementor conversion, AI web design tools, WordPress plugin architecture, Elementor JSON, Claude API integration, offline conversion engine, page builder automation

**Suggested categories:** Plugin Development, WordPress Engineering, AI Tools, Web Design Automation
