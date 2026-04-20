# Stack Blueprint — Product Vision & Prompt

**Author:** Cr8v Stacks | cr8vstacks.com
**Plugin slug:** stack-blueprint
**Version:** 1.0.0

---

## What Stack Blueprint Is

Stack Blueprint is a WordPress plugin that converts custom HTML/CSS/JS web design prototypes into importable Elementor page templates using Claude AI. It bridges the gap between creative freedom and the constraints of a visual page builder: you design without limits, then Stack Blueprint produces a structured, class-mapped, companion-CSS-backed Elementor JSON file you can import in seconds.

The core insight driving Stack Blueprint is that the hardest part of using Elementor for serious design work isn't building in the editor — it's getting a high-fidelity custom design *into* the editor cleanly. Stack Blueprint solves that problem entirely.

---

## The Problem It Solves

Designers and agencies working with Elementor face a consistent friction point: they can prototype or generate beautiful custom designs in HTML/CSS/JS, but getting those designs into Elementor requires either (a) painstakingly rebuilding everything widget by widget, or (b) pasting the entire design as a raw HTML widget, sacrificing editability. Neither is acceptable for client delivery.

Stack Blueprint removes this friction. Upload the prototype. Choose a strategy. Download a structured, production-ready Elementor template.

---

## Two Conversion Strategies

### V1 — HTML Fidelity
**Philosophy:** Preserve the design exactly. Complex, animated, or visually intricate sections are kept as self-contained HTML widgets that carry their own styles and scripts. Native Elementor widgets are used only where the design is simple enough to map directly.

**Best for:** Agency portfolios, personal sites, developer-maintained projects, designs with heavy animation, prototypes that must match the original pixel-for-pixel.

**Trade-off:** Content editing requires opening HTML widget source. Non-technical editors cannot update copy without developer involvement.

### V2 — Native Components *(Recommended)*
**Philosophy:** Maximum editability. Every piece of user-facing text, every heading, every button, every navigation link becomes a native Elementor widget. HTML widgets are reserved exclusively for non-editable visual or animated elements — particle canvas backgrounds, custom cursors, marquee strips, orbital animations — things that are *looked at*, never *edited*.

**Best for:** Client websites, content-heavy landing pages, any site where the owner needs to update copy without touching code.

**Trade-off:** Some visual fidelity is sacrificed in edge cases where complex layouts or exotic animations cannot be reproduced natively by Elementor's grid system.

### The Hybrid Reality
V2 is itself a hybrid: native containers and widgets for everything editable, HTML widgets for everything decorative. This is the right model for nearly all real-world client delivery. V1 is a specialist tool for when pixel accuracy overrides editability — a legitimate need that exists, and should have a first-class conversion path.

---

## What It Does Well (v1.0.0)

- **Structured AI prompting:** Both conversion strategies use carefully engineered system prompts encoding the lessons of real build sessions — the canvas injection pattern, the class naming convention, the global setup widget structure, the companion CSS organisation. The AI is not making ad hoc decisions; it is following a proven blueprint.

- **CSS class discipline:** Every element in the output JSON has `_css_classes` and `_element_id` pre-populated. The companion CSS targets these classes with precision. There is no hunting for auto-generated Elementor class names.

- **Multi-file support:** Upload a single self-contained HTML file, or provide separate HTML, CSS, and JS files. The plugin merges them intelligently before sending to Claude.

- **Design Tokens extractor:** Paste any HTML prototype to extract its CSS custom property colour palette and Google Fonts. This powers the Elementor Global Colors and Global Fonts configuration step that should always precede template import.

- **Conversion history:** Every conversion is logged. Output files can be re-downloaded at any time.

- **Save to Elementor Library:** Converted templates can be saved directly to the Elementor library as page templates, skipping the manual import step.

- **Full custom admin UI:** A purpose-built dark editorial interface with sidebar navigation, custom component design, and zero WP admin default styling. The UI signals what this plugin is about: it was built for people who care about design.

- **Elementor Free compatible:** No Pro subscription required. The companion CSS approach means any per-element styles that would require Pro are moved to Site Settings → Custom CSS instead.

---

## Known Limitations (v1.0.0)

**AI output reliability:** A full Elementor JSON template with 40+ uniquely identified elements, grid settings, typography objects, and responsive keys is the most complex single-turn output request that can be made of a large language model. Claude handles it well, but output consistency across very large or structurally unusual prototypes is not guaranteed. Some conversions will require manual review and repair.

**Responsive configuration:** The companion CSS includes breakpoint rules, but Elementor's responsive *panel* settings — column direction, per-breakpoint padding, per-breakpoint font sizes — are not written into the JSON. These must be configured manually in the Elementor editor after import. This is a deliberate scope decision for v1.0.0; it is more reliable than having the AI attempt to produce correct Elementor responsive key syntax.

**Bento/asymmetric grid fidelity:** HTML prototypes using `grid-row: span` create variable-height bento cards naturally. Elementor's Grid container does not. V2 output equalises card heights per row. The fix (setting minimum heights per card) is documented in the companion CSS and in the plugin UI, but it is a manual step.

**Framework-based prototypes:** Prototypes built with Tailwind, Bootstrap, or other CSS frameworks do not convert cleanly. The AI will attempt the conversion, but class-based utility frameworks have no direct Elementor equivalent. Best practice: convert the prototype to vanilla CSS first.

**Editor vs. frontend gap:** Particle canvas, custom cursors, scroll reveal animations, and any effect injected via `document.body.appendChild` will not appear in the Elementor editor sandbox. They appear correctly on the published frontend. This is an Elementor editor limitation, not a plugin bug, but it surprises users.

