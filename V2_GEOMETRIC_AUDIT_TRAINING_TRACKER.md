# V2 Geometric SaaS Audit Training Tracker

Last updated: 2026-04-23

This file tracks the V2 audit for the geometric SaaS conversion and converts the findings into broad converter training targets. This is not a file-specific patch list. Every item should be implemented as a reusable converter capability that improves future uploads, not only this sample.

## Source Artifacts

| Role | Path | Notes |
|---|---|---|
| Original HTML | `training-files/inline css/01-saas-geometric-light(1).html` | Source design uploaded into V2 converter. |
| Generated JSON | `my-project-saas-geometric-elementor.json` | Existing conversion artifact from V2. |
| Generated CSS | `my-project-saas-geometric-elementor.css` | Actual CSS file found in repo. |
| Missing referenced CSS | `my-project-saas-geometric-elementor (1).css` | User-referenced file does not exist in workspace. |

## Audit Principles

- Treat the audit as training evidence, not as a one-off repair target.
- Fix root-level converter systems: segmentation, extraction, widget mapping, CSS token resolution, CSS bridge, JS bridge, and diagnostics.
- Avoid regression by extending and merging existing logic rather than deleting prior working logic.
- No silent fallback or fake success. If a section is incomplete, diagnostics should say what was degraded and why.
- V2 objective remains container/native-widget first, with in-place hybrid HTML only for true behavior islands.

## First Audit Snapshot

These were the first deductions from the initial pass:

| Area | Deduction | Training Meaning |
|---|---|---|
| File resolution | `my-project-saas-geometric-elementor (1).css` does not exist; audited `my-project-saas-geometric-elementor.css`. | Verifier/audit tooling should report missing companion files clearly and continue with actual found artifacts. |
| Elementor import shape | Existing JSON has 9 top-level items and old repair wrappers for global setup/nav with duplicate `_element_id` values. | Builders must emit top-level containers from the start; repair should be a last-resort guard only. |
| CSS/design fidelity | Generated CSS uses dark/neon `#0a0a12` and `#c8ff00`; source is light geometric SaaS with `#F7F5F0`, `#1A1A2E`, `#E85D26`. | Source token/theme resolution is too weak and template defaults are overriding real source design. |
| JS contract | Output is missing `navbar`, `hero-text`, `hero-visual`, `chart-bars`, `stat-deals`, `stat-rev`, `stat-rate`, `btn-annual`, `.bar`, `.price`. | JS bridge needs source selector/ID preservation or reliable retargeting, plus behavior-island preservation when native rebuild removes JS targets. |
| Content degradation | Brand `veltro.` missing, logo strip names mostly lost, CTA misclassified/duplicated, pricing loses Annual/Starter/Pro/Enterprise, footer emits generic 2026 copy. | Content extraction needs broad coverage for brand, repeated logo strips, CTA, pricing cards/toggles, and footer identity. |

## Expanded Audit: Content Completeness

| Source Section | Source Content/Structure | Generated Output Problem | Global Fix Target |
|---|---|---|---|
| Nav | `veltro.` brand, links, login, `Get started free` CTA, source `id="navbar"`. | Brand is blank, CTA is treated like a normal link, old output changed nav DOM ID. | Improve nav brand/CTA extraction and preserve safe source IDs while keeping prefixed identity. |
| Hero copy | Eyebrow `Now in public beta`, headline, paragraph, `Start for free`, `Watch demo`, trial note. | Headline/paragraph mostly present; eyebrow, secondary CTA, and trial note are missing or reduced to hybrid fragment. | Hero extractor should map eyebrow, multiple CTAs, helper notes, and text hierarchy into native widgets. |
| Hero visual | Dashboard card, `Pipeline Overview`, `This week`, six `.bar` elements with `data-h`, weekday labels, stat labels and stat IDs. | Visual/dashboard structure mostly absent; behavior targets are missing. | Add broad dashboard/stat/chart primitive extraction or preserve visual as behavior island with source selectors intact. |
| Logo strip | `Trusted by teams at`, `Axiom`, `Fluxora`, `Draftly`, `Nuvio`, `Stackline`. | Only the intro text survives. | Add repeated-logo/client-strip extraction for direct text/logo children. |
| Features | Eyebrow `What Veltro does`, 3 feature cards with icons and source accent styling. | Main card copy survives, but source icon/accent/visual styling is not faithful. | Preserve card icon blocks and source card-level styling hooks while still using native headings/text. |
| Process | `Process`, 3 steps, step numbers, titles, descriptions, dark section styling. | Content mostly present, but an invented orbital visual is added. | Template library should not invent visuals unless source has matching visual payload or an explicit enhancement mode is enabled. |
| Pricing | Toggle `Monthly`/`Annual -20%`, cards `Starter`, `Pro`, `Enterprise`, prices `$29`, `$79`, `Custom`, per-card feature lists, CTAs. | Annual missing; card names missing; only `$29` cleanly survives; card features are merged; `Talk to sales` missing. | Pricing extractor needs card grouping by repeated plan containers, toggle preservation, per-card lists/prices/CTAs, and data attributes. |
| CTA | `Ready to close smarter?`, description, `Start your free trial`. | Misclassified as generic, duplicated, not emitted as CTA section. | CTA classifier/root promotion should detect standalone CTA blocks even when no `id` or explicit `cta` class exists. |
| Footer | `veltro.`, `2025 Veltro Inc. All rights reserved.`, Privacy/Terms/Contact. | Brand/copyright lost; generic 2026 footer emitted. | Footer identity/copyright extraction should prefer source text and never invent replacement legal copy. |

## Expanded Audit: CSS Contract

