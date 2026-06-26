# Random V2 Preview Audit

## Source

- HTML: [03-agency-portfolio-brutalist.html](C:/Users/HP/Local%20Sites/devplayground/app/public/wp-content/plugins/stack-blueprint/training-files/inline%20css/03-agency-portfolio-brutalist.html)
- Strategy: `v2`

## Generated Artifacts

- JSON: [random-v2-audit-03-agency-portfolio-brutalist.json](C:/Users/HP/Local%20Sites/devplayground/app/public/wp-content/plugins/stack-blueprint/audit-output/random-v2-audit-03-agency-portfolio-brutalist.json)
- CSS: [random-v2-audit-03-agency-portfolio-brutalist.css](C:/Users/HP/Local%20Sites/devplayground/app/public/wp-content/plugins/stack-blueprint/audit-output/random-v2-audit-03-agency-portfolio-brutalist.css)
- Diagnostics: [random-v2-audit-03-agency-portfolio-brutalist-diagnostics.json](C:/Users/HP/Local%20Sites/devplayground/app/public/wp-content/plugins/stack-blueprint/audit-output/random-v2-audit-03-agency-portfolio-brutalist-diagnostics.json)
- Report: [random-v2-audit-03-agency-portfolio-brutalist-report.json](C:/Users/HP/Local%20Sites/devplayground/app/public/wp-content/plugins/stack-blueprint/audit-output/random-v2-audit-03-agency-portfolio-brutalist-report.json)
- Audit JSON: [random-v2-audit-03-agency-portfolio-brutalist-audit.json](C:/Users/HP/Local%20Sites/devplayground/app/public/wp-content/plugins/stack-blueprint/audit-output/random-v2-audit-03-agency-portfolio-brutalist-audit.json)

## Preview-Like Reading

- Top-level elements: `8`
- Total nodes: `42`
- Containers: `18`
- Widgets: `24`
- HTML widgets: `10`
- Widget mix: `heading=8`, `text-editor=6`, `html=10`

## Findings

1. Theme/token carryover is still wrong at the root level.
The generated global setup and companion CSS switch the source into an alien accent and font system: source visual language is black/stone with Bebas Neue + Overpass, but output injects `--preview-audi-accent: #c8ff00` and `--preview-audi-fd: 'Syne', sans-serif`. This also leaks into cursor color, button styling, nav styling, and shared template CSS. This is not a file-specific bug; it means source token resolution is still losing to fallback/template defaults.

2. Content interpretation is incomplete even before styling.
Coverage is only `0.8462`, with `8` meaningful source phrases missing. The missing set is not random noise:
`Creative agency —`
`Strategy, identity, and digital experience for companies redefining their categories.`
`SEE OUR WORK →`
`Our process is collaborative, fast, and built around results that outlast the trend cycle.`
`140+`
`years running`
`awards won`
`studio locations`
This means the converter is still dropping or misrouting real semantic content during extraction. Example: hero eyebrow/subcontent got remapped incorrectly, and studio stat content was not reconstructed faithfully.

3. Native rebuild is still too HTML-heavy for a supposedly native-first pass.
The preview-like structure shows `10` HTML widgets out of `24` widgets. The work/gallery section is fully preserved as HTML, contact is hybrid, hero still needs multiple HTML widgets, and nav/global setup are pure HTML wrappers. This is not “no native output,” but it is still far from a robust native-first baseline for a relatively interpretable agency page.

4. Navigation/header extraction is losing real source meaning.
The source nav brand is `FORMA`, but the generated nav logo anchor is empty. The original `nav-link` contract is also missing from output coverage. So even where the nav visually exists, the semantic/header extraction is incomplete and the bridge cannot see the original contract fully.

5. Hero semantic routing is wrong.
The hero has the main headline, but the supporting content is misread:
- `Creative agency —` is missing.
- The real subcopy is missing.
- `SEE OUR WORK →` is missing.
- `Lagos · London · Lisbon` gets reused in places that do not match the original semantic role.
This means the converter is identifying some hero text, but not preserving role fidelity between eyebrow, descriptive paragraph, CTA, and decorative/location copy.

6. Studio/stat extraction is partially broken.
The studio section body and service list survive, but the stats do not survive faithfully. Missing samples include `140+`, `years running`, `awards won`, and `studio locations`. At the JS/selector level, `stat-num` is also reported missing. That points to a broad stats pipeline problem:
- stat value extraction is incomplete,
- stat label extraction is incomplete,
- stat hook propagation is incomplete.

