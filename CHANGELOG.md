# Stack Blueprint — Changelog

All notable changes to this plugin are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [Unreleased] — In Progress

### Fixed (implemented 2026-04-10)

- [x] **F-01a** `build_sections()` — Replaced early-break tiered XPath with `//body/*` collection; all direct body children processed in document order. Top-level `<div>` sections (marquee, stats, CTA) are no longer silently skipped. (Fixes BUG-02, 03, 04)
- [x] **F-01b** `decide_strategy()` / `build_sections()` — Marquee, stats, and CTA now route through the template library. `has_template()` method added to `TemplateLibrary`. `nav` type skipped in section loop (handled by `build_nav()`). (Fixes GAP-01)
- [x] **F-02** `extract_section_content()` process — Fixed XPath double-walk bug. Previous `.//*[contains(@class,"step")]` matched wrapper AND each child. New approach: find `.process-steps` wrapper first, query its direct block children, filter parent nodes via `contains()`. (Fixes BUG-06)
- [x] **F-03a** `extract_pricing_cards()` — Complete rewrite: finds individual price-card containers, extracts plan/price/period/featured/badge/features/cta per card (not per whole-section). (Fixes BUG-07)
- [x] **F-03b** `extract_testimonial_cards()` — New dedicated method extracting `quote`, `name`, `role` per testimonial card. Feeds the template library testimonials() correctly. (Fixes BUG-05)
- [x] **F-06** `get_heading()` — Detects `<em>`, `<strong>`, `<span class>`, `<br>` in headings and returns inner HTML preserving formatting; Elementor Heading widget accepts HTML in title. (Fixes BUG-09)
- [x] **F-08** `validate_and_repair()` — Added `$seen_el_ids` tracking for `settings._element_id` values; duplicates auto-renamed with numeric suffix + warning logged. (Fixes BUG-13)
- [x] **F-10** `build_companion_css()` — Class map header now enumerates all 11 output sections in fixed order; dynamic entries merged after. (Fixes BUG-14)

### Added (2026-04-10)

- [x] **UI — 9-Pass Pipeline Progress** — `page-converter.php` progress block expanded to 9 labelled pass steps with individual IDs (`sb-step-1` → `sb-step-9`).
- [x] **UI — Animated Pipeline Stepping** — `admin.js` `runConversion()` rewritten with `setInterval` timer: passes 1–6 cycle at 700ms while API request runs; passes 7–9 flash at 280ms each on response; all steps `done` on complete.
- [x] **Template Library** — `has_template()` public method added to `TemplateLibrary` for routing-without-get pattern (avoids constructing unused output).

### Planned (P1 — next sprint)

- [ ] **F-05** Resolve CSS `var()` in font names before Google Fonts URL builder
- [ ] **F-07** Preserve animated sub-components (orbital, pipeline bars) as HTML widgets inside parent native card containers
- [ ] **F-09** Expand template library testimonials fallback to include empty-card notice when no cards extracted
- [ ] **F-11** CSS `clamp()` → companion CSS with `px` fallback to Elementor JSON setting
- [ ] **F-12** CSS shorthand expansion (`padding: T R B L`, `border: W S C`)
- [ ] **F-14** Complex `<a>`/`<button>` children → `HTML_WIDGET_COMPLEX`
- [ ] **F-17** Remove CSS Prefix manual label from admin form (auto-detection already works)

---



## [1.0.0] — 2026-04-10

### Added
- Initial plugin release
- V2 (Editable Mode / Native Components) conversion engine using Claude AI
- V1 (HTML Fidelity Mode) conversion engine using Claude AI  
- Admin converter page (`Stack Blueprint → Convert`) with file upload, project name, output mode selector, Claude API key field
- Companion CSS auto-generation with `sb-` prefix convention
- Google Fonts link tag generation in Global Setup widget
- CSS variables (`:root` block) in Global Setup
- Scroll reveal IntersectionObserver injected via Global Setup HTML widget
- Nav scroll class toggle JS in Global Setup
- Custom cursor injection via Global Setup HTML widget
- Elementor JSON template export (`.json` downloadable file)
- Companion CSS export (`.css` downloadable file)
- Combined ZIP download (JSON + CSS together)
- REST API endpoint for async conversion (`/wp-json/stack-blueprint/v1/convert`)
- `class-native-converter.php` — main 60KB conversion engine
- `class-css-resolver.php` — CSS cascade resolution and property translation
- `class-template-library.php` — known pattern matching and scaffold generation
- `class-conversion-manager.php` — pipeline orchestration
- Template for hero, features bento, process steps, pricing grid, footer
- `PRODUCT.md` — product vision and roadmap documentation
- `AUDIT.md` — conversion audit and architecture gap analysis (this document)

### Known Issues (V1.0.0)
- Top-level `<div>` sections (marquee, stats, CTA) not detected by segmenter → missing from output
- Canvas/particle background not injected into Global Setup
- Process steps duplicated by recursive tree walker bug
- Pricing and testimonial card content not extracted from source HTML
- Hero headline mixed inline elements (`<em>`, `<span>`) stripped to plain text
- Bento grid column/row spans not mapped to Elementor grid child settings
- Google Fonts URL contains unresolved CSS `var()` strings
- Duplicate `_element_id` values not caught by validator
- Testimonial card grid has no template library pattern match
- Large HTML files may exceed Claude API token limit (no chunked conversion yet)
- Responsive panel settings (tablet/mobile) not written to JSON — rely on companion CSS

---

*For architecture decisions and technical specifications, see `elementor-plugin-architecture-article.md`.*
*For detailed bug descriptions and gap analysis, see `AUDIT.md`.*
