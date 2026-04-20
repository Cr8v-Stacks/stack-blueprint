# Stack Blueprint Converter Tasks And Ground Rules

Last updated: 2026-04-13
Status: Active working tracker

## Purpose

This file exists so the converter roadmap does not keep getting lost in chat threads or reduced to one-off section fixes.

It must stay aligned with the existing **9-pass pipeline** already implemented in the plugin.

References used to build this tracker:
- `elementor-plugin-architecture-article.md`
- `enhancement-implementation-guide.md`
- `AUDIT.md`
- `CHANGELOG.md`
- `includes/converter/class-native-converter.php`

## Non-Negotiable Ground Rules

1. **Structure first.**
   The first success condition is that the imported Elementor layout matches the source section structure and hierarchy closely enough to edit safely.

2. **Native first, not native only.**
   We prefer free Elementor-native widgets and containers where they can carry the section, but we do not strip complex inner pieces just to stay “pure native”.

3. **Hybrid is allowed anywhere.**
   Any rebuilt native section may contain preserved HTML widgets for hard inner fragments such as animated visuals, canvases, terminals, pipeline bars, SVG-heavy blocks, orbitals, charts, or unusual media structures.

4. **Do not localize a fix unless it becomes a rule.**
   Every bug fix must answer: “what engine rule was missing?” If that answer is not written down and implemented broadly, the fix is incomplete.

5. **Preserve useful hooks when simplifying.**
   Rebuilding content into native Elementor widgets must not discard the classes, IDs, wrappers, relationships, and selectors that the companion CSS/JS still needs.

6. **Pseudo-elements are first-class.**
   `::before` and `::after` are not optional polish. They often carry icons, dividers, overlays, glows, decorative marks, accent lines, watermark text, and layout-critical visuals.

7. **Top-of-page systems are first-class.**
   Cursor systems, particle canvases, preloaders, viewport overlays, floating widgets, and page bootstrap scripts belong in the Global Setup path and must be preserved or rebuilt intentionally.

8. **No fake success.**
   A conversion is not “good” just because JSON parsed. Fidelity validation must catch missing scripts, missing pseudo rules, missing spans, missing top-level assets, and selector drift.

9. **Use the simplest stable native widget when possible.**
   Examples:
   - list-like content with repeated `ul/li` or equivalent rows → prefer `icon-list`
   - flowing copy with paragraph semantics → prefer `text-editor`
   - repeated label/value blocks → prefer nested containers + heading/text widgets
   - complex animated or highly custom inner visuals → preserve as in-place HTML widget

10. **Root namespace only; do not over-prefix descendants.**
    The isolation boundary should live primarily at the page/root namespace level. Source selectors, classes, and IDs should be preserved and retargeted beneath that root where practical, instead of inventing a second noisy descendant naming universe.

## The 9-Pass Pipeline Must Remain The Spine

The current engine already declares this pipeline in `includes/converter/class-native-converter.php`. All work below must map back to these passes rather than creating a second conflicting workflow.

### Pass 1 — Document Intelligence

Goal:
- Discover the real page inventory before conversion begins.

Open tasks:
- Detect major top-level `<div>` sections, not only semantic section tags.
- Detect body-level/global systems:
  - `<canvas>`
  - cursor overlays
  - preloaders
  - fixed overlays
  - global modals
  - floating widgets
- Detect framework signatures, external dependencies, and minification signals.
- Extract inline-markup patterns that matter later:
  - split-word logos
  - nested highlight spans
  - mixed inline emphasis inside headings, badges, nav labels, cards, blog titles
- Build a real animation and interaction inventory:
  - `requestAnimationFrame`
  - `setInterval`
  - `IntersectionObserver`
  - GSAP/Lottie/ScrollTrigger
  - marquee loops
  - counters
  - cursor motion
  - canvas/WebGL patterns

### Pass 2 — Layout Analysis

Goal:
- Understand the source layout contract, not just the tag tree.

Open tasks:
- Preserve real grid contracts from source:
  - bento/mosaic spans
  - asymmetric grids
  - sticky columns
  - masonry-like layouts