| Issue | Evidence | Global Fix Target |
|---|---|---|
| Wrong theme tokens | Source body uses light background `#F7F5F0`, text `#1A1A2E`, accent `#E85D26`; generated CSS uses dark defaults. | Resolve source palette from inline styles as well as style blocks before template defaults. |
| Missing source selector bridge | Sample reports source selector candidates but no output CSS bridge for key source selectors. | Bridge should map source classes/IDs to emitted hooks, including hybrid fragments and native widgets. |
| Dropped structure means dropped CSS | Missing `.bar`, `.price`, stat IDs, pricing cards, and logo brand spans means their CSS/behavior also disappears. | Content completeness checks should include CSS/JS selector coverage, not only visible text. |
| Template CSS overpowers source design | Generic bento/pricing/footer CSS uses fixed dark/neon assumptions. | Template library should consume resolved source tokens and avoid hard-coded design defaults when source tokens are available. |

## Expanded Audit: JS Contract

| Issue | Evidence | Global Fix Target | Status |
|---|---|---|---|
| Source selectors missing after native rebuild | JS references `navbar`, `hero-text`, `hero-visual`, `.bar`, `.price`, `btn-annual`; output lacks many of them. | Preserve safe source IDs/classes on native/hybrid targets or retarget selectors comprehensively. | Partial: nav source ID preserved; broader target preservation pending. |
| Inline handler functions not exported | `onclick="setPricing(...)"` needs `window.setPricing`; handler discovery had no raw HTML source. | Store raw HTML and export discovered handler functions globally after bridge insertion. | Done. |
| Elementor late script execution | Source `load`/`DOMContentLoaded` callbacks may never run when HTML-widget scripts execute after page events. | Runtime bridge should replay ready callbacks when events already fired. | Done. |
| Async listener failures | Missing nodes inside event callbacks can throw after outer bridge try/catch finishes. | Wrap registered event listeners so one failure is logged without killing the behavior island. | Done. |
| JS silently dropped when no retarget exists | Previous bridge returned empty JS when maps were unavailable. | Emit guarded passthrough with diagnostics and warnings instead of fake success. | Done. |

## Implemented Broad Fixes From This Audit

| Fix | Files | Status | Validation |
|---|---|---|---|
| Admin Project Name auto-updates from uploaded HTML filename. | `admin/js/admin.js` | Done | `node --check admin/js/admin.js` passed. |
| Global setup and nav emit top-level containers from the start. | `includes/converter/class-native-converter.php` | Done | Targeted V2 check has no top-level widget repair warning. |
| Full preserved HTML sections return a container wrapping the HTML widget. | `includes/converter/class-native-converter.php` | Done | `fully_preserved_source` sections show container plus HTML widget. |
| Section append boundary normalizes any remaining top-level widget before validation. | `includes/converter/class-native-converter.php` | Done | Manual inspection: zero top-level widgets. |
| Safe source nav IDs are preserved while generated prefixed identity remains available. | `includes/converter/class-native-converter.php` | Done for nav | Manual inspection confirms `id="navbar"` and generated data identity. |
| Document intelligence stores raw HTML for cross-pass handler analysis. | `includes/converter/passes/class-pass-document-intelligence.php` | Done | Manual inspection confirms `window.setPricing = setPricing`. |
| Script bridge exports inline handlers, replays ready callbacks, and wraps listeners. | `includes/converter/helpers/class-script-bridge-helper.php` | Done | Manual inspection confirms runtime guard code in generated JSON. |
| Source JS guarded passthrough replaces silent drop when no bridge target exists. | `includes/converter/class-native-converter.php` | Done | Diagnostics emit guarded passthrough path when applicable. |
| Source-vs-output content and selector coverage diagnostics. | `includes/converter/class-native-converter.php` | Done | Geometric V2 report now surfaces missing source phrases plus missing `.bar`, `.price`, stat IDs, `hero-*`, and `btn-annual` hooks. |
| Repeated pricing-card extraction with billing toggle and price data island preservation. | `includes/converter/class-native-converter.php`, `includes/converter/class-template-library.php` | Partial | Geometric V2 pricing now emits 3 buttons, 3 icon-lists, preserved `.price`, and `btn-annual`; full suite still has pricing fail codes. |
| Hero dashboard/stat behavior island extraction. | `includes/converter/class-native-converter.php`, `includes/converter/class-template-library.php` | Done for dashboard-style hero visuals | Geometric V2 bridge now preserves `.bar`, `hero-visual`, `chart-bars`, and stat IDs; script rewrite count is 7. |

## Remaining Broad Implementation Targets

| Priority | Target | Root-Level Implementation Direction | Evidence From Audit |
|---|---|---|---|
| P0 | Pricing card extraction | Detect repeated pricing-card containers, preserve plan grouping, price spans/data attributes, feature lists, toggle controls, and CTAs. | Partial: geometric pricing improved, but full suite still reports pricing card fail codes. |
| P0 | Source token/theme resolution | Extract dominant source palette from inline styles, body styles, buttons, section backgrounds, and CSS variables before template defaults. | Generated dark theme conflicts with light source. |
| P1 | CTA classification/root promotion | Detect CTA by heading+description+button pattern even without `id`/class. Avoid generic duplication. | CTA became duplicated generic. |
| P1 | Hero visual/stat extraction | Map dashboard/card/chart/stat structures into native widgets or in-place hybrid islands with source selectors. | Done for dashboard behavior islands in geometric sample; broaden to additional chart/stat layouts as more evidence appears. |
| P1 | Logo/client strip extraction | Detect repeated short brand/logo text children and emit native logo strip/marquee appropriately. | Company names dropped. |
| P1 | Footer identity/copyright extraction | Prefer source brand and copyright; never invent legal text. | Footer emits generic 2026 copy. |
| P1 | Source selector preservation on native widgets | Attach safe source IDs/classes to emitted widgets/containers where behavior/CSS references them. | JS references missing IDs/classes after native rebuild. |
| P2 | Template no-invention guard | Prevent template visuals from being added when source has no matching visual payload. | Process section gained invented orbital visual. |
| P2 | CSS bridge for inline-style-only pages | Source CSS bridge currently favors `<style>` rules, but many uploaded pages use inline styles. | Geometric sample is mostly inline CSS. |

