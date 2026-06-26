# Stack Blueprint - Changelog

All notable changes to this plugin are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [1.1.0] - 2026-04-25

### Added

- Live converter preview in the admin sidebar with desktop, tablet, mobile, refresh, and fullscreen controls.
- Preview-side audit badges for missing content, missing selectors, CSS bridge state, and preview sanitization activity.
- DB-free verifier and preview audit CLI coverage for faster conversion inspection without opening Elementor.
- Random-sample audit artifact generation under `audit-output/` so conversion issues can be tracked from real outputs.

### Changed

- V2 converter architecture was decongested into active strategy profiles, parser modules, priority rules, and focused helpers instead of continuing to overload `class-native-converter.php` alone.
- Native conversion diagnostics now persist through conversion records and surface in the converter UI.
- The execution direction has pivoted toward a browser-truth fidelity path because heuristic-only native reconstruction is not meeting the required near-total fidelity target on arbitrary uploaded HTML.
- Conversion review is now treated more honestly: preserved source and partial/native interpretation can be diagnosed instead of being mistaken for a complete success.

### Fixed

- Native conversion runtime now degrades cleanly on unexpected converter exceptions instead of crashing the REST route.
- Unsupported DOM API usage was removed from native extraction paths.
- Live preview rendering was hardened against malformed source HTML, inline handlers, `javascript:` URLs, and iframe-side script execution errors.
- Uploading an HTML file now auto-fills the project/file name field from the uploaded filename.
- Preview surfaces now report sanitization stripping so removed content is visible during review instead of failing silently.

### Notes

- This release records a product-level direction change, not a claim that V2 native conversion already meets the final fidelity goal.
- The fidelity gate remains strict: if a path cannot get close to browser-truth output, it should not be the main conversion strategy.

## [Unreleased] - In Progress

### Added

- Browser-truth extraction spike powered by Playwright under `extractor/`.
- PHP bridge `BrowserExtractor` for running the browser extractor from plugin/runtime code.
- DB-free CLI runner `tools/run-browser-extraction-cli.php` for writing browser-truth audit artifacts under `audit-output/`.
- Browser-vs-V2 comparison tooling with a normalized intermediate layout model:
  - `includes/converter/class-browser-layout-normalizer.php`
  - `tools/run-browser-v2-comparison-cli.php`
- Browser role-gap profiling inside the normalized comparison model for:
  - services-like repeated items
  - CTA/link counts
  - nav-link counts
  - email-link counts
  - stat-pair counts

### Changed

- Browser extraction now falls back to a real local Chrome or Edge executable when Playwright's bundled Chromium is unavailable.
- Extractor runtime status now reports browser availability truthfully instead of treating a partial `node_modules` install as ready.
- Generic native block interpretation now extracts multiple paragraph-like text blocks and multiple CTA/action candidates instead of flattening repeated content down to a single body line and one button.
- V2 native affinity now considers repeated content-group structure before preserve decisions, so editable repeated blocks can compete on structure instead of keyword-only heuristics.
- Browser-vs-V2 comparison now reads preserved HTML widgets more like Elementor preview does:
  - descendant ids/classes inside preserved HTML count toward hook landing
  - preserved HTML headings/text/actions contribute to audit-side role counts
  - section kind inference now sees descendant hooks and samples instead of only root wrapper text
- V2 section interpretation now uses broader structural marquee signals:
  - descendant marquee/ticker hooks can influence section classification
  - nowrap ticker strips can be interpreted as `marquee` without sample-specific class hardcoding
- Hero and marquee payloads now carry deeper source-hook aliases into emitted output:
  - hero CTA source ids/classes now land on native button surfaces
  - hero visual subtree hooks now land on the hybrid visual wrapper
  - marquee subtree hooks now land on the emitted marquee wrapper/widget
- Fixed-nav HTML rebuild now preserves source nav-link classes on emitted nav anchors.
- Hero root and hybrid visual wrappers now also carry broader hero-subtree hook aliases, so decorative source hooks can land without hardcoding any one design.
- Feature/bento native templates now render extracted generic stat pairs as native stat cards instead of dropping those semantics during assembly.
- Global cursor behavior is now emitted as real markup plus hydrated runtime behavior, so source cursor hooks are visible to audits and not hidden only inside JS creation code.
- Hero extraction now keeps broader semantic copy blocks instead of a single surviving paragraph, and the native hero template now renders multiple meaningful text surfaces when they exist.
- Generic card/block text extraction now keeps shorter support/meta lines more broadly, and native feature/bento cards now render all recovered text blocks instead of only the first body line.
- Browser-vs-V2 normalization now separates `global-setup` and `footer` from hero/studio classification so hidden document-title markup stops polluting role-gap training signals.

### Validated

- Successful browser-truth extraction run completed for `training-files/inline css/03-agency-portfolio-brutalist.html`.
- The generated browser-truth audit captured:
  - page title `FORMA — Creative Agency`
  - detected fonts `Overpass`, `Bebas Neue`, `Overpass Mono`
  - extracted keyframes `bob` and `marquee`
  - multi-viewport DOM/layout snapshots with `114` extracted nodes on desktop/tablet/mobile