- Stop collapsing complex source layouts into generic equal-height card grids.
- Track wrapper contracts explicitly so rebuilt native output does not drift from the source shape.

### Pass 3 — Content Classification

Goal:
- Choose the right representation model for each node.

Required decision ladder:
1. Can this be represented cleanly with free Elementor-native structure?
2. If not fully, can it be rebuilt natively with preserved inner HTML fragments?
3. If not safely, preserve the full source block.

Open tasks:
- Promote this ladder into a formal engine rule, not a section rule.
- Expand hard-pattern coverage:
  - stats/counters
  - bento/mosaic cards
  - process/timelines
  - FAQs/accordions
  - tabs
  - sliders/carousels
  - blog cards
  - comparison tables
  - dashboards/terminal blocks
- Preserve animated sub-components in-place inside native cards anywhere, not just in isolated templates.
- Repeated-sequence extraction has started for process-family sections:
  - process/timeline/workflow/roadmap/milestone wrappers now use a broader repeated-child detection path instead of depending only on `process-step`-style class names
  - nested wrapper/process-shell layouts now also use repeated descendant-group detection, so step families can still resolve when the actual items are one level deeper than the obvious wrapper
  - repeated card extraction is used as a last-resort native process fallback instead of preserving or inventing steps
- Repeated-column extraction has started for footer-family sections:
  - footer menus now use broader repeated-column detection instead of depending only on `footer-col`-style class names
  - repeated sibling columns with heading + links and repeated descendant groups under a shared parent can now resolve as footer columns
  - repeated `ul/ol` list groups can now resolve as footer navigation columns even when the source has weak wrapper naming or only exposes list structure
  - repeated anchor clusters can now resolve as footer navigation groups when wrappers and list markup are both weak or missing
  - when grouping structure is too weak but the footer still exposes real links, a final real-link aggregation path can emit a non-synthetic navigation column from those source links instead of failing empty
  - brand/logo-only columns are filtered so they do not crowd out actual navigation columns

### Pass 4 — Style Resolution

Goal:
- Resolve the real styling contract that must survive conversion.

Open tasks:
- Make `::before` / `::after` first-class in the resolver and output pipeline.
- Preserve the gradient-text trio as a locked bundle.
- Resolve CSS custom properties before downstream consumers depend on them.
- Preserve wrapper-level hover, overlay, and pseudo contracts, not only inner content styles.
- Capture inline, descendant, grouped, and media-query rules in a form the retargeter can use later.

### Pass 5 — Class And ID Generation

Goal:
- Produce stable hooks without breaking the original styling model.

Architecture direction to implement:
- Keep one root/page namespace for collision safety.
- Stop treating source classes as irrelevant.
- Preserve source classes/IDs/selectors as the primary styling language under that root.

Open tasks:
- Replace descendant over-prefixing with root-level scoping plus source-selector preservation.
- Stop leaking inconsistent per-item IDs/classes that the CSS companion does not know how to target.
- Ensure every emitted native wrapper still receives usable structural hooks in the Advanced tab.
- Prevent JSON/CSS mismatches between generated `_element_id`, `_css_classes`, and companion selectors.

### Pass 6 — Global Setup Synthesis

Goal:
- Build the true page bootstrap block.

Open tasks:
- Generalize top-of-page asset carryover:
  - fonts
  - tokens
  - cursor setup
  - canvas injection
  - reveal bootstrapping
  - dependency scripts
  - global animation setup
- Auto-inject supported CDN dependencies where required.
- Ensure preserved scripts and global setup scripts can coexist without duplicate bootstrapping.

### Pass 7 — JSON Assembly

Goal:
- Emit a clean editable Elementor tree that still honors source structure.

Open tasks:
- Enforce a general hybrid-fragment contract:
  - native outer layout
  - preserved inner complex fragment in correct position
  - wrapper hooks preserved
- Prevent duplicate visitation / duplicated children.
- Preserve inline HTML where Elementor widgets support it:
  - heading title HTML
  - button text HTML where safe
  - inline span/em markup
- Ensure rebuilt JSON remains readable and predictable, not a mix of random source classes and half-prefixed artifacts.

### Pass 8 — Companion CSS Generation