## Verification Baseline

| Command | Current Result |
|---|---|
| `php -l includes/converter/class-native-converter.php` | Passed |
| `php -l includes/converter/helpers/class-script-bridge-helper.php` | Passed |
| `php -l includes/converter/passes/class-pass-document-intelligence.php` | Passed |
| `node --check admin/js/admin.js` | Passed |
| `php tools/verify-native-converter-cli.php --file "training-files/inline css/01-saas-geometric-light(1).html" --strategy v2` | `REPORT_OK` for custom file; coverage diagnostic reports 14 missing source phrases and 1 missing selector hook. |
| `php tools/run-training-suite-cli.php --quality-floor --floor-profile=balanced --trend-report` | Fails balanced floor on run success gates |

## Latest Coverage Diagnostic Evidence

| Metric | Geometric V2 Result |
|---|---|
| Source text phrases | 80 |
| Missing text phrases | 14 |
| Text coverage ratio | 0.825 |
| Source selector contracts | 13 |
| Missing selector hooks | 1 |
| Selector coverage ratio | 0.9231 |
| Missing selector examples | `pricing` |
| Missing content examples | `Watch demo`, trial note, logo strip names, `What Veltro does`, `Process`, `per user`, `Pro Most popular`, source copyright/title |

## Current Balanced Floor Fail Codes

| Fail Code | Count | Training Area |
|---|---:|---|
| `generic_repeated_structure_degraded` | 2 | Generic repeated block/card extraction |
| `process_step_count_degraded` | 2 | Process step extraction/deduplication |
| `pricing_card_count_degraded` | 2 | Pricing card grouping/count |
| `feature_cards_unresolved` | 2 | Feature card extraction |
| `footer_columns_unresolved` | 1 | Footer column extraction |
| `pricing_cards_unresolved` | 1 | Pricing card extraction |

## Latest Pass Notes

- Step 24 moved the geometric V2 sample from "missing most JS structures" to "one remaining selector miss".
- Pricing is no longer a one-card/one-button degradation in the targeted sample, but it is not globally done because the full training floor still reports pricing failures.
- Hero dashboard JS is now handled as the intended hybrid island model: native copy/widgets outside, source behavior subtree inside the same hero layout.
- Next recommended root-level pass: source token/theme resolution, because the audit still shows dark template defaults overriding the light geometric source design.

## How To Use This Tracker

- Before each implementation pass, pick one or two root-level targets from "Remaining Broad Implementation Targets".
- Update the relevant status row after each task.
- Add validation evidence after every task.
- Do not mark an item done based only on the geometric sample; it should pass targeted checks and not regress the training suite.
- Keep this file focused on audit evidence and broad fixes. Detailed chronological execution belongs in `CONVERTER_REBUILD_EXECUTION_PLAN.md`.

## 2026-04-23 Update - Step 25

### New Broad Fixes Landed

| Fix | Files | Status | Validation |
|---|---|---|---|
| Generic repeated-card extraction now works from repeated descendants and narrative panels, not only obvious `.card` markup. | `includes/converter/class-native-converter.php` | Done | Both previous V2 `feature_cards_unresolved` references now return `REPORT_OK`. |
| Script-array card extraction is no longer limited to `features = [...]`; repeated object arrays like `services` and similar datasets are now interpreted broadly. | `includes/converter/helpers/class-v2-primitive-assembler-helper.php` | Done | `training-files/inline css advanced/10-minimal-systems-complex(1).html` now passes V2 instead of failing at features extraction. |
| Footer success no longer depends on invented nav columns when the source footer is a simple text/copyright bar. | `includes/converter/class-native-converter.php`, `includes/converter/class-template-library.php` | Done | `training-files/inline css/03-agency-portfolio-brutalist.html` now returns `REPORT_OK`. |
| Footer template now emits extracted source footer bottom text instead of synthetic legal/status copy and preserves safe source footer IDs. | `includes/converter/class-template-library.php` | Done | Simple/footer-only outputs now pass truthfully through native rebuild. |
| Preserved wrapper integrity now validates against source signature/tag/id/class, avoiding false wrapper-loss failures on preserved generic islands. | `includes/converter/class-native-converter.php` | Done | `training-files/inline css advanced/11-wellness-spa-retreat(1).html` now returns `REPORT_OK`. |

### Current Broad Status

| Area | Current Status | Notes |
|---|---|---|
| Repeated pricing-card extraction | Done for current broad gate | Full balanced suite passes; geometric pricing now preserves plan-line markup, billing notes, and selector coverage 1. |
| Feature/process/generic repeated structures | Done for current broad gate | No remaining suite fail codes after repeated-group extraction widening. |
| Footer identity/copyright truthfulness | Improved broadly | Simple footers no longer require fake columns or synthetic legal/status text. |
| Source selector preservation on native widgets | Improved | Geometric selector coverage is now `1.0`; broader JS bridge work still has room to grow on non-geometric references. |
| Source token/theme resolution | Improved broadly | Global setup, nav, companion CSS, and template CSS now use source-derived rgba values instead of hardcoded dark/neon literals for muted states and hover accents. |
| Logo/client strip extraction | Done for current geometric reference | Repeated short-text strip extraction now retains the geometric logo/client names in output. |
| Eyebrow/label retention | Done for current geometric reference | Wrapped section labels and source title semantics now survive output coverage checks. |
| Sample-shaped template visuals | Removed | Synthetic orbital/process visual output and synthetic footer status output were removed from the template library. |
| Bridge target depth around real emitted widgets/islands | Improved broadly | Hero visual/copy, pricing toggle/amount, card visual widgets, and footer brand markup now participate in selector/script bridge targeting. |
| Non-selector JS state contracts | Improved broadly | The script bridge now rewrites state/reference patterns like `setAttribute(...)`, `dataset.*`, and `location.hash` when they point at bridged ids or selectors. |
| Dynamic runtime target resolution | Improved broadly | The runtime bridge now falls back through alias-aware DOM lookups for computed or deferred target access instead of relying only on direct selector literals. |
| Converter live preview workflow | Improved broadly | The converter admin sidebar now renders converted JSON plus companion CSS in a sandboxed live preview so structural checks no longer depend on opening Elementor for every pass. |
| Preview-side audit surfacing | Improved broadly | Native conversion diagnostics now persist through conversion records so the converter preview can show missing-content and missing-selector badges from the actual run. |
| Runtime stability under PHP DOM differences | Improved broadly | Native conversion no longer depends on `DOMElement::compareDocumentPosition()`, and runtime converter exceptions are now caught and returned as clean conversion errors instead of fataling the REST request. |