7. Selector bridge output is still effectively absent on this sample.
Source CSS exists, source hooks exist, bridge targets exist, but selector bridge output is still:
- `has_source_selector_hits: false`
- `matched_rule_count: 0`
- `has_output_css: false`
Coverage for selector/behavior hooks is only `0.4167`, with `7` of `12` hooks missing. Missing hooks include:
- ids: `cursor-dot`, `cursor-ring`, `para-bg`, `cta-btn`, `studio`
- classes: `nav-link`, `stat-num`
This means the CSS bridge is still not carrying real source contracts broadly enough, even after native output exists.

8. JS bridge is present but only partial.
Script bridge survives better than selector bridge:
- `has_rewrite: true`
- `rewrite_count: 4`
- cursor source/output both detected
But several source behaviors still target structures that do not survive as native bridged surfaces, especially `para-bg`, `stat-num`, `work-item`, and some section ids/classes. In other words, JS is not “dead,” but it is leaning on preserved HTML islands and partial aliasing instead of broad structural carryover.

9. Some preserved areas are carrying important content, which hides extraction weakness.
The work section still contains `Selected work`, `View all projects →`, and all five project rows only because the section is preserved as raw HTML. The contact email survives as HTML too. So content presence here should not be misread as strong native interpretation; in multiple places the content is present only because preservation saved it.

## Deductions

- The biggest root issue exposed by this audit is still source interpretation, not just styling polish.
- Theme token extraction is still being overridden by fallback/template defaults.
- Semantic role extraction is still weak for:
  - hero eyebrow vs location labels
  - hero descriptive paragraph vs decorative copy
  - CTA/button recovery
  - stat number/label pairs
  - nav brand/logo text
- The selector bridge is still underpowered on section-local contracts, especially when the original hook does not survive as a simple one-to-one class/id in native output.
- HTML preservation is still masking interpretation gaps. The output can look less broken than it really is because preserved islands keep source content alive.

## Refresh After Role Pass

- The audit was rerun after the role-based primitive interpretation pass.
- Text coverage improved from `0.8462` to `0.8846`.
- Missing text dropped from `8` phrases to `6`.
- The hero now emits a native CTA button instead of losing that action entirely.
- The contact section now emits `2` native buttons instead of relying only on HTML preservation.
- Preview-like structure is now:
  - top-level elements `8`
  - total nodes `46`
  - containers `19`
  - widgets `27`
  - html widgets `10`
  - widget mix `button=3`, `heading=8`, `text-editor=6`, `html=10`
- What did not improve yet:
  - selector coverage stayed `0.4167`
  - selector bridge output is still effectively absent on this sample
  - token/theme drift is still present
  - studio stat semantics are still incomplete

### Updated Deduction

- This confirms the right next direction: role-aware interpretation is a real global lever.
- It improved semantic carryover without sample hardcoding.
- But it also confirms that two separate ceilings still remain:
  - source token/theme fidelity
  - selector/behavior hook landing on native output

## Global Fix Direction

- Strengthen source token precedence so extracted page palette and font system beat template defaults everywhere:
  - global setup
  - template library CSS
  - nav CSS
  - cursor/bridge assets
- Expand semantic extraction by role, not by sample:
  - brand/logo text
  - eyebrow/label text
  - descriptive paragraph text
  - CTA/button text and href
  - stat number/label pairs
- Push section-local hook propagation deeper so original ids/classes can survive onto native leaves or wrapper aliases without requiring whole-section preserve.
- Treat preserved HTML sections as a diagnostic failure signal for training, not as proof the converter “handled” the section.

## Next Recommended Root-Level Pass

- Build role-based text extraction for header/hero/stats/button semantics before layout assembly.
- Reason:
  - this audit shows the converter is not just missing CSS hooks,
  - it is still misclassifying and dropping core content roles,
  - without stronger role extraction, native reconstruction will keep looking structurally present but semantically wrong.

## Refresh After Theme + Generic Stat Pass

- The audit was rerun again after the broad source-theme-precedence pass and the first generic-stat reuse pass.
- What improved globally:
  - the output is no longer injecting the old sample-shaped neon/dark fallback system,
  - companion CSS now uses source-aligned dark/stone tokens instead of the previous `#c8ff00` + `Syne` baseline,
  - the refreshed CSS shows:
    - `--preview-audi-bg: #0d0d0d`
    - `--preview-audi-accent: #f2efe8`
    - `--preview-audi-text: #f2efe8`
    - `--preview-audi-fb: 'Bebas Neue', sans-serif`
    - `--preview-audi-fm: 'Overpass Mono', monospace`
  - hardcoded sample-style defaults were also reduced in the core/template fallback layer, so weak-theme inputs no longer default back to the old neon/Syne stack.
