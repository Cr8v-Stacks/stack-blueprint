# Elementor Free Widget Capability Matrix

Last updated: 2026-04-20
Status: Working engine reference

## Purpose

This file turns the free-widget discussion into a concrete converter artifact.

It exists to support:
- Pass 3 `Content Classification`
- Pass 7 `JSON Assembly`
- the native-first, hybrid-allowed rule set in `CONVERTER_TASKS_AND_GROUND_RULES.md`

This matrix is not a marketing list of widgets. It is an engine-facing map of:
- what each free widget represents well
- what structure it can safely carry
- where companion CSS/JS is expected
- when hybrid HTML-in-place is still the right move

## Decision Rule

For any source node or section:

1. Can a free Elementor widget or simple native widget combination represent the structure cleanly?
2. If yes, use the simplest stable native structure.
3. If part of the structure is too custom, keep the native outer layout and preserve the hard inner fragment as HTML in-place.
4. Only preserve the entire source block when the native + hybrid path is still not safe.

## Widget Matrix

### Heading

- Best for:
  - titles
  - hero headlines
  - card titles
  - section titles
  - numeric/value headings
- Native strengths:
  - semantic heading levels
  - inline markup in title text when safe
  - typography, alignment, color, spacing
- Good source patterns:
  - `h1`–`h6`
  - split-word titles with `span`, `em`, `strong`, `br`
- Companion CSS usually carries:
  - gradient text
  - accent span styling
  - pseudo-element decoration on the heading host
- Hybrid trigger:
  - animated inline SVG/text fragments inside the heading

### Text Editor

- Best for:
  - paragraphs
  - short rich text blocks
  - descriptions
  - article excerpts
  - mixed inline emphasis
- Native strengths:
  - paragraph flow
  - links
  - inline markup
  - simple lists or quotes when editability matters more than strict structure
- Good source patterns:
  - `p`
  - `blockquote`
  - simple grouped copy
  - inline `span`, `em`, `strong`, `br`
- Companion CSS usually carries:
  - descendant typography tuning
  - pseudo decoration on direct child text nodes
- Hybrid trigger:
  - embedded animated fragments or nontrivial inline visual systems

### Button

- Best for:
  - CTA actions
  - repeated action rows
  - hero/cta/footer actions
- Native strengths:
  - single-action links
  - typography
  - spacing
  - standard hover targets
- Good source patterns:
  - `a`
  - `button`
  - inline text spans inside CTA labels
- Companion CSS usually carries:
  - advanced hover skins
  - pseudo glows
  - animated borders
- Hybrid trigger:
  - CTA contains custom progress bars, icons with animation, or nested unusual DOM

### Icon List

- Best for:
  - navigation-like lists
  - pricing features
  - footer link columns
  - benefit lists
  - repeated list rows
- Native strengths:
  - repeated list structure
  - icon + text relationship
  - better editability than text blobs or raw HTML
- Good source patterns:
  - `ul/li`
  - `ol/li`
  - repeated anchor rows
  - pseudo-icon lists using `::before` / `::after`
- Companion CSS usually carries:
  - custom bullets
  - spacing and divider rules
  - hover/active states
- Hybrid trigger:
  - each row contains mixed rich sub-layout beyond icon + text + link

### Image

- Best for:
  - single images
  - figure-like media blocks
  - logos
  - decorative images inside cards
- Native strengths:
  - editable media selection
  - responsive image output
  - stable wrapper for retargeted CSS
- Good source patterns:
  - `figure`
  - `picture`
  - `img`
- Companion CSS usually carries:
  - overlays
  - masks
  - pseudo decoration on the image wrapper
  - hover transforms
- Hybrid trigger:
  - layered image stacks, SVG-heavy art direction, or embedded animation tied to image internals

### Video

- Best for:
  - embedded video
  - iframe media
  - simple demo/video blocks
- Native strengths:
  - stable wrapper and responsive embed behavior
- Good source patterns:
  - `video`
  - `iframe`
  - embed/player wrappers
- Companion CSS usually carries:
  - overlay styling
  - host pseudo decoration
  - aspect-ratio and frame polish
- Hybrid trigger:
  - custom player chrome or scripted media overlays beyond a normal wrapper

### Containers

- Best for:
  - layout
  - repeated cards
  - stat grids
  - process steps
  - testimonial cards
  - pricing cards
  - bento shells
- Native strengths:
  - structural hierarchy
  - grid/flex layout
  - wrapper classes and IDs
  - composition of other widgets
- Good source patterns:
  - repeated blocks
  - nested layout shells
  - card families
- Companion CSS usually carries:
  - wrapper hover states
  - borders, overlays, pseudo accents
  - spacing and responsive contracts
- Hybrid trigger:
  - preserve complex inner visuals, canvases, SVG clusters, dashboards, terminals, charts, orbitals

### HTML Widget

- Best for:
  - hard inner fragments
  - preserved animation islands
  - canvases
  - SVG-heavy visuals
  - dashboards/terminals/charts when native shells still hold the layout
- Native strengths:
  - exact markup preservation
  - arbitrary fragment carryover
- Good source patterns:
  - script-bearing inner visuals
  - bespoke interactive fragments
  - inline SVG/canvas blocks
- Companion CSS/JS usually carries:
  - original fragment styling
  - scoped behavior carryover
- Use rule:
  - prefer in-place hybrid HTML inside a native shell before preserving the whole section

## Current Engine Mapping

The converter should currently treat these as the main free-widget families for semantic retargeting:
- `heading`
- `text-editor`
- `button`
- `icon-list`
- `image`
- `video`
- `container`
- `html`

## What Still Needs Formalization

- divider/spacer semantics as an explicit matrix entry
- native handling for galleries and posts/cards when used as layout primitives
- a machine-readable form of this matrix for Pass 3/7 instead of markdown-only guidance
- verification against the current free Elementor set whenever the plugin is updated

## Implementation Notes

- Keep one root namespace for isolation.
- Preserve source classes and IDs beneath that root whenever practical.
- Use this matrix to decide whether to:
  - rebuild natively
  - rebuild natively with preserved inner HTML
  - preserve the full block
- Do not use this matrix as a fallback system.
  It should make widget decisions more honest, not hide missing carryover work.