### Updated Verification Baseline

| Command | Current Result |
|---|---|
| `php -l includes/converter/class-native-converter.php` | Passed |
| `php -l includes/converter/helpers/class-v2-primitive-assembler-helper.php` | Passed |
| `php -l includes/converter/class-template-library.php` | Passed |
| `php tools/verify-native-converter-cli.php --file "training-files/inline css/01-saas-geometric-light(1).html" --strategy v2` | `REPORT_OK`; text coverage `1.0`, selector coverage `1.0`. |
| `php tools/verify-native-converter-cli.php --file "training-files/inline css advanced/10-minimal-systems-complex(1).html" --strategy v2` | `REPORT_OK` |
| `php tools/verify-native-converter-cli.php --file "training-files/inline css/02-saas-editorial-typographic.html" --strategy v2` | `REPORT_OK` |
| `php tools/verify-native-converter-cli.php --file "training-files/inline css/03-agency-portfolio-brutalist.html" --strategy v2` | `REPORT_OK` |
| `php tools/verify-native-converter-cli.php --file "training-files/inline css advanced/11-wellness-spa-retreat(1).html" --strategy v2` | `REPORT_OK` |
| `php tools/run-training-suite-cli.php --quality-floor --floor-profile=balanced --trend-report` | Passed balanced floor; 22/22 runs OK |

### Updated Geometric Evidence Snapshot

| Metric | Current Geometric V2 Result |
|---|---|
| Source text phrases | 80 |
| Missing text phrases | 0 |
| Text coverage ratio | 1.0 |
| Source selector contracts | 13 |
| Missing selector hooks | 0 |
| Selector coverage ratio | 1.0 |
| Missing content examples | None in latest targeted geometric V2 verification |

### Updated Global Deduction

- The current bottleneck is no longer “can V2 interpret basic repeated structures?” because the broad gate is now green and the targeted geometric reference now has full text/selector coverage.
- The next bottleneck is deeper bridge fidelity on harder JS/CSS contracts, not baseline interpretation of uploaded HTML.
- Audits should keep being treated as training references only; fixes should continue targeting extraction systems, render contracts, and bridge logic broadly rather than patching any one audited file.

### Next Recommended Root-Level Pass

- Bridge-depth expansion for sources with richer JS/CSS contracts.
- Reason:
  - the structural and semantic baseline is now strong enough,
  - the next remaining ceiling is broader selector/script richness on references that already pass structurally,
  - this is the next highest-value broad pass without regressing the newly restored native-first interpretation baseline.

## 2026-04-23 Update - Step 26

### New Broad Fixes Landed

| Fix | Files | Status | Validation |
|---|---|---|---|
| Section-tag extraction now inspects direct children and near-top descendants, so wrapped labels are not lost when a section header uses an inner wrapper div. | `includes/converter/class-native-converter.php` | Done | Geometric V2 text coverage moved from `0.875` to `1.0`; `What Veltro does` and `Process` no longer miss. |
| Pricing payloads now preserve richer semantics: inline plan-line markup, separate unit/billing notes, and badge deduplication when badge text already lives inside the preserved plan line. | `includes/converter/class-native-converter.php`, `includes/converter/class-template-library.php` | Done | Geometric V2 no longer misses `per user` or `Pro Most popular`. |
| Source document title is now carried into output as hidden semantic metadata instead of being silently dropped. | `includes/converter/class-native-converter.php` | Done | Geometric V2 no longer misses `Veltro — Pipeline Intelligence`. |
| Global/nav/companion/template styling now derives muted/hover/accent rgba values from source tokens instead of hardcoded dark-neon literals. | `includes/converter/class-native-converter.php`, `includes/converter/class-template-library.php` | Done | Geometric V2 remains `REPORT_OK`; balanced floor still passes after theme cleanup. |

### Updated Verification Baseline

| Command | Current Result |
|---|---|
| `php -l includes/converter/class-native-converter.php` | Passed |
| `php -l includes/converter/class-template-library.php` | Passed |
| `php tools/verify-native-converter-cli.php --file "training-files/inline css/01-saas-geometric-light(1).html" --strategy v2` | `REPORT_OK`; text coverage `1.0`, selector coverage `1.0`. |
| `php tools/run-training-suite-cli.php --quality-floor --floor-profile=balanced --trend-report` | Passed balanced floor; 22/22 runs OK |

## 2026-04-23 Update - Step 27

### New Broad Fixes Landed

