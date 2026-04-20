=== Stack Blueprint ===
Contributors:      cr8vstacks
Tags:              elementor, page builder, template, html, converter, ai, design, landing page
Requires at least: 6.3
Tested up to:      6.7
Requires PHP:      8.1
Stable tag:        1.0.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Convert custom HTML/CSS/JS prototypes into importable Elementor page templates using AI. Design freely, convert precisely.

== Description ==

Stack Blueprint bridges the gap between designing freely in HTML/CSS/JS and building in Elementor. Upload any HTML prototype — whether hand-coded or AI-generated — and Stack Blueprint uses Claude AI to produce a ready-to-import Elementor JSON template plus a companion CSS file.

**Two conversion strategies:**

* **V1 — HTML Fidelity:** Preserves complex, animated, and visually intricate sections as self-contained HTML widgets. Maximum visual accuracy. Best for developer-maintained and agency sites.
* **V2 — Native Components (Recommended):** Converts every editable section into native Elementor widgets (Heading, Text Editor, Button, Icon List, Grid Container). HTML widgets are used only for non-editable visual/animated effects like particle canvas backgrounds and custom cursors. Maximum editability for client sites.

**Key features:**

* Upload a single self-contained HTML file, or provide separate HTML + CSS + JS files
* Choose your CSS class prefix — all generated classes are namespaced to avoid conflicts
* Automatic global setup: particle canvas, custom cursor, scroll reveal, fixed nav, and font imports are all handled
* Download the Elementor JSON template and companion CSS separately
* Save converted templates directly to the Elementor Template Library (requires Elementor active)
* Design Tokens extractor: paste any HTML prototype to extract its colour palette and Google Fonts for use in Elementor Site Settings
* Conversion history with re-download capability
* Fully custom admin UI — not a default WordPress admin form in sight
* Works with Elementor Free — no Pro subscription required for core conversion

**Requirements:**

* An Anthropic API key (get one free at console.anthropic.com)
* Elementor 3.0+ (free version is sufficient)
* PHP 8.1+
* WordPress 6.3+

== Installation ==

1. Upload the `stack-blueprint` folder to `/wp-content/plugins/`
2. Activate the plugin via the Plugins menu in WordPress
3. Navigate to **Stack Blueprint → Settings** and enter your Anthropic API key
4. Click **Test Connection** to verify the key is working
5. Go to **Stack Blueprint → Converter** and upload your first HTML prototype

== Frequently Asked Questions ==

= Do I need Elementor Pro? =

No. Stack Blueprint works with Elementor Free (3.0+). The one exception is per-element Custom CSS in the Elementor panel — that requires Pro. Any such styles are included in the companion CSS file instead and should be added to Elementor Site Settings → Custom CSS.

= Where do I get an Anthropic API key? =

Visit https://console.anthropic.com, sign in or create an account, and generate a key under API Keys. Stack Blueprint uses the claude-sonnet-4 model by default.

= What makes a good HTML prototype for conversion? =

Use standard block-level layout (div, section, header, footer), meaningful class names, CSS custom properties for design tokens, and vanilla JavaScript. Avoid CSS frameworks like Tailwind or Bootstrap — these do not map cleanly to Elementor's widget system.

= V1 or V2 — which should I choose? =

Choose **V2** if a client or non-technical editor will maintain the site. Choose **V1** if you or a developer will maintain it and visual accuracy is the top priority. When in doubt, run V2 first.

= Why don't particle canvas and cursor effects appear in the Elementor editor? =

The Elementor editor runs scripts in a sandboxed environment. These effects are injected into document.body via JavaScript and will work correctly on the published frontend. Always verify these effects on the published page, not the editor.

= Can I convert a design built with a CSS framework? =

Partially. Stack Blueprint will attempt the conversion, but the AI works best with vanilla HTML and CSS. For framework-heavy prototypes, ask Claude or another AI to rewrite the sections in plain CSS before uploading.

== Screenshots ==

1. Converter page — upload your HTML prototype and choose a strategy
2. Conversion progress view
3. Result panel — download JSON, download CSS, save to Elementor Library
4. Design Tokens extractor — colour palette and font detection
5. Settings page — API key, model selection, and defaults

== Changelog ==

= 1.0.0 =
* Initial release
* V1 (HTML Fidelity) and V2 (Native Components) conversion strategies
* Design Tokens extractor for colour palette and typography
* Conversion history with re-download
* Save to Elementor Library
* Fully custom dark admin UI

== Upgrade Notice ==

= 1.0.0 =
Initial release. No upgrade steps required.