Goal:
- Generate one trustworthy companion stylesheet for the emitted structure.

Open tasks:
- Build a real selector-retargeting engine, not section-specific bridge patches.
- Scope source CSS under the root namespace while preserving source selectors beneath it.
- Carry over:
  - pseudo-elements
  - media queries
  - keyframes
  - hover states
  - descendant selectors
  - grouped selectors
- Ensure the CSS only targets structures that actually exist in the emitted JSON.
- Remove stale state leakage across conversions.

### Pass 9 — Validation And Repair

Goal:
- Validate fidelity, not only syntax.

Open tasks:
- Validate that:
  - top-of-page/global systems are present
  - expected scripts are present
  - pseudo-driven visuals are covered
  - mosaic spans match source layout
  - wrapper/grid contracts remain coordinated
  - IDs are unique
  - emitted CSS selectors map to emitted JSON hooks
- Emit honest diagnostics:
  - `native_rebuilt`
  - `hybrid_native`
  - `fully_preserved_source`
- Fail loudly on structural degradation instead of pretending success.

## Cross-Pass Architecture Epics

These are the broad-spectrum tasks that matter more than one section fix.

### Epic A — Unified Selector Model

Problem:
- The converter currently mixes a native prefixed Elementor model with a raw source-selector rescue model.

Target state:
- one root namespace for isolation
- source selectors preserved beneath that root
- no noisy descendant over-prefixing as the primary model

### Epic B — CSS Retargeting Engine

Problem:
- companion CSS is still partly hand-bridged and section-shaped

Deliverables:
- generic selector retargeting
- pseudo-element support
- keyframe carryover
- media query carryover
- grouped selector support
- emitted-selector validation

### Epic C — JS Carryover And Scoping Engine

Problem:
- animations and setup JS are still inconsistently carried

Deliverables:
- selector/ID retargeting for preserved scripts
- global setup script scoping
- fragment-level script carryover where needed
- page boot order rules
- dependency injection rules

### Epic D — General Hybrid Rendering

Problem:
- HTML fragment preservation is still too local

Deliverables:
- preserved inner HTML fragment support anywhere
- wrapper hover/pseudo/layout rules preserved with the native host
- clear native/hybrid/preserved diagnostics

### Epic E — Fidelity Validator

Problem:
- JSON validity is being mistaken for successful conversion

Deliverables:
- structural diff checks
- selector coverage checks
- animation inventory checks
- span/layout contract checks
- global asset checks

## Immediate Priority Backlog

- [ ] Resolve the architecture conflict in `class-native-converter.php` line 18: stop treating input classes as irrelevant.
- [ ] Define and implement the root-namespace + preserved-source-selector model.
- [ ] Make `::before` / `::after` fully supported across detection, retargeting, and validation.
- [ ] Build the generic JS carryover/scoping path for reveal, counters, marquee, cursor, canvas, and similar systems.
- [ ] Finish general hybrid rendering so complex inner visuals can survive anywhere inside native sections.
- [ ] Fix source-to-output selector mismatches between JSON hooks and companion CSS.
- [ ] Fix stale state leakage across conversions in span/layout metadata.
- [ ] Build a fidelity checklist report after conversion, not just a class map.
- [ ] Preserve inline markup patterns generally, not only in footer logos.
- [ ] Cover blog/article prototypes with the same rules, not with separate emergency logic.
- [ ] Build an Elementor Free Widget Capability Matrix and use it as a classifier/settings input for native widget decisions.

## Latest Audit Snapshot

This section captures the broader-spectrum audit conclusion that should guide work going forward.

### Core Criticism

The converter still does not have a robust, general carryover system for arbitrary source CSS, JS, selectors, and behaviors.

Even when some fixes sound engine-like, they are still too often framed around section families instead of the real missing capabilities.

### Things The Audit Says Must Be Treated Explicitly

- `::before` / `::after` are still not a solved first-class rule.
  They carry visual polish, icons, dividers, overlays, hover glows, decorative marks, accent lines, watermark text, and layout cues on host selectors rather than in HTML.

- Missing JS animations are a core engine issue, not a card issue.
  Reveal, counters, marquee, cursor, particles, orbital effects, and similar behavior all depend on script carryover and selector compatibility.