| Fix | Files | Status | Validation |
|---|---|---|---|
| Removed the synthetic process orbital visual so the library no longer invents a sample-shaped `AI` diagram when the source does not contain one. | `includes/converter/class-template-library.php` | Done | Geometric/custom V2 remains `REPORT_OK`; full balanced floor still passes. |
| Removed the dead synthetic footer status/legal fragment path and its support CSS. | `includes/converter/class-template-library.php`, `includes/converter/class-native-converter.php` | Done | No regression in footer coverage or training-suite floor. |
| Widened bridge maps around real emitted widget/island surfaces: hero copy/visual, card visuals, process/testimonial visuals, pricing toggle, pricing amount, and footer brand logo. | `includes/converter/class-native-converter.php` | Done | Targeted custom/geometric bridge coverage now includes `price`, `btn-monthly`, and `btn-annual`; script rewrite count improved to `7`. |

### Updated Global Deduction

- The converter is now more trustworthy because sample-shaped visuals are being removed rather than hidden behind “nice-looking” output.
- The remaining bridge-depth work is about broader JS/CSS contract types, not about plugging gaps with synthetic design blocks.

### Next Recommended Root-Level Pass

- Bridge-depth expansion for non-selector JS state contracts.
- Reason:
  - selector-based bridge targeting is broader now,
  - the next meaningful gap is behavior that flows through attribute/state APIs rather than direct selector literals,
  - this keeps the system general without teaching it any one reference design.

## 2026-04-24 Update - Step 28

### New Broad Fixes Landed

| Fix | Files | Status | Validation |
|---|---|---|---|
| JS bridge now rewrites attribute/state reference patterns like `setAttribute(...)`, `dataset.*`, `location.hash`, and state comparisons when they reference bridged ids/selectors. | `includes/converter/class-native-converter.php` | Done | Lint passed; balanced floor still passes with no regression. |
| JS bridge helper now also hardens `document.querySelector(...).prop` access chains with optional chaining. | `includes/converter/helpers/class-script-bridge-helper.php` | Done | Lint passed; targeted V2 verifier remains `REPORT_OK`. |

### Updated Global Deduction

- This pass broadens bridge capability even though the current verification corpus did not show a visible metric jump.
- That means the training set likely under-exercises these attribute/state contracts today, so the right interpretation is “broader behavior coverage without regression,” not “measurable corpus gain.”

### Next Recommended Root-Level Pass

- Bridge-depth expansion for dynamic runtime target resolution.
- Reason:
  - direct selectors and attribute/state references are now covered more broadly,
  - the next gap is behavior that derives targets through arrays, computed names, or event payloads at runtime,
  - that is the next general step without slipping back into sample-shaped logic.

## 2026-04-24 Update - Step 29

### New Broad Fixes Landed

| Fix | Files | Status | Validation |
|---|---|---|---|
| The bridge now rewrites and recognizes more runtime-derived state references, including `setAttribute(...)`, `dataset.*`, `location.hash`, `href/hash` assignment, and state comparisons against bridged targets. | `includes/converter/class-native-converter.php` | Done | Lint passed; targeted V2 verifier remains `REPORT_OK`; balanced floor still passes. |
| Runtime safety now wraps bridged execution with alias-aware DOM lookup fallbacks for `getElementById`, `querySelector`, `querySelectorAll`, `getElementsByClassName`, `closest`, and `matches`. | `includes/converter/helpers/class-script-bridge-helper.php` | Done | Lint passed; no regression in targeted or full-suite verification. |
| Event callbacks registered during bridged execution now inherit the same alias-aware runtime resolution instead of losing bridge context after initial bootstrap. | `includes/converter/helpers/class-script-bridge-helper.php` | Done | Broader capability landed without sample-shaped logic; current corpus shows no visible metric jump. |

### Updated Global Deduction

- This pass broadens runtime behavior support for source JS that derives targets indirectly through bridge aliases or deferred callback execution.
- The current corpus still does not heavily stress these paths, so the right reading is broader capability plus no regression, not a claimed score increase.
- The baseline remains broad and general: this pass does not teach the converter any one audited layout or sample-specific selector shape.

### Next Recommended Root-Level Pass

- Bridge-depth expansion for array-driven and event-payload-driven runtime target maps.
- Reason:
  - direct selectors, attribute/state references, and alias-aware DOM lookups are now covered more broadly,
  - the next remaining gap is JS that computes target names through arrays, config objects, or event payload data before lookup,
  - that keeps the training direction broad and interpretation-first instead of sample-shaped.

## 2026-04-24 Update - Step 30

### New Broad Fixes Landed

| Fix | Files | Status | Validation |
|---|---|---|---|
| Source JS bridge now rewrites reference-like config values in broad variable assignments, object properties, and array literals when they carry selector, target, panel, tab, section, id, class, or similar runtime target semantics. | `includes/converter/class-native-converter.php` | Done | Full balanced floor still passes, and global `script_rewrite_ratio` rose from `0.6363636363636364` to `0.8181818181818182`. |
| Runtime bridge now rewrites event payload channels like `event.detail`, `event.data`, and `event.state` through the same alias maps used for DOM lookups, so deferred target names survive more custom-event flows. | `includes/converter/helpers/class-script-bridge-helper.php` | Done | Targeted V2 verifier remains `REPORT_OK`; no bridge regressions in the full suite. |
| Converter admin now includes a right-sidebar live preview that renders converted Elementor JSON plus companion CSS inside a sandboxed iframe for immediate structural/styling inspection. | `admin/views/page-converter.php`, `admin/js/admin.js`, `admin/css/admin.css` | Done | PHP view lint passed; `node --check admin/js/admin.js` passed. |

### Updated Global Deduction

- This pass moved beyond “capability only” and produced a measurable global bridge gain: the wider corpus now rewrites more source scripts successfully.
- The gain came from broader references across the training suite, not from teaching any one audited layout, which is the right kind of movement.
- The new live preview reduces the review loop cost, but it should be treated as a fast sandboxed inspection surface, not as a perfect clone of Elementor’s frontend engine.