- What did not move on this sample:
  - text coverage stayed `0.8846`
  - missing text stayed at `6`
  - selector coverage stayed `0.4167`
  - studio stat semantics are still incomplete on this file
- Updated deduction:
  - source theme precedence is materially healthier now,
  - but generic stat pairing still needs a stronger interpretation pass before the brutalist sample will recover `140+`, `years running`, `awards won`, and `studio locations`,
  - so the next ceiling is no longer the old neon fallback problem first; it is generic stat semantics plus deeper selector landing on native output.

## Refresh After Bridge + Preview Diagnostics Pass

- The audit was rerun again after the bridge-target/output-id pass and the preview-strip diagnostics pass.
- What improved:
  - selector coverage improved from `0.4167` to `0.5`
  - bridged ids now include:
    - `studio`
    - `work`
    - `contact`
    - `hero-main`
  - the converter no longer loses those section ids simply because the emitted anchor differs from the old assumed `#prefix-type` target
  - the converter-page preview now reports when sanitization stripped content from a widget/block instead of failing silently
- What did not improve yet:
  - text coverage stayed `0.8846`
  - missing text stayed at `6`
  - `stat-num` is still missing as a landed source hook
  - `nav-link` and `cta-btn` are still not fully carried through
- Updated deduction:
  - bridge truthfulness is better now,
  - but the next real ceiling is still semantic interpretation of nested stat structures and nav/CTA semantics, not preview safety or section-id bookkeeping.

## Browser-Truth Comparison Refresh

- A browser-vs-V2 comparison model now exists for this same brutalist sample.
- Browser summary:
  - sections: `6`
  - headings: `13`
  - text blocks: `43`
  - actions: `6`
  - behavior islands: `8`
- Current V2 summary:
  - sections: `8`
  - headings: `8`
  - text widgets: `6`
  - buttons: `3`
  - HTML widgets: `10`
  - native widget ratio: `0.6296`
- High-signal deductions from that comparison:
  - V2 is still preserving more HTML than the browser model suggests is necessary.
  - Hook landing remains incomplete for ids like `cta-btn`, `work-grid`, and `email-link`, and classes like `nav-link`, `work-item`, and `stat-num`.
  - The `services` section kind is present in browser truth but not yet being recognized cleanly at top-level V2 interpretation.
- Artifacts:
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare-browser-model.json`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare-elementor-model.json`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare-comparison.json`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare.md`

## Browser Role-Gap Refresh

- The browser-vs-V2 comparison layer was extended again so it can expose role-level deficits per section instead of only whole-page count differences.
- The current high-signal role gaps on this sample are:
  - `services` section kind still missing from Elementor interpretation
  - `gallery` still under-landing actions and text blocks
  - `studio` still under-landing text blocks
- This makes the next real output-improving targets clearer:
  - browser-guided services-section rebuild
  - browser-guided CTA/link landing
  - browser-guided text-block recovery

## Generic Interpretation Refresh

- A broader global interpretation pass was applied after that role-gap audit.
- What changed globally:
  - repeated generic blocks can now carry multiple paragraph-like text surfaces instead of a single flattened body line
  - repeated generic blocks can now carry multiple CTA/action candidates instead of one winning button only
  - section scoring now gives repeated heading + text/list groups more weight toward service/features interpretation
  - V2 native affinity now also sees repeated content-group structure before preserve decisions
- What this did not solve on this sample:
  - the refreshed browser-vs-V2 comparison still reports:
    - browser headings `13` vs Elementor headings `3`
    - browser text blocks `43` vs Elementor text widgets `3`
    - browser actions `6` vs Elementor buttons `3`
    - missing section kinds `services` and `studio`
- Updated deduction:
  - the richer payload model is not the main blocker yet
  - some repeated-content sections are still being routed into preserve-heavy paths before the native block builder can use the broader text/action extraction
  - the next root-level target should be early preserve-routing and section partitioning, not another one-off payload tweak for this file

## Preview-Truth Comparison Refresh

- The browser-vs-V2 comparison layer was upgraded to read preserved HTML widgets more like Elementor preview instead of treating each HTML widget as one opaque text blob.
- What improved in the audit model:
  - descendant ids/classes inside preserved HTML now count toward hook landing
  - preserved HTML headings, text blocks, and actions now contribute to comparison counts
  - section-kind inference now sees descendant hooks and text samples, not just the top wrapper text