- Top-of-page setup like `<canvas>`, global `<script>`, and page-level setup assets are not fully generalized yet.
  Right now some are manually curated into “global setup”, but arbitrary uploaded setups are not reliably classified and preserved.

- Inline-markup preservation is still too local.
  A footer logo using nested `<span>` is only one example. The same pattern can appear in nav, hero, cards, blog headings, badges, and other content-bearing widgets.

- Source-selector carryover is still too fragmented.
  The current output still risks mixing:
  - root prefixing
  - descendant prefixing
  - raw source bridge rules
  - aliases
  - rescue CSS
  when what we actually want is a cleaner single model.

## Architecture Direction To Implement

This is the broad-spectrum plan that should shape the implementation work.

### 1. Unify The Selector Model

The converter should stop inventing a second descendant class system for rebuilt content.

Target:
- keep one page/root namespace for isolation
- preserve source classes, IDs, and selectors as the primary styling contract wherever possible

### 2. Build A Real CSS Retargeting Engine

This should not be “bridge bento” or “bridge stats”.
It should be a generic pass that:

- scopes source CSS under the generated root namespace
- preserves media queries and keyframes
- rewrites selectors against emitted DOM structure
- handles descendant selectors, combinators, and grouped selectors
- treats `::before` and `::after` as first-class

### 3. Build A Real JS Carryover Engine

This should be a generic pass that:

- preserves source script blocks
- scopes and rewrites selectors and IDs to the emitted DOM
- supports top-page setup like cursor, canvas, particles, reveal, counters, marquee, and similar page behaviors
- injects global setup once, fragment setup where needed

### 4. Generalize Hybrid Rendering

For any rebuilt native block, if part of it is too custom, preserve that inner piece as HTML in-place.

The rule should be:
- native outer structure and layout
- preserved inner fragment where complexity lives
- wrapper hover, layout, and pseudo contracts preserved too

### 5. Add Fidelity Validation Before Declaring Success

The converter should compare source vs output for:

- top-level setup assets present
- grid span metadata preserved
- visual fragments preserved
- pseudo-element rules carried
- script blocks carried
- selector coverage not orphaned

## Prefixing Decision

Recommended architecture:

- keep root prefixing
- drop descendant over-prefixing as the primary model
- preserve and retarget source selectors instead

Why:

- collision safety in WordPress and Elementor
- fewer prefix mismatches
- less JSON and CSS mess
- less selector drift
- better carryover from arbitrary uploaded HTML

This means the future model should move away from:

- prefixed root
- prefixed descendants
- raw source bridge
- extra aliases
- rescue CSS

and toward:

- prefixed root only
- source selectors preserved underneath
- CSS and JS rewritten and scoped to that root

## Dedicated Implementation Sprint

No more blind fixing.

The next implementation sprint should focus on these four engine rules first:

1. root namespace model
2. CSS retargeting with pseudo support
3. JS carryover and scoping
4. fidelity validation

Everything else should be treated as dependent work hanging off those four foundations.

## Current Blockers From Latest Sample Outputs

These are the concrete failures already observed in recent generated outputs and should be used as regression checks while implementing the engine work above.

### Selector And Prefix Model

- Prefixes are now more project-derived, but the selector system is still messy because generated IDs/classes and companion selectors do not consistently agree.
- Descendant-level generated hooks still drift away from the styling model the source HTML actually used.
- JSON output can still leak awkward or inconsistent classes that the companion CSS never cleanly expects.

### Bento And Mosaic Layout

- Bento layouts are still collapsing toward equal-height card grids instead of preserving true mosaic structure.
- Span metadata has been unstable and has leaked stale state across conversions.
- Even when the right cards exist, the JSON layout contract and CSS layout contract still disagree too often.

### Hybrid Fragments And Animations

- Preserved inner visuals can reappear without their full behavior contract.
- Card-level hover, overlay, pseudo, and animation behavior is still frequently lost when only the inner fragment is preserved.
- Missing JS carryover still affects reveal, counters, marquee, cursor, particles, orbitals, and similar systems.