### Next Recommended Root-Level Pass

- Widen source CSS selector/output depth on native surfaces and expose audit signals inside the preview.
- Reason:
  - script bridge depth improved materially on this pass,
  - the next visible quality ceiling is still selector-output richness, with `selector_output_css_ratio` at `0.36363636363636365`,
  - pairing that work with preview-side audit signals would make broad regressions easier to spot before opening Elementor.

## 2026-04-24 Update - Step 31

### New Broad Fixes Landed

| Fix | Files | Status | Validation |
|---|---|---|---|
| Native conversion diagnostics now persist through conversion records instead of being dropped after conversion, which exposes real coverage/bridge signals to admin-side tooling. | `includes/converter/class-conversion-manager.php` | Done | PHP lint passed; converter records now carry `diagnostics` alongside `warnings` and `class_map`. |
| Converter preview now surfaces audit badges for missing content, missing selectors, and CSS bridge state based on the stored native diagnostics. | `admin/views/page-converter.php`, `admin/js/admin.js`, `admin/css/admin.css` | Done | PHP/JS lint passed; preview card now has live audit chips and a wider inspection surface. |
| CSS bridge target filtering now expands section-root targets into related native widget semantic surfaces before validation, so section-level source hooks can reach more heading/text/button/image/media wrappers broadly. | `includes/converter/class-native-converter.php` | Done | Balanced floor still passes with no regression; current corpus did not yet move `selector_output_css_ratio`. |
| Converter layout was rebalanced so the main content area is narrower and the right sidebar is wider, which makes the live preview materially more usable. | `admin/css/admin.css` | Done | Admin assets lint clean. |

### Updated Global Deduction

- The review loop is much better now because the converter page itself can show both a live preview and actual conversion audit badges.
- The CSS widening landed safely, but today’s corpus still leaves `selector_output_css_ratio` at `0.36363636363636365`, so this pass should be read as groundwork plus no regression rather than a measured selector-output gain.
- That means the next CSS pass should target the actual mapping ceiling more directly, not just the UI around it.

### Next Recommended Root-Level Pass

- Expand broad source-to-native CSS mapping for section-local descendant contracts, especially where original section hooks need to land on native widget wrappers instead of section roots.
- Reason:
  - diagnostics are now visible immediately in the converter UI,
  - the remaining visible bridge ceiling is still selector-output richness rather than JS survival,
  - the next step should focus on root-to-leaf native surface mapping, not more interface work.

## 2026-04-24 Update - Step 32

### New Broad Fixes Landed

| Fix | Files | Status | Validation |
|---|---|---|---|
| Replaced the unsupported `DOMElement::compareDocumentPosition()` section-tag ordering check with a DOM-compatible document-order helper. | `includes/converter/class-native-converter.php` | Done | Fatal 500 path removed; PHP lint passed; training suite still green. |
| Native conversion runtime is now wrapped in a broad `Throwable` catch so unexpected converter failures return a clean WP error instead of crashing the REST endpoint. | `includes/converter/class-conversion-manager.php` | Done | PHP lint passed; REST-facing failure mode is now controlled. |

### Updated Global Deduction

- This pass was a necessary stability layer for the descendant-mapping phase: the converter cannot be trained reliably if a single DOM API difference can take the whole route down.
- The broader CSS descendant-mapping ceiling remains, but it is now safer to continue pushing on it because native conversion errors should degrade cleanly instead of killing the request.
- Metrics did not move on this pass, which is expected because this was primarily a compatibility and resilience correction.

### Next Recommended Root-Level Pass

- Continue broad section-local descendant CSS mapping with explicit root-to-leaf semantic retargeting for headings, text blocks, buttons, lists, and media wrappers.
- Reason:
  - the crash blocker is now gone,
  - the converter workflow is more stable for training and auditing,
  - the remaining visible ceiling is still selector-output richness on native surfaces.

## 2026-04-24 Update - Step 33

### New Broad Fixes Landed

| Fix | Files | Status | Validation |
|---|---|---|---|
| Section-root selector retargeting now pushes more descendant contracts onto broad native leaf wrappers for headings, text, buttons, lists, and media when those wrappers exist in the emitted inventory. | `includes/converter/class-native-converter.php` | Done | PHP lint passed; targeted V2 verifier remains `REPORT_OK`; balanced floor still passes. |
| Converter preview now has a fullscreen mode for faster inspection from the admin sidebar. | `admin/views/page-converter.php`, `admin/js/admin.js`, `admin/css/admin.css` | Done | PHP/JS lint passed; preview card can now expand without opening Elementor. |
| Preview HTML sanitization now covers raw rich-text and fallback widget paths too, strips quoted/unquoted inline handlers, removes script tags, neutralizes `javascript:` URLs, and runs inside a non-script sandboxed iframe. | `admin/js/admin.js`, `admin/views/page-converter.php` | Done | Admin asset lint passed; preview rendering is materially safer against malformed converted source markup. |

### Updated Global Deduction

- This pass improves two root-level training needs at once:
  - broader section-local descendant CSS landing surfaces,
  - much safer and faster preview inspection.
- The preview fix is broad and architectural, not a one-off patch for a specific converted file:
  - malformed script fragments should no longer break `about:srcdoc`,
  - the preview is intentionally a structural/styling surface, not a JS-execution clone of Elementor.
- The selector-output ceiling is still not moving in the current corpus, so the honest reading is:
  - broader retargeting landed,
  - preview reliability improved,
  - measured CSS bridge richness still needs a deeper decomposition pass.

### Next Recommended Root-Level Pass

- Decompose section-local CSS contracts before retargeting so bundled section-root selectors split into smaller descendant rules that can map more precisely onto native leaf widgets.
- Reason:
  - the current broad root-to-leaf remap is now in place,
  - but some source rules are still too coarse before they reach the retargeting stage,
  - the next general gain should come from better selector decomposition rather than sample-shaped exceptions.