- Successful browser-vs-V2 comparison run completed for the same brutalist sample.
- The normalized comparison exposed immediate fidelity gaps:
  - browser headings `13` vs Elementor headings `8`
  - browser text blocks `43` vs Elementor text widgets `6`
  - browser actions `6` vs Elementor buttons `3`
  - browser behavior islands `8` vs Elementor HTML widgets `10`
  - missing hooks including `cursor-dot`, `cursor-ring`, `para-bg`, `cta-btn`, `work-grid`, `email-link`, `nav-link`, `work-item`, and `stat-num`
  - missing interpreted section kind `services`
- The refined role-gap pass now also surfaces section-level deficits directly:
  - `services` section kind missing from Elementor interpretation
  - `gallery` section still under-landing actions and text blocks
  - `studio` section still under-landing text blocks
- The latest broad interpretation pass kept the full balanced gate green:
  - `php tools/run-training-suite-cli.php --quality-floor --floor-profile=balanced --trend-report`
  - `22/22` OK, zero fail codes
- The latest brutalist browser-vs-V2 rerun is now much closer to preview truth:
  - headings `13` vs `14`
  - text blocks `43` vs `40`
  - actions `6` vs `9`
  - missing section kind is now cleared
  - remaining hook misses are now concentrated on `cursor-dot`, `cursor-ring`, `para-bg`, `scroll-indicator`, and `stat-num`
- The latest targeted brutalist verifier stayed `REPORT_OK` and now detects:
  - `hero`, `marquee`, `features`, `contact`, and `footer`
  - bridged ids including `hero-main` and `cta-btn`
  - missing selector coverage narrowed to `cursor-dot`, `cursor-ring`, `para-bg`, and `stat-num`
- The latest brutalist verification and browser-vs-V2 comparison now show hook landing fully cleared on that sample:
  - targeted verifier selector coverage is now `1.0`
  - browser-vs-V2 missing ids/classes are now both empty
  - remaining deficits are now semantic-density gaps, not missing landed hooks:
    - hero still under-recovers text/actions versus browser truth
    - studio still under-recovers text density versus browser truth
- The latest browser-vs-V2 brutalist rerun tightened those remaining semantic gaps further:
  - `global-setup` is now classified separately instead of being misread as hero
  - hero role-gap improved from browser-truth text `6` vs Elementor `1` to `6` vs `3`
  - the old false hero action-gap is gone
  - the remaining high-signal role gaps are now:
    - hero text density `6` vs `3`
    - studio text density `18` vs `9`
- The latest full balanced gate still passes after the hook/stat/global-setup pass:
  - `php tools/run-training-suite-cli.php --quality-floor --floor-profile=balanced --trend-report`
  - `22/22` OK
  - `run_success_rate = 1`
  - `selector_output_css_ratio = 0.36363636363636365`
  - `script_rewrite_ratio = 0.8181818181818182`
  - `global_script_bridge_ratio = 1`

### Historical Notes

- Early V2 native interpretation work, widget extraction work, and admin UX experiments from 2026-04-10 onward are retained in the execution plan and audit trackers rather than repeated here line by line.
- The current repo history after 1.0.0 is best understood through:
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
  - `V2_GEOMETRIC_AUDIT_TRAINING_TRACKER.md`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist.md`

## [1.0.0] - 2026-04-10

### Added

- Initial plugin release.
- V2 (Editable Mode / Native Components) conversion engine using Claude AI.
- V1 (HTML Fidelity Mode) conversion engine using Claude AI.
- Admin converter page (`Stack Blueprint -> Convert`) with file upload, project name, output mode selector, and Claude API key field.
- Companion CSS auto-generation with `sb-` prefix convention.
- Google Fonts link tag generation in the global setup widget.
- CSS variables (`:root` block) in global setup.
- Scroll reveal IntersectionObserver injected via global setup HTML widget.
- Nav scroll class toggle JS in global setup.
- Custom cursor injection via global setup HTML widget.
- Elementor JSON template export (`.json` downloadable file).
- Companion CSS export (`.css` downloadable file).
- Combined ZIP download (JSON + CSS together).
- REST API endpoint for async conversion (`/wp-json/stack-blueprint/v1/convert`).
- `class-native-converter.php` as the main conversion engine.
- `class-css-resolver.php` for CSS cascade resolution and property translation.
- `class-template-library.php` for known pattern matching and scaffold generation.
- `class-conversion-manager.php` for pipeline orchestration.
- Templates for hero, features bento, process steps, pricing grid, and footer.
- `PRODUCT.md` for product vision and roadmap documentation.
- `AUDIT.md` for conversion audit and architecture gap analysis.

### Known Issues

- Top-level `<div>` sections such as marquee, stats, and CTA were not detected by the original segmenter.
- Canvas and particle background setup was incomplete.
- Process steps could duplicate because of recursive tree walking issues.
- Pricing and testimonial card content extraction was incomplete.
- Hero headlines with mixed inline formatting could be flattened.
- Bento grid column and row spans were not mapped to Elementor grid child settings.
- Google Fonts URL generation could keep unresolved CSS `var()` strings.
- Duplicate `_element_id` values were not caught by validation.
- Large HTML files could exceed the single-request AI token budget.
- Responsive panel settings were not written into Elementor JSON and relied on companion CSS.

---

For architecture decisions and technical specifications, see `elementor-plugin-architecture-article.md`.
For detailed bug descriptions and gap analysis, see `AUDIT.md`.