### Stats And Rebuilt Native Cards

- Stats may be editable again, but the rebuilt version can still miss wrapper-level design, pseudo styling, and counter behavior.
- Native rebuilding is still at risk of preserving content while losing the structural hooks that styling and JS depend on.
- Wrapper/grid relationships are still fragile enough that designs can feel “present but wrong”.

### Global Setup And Top-Level Assets

- `<canvas>`, page bootstrap scripts, and other top-level setup assets are still not generalized enough.
- Some global setup features work in curated cases, but arbitrary uploaded page-level setup is not yet reliably classified, preserved, and retargeted.

### Inline Markup And Pseudo Content

- Beautified inline markup such as split-word logos, highlighted spans, and mixed emphasis is still not guaranteed outside isolated successful cases.
- `::before` and `::after` driven icons and decorative content still do not reliably influence widget choice or survive retargeting.

### Blog And Non-Landing Variants

- The same structural weaknesses show up even faster on blog/article prototypes and other non-landing layouts.
- This confirms the problem is architectural, not specific to one sample or one section family.

## Regression Checklist For The Next Engine Sprint

Do not call the sprint successful unless these checks pass on fresh sample outputs:

- root namespace is present, but descendant selector chaos is reduced
- source selectors are preserved and retargeted more directly
- companion CSS only targets structures that actually exist in emitted JSON
- `::before` / `::after` rules survive and attach to valid hosts
- top-level setup assets and scripts are carried intentionally
- hybrid fragments preserve both inner complexity and wrapper behavior contracts
- mosaic grids preserve real spans without stale spillover
- latest free-widget capability assumptions are documented and mapped into classifier decisions
- rebuilt native cards keep the hooks needed for styling, hover, and animation
- inline markup survives in headings, nav, badges, logos, and similar content-bearing widgets
- diagnostics tell the truth about native, hybrid, and preserved output modes

## Current Engine Progress

This section is only for broad-spectrum engine work already started. Do not log section-only fixes here.

- Root namespace model has started:
  - `body.{prefix}-page` is now emitted as the main page isolation anchor.
  - top-level section anchors are now being assigned deterministically during assembly for repeated same-type sections instead of relying on Pass 9 duplicate-ID repair
  - structural root promotion has started for repeated-family sections, so section fragments can climb to a better ancestor before extraction when the classifier picked a partial slice
  - footer-family sections now score ancestor candidates using footer-specific structure signals like repeated columns, brand identity, and footer-bottom markers
- CSS retargeting has started:
  - generic source-selector rewriting exists
  - selector scoping under the root namespace exists
  - `@media` and `@supports` blocks are now part of the rewrite path
  - native-widget semantic retargeting has started for `icon-list`, `text-editor`, `heading`, and `button` widgets so source selectors aimed at raw tags can be mapped onto Elementor-rendered DOM
- JS carryover has started:
  - generic source-script rewriting exists for selector APIs, ID/class lookups, class-state methods, simple jQuery selector calls, delegated jQuery selector methods, common jQuery class/state helpers, and broader traversal-style selector methods
- Shared inline-markup carryover has started:
  - heading/text/nav extraction now preserves safe inline markup such as `span`, `em`, `strong`, and `br` instead of flattening everything to plain text
  - inline-markup CSS carryover now records its own bridge diagnostics instead of overwriting the main source-selector bridge coverage state
- Generic native rebuilding has started:
  - low-signal repeated sections can now be rebuilt from repeated generic child/descendant groups using native heading, text, icon-list, button, and preserved visual fragments before falling back to full HTML preservation
- Pseudo-content interpretation has started:
  - source `::before` / `::after` content is now being inspected during extraction for list-like native payloads
  - pricing feature lists and footer link columns can now carry inferred source icon intent into native `icon-list` widgets instead of only relying on hardcoded icon choices
  - common icon-font pseudo content is now mapped through a first-pass font-aware decoder instead of only relying on visible glyph characters
  - the selector bridge now understands native `icon-list` semantics, so source `ul/ol/li/a` selectors can be retargeted onto Elementor list wrappers/items/text/icon nodes instead of only surviving on matching raw HTML
  - conversion now fails honestly when source list pseudo-content is detected but no native icon mapping can be produced for the rebuilt list widget