## 2026-04-24 Update - Step 34

### New Broad Fixes Landed

| Fix | Files | Status | Validation |
|---|---|---|---|
| Source selector parsing now respects commas inside brackets, quotes, and selector functions instead of naively splitting every comma in the rule block. | `includes/converter/class-native-converter.php` | Done | PHP lint passed; balanced floor still passes. |
| Grouped selector functions like `:is(...)` and `:where(...)` now expand into descendant variants before native widget-semantic retargeting. | `includes/converter/class-native-converter.php` | Done | Targeted V2 verifier remains `REPORT_OK`; no regression in full-suite verification. |
| Added a reusable DB-free preview-audit runner that converts a file, writes JSON/CSS/diagnostics/report artifacts, and emits a preview-like structure summary for local auditing. | `tools/run-preview-audit-cli.php` | Done | PHP lint passed; random V2 audit run completed successfully. |
| Generated a real random-sample audit artifact set for `03-agency-portfolio-brutalist.html` under `audit-output/`, including a written markdown audit with deductions and broad fix direction. | `audit-output/random-v2-audit-03-agency-portfolio-brutalist*` | Done | Audit completed from real conversion artifacts, not from a guessed inspection. |

### Updated Global Deduction

- The selector-decomposition pass is valid and broad, but it did not move the current `selector_output_css_ratio`.
- The random V2 audit is more important than the unchanged metric here because it exposes the next real ceiling clearly:
  - token fallback is still overriding source design,
  - semantic role extraction is still dropping or misrouting content,
  - selector bridge limits are still real, but they are no longer the only or even biggest visible quality problem on some files.
- In short:
  - CSS syntax handling widened,
  - auditability improved,
  - the next broad converter gain should target content-role interpretation before layout assembly.

### Next Recommended Root-Level Pass

- Build stronger role-based extraction for brand/logo, eyebrow labels, descriptive paragraphs, CTA/button text+href, and stat number/label pairs before native section assembly.
- Reason:
  - the random preview audit showed missing hero copy, missing CTA semantics, missing stat semantics, and lost nav brand text,
  - those are interpretation failures, not just styling misses,
  - stronger role extraction will unlock better native rebuild and make later CSS/JS bridging land on the right nodes.

## 2026-04-24 Update - Step 35

### New Broad Fixes Landed

| Fix | Files | Status | Validation |
|---|---|---|---|
| Added a broad HTML-to-Elementor-free widget matrix so the converter has an explicit baseline for headings, paragraphs, buttons, lists, images, and table/SVG preservation. | `includes/converter/helpers/class-v2-primitive-assembler-helper.php` | Done | PHP lint passed; balanced floor still passes. |
| Added container-first native block extraction that scans direct child containers as primitive blocks and collects widget descriptors in DOM order with source hooks preserved. | `includes/converter/helpers/class-v2-primitive-assembler-helper.php` | Done | Targeted V2 verifier remains `REPORT_OK`; no regression in the full suite. |
| V2 primitive fallback now rebuilds native container blocks from widget descriptors before dropping to the older flat primitive bucket emitter. | `includes/converter/class-native-converter.php` | Done | Full balanced floor still passes with unchanged global metrics. |

### Updated Global Deduction

- This pass aligns the converter more closely with the intended V2 mindset:
  - container first,
  - broad primitive interpretation second,
  - in-place hybrid HTML only where native free widgets cannot express the behavior.
- The current verification corpus did not produce a measurable metric jump, so the honest reading is:
  - baseline interpretation got broader and cleaner,
  - fallback structure is more reusable,
  - the next big gain still depends on role-based content interpretation inside those blocks.
- In other words, the converter is now better positioned to answer:
  - what are the containers?
  - what native widgets belong inside them?
  but it still needs to answer more accurately:
  - which text is brand, eyebrow, description, CTA, stat label, or decorative copy?

### Next Recommended Root-Level Pass

- Add stronger role-based primitive interpretation on top of the widget matrix:
  - brand/logo extraction
  - eyebrow/overline extraction
  - description/subtitle extraction
  - CTA/button extraction with href preservation
  - stat number/label pairing
- Reason:
  - the matrix now gives V2 a better baseline,
  - but audits still show semantic misrouting inside otherwise valid native structures,
  - the next broad win is to improve meaning assignment before assembly, not to add more section-type exceptions.

## 2026-04-24 Update - Step 36

### New Broad Fixes Landed

| Fix | Files | Status | Validation |
|---|---|---|---|
| Primitive widget descriptors now infer broad semantic roles like `brand`, `eyebrow`, `description`, `headline`, `cta`, `nav-link`, `nav-list`, `stat-value`, and `stat-label`. | `includes/converter/helpers/class-v2-primitive-assembler-helper.php` | Done | PHP lint passed; targeted V2 verifier remains `REPORT_OK`. |
| Native primitive widgets now emit role classes on output, giving later CSS/JS bridge work clearer landing surfaces than anonymous generic widgets. | `includes/converter/class-native-converter.php` | Done | No regression in full-suite verification; balanced floor still passes. |
| Paragraph extraction now prefers description-like copy and deprioritizes eyebrow/stat/all-caps utility text. | `includes/converter/class-native-converter.php` | Done | Random V2 audit improved text coverage from `0.8462` to `0.8846`. |
| CTA extraction now preserves hrefs broadly for hero, CTA sections, and generic native blocks instead of discarding destinations into `#`. | `includes/converter/class-native-converter.php`, `includes/converter/class-template-library.php` | Done | Hero/contact output now shows more native buttons in the refreshed audit artifacts. |
| Refreshed the random brutalist V2 audit artifacts and markdown audit so they reflect this broader semantic pass honestly. | `audit-output/random-v2-audit-03-agency-portfolio-brutalist*` | Done | Audit rerun completed from real conversion artifacts. |