- The refreshed brutalist comparison is now much closer to preview truth:
  - browser headings `13` vs Elementor `14`
  - browser text blocks `43` vs Elementor `40`
  - browser actions `6` vs Elementor `9`
  - missing section kinds reduced from `services` to `marquee`
- Updated deduction:
  - a meaningful chunk of the previous deficit was measurement loss, not converter loss
  - the remaining high-signal problems are now clearer:
    - hero hook/text carryover is still incomplete around `para-bg`, `scroll-indicator`, and `cta-btn`
    - marquee is still not landing as its own interpreted top-level kind
    - studio semantic text carryover is still thinner than browser truth
    - `nav-link` and `stat-num` are still missing as landed source classes

## Converter Pass Refresh

- A follow-up converter-side pass was applied after the preview-truth comparison cleanup.
- What changed globally:
  - section classification now sees broader marquee structure signals instead of relying only on obvious class names
  - marquee item extraction can now recover repeated strip labels from ticker text even when there are no clean `.item` children
  - hero CTA source ids/classes now land on the emitted native button surface
  - hero visual subtree hooks now land on the emitted hybrid visual wrapper/widget
  - marquee subtree hooks now land on the emitted marquee wrapper/widget
  - nav rebuild now preserves original `nav-link` classes on emitted anchors
- What improved on this brutalist sample:
  - `marquee` now lands as a real interpreted top-level kind
  - missing hook set narrowed:
    - removed `cta-btn`
    - removed `marquee`
    - removed `nav-link`
  - current missing hooks in the browser-vs-V2 comparison are now:
    - ids: `cursor-dot`, `cursor-ring`, `para-bg`, `scroll-indicator`
    - classes: `stat-num`
- Updated deduction:
  - this was a real converter gain, not just an audit-model gain
  - the highest-value remaining global gaps are now:
    - deeper hero decorative hook landing for non-content nodes like background labels and scroll indicators
    - stronger stat hook propagation for classes like `stat-num`
    - thinner studio semantic text carryover than browser truth

## Hook-Coverage Refresh

- A broader global hook-propagation pass was applied after the decorative-hero/stat review.
- What changed globally:
  - hero root and hybrid visual wrappers now carry broader hero-subtree hook aliases, not only narrower CTA/visual hooks
  - feature/bento native templates now render extracted generic stat pairs as native stat cards
  - stat value/label widgets now carry semantic aliases like `stat-num` and `stat-label`
  - source-driven cursor behavior is now emitted as real global-setup markup and then hydrated by runtime JS instead of living only inside script-created nodes
- What improved on this brutalist sample:
  - targeted verifier selector coverage is now `1.0`
  - browser-vs-V2 comparison missing ids are now cleared
  - browser-vs-V2 comparison missing classes are now cleared
  - current browser-vs-V2 native widget ratio is `0.7222`
- Updated deduction:
  - the main remaining brutalist gap is no longer hook loss
  - the remaining deficits are now semantic-density gaps:
    - hero still under-recovers browser-truth text/actions in the role-gap model
    - studio still under-recovers browser-truth text density
  - this means the next global fixes should move from hook propagation to broader role recovery and section-text interpretation, not more selector alias tuning

## Role-Recovery Refresh

- A broader semantic text-role pass was applied after the hook-coverage cleanup.
- What changed globally:
  - hero payloads now keep multiple `hero_text_blocks` instead of only one surviving body paragraph
  - short but meaningful support/meta copy can now be recovered more broadly by the text extractor
  - generic feature/bento card payloads now keep more distinct text blocks while filtering duplicate title/action text
  - native feature/bento templates now render all recovered text blocks instead of only the first body line
  - browser-vs-V2 normalization now classifies `global-setup` separately, so hidden source-document-title text no longer pollutes hero comparisons
- What improved on this brutalist sample:
  - browser-vs-V2 missing hooks remain fully cleared
  - browser-vs-V2 kind list is now cleaner:
    - browser kinds: `hero`, `marquee`, `gallery`, `studio`, `contact`, `footer`
    - elementor kinds: `global-setup`, `nav`, `hero`, `marquee`, `gallery`, `studio`, `contact`, `footer`
  - the old false hero action-gap is gone
  - hero role-gap improved from browser text `6` vs Elementor `1` to browser text `6` vs Elementor `3`
- Updated deduction:
  - this was a real semantic improvement, not only an audit-model cleanup
  - the remaining high-signal gaps are now narrower and clearer:
    - hero still under-recovers some browser-truth support copy
    - studio still lands roughly half of browser-truth text density
    - preserve-heavy output is still a broader ceiling because Elementor HTML widgets are still `10` while the browser model estimates `8` likely behavior islands