**Token context limits:** Very large HTML prototypes (full landing pages with inline SVGs, extensive CSS, and long scripts) may approach token limits for a single API call. The plugin currently sends the full prototype in one call. Future versions will support section-by-section chunked conversion for very large files.

---

## Future Improvements (Roadmap)

### Near term
- **Responsive JSON configuration:** Write Elementor's `_mobile` and `_tablet` responsive keys directly into the output JSON for containers, padding, and heading sizes — reducing the manual responsive setup required after import.
- **Section-by-section chunked conversion:** Split large prototypes into sections before sending to the API, then stitch the resulting JSON. This improves accuracy and removes token-limit constraints.
- **Conversion preview:** Render a visual diff between the original HTML prototype and the converted template output, side by side, before downloading.
- **Pre-conversion HTML audit:** Analyse the uploaded prototype and flag patterns that will cause conversion problems (framework classes, fixed-position ancestors, canvas elements placed inside widgets, etc.) before spending API tokens.

### Medium term
- **V3 — Structured Analysis Strategy:** Instead of sending the full HTML to Claude and asking for a complete JSON conversion, pre-analyse the HTML into a structured section map (section type, content elements, animation flags, grid layout) and then make targeted per-section API calls. This architecture produces more reliable and auditable output at the cost of more API calls.
- **Elementor Global Colors sync:** After extracting design tokens, optionally write them directly into Elementor's global color and font settings via the Elementor API — removing the manual Site Settings configuration step.
- **Template library browser:** Browse and re-import any past conversion directly from the Stack Blueprint admin, with a thumbnail preview of the original prototype.
- **Companion CSS editor:** An in-browser editor for the companion CSS file with syntax highlighting, so designers can make adjustments without leaving WordPress.

### Long term
- **Direct Figma/Sketch import:** Accept design files from Figma (via the API or export) as the source, extracting component structures and design tokens before converting to Elementor.
- **Block editor (Gutenberg) output:** Offer a third output target alongside Elementor: a full Gutenberg block template using `theme.json` for design tokens.
- **Multi-page project support:** Upload multiple HTML prototype files representing different page templates and convert them as a coherent project with shared design token configuration.
- **White-label mode:** Allow agencies to rebrand the plugin UI for client delivery.

---

## Technical Architecture

```
stack-blueprint/
├── stack-blueprint.php          # Plugin entry point, constants, autoloader
├── readme.txt                   # WP repository readme
├── PRODUCT.md                   # This document
│
├── includes/
│   ├── class-plugin.php         # Singleton bootstrap
│   ├── class-activator.php      # Activation: DB tables, default options
│   ├── class-deactivator.php    # Deactivation: cron cleanup
│   ├── class-rest-api.php       # REST API controller (stack-blueprint/v1/*)
│   │
│   ├── converter/
│   │   ├── class-conversion-manager.php  # Orchestrates conversion workflow
│   │   ├── class-converter-v1.php        # V1 strategy (future: pre-processing)
│   │   ├── class-converter-v2.php        # V2 strategy (future: pre-processing)
│   │   └── class-html-parser.php         # HTML analysis utilities
│   │
│   ├── elementor/
│   │   ├── class-template-builder.php    # Saves JSON to Elementor library
│   │   └── class-widget-registry.php     # Future: custom widget registration
│   │
│   └── utilities/
│       ├── class-api-client.php          # Anthropic API wrapper
│       ├── class-file-handler.php        # Upload validation and reading
│       └── class-helpers.php             # General utilities
│
├── admin/
│   ├── class-admin.php          # Menu registration, asset enqueueing
│   ├── css/admin.css            # Full custom admin UI stylesheet
│   ├── js/admin.js              # Admin UI logic, API calls, state
│   ├── views/
│   │   ├── page-converter.php   # Converter page
│   │   ├── page-history.php     # Conversion history
│   │   ├── page-tokens.php      # Design tokens extractor
│   │   └── page-settings.php    # Settings page
│   └── partials/
│       ├── layout-open.php      # App shell open (topbar + sidebar)
│       └── layout-close.php     # App shell close
│
├── assets/
│   ├── css/                     # Future: frontend assets if needed
│   ├── js/                      # Future: frontend assets if needed
│   └── images/                  # Plugin assets
│
├── templates/                   # Future: bundled starter templates
└── languages/                   # i18n .pot file
```

**REST API endpoints:**

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/stack-blueprint/v1/convert` | Upload files and start conversion |
| GET | `/stack-blueprint/v1/convert/{id}` | Get conversion status and result |
| GET | `/stack-blueprint/v1/history` | Get conversion history |
| GET | `/stack-blueprint/v1/settings` | Get plugin settings |
| POST | `/stack-blueprint/v1/settings` | Save plugin settings |
| POST | `/stack-blueprint/v1/test-api` | Test Anthropic API key |
| POST | `/stack-blueprint/v1/save-template` | Save result to Elementor Library |
| GET | `/stack-blueprint/v1/download/{id}/{type}` | Download JSON or CSS output |

All endpoints require `manage_options` capability. All responses use standard WP_REST_Response. Nonce authentication via `wp_rest` nonce.

---

## Design Philosophy

Stack Blueprint should feel like it was built by the same people who use it. The admin UI is a deliberate rejection of WordPress's default admin aesthetic — not because the default is wrong in general, but because a tool for designers should not look like a settings page from 2012. The dark editorial interface, the Syne display font, the monospace labels, the teal accent — these are choices made by people who design. That alignment is itself a signal about what the plugin is for and who it is for.

The same discipline applies to the conversion output. The CSS class naming system, the companion CSS structure, the global setup widget pattern — these are not AI defaults. They are documented conventions developed through real build sessions and encoded into the AI system prompts. When the output is good, it is because the instructions behind it are specific.

---

*Stack Blueprint is a product of Cr8v Stacks.*
*cr8vstacks.com*