### Updated Global Deduction

- This pass confirms that role-based interpretation is a real global quality lever:
  - the converter preserved more meaning without any file-specific hardcoding,
  - hero/contact CTA handling improved,
  - broad description picking improved.
- The full-suite headline metrics stayed flat, so the honest reading is:
  - semantic interpretation improved,
  - architecture stayed stable,
  - selector fidelity and token fidelity still cap visible quality in many files.
- The refreshed random audit is useful evidence:
  - text coverage improved,
  - CTA survival improved,
  - but selector coverage and token/theme drift still remain weak.

### Next Recommended Root-Level Pass

- Strengthen source token/theme precedence and generic stat-pair interpretation.
- Reason:
  - the new role layer improved meaning assignment,
  - but the audit still shows wrong accent/theme carryover and missing studio stat semantics,
  - those are now the clearest global blockers before further selector-bridge polishing.

## 2026-04-24 Update - Step 37

### New Broad Fixes Landed

| Fix | Files | Status | Validation |
|---|---|---|---|
| Source theme precedence now reads broad page-level CSS selectors and applies source-derived background, text, accent, and font hints before template fallback defaults. | `includes/converter/class-native-converter.php` | Done | PHP lint passed; refreshed brutalist audit shows the old neon/Syne fallback is gone from output CSS. |
| Palette fallback now infers contrast roles from detected source colors when explicit background/text tokens are absent, instead of clinging to the previous sample-shaped fallback pair. | `includes/converter/class-native-converter.php` | Done | Full balanced floor still passes with no regression. |
| Reduced sample-shaped last-resort defaults in both the converter and template library, so weak-theme inputs no longer default to the old neon/Syne stack. | `includes/converter/class-native-converter.php`, `includes/converter/class-template-library.php` | Done | Training suite remains `22/22` OK. |
| Reused the shared stat extraction path for explicit stats sections and broadened generic section/block assembly so repeated stat pairs can be emitted outside dedicated `stats` sections. | `includes/converter/class-native-converter.php` | Done | Broad runtime path is in place; no regression in the suite. |
| Refreshed the random brutalist preview audit and markdown notes to reflect the new theme-precedence state honestly. | `audit-output/random-v2-audit-03-agency-portfolio-brutalist*` | Done | Audit rerun completed from real conversion artifacts. |

### Updated Global Deduction

- The source-theme problem has materially improved at the root:
  - output CSS is no longer falling back to the old neon accent and Syne stack,
  - source-aligned dark/stone tokens now survive in the refreshed audit artifacts,
  - source body/mono font hints are now visible in output.
- The honest limit is that this pass did not move the main audit coverage numbers:
  - text coverage on the brutalist audit stayed `0.8846`,
  - selector coverage stayed `0.4167`,
  - studio stat semantics still did not recover fully.
- So the current reading is:
  - theme fidelity is healthier,
  - sample-shaped fallback leakage is reduced,
  - but generic stat semantics and selector landing are still the next true ceilings.

### Next Recommended Root-Level Pass

- Strengthen generic stat-pair interpretation and hook landing for harder layouts.
- Reason:
  - the theme ceiling is less severe now,
  - but the audit still shows missing stat values/labels and missing `stat-num`/section hooks,
  - the next broad gain is to recover stat semantics and land original hooks on the native widgets that now exist.

## 2026-04-25 Update - Step 39

### New Broad Fixes Landed

| Fix | Files | Status | Validation |
|---|---|---|---|
| Generic/native stat widgets now emit broader stat hook aliases and carry source stat hooks more consistently when those contracts exist in source CSS/JS. | `includes/converter/class-native-converter.php` | Done | Full training suite passes again; no hook-orphan regressions remain. |
| Section selector bridge targets now use the real emitted `output_element_id` from payloads instead of always assuming `#prefix-type`. | `includes/converter/class-native-converter.php` | Done | Restored full `22/22` suite pass while keeping the brutalist bridge gain. |
| Hook inventory now treats `src-id-*` alias classes as valid emitted id hooks during bridge validation. | `includes/converter/class-native-converter.php` | Done | Prevents false orphan-hook failures where source ids survive as alias hooks rather than literal ids. |
| Converter-page live preview now records sanitization diagnostics per widget/block/CSS surface and shows them in the right sidebar. | `admin/js/admin.js`, `admin/views/page-converter.php`, `admin/css/admin.css` | Done | `node --check` passed; admin view/CSS lint checks passed. |
| Refreshed the random brutalist V2 audit artifacts again so the tracker points at the current bridge baseline. | `audit-output/random-v2-audit-03-agency-portfolio-brutalist*` | Done | Audit rerun completed from real conversion artifacts. |

### Updated Global Deduction

- This pass made a real bridge/hook gain without reintroducing regressions:
  - the full suite is back to `22/22`,
  - bridge validation is no longer assuming synthetic section ids where real emitted ids differ,
  - preview sanitization is now inspectable instead of silent.
- The refreshed brutalist audit confirms a real improvement:
  - selector coverage improved from `0.4167` to `0.5`,
  - bridged ids now include `studio`, `work`, `contact`, and `hero-main`.
- The honest limit is that the core semantic misses are still there:
  - text coverage stayed `0.8846`,
  - missing studio stat values/labels remain,
  - `stat-num`, `nav-link`, and `cta-btn` are still not all landing where we want them.

### Next Recommended Root-Level Pass

- Strengthen hard-layout generic stat-pair interpretation and semantic hook landing.
- Reason:
  - hook presence is healthier now,
  - but the brutalist audit still shows that missing content, not just missing hook aliases, is the next ceiling,
  - the next broad win is to recover nested stat value/label structures and nav/CTA semantics more faithfully before assembly.
