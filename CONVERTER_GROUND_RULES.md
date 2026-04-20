# Converter Ground Rules

This file captures the non-negotiable rules the converter must follow.
It exists to stop regressions where one sample is "fixed" in a way that
breaks another sample.

## Core Rules

1. Structure first.
The converter's first job is to preserve layout structure and editability.
Text/content fidelity matters, but it must not come at the cost of broken layout skeletons.

2. Native first, not native only.
Use free Elementor-native widgets and containers whenever they can express the section.
Do not force pure-native output when a complex inner fragment is better preserved as HTML.

3. Hybrid is a first-class mode.
Any rebuilt native section may contain preserved HTML fragments inside it.
This is not limited to bento/features. It may apply to stats, pricing, process, testimonials, nav, footer, blog cards, timelines, dashboards, etc.

4. Simplification must not strip useful structure.
When simplifying a section into native widgets, preserve:
- CSS IDs
- CSS classes
- repeated-card identity
- visual fragment wrappers
- behavior hooks
- source span/ratio/grid metadata

5. No synthetic content.
Never invent labels, links, cards, descriptions, badges, or stats just to fill gaps.
If content is unresolved, preserve source or fail loudly.

6. Preserve full fragments, not amputated fragments.
When preserving a complex inner block, carry:
- matching class rules
- matching ID rules
- keyframes
- inline scripts
- nested markup structure

7. Companion CSS must target emitted output, not assumed output.
If the JSON emits per-card classes or IDs such as `prefix-bento-card-a`, the companion CSS must understand them.
CSS rules must match what was actually emitted.

8. Pseudo-elements are first-class styling.
`::before` and `::after` are not optional polish.
If source styling relies on pseudo-elements, the converter must either:
- emit compatible classes/markup so the companion CSS can reapply them, or
- preserve the relevant fragment as source HTML/CSS.

9. Prefixes must be human-legible and project-derived.
Default prefixes should come from the project name in a way that is predictable and understandable.
Opaque or stale abbreviations are not acceptable defaults.

10. Validation must check fidelity, not just syntax.
A conversion should not count as successful only because JSON parses.
It must also preserve expected:
- wrapper structure
- repeated item counts
- grid spans
- hover/reveal hooks
- preserved visual fragments

## Section Family Rules

### Global Setup

- Always emit one global setup block.
- Global setup must contain only truly global CSS/JS.
- Section-specific visuals belong in the section/card they came from, not in the global block.

### Navigation

- Prefer a simple native/hybrid structure.
- If the design depends on custom nav markup, preserve the nav HTML.
- Brand/logo beautification such as nested `<span>` styling must carry through in CSS.

### Hero

- Preserve inline emphasis such as `<em>`, `<span class="...">`, and line breaks.
- Do not flatten expressive headline markup into plain text.
- CTA text and arrow treatment must remain visually faithful.

### Marquee / Ticker

- Preserve actual track structure and animation behavior.
- If native rebuilding cannot keep the ticker semantics, preserve the full widget.

### Stats / KPI Rows

- Stats are usually simple repeated layout blocks, not "complex by default".
- Preferred output is native repeated cards/containers.
- Preserve:
  - section wrapper
  - repeated card count
  - numeric/value styling
  - label styling
  - count/reveal hooks
- If special visuals exist inside a stat card, preserve them inside that card.

### Bento / Feature Grids

- Rebuild the main grid natively.
- Preserve each complex inner visual fragment inside its original card position.
- Preserve grid span metadata from the source.
- Companion CSS must restore mosaic sizing and hover behavior on emitted cards.

### Process / Timeline / Step Sections

- Rebuild steps natively.
- Preserve step identity with repeated classes/IDs.
- If a step contains a visual or animated fragment, keep it inside that step as HTML.

### Testimonials / Social Proof

- Rebuild card shells natively.
- Preserve nested author/meta structure.
- Keep avatar/name/role hooks distinct.
- Do not leak quote text into section descriptions.

### Pricing

- Rebuild cards natively.
- Use icon list for real list structures.
- Preserve badges, featured-state hooks, CTA hooks, and any complex visuals within the correct card.

### Footer

- Use native heading/list structures where practical.
- Brand/logo fragments may remain HTML if beautified markup is required.
- Do not collapse columns just because a link label repeats.
- Footer link/title/brand styling must match emitted markup exactly.

### Blog / Post Lists

- Treat blog lists as repeated card/list structures first.
- Preserve thumbnail/media fragments when needed.
- Do not flatten card metadata, category tags, and CTA affordances into plain paragraphs.

## Widget Choice Rules

### Text Editor

Use for:
- flowing prose
- paragraph copy
- short rich text blocks

Do not use for:
- real lists
- card metadata rows
- nav/footer menus

### Icon List

Use for:
- `ul/li` structures
- repeated bullet/feature/footer/pricing link lists
- list patterns where pseudo-icons can be mapped or hidden

If the source uses `li::before` or `li::after`, the converter must either:
- map that treatment into icon-list styling, or
- preserve the source list fragment.

### Heading

Use for:
- real titles
- labels
- numeric values
- badges/eyebrows when they behave like stand-alone display text

### HTML Widget

Use for:
- preserved complex inner fragments
- source-faithful interactive pieces
- custom visuals that cannot be expressed faithfully with free native widgets

Do not use as a lazy escape hatch for whole sections that could be rebuilt natively.

## CSS / JS Rules

1. CSS must follow emitted classes and IDs exactly.
2. Rebuilt native widgets must still receive the classes/IDs needed for styling.
3. Per-item aliases are required when repeated blocks are emitted with item-specific classes or IDs.
4. Hover, reveal, counters, and layout spans must be validated against emitted markup.
5. Pseudo-element rules from source CSS must be preserved or re-targeted when they are part of the design language.

## Failure Rules

Fail loudly when:
- content is unresolved and no preservation path exists
- preserved fragments are empty or structurally incomplete
- repeated card/layout counts collapse unexpectedly
- grid span metadata is lost
- emitted selectors and companion CSS no longer line up

Do not fail merely because a section used hybrid output.
Hybrid output is a valid success mode when structure and fidelity are preserved.