- Validation has started:
  - emitted hook inventory is recorded
  - global setup asset inventory is recorded
  - companion CSS pseudo-host and keyframe coverage is recorded
  - optional companion pseudo rules are now pruned against emitted hook inventory before validation so static CSS for absent hooks does not trigger false prefixed pseudo-host failures
  - unresolved source CSS bridge now fails honestly
  - Pass 8 now distinguishes between bridge targets that merely exist in theory and source CSS rules that actually matched those targets, so `source_css_bridge_unresolved` only fires when matched source rules existed and still produced no retargeted CSS
  - unresolved source JS bridge now fails honestly
  - Pass 8 now makes the same distinction for JS, so script-bridge failures only fire when the source script actually referenced mappable classes or IDs in selector/state contexts
  - unresolved source pseudo/media/supports carryover now fails honestly
  - unresolved source behavior/selector API carryover now fails honestly
  - unresolved native-widget semantic carryover now fails honestly when source selectors used tag semantics for mappable `icon-list`, `text-editor`, `heading`, or `button` hooks but the bridge did not carry them into Elementor-rendered output
  - unresolved native-widget semantic carryover now fails honestly for JS too when source behavior code targeted widget-family tag semantics but the script bridge did not carry them into Elementor-rendered output
  - unexpected cursor/canvas assets in Global Setup now fail honestly
  - Global Setup cursor/canvas injection is now source-driven instead of unconditional
  - orphaned source CSS/JS hook candidates now fail honestly when none map to emitted output hooks
  - input-shape warnings are now held to a stronger threshold so business/marketing pages with some dashboard-like structure do not generate noisy soft warnings as easily

Still open even after the above:

- selector rewriting coverage is not yet broad enough for all real-world CSS/JS shapes
- `::before` / `::after` are still not fully solved as a first-class retargeting system
- global setup carryover is still stronger for known patterns than for arbitrary uploaded systems
- fidelity validation still needs broader structural comparison against source layouts

## Elementor Free Widget Capability Matrix

This should become part of the converter architecture, not just a side note.

Why:
- the converter should know what Elementor free widgets can really do before escalating to hybrid or full HTML preservation
- widget choice should be based on a maintained capability map, not only local heuristics
- the same matrix can guide both Pass 3 classification and Pass 7 settings assembly

What the matrix should record:
- widget name
- what structure/content it represents well
- what inline HTML or nested content it can safely carry
- what styling is natively supported
- what still needs companion CSS
- what still requires hybrid or full HTML preservation

How the converter should use it:
- list-like content should check `icon-list` capability first
- inline-markup headings should check heading HTML-title support first
- pricing rows should compare `icon-list`, nested containers, and text-editor based on the matrix
- FAQ or accordion patterns should check current free-widget availability before falling back

Implementation note:
- because “latest Elementor free widgets” can change over time, this matrix should be verified against current official Elementor free capabilities when we formalize it, rather than frozen from memory

## Known Problem Families To Keep In Scope

- Bento/mosaic grids collapsing into equal-height cards
- stats cards losing wrapper/pseudo/counter behavior
- hover states disappearing from rebuilt cards
- preserved inner visuals losing animation behavior
- nav/hero/card/blog inline span markup being flattened
- icon-like `::before` / `::after` content not influencing widget choice
- canvas/global setup assets missing from output
- CSS/JSON class and ID mismatches
- source CSS not being fully carried into the companion file
- supported-input-shape heuristics overfiring on real page targets with sidebars, tables, or business/dashboard vocabulary

## Working Rule For Future Fixes

Before implementing any fix, answer these three questions inside the task or PR:

1. Which pass is actually responsible?
2. What broad engine rule was missing?
3. How will we verify the rule on layouts beyond the sample that exposed it?

If those answers are not clear, the fix is probably still too local.

## References To Keep Open During Work

- `elementor-plugin-architecture-article.md`
- `enhancement-implementation-guide.md`
- `AUDIT.md`
- `CHANGELOG.md`
- `CONVERTER_GROUND_RULES.md`
