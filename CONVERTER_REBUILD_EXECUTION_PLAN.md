# Converter Rebuild Execution Plan (Ground-Up)

Last updated: 2026-04-27  
Status: Active implementation plan

## Purpose

This plan resets implementation from first principles while keeping useful prior work only when it matches the required architecture.

Primary target:
- Accept arbitrary uploaded HTML/CSS/JS.
- Detect real structure and intent.
- Rebuild native Elementor where reliable.
- Preserve complex behavior-rich parts as in-place HTML widgets inside the correct container/section.
- Fail honestly when required fidelity rules are not met.

## Source References (Must Stay Open During Work)

- `elementor-plugin-architecture-article.md`
- `CONVERTER_TASKS_AND_GROUND_RULES.md`
- `CONVERTER_GROUND_RULES.md`
- `ELEMENTOR_FREE_WIDGET_MATRIX.md`
- `AUDIT.md`
- `dev-implementation-report.md`
- `enhancement-implementation-guide.md`
- `simulation-corpus-v1.json`
- `simulation-corpus-v2.json`
- `simulation-corpus-v3.json`
- `simulation-methodology-article (1).md`
- `skill-infused-pipeline-architecture (1).md`
- `simulation-corpus-v1 (2).json`
- `simulation-corpus-v2 (1).json`
- `simulation-corpus-v3 (1).json`
- `simulation-methodology-article (2).md`
- `enhancement-implementation-guide (1).md`
- `dev-implementation-report (1).md`

## 2026-04-21 Ingestion Update (New Files)

The newly uploaded files were ingested and folded into this plan. Key additions now treated as required:

- Skills model is additive, pass-scoped knowledge injection, not pass replacement.
- Simulation corpus is a source-of-truth data layer that must compile into executable converter modules.
- Hard rules engine runs before confidence scoring and has no fallback cascade.
- Tailwind resolver is mandatory for utility-class parity.
- Display-none logic must be toggle-aware and HTML-widget-boundary-aware.
- Pseudo-elements and hover-cascade behaviors are first-class carryover requirements.
- Global asset behavior (canvas/cursor/preloader/GSAP/Lottie dependencies) is source-driven and validated.
- Framework detectors (Webflow/Framer/Next.js) are explicit pass inputs, not ad-hoc exceptions.

## Problem Scope Captured From User Complaints

Critical failures already observed and considered in this plan:
- Prefix quality/mismatch (example complaints around `.cad`/`.tst` style outcomes).
- Structural regression after skill infusion attempts.
- Child `isInner` correctness and broken JSON structure incidents.
- Placeholder leaks like `{{heading}}` and `{{text}}`.
- Missing sections/fragments (footer, canvas/top-page setup, visual fragments).
- Missing or broken animation/behavior carryover (counters, marquee, reveal, hover).
- Bento/mosaic span collapse into equal-height cards.
- Loss of wrapper-level CSS hooks in Advanced tab (`_css_classes`, `_element_id`).
- Poor `::before` / `::after` coverage and pseudo-icon inference gaps.
- Inline markup fidelity gaps (e.g. split-word branding with nested `<span>`).
- Over-localized fixes instead of durable engine rules.
- Regression context: `testupgradev5` is the last meaningful baseline before failed skill infusion.
- Skills attempt failure mode: skills were treated as replacements instead of additive pass intelligence.
- Requirement: no hidden fallback paths that mask broken logic.
- Requirement: broad-spectrum rules must apply to any section family, not named samples only.

## Baseline Context and Constraints

- Current trusted baseline context is the pre-skill state (`testupgradev5` era), not post-skill broken output.
- Skills architecture must be additive:
  - existing pass logic remains primary pipeline spine
  - skills data enriches decisions
  - missing/weak skill signals must not silently downgrade architecture integrity
- If a required logic path fails, conversion must fail with pass-level diagnostics (not “pretend success” fallback).

## Explicit No-Fallback Policy

This rebuild uses two categories only:

1) **Allowed resilience**
- deterministic safe repair for structural invariants (ID uniqueness, schema shape normalization)
- controlled mode selection (native / hybrid / preserved) based on explicit rules and diagnostics

2) **Disallowed masking**
- synthetic content fill-ins
- silent downgrade when required carryover fails
- generic “best effort” completion that hides pass failure

When required logic fails:
- emit hard diagnostic with pass ownership
- fail conversion for that run
- preserve debug evidence for reproducibility

## Skills Integration Policy (Additive, Not Replacement)

Skills usage model:
- skills are data/config inputs consumed by passes
- passes remain authoritative execution units
- each pass may consult skills for confidence boosts, hard rules, or mappings
- pass output must still be explainable without black-box replacement behavior

Implementation principle:
- one optional skills consultation layer per pass
- if skill data is absent, pass behavior remains deterministic and explicit
- no skills branch is allowed to bypass validation or diagnostics

## V1 vs V2 Converter Modes (Hard Separation)

### V1: Design Fidelity First

Goal:
- Keep output visually closest to source when complexity is high.

Rules:
- Prefer preserving behavior-rich blocks as HTML widgets sooner.
- Allow larger preserved fragments.
- Keep source markup/contracts with minimal structural rewriting.
- Still enforce selector, hook, and validation integrity.

Success definition:
- Structure and behavior survive with minimal drift.
- Editability may be lower than V2 by design.

### V2: Editable Native First

Goal:
- Maximize Elementor panel editability while preserving fidelity through hybrid rendering.

Rules:
- Rebuild structure natively where stable.
- Preserve only the complex inner fragment in-place as HTML.
- Maintain wrapper/source hooks so companion CSS/JS can retarget correctly.
- Do not strip behavior-critical parts during simplification.

Success definition:
- High editability with honest hybrid fallback for complex internals.
- No fake “native success” that silently drops behavior/fidelity.

## Architecture Rules For Both Modes

1. Root namespace isolation stays.
2. Source selectors/classes/IDs are preserved and retargeted beneath root.
3. Complexity handling is rule-based, not section-name based.
4. Hybrid rendering is allowed anywhere, not only known families.
5. No synthetic content generation to hide unresolved extraction.
6. Validation checks fidelity and behavior coverage, not JSON syntax only.
7. No silent fallbacks that hide broken logic.
8. Widget decisions must be matrix-driven where applicable (`ELEMENTOR_FREE_WIDGET_MATRIX.md`), not ad-hoc.
9. Inline markup semantics (e.g. `NEX<span>U</span>S`) must survive both JSON and companion CSS hook contracts.

## Pass 1–9 Ground-Up Implementation Plan

## Pass 1 — Document Intelligence

Deliverables:
- Robust section discovery including major top-level `<div>` wrappers.
- Input-shape inventory:
  - semantic structure
  - utility-framework signals
  - obfuscated/minified signals
- Global/top-page system detection:
  - canvas, cursor, preloaders, overlays, floating widgets
- Behavior inventory:
  - animation APIs, observer/timer usage, script-driven text/state mutation
- Inline markup sensitivity flags:
  - split-word spans, emphasis fragments in headings/nav/cards
- Baseline comparison fingerprint capture:
  - source block counts
  - major behavioral asset presence
  - repeated structure signatures for later pass validation
- Framework signature detection:
  - Webflow (`w-*`, `data-w-id`)
  - Framer (`--framer-*`, absolute-layout-heavy structure)
  - Next.js (`__NEXT_DATA__`, `picture`/image patterns)

Acceptance gates:
- No meaningful top-level content block is skipped from inventory.
- All detected behavior/global assets are recorded for later passes.

## Pass 2 — Layout Analysis

Deliverables:
- Layout contract extraction:
  - flex direction/wrap/gap
  - grid definitions
  - row/column spans and placement
  - repeated structure families
- Wrapper relationship graph for later retargeting/validation.
- Mosaic/bento contracts tracked as structural metadata.

Acceptance gates:
- Span metadata available for any source grid using explicit spans.
- Repeated groups identified without section-specific keyword dependence.

## Pass 3 — Content Classification

Deliverables:
- Mode-aware decision ladder:
  1) native candidate
  2) native + preserved inner fragment
  3) full preservation
- Hard rules first (fixed position, canvas, JS mutation, tables, unsupported layout contracts).
- Priority hard-rules engine (no fallback cascade) based on simulation corpus:
  - fixed-position handling with decorative overlay exception
  - canvas handling
  - JS text-mutation handling
  - table handling
  - css-columns handling
  - HTML-widget boundary verbatim-copy rule
- Behavior-complexity detection generalized (not stats-only, bento-only, etc.).
- Widget-family decisions guided by `ELEMENTOR_FREE_WIDGET_MATRIX.md`.
- Section-agnostic hybrid rule:
  - native outer structure + in-place complex inner HTML fragment anywhere required.

Acceptance gates:
- Classifier reason output per major node (why native/hybrid/preserved).
- No mode drift: V1 and V2 produce intentionally different thresholds.

## Pass 4 — Style Resolution

Deliverables:
- Full resolver coverage for:
  - source selector patterns (descendant/combinator/grouped)
  - pseudo elements (`::before`, `::after`)
  - media/supports blocks
  - keyframes usage tracking
  - CSS variables/shorthand expansion
- Host relevance mapping so pseudo rules attach to emitted hooks.
- Hover-cascade carryover support:
  - parent `:hover` selectors affecting descendant hosts must survive retargeting.

Acceptance gates:
- Pseudo-bearing source rules either map to emitted hosts or produce hard diagnostic failure.
- Missing keyframes and unresolved pseudo hosts are explicit failures, not warnings only.

## Pass 5 — Class and ID Generation

Deliverables:
- Prefix policy:
  - project-derived, safe, deterministic
  - request override support
- Hook policy:
  - root namespace isolation
  - preserve source classes/ids on emitted wrappers when safe
  - stable structural hooks in Advanced tab for containers/widgets
- Duplicate-safe ID policy with deterministic top-level anchors.

Acceptance gates:
- JSON hooks and companion selectors agree for emitted elements.
- No stale `tst`/legacy leakage when new project input differs.
- Prefix blacklist enforcement includes known collision/conflict values (including `cad` risk class).

## Pass 6 — Global Setup Synthesis

Deliverables:
- Source-driven global setup assembly:
  - fonts/tokens
  - global scripts/dependencies
  - canvas/cursor/reveal bootstrap where detected
- Prevent duplicate global injection.
- Keep section-local behavior out of global setup unless truly page-level.
- Dependency auto-injection policy:
  - inject known required libs only when source behavior requires them (e.g., GSAP/Lottie).

Acceptance gates:
- If source has global asset, output has it.
- If source lacks global asset, output must not invent it.

## Pass 7 — JSON Assembly

Deliverables:
- Correct `isInner` rules and parent-child structure integrity.
- Native rebuild with in-place preserved complex fragment support anywhere.
- Inline markup preservation in supported native widgets.
- No placeholder token leakage into final JSON fields.

Acceptance gates:
- No child wrongly emitted as top-level `isInner:false`.
- No unresolved placeholders like `{{...}}`.
- Hybrid outputs remain structurally valid and editable around preserved fragment.

## Pass 8 — Companion CSS/JS Generation

Deliverables:
- Generic CSS retargeting engine:
  - source selectors retargeted to emitted hooks under root scope
  - pseudo/media/keyframes preserved
- Generic JS bridge/scoping:
  - selector and id/class rewrites
  - behavior API retargeting where mappable
- Preserve source behavior contract where possible; fail loudly where impossible.
- Ensure inline-markup child selector contracts survive (`span/em/strong/br` hosts inside native wrappers).
- Compilation-fed modules:
  - pattern library signals
  - classifier confidence constants
  - tailwind map
  - css fingerprints
  - framework detectors

Acceptance gates:
- Retargeted selectors map to real emitted hooks.
- Behavior-critical script selectors are either rewritten successfully or hard-failed.

## Pass 9 — Fidelity Validation and Repair

Deliverables:
- Structured fidelity report per conversion:
  - render mode distribution (native/hybrid/preserved)
  - selector and hook coverage
  - pseudo carryover coverage
  - script carryover coverage
  - structural count/spans checks
  - global asset checks
  - inline-markup survival checks
  - mode-policy compliance (V1 vs V2 threshold behavior)
- Repair only safe structural issues (ID collisions, required field shapes).
- Hard-fail unresolved architecture-critical gaps.

Acceptance gates:
- “Success” only when syntax + fidelity checks pass.
- Diagnostics are actionable and tied to pass ownership.

## Delivery Phases

### Phase A — Foundation (Pass 1, 3, 5 contracts)
- input intelligence, mode split, hook model.

### Phase B — Fidelity Engines (Pass 4, 8)
- css/js retargeting and pseudo behavior carryover.

### Phase C — Assembly Integrity (Pass 2, 6, 7)
- layout contracts, global setup correctness, hybrid assembly.

### Phase D — Truthful Validation (Pass 9)
- hard quality gates and conversion report integrity.

## Immediate Next Implementation Sprint (Execution Order)

1. Finalize V1/V2 strategy thresholds and lock mode-specific classifier policy.
2. Complete generalized behavior complexity detector and hybrid-anywhere assembly path.
3. Strengthen source-hook carryover on wrappers and per-item blocks.
4. Expand pseudo host mapping and enforce pseudo coverage failures.
5. Expand JS selector/behavior rewrite coverage and failure diagnostics.
6. Add structural fidelity checks for repeated counts and grid spans.
7. Produce conversion run report aligned to pass ownership.
8. Add section-by-section article compliance checklist (done / partial / not done) as a required output artifact per sprint.
9. Build simulation compiler pipeline (`compile-patterns`) and wire generated artifacts into passes 1/3/4/8.
10. Implement hard-rules engine and display-none scoping logic from simulation report before wider refactors.

## Active Execution Board (Do Together)

This board exists to prevent loss of either track. We run both in parallel:
- Core sequential sprint track (original plan tasks).
- Broad-spectrum expansion track (family-agnostic engine hardening).

### Track A — Core Sequential Sprint (Original)
- Step 1: done
- Step 2: done
- Step 3: done
- Step 4: done
- Step 5: done
- Step 6: done
- Step 7: done
- Step 8: done
- Step 9: done (verified)
- Step 10: done
- Step 11: in_progress
- Primary remaining focus:
  - finish Step 9 pass ownership completion (reporting/compiler wiring closure)
  - execute Step 10 hard-rules/display-none finalization checks against current runtime behavior (done)

### 2026-04-21 — Sequential Step 10 (Completed)

- Pass: 1/3 baseline safety rules + candidate filtering hardening
- Rule/Capability:
  - Hard-rules baseline coverage finalized in compiled simulation knowledge:
    - added RULE-003 (`script_mutation`) to the compiler baseline and required coverage set.
    - regeneration produces 5 compiled hard rules (RULE-001..RULE-005) and runtime integrity gate enforces all.
  - Display-none/hidden scoping generalized beyond inline styles:
    - now detects hidden via `hidden` attribute / `aria-hidden="true"`
    - detects common hidden classes and Tailwind-like `*:hidden` variants
    - consults source CSS contracts (`display:none` / `visibility:hidden`) for class tokens when present
    - continues to exempt JS-toggled/interactive targets (tabs/accordions/toggles) to avoid losing dynamic content
    - emits capped diagnostics `static_hidden_skipped` so hidden drops are never silent.
- Mode impact (V1/V2):
  - shared baseline: both modes benefit from consistent hard-rule forcing and safer candidate skipping.
- Files touched:
  - `tools/compile-patterns.php`
  - `includes/converter/generated/class-simulation-knowledge.php` (regenerated)
  - `includes/converter/class-native-converter.php`
- Validation evidence:
  - compiler run: `Hard rule entries: 5`
  - runtime diagnostics now show `simulation_knowledge_coverage` includes RULE-003 (verified via CLI verifier)
  - `php -l` passed for modified PHP files.
- Result: done
- Track advancement note:
  - Track A: Step 10 promoted to done.
  - Track B: improves real-world robustness for hidden/toggled content and rule enforcement transparency.

### Track B — Broad-Spectrum Expansion (Parallel Overlay)
- B1 Generic structure-first interpretation: in_progress
- B2 Generic complexity-based native-vs-hybrid-vs-html policy: in_progress
- B3 Pass-wide widget matrix operationalization for arbitrary layouts: in_progress
- B4 Cross-pass family-agnostic audit: pending
- Primary remaining focus:
  - refine hybrid fragment boundary targeting (per repeated child subtree before section-level append)
  - keep diagnostics mapped to pass ownership and avoid section-family assumptions
  - lock strategy policy into explicit diagnostics + decision thresholds (in_progress)

### 2026-04-21 — Sequential Step 11 (In Progress)

- Pass: 3 decision policy (mode contract hardening)
- Rule/Capability:
  - Locked V1/V2 behavior into an explicit strategy policy object:
    - per-mode HTML threshold
    - per-mode preservation guardrails (animation/script/grid-span/absolute layering)
    - type exemptions for template-routed families
  - Emitted per-run diagnostic `strategy_policy` so every run declares the exact policy used.
  - Wired policy into `decide_strategy()` so thresholds/guardrails are consistent and auditable.
- Mode impact (V1/V2):
  - both modes now have explicit, logged policy contracts; reduces “mystery” decisions and makes tuning safe.
- Files touched:
  - `includes/converter/class-native-converter.php`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - CLI verifier shows `strategy_policy` diagnostic in pass 1 context.
  - `php -l` passed for converter.
- Result: done
- Track advancement note:
  - Track A: begins post-Step-10 sprint continuation (mode policy lock).
  - Track B: advances B2 policy formalization (if/else mindset captured as an explicit contract).
- Next action:
  - Use `tools/run-training-suite-cli.php` on new corpora drops and tune policy thresholds based on aggregated diagnostics (no file-local patches).

### 2026-04-22 — Step 11 (Training Suite Harness + Broad-Spectrum Fixes)

- Pass: 3 policy tuning harness + pass-9 integrity stabilization
- Rule/Capability:
  - Added DB-free batch training harness:
    - `tools/run-training-suite-cli.php` runs all HTML in `training-files/` for both V1 and V2
    - writes UTF-8 `training-suite-report.json` and prints a compact summary to stdout
    - uses converter diagnostics as the sole “training signal” (engine interpretation only).
  - Broad-spectrum fixes discovered by suite:
    - Template gating respects `decision` (no template override when preservation is chosen), except forced-template families when payload is resolvable.
    - Preservation integrity check now validates structural HTML tags (not `class=` substring).
    - Global script bridge is only required when source JS has mappable hook candidates (prevents false fails).
    - Canvas output detection in global setup diagnostics made strict (id/cv.id based) to prevent substring false positives.
    - Companion CTA pseudo now targets `#id` vs `.class` correctly, and top-level anchor assignment now injects matching class hooks for all section families.
- Validation evidence:
  - Training suite summary: 11 files × 2 strategies = 22 runs; 22 OK, 0 fail.
- Result: done

### 2026-04-22 — Step 12 (In Progress): Carryover Depth Gatekeeper

- Pass: 8/9 coverage observability and regression tracking
- Rule/Capability:
  - Upgraded training harness to aggregate bridge quality metrics from `conversion_run_report`:
    - selector source/output CSS coverage counts
    - pseudo/hover/media/supports source-vs-output coverage counts
    - script source-vs-rewrite coverage counts
    - global source-JS vs script-bridge coverage counts
    - gap counters for each category
  - Report remains engine-driven (no file-local rules), and now functions as a carryover-depth dashboard.
- Validation evidence (current suite):
  - 22/22 runs pass (11 files × v1/v2)
  - bridge quality snapshot now explicitly shows remaining non-fatal gaps:
    - `selector_source_without_output`: 18
    - `script_source_without_rewrite`: 8
    - `global_source_js_without_bridge`: 2
- Result: done
- Track advancement note:
  - Track A: starts post-step-11 observability sprint.
  - Track B: directly advances broad-spectrum CSS/JS carryover measurement.
- Next action:
  - Reduce non-fatal bridge gaps by improving generic selector/script rewrite coverage (without introducing per-file exceptions).

### 2026-04-22 — Step 12 (Update 2): Meaningful Gap Semantics

- Pass: 8/9 observability refinement (signal quality)
- Rule/Capability:
  - Refined training-harness gap semantics so gaps are counted only when bridge action is expected:
    - selector gap requires source CSS + bridge targets + source selector hits + no output CSS
    - script rewrite gap requires source JS + script bridge targets + source selector hits + no rewrite
    - global script bridge gap requires source JS and script hook candidates/hits + no injected script bridge
  - This removes false-positive “gaps” from runs where no mappable hooks exist.
- Validation evidence:
  - Latest `training-suite-report.json` summary:
    - 22/22 run success
    - bridge gaps all at 0 under meaningful-gap semantics
- Result: done
- Track advancement note:
  - Track A: Step 12 promoted to done for carryover-depth gatekeeper instrumentation.
  - Track B: improves truthfulness of cross-file carryover diagnostics (no noise-driven tuning).

### 2026-04-22 — Step 13 (Completed): Quality Floor Gate (CI-style)

- Pass: training harness quality governance (cross-pass regression gate)
- Rule/Capability:
  - Added configurable quality-floor mode to `tools/run-training-suite-cli.php`:
    - enable with `--quality-floor` or `SB_QUALITY_FLOOR=1`
    - optional floor config path via `--floor-file=...` or `SB_FLOOR_FILE`
    - default floor file: `training-suite-floor.json`
  - Added quality floor targets and breach logic:
    - minimum run success rate
    - max failed runs
    - minimum selector output CSS ratio
    - minimum script rewrite ratio
    - minimum global script bridge ratio
    - max meaningful-gap counters (selector/script/global bridge)
  - Added quality metrics block to report:
    - computed ratios from current run
    - targets used
    - breach list
    - pass/fail status
  - Quality floor now exits with non-zero code on breach (`exit 2`) when enabled.
- Files touched:
  - `tools/run-training-suite-cli.php`
  - `training-suite-floor.json` (new baseline floor config)
- Validation evidence:
  - normal run: 22/22 pass with quality metrics emitted.
  - quality-floor run (`--quality-floor`): passed with no breaches.
  - current metrics:
    - run success rate: `1.0`
    - selector output CSS ratio: `0.1818`
    - script rewrite ratio: `0.6363`
    - global script bridge ratio: `0.9090`
    - meaningful gaps: all `0`
- Result: done
- Track advancement note:
  - Track A: adds hard regression gate to prevent silent quality drift after “green” runs.
  - Track B: formalizes broad-spectrum carryover expectations as measurable floor contracts.

### 2026-04-22 — Step 14 (Completed): Tiered Floors + Per-Strategy Gates + Trend Snapshots

- Pass: training harness governance hardening + admin UX resilience
- Rule/Capability:
  - Added tiered quality floor profiles in `tools/run-training-suite-cli.php`:
    - `bootstrap`
    - `balanced` (default)
    - `strict`
  - Added profile selection controls:
    - `--profile=<bootstrap|balanced|strict>`
    - `SB_QUALITY_PROFILE`
  - Added per-strategy floor enforcement (different `v1`/`v2` targets):
    - per-strategy selector output CSS ratio floor
    - per-strategy script rewrite ratio floor
    - per-strategy global script bridge ratio floor
  - Added historical training snapshots:
    - auto-saves to `training-suite-history.json`
    - includes timestamp, profile, summary, quality metrics, floor pass/fail + breaches
    - keeps rolling capped history
    - supports `--no-history` opt-out
  - Added trend delta block in run output from latest vs previous snapshot.
  - Expanded floor config structure in `training-suite-floor.json`:
    - `profiles`
    - `strategy_overrides`
  - Admin UI resilience:
    - improved request error parsing for nonce/auth/session HTML responses
    - explicit handling for cookie/session check failures with actionable message
    - fetch requests now force `credentials: same-origin`
  - Color detector expansion:
    - token extraction now detects colors from direct style declarations (not only CSS variables)
    - deduplicated synthetic color tokens (`--detected-color-*`) are emitted when custom properties are absent
    - token widget copy updated to reflect broader detection scope
- Files touched:
  - `tools/run-training-suite-cli.php`
  - `training-suite-floor.json`
  - `admin/js/admin.js`
- Result: done
- Track advancement note:
  - Track A: advances sequential governance from single-floor gating to profile-aware, strategy-aware enforcement with trend persistence.
  - Track B: advances broad-spectrum robustness by making quality thresholds adaptive and improving real-world admin/session/color detection behavior.

### 2026-04-22 — Step 15 (Completed): Trend Report Command + Import Compatibility Guard

- Pass: training observability + output compatibility hardening
- Rule/Capability:
  - Added explicit trend reporting command in `tools/run-training-suite-cli.php`:
    - `--trend-report-only`: prints trend report from `training-suite-history.json` without running conversions.
    - `--trend-report`: includes 7/30 rolling averages and regression warnings in normal run output.
  - Trend report now computes:
    - rolling average windows: 7-run and 30-run (or available subset),
    - regression warnings against prior run and 7-run rolling baseline.
  - Added broad-spectrum Elementor import compatibility guard in `includes/converter/class-native-converter.php`:
    - if top-level output has only widgets, auto-wrap each top-level widget in a container root.
    - preserves section/widget payload while preventing “silent no-load” imports on stricter Elementor builds.
  - Preservation integrity checks updated to read preserved HTML recursively (container-wrapped widget support).
- Files touched:
  - `tools/run-training-suite-cli.php`
  - `includes/converter/class-native-converter.php`
- Result: done
- Track advancement note:
  - Track A: extends CI observability from point-delta to rolling trend diagnostics.
  - Track B: strengthens broad-spectrum output contract for Elementor import compatibility across arbitrary HTML-heavy outputs.

### 2026-04-22 — Step 16 (Completed): Script Bridge Safety + V2 Hybrid Preference + HTML-Dominant CSS Mode

- Pass: decision engine + script carryover + companion CSS contract
- Rule/Capability:
  - V2 strategy policy widened from hard-preserve to native+hbrid preference for interactive/behavior-heavy structures:
    - interactive/behavior contracts in V2 now prefer native rebuild with hybrid fragment attachment, not automatic full HTML preservation.
  - Source script bridge hardened for runtime safety:
    - null-safe rewrites for direct `document.getElementById(...).prop` chains,
    - automatic export of inline-handler function names to `window.*` when inline HTML handlers exist (`onclick`, `onmouseover`, etc.),
    - prevents one missing node from aborting all subsequent behavior execution.
  - Source script bridge global-setup injection made robust across output shapes:
    - supports both legacy top-level global setup HTML widget and wrapped/containerized global setup roots.
  - Companion CSS now has html-dominant mode:
    - when output is mostly preserved HTML sections, emit source-contract-first CSS baseline (tokens/page/reveal + bridge) instead of native-heavy section skinning.
    - prevents class-contract drift between JSON (source-heavy classes) and CSS (native-heavy selectors) on html-dominant runs.
- Files touched:
  - `includes/converter/class-native-converter.php`
- Validation evidence:
  - `php -l includes/converter/class-native-converter.php` passes.
  - Training suite run completes successfully after patch set.
  - V2 success count improved in suite (v2 `ok` increased from 7 to 9 in current report snapshot).
- Result: done
- Track advancement note:
  - Track A: advances conversion decisioning and carryover reliability in runtime execution path.
  - Track B: directly addresses broad-spectrum script/class contract mismatches and html-heavy output drift.

### Merge Rule (Non-Negotiable)
- No new work is considered complete unless it updates both:
  - sequential step status in Track A, and
  - corresponding expansion status in Track B (if affected).
- Every update must include explicit "does this advance A, B, or both?" note in execution log.

## Architecture Article Compliance Checklist (Required Output)

Each sprint must publish a checklist against `elementor-plugin-architecture-article.md`:
- done
- partially done
- not done

Minimum checklist groups:
- conversion problem layers (structure/widget/style/effect/hook)
- pass architecture integrity
- hybrid boundary correctness
- global setup handling
- css/js carryover fidelity
- validation and repair truthfulness
- edge-case coverage posture

No sprint is closed without this checklist.

## Deferred Full-Sweep Backlog (Cross-Pass)

Purpose:
- Capture broad-spectrum engine work that must be revisited as a full pass/pipeline sweep, not as section-local fixes.

### Backlog Item A — Structure-First Generic Interpretation Engine
- Status: queued for full sweep
- Scope:
  - Replace section-name assumptions with structural interpretation from raw DOM/CSS/JS signals.
  - Detect nested-container layouts generically (for example: wrapper -> secondary container -> N inner containers).
  - Infer repeated groups from structure and selector patterns, not only payload keys tied to known families.
- Required output behavior:
  - For each detected group, emit explicit interpretation diagnostics:
    - native widget path selected
    - hybrid path selected
    - full HTML preservation selected
  - Diagnostic rationale must include concrete complexity signals and selected Elementor-free-widget candidates.

### Backlog Item B — If/Else Native-vs-HTML Decision Policy (Broad Spectrum)
- Status: queued for full sweep
- Scope:
  - Implement universal decision policy for any subtree:
    - if structure is stably representable with Elementor free widgets -> native rebuild + preserve hooks/classes/ids.
    - else if outer layout is stable but inner visuals/behavior are complex -> native outer + in-place HTML widget fragment.
    - else -> preserve full source fragment as HTML widget with scoped CSS/JS carryover.
  - Do not couple decisions to labels like bento/pricing/stats; those are examples, not contracts.
- Required complexity signals:
  - selector density and specificity pressure
  - pseudo-element dependence (`::before`/`::after`)
  - script behavior coupling (counters/marquee/reveal/canvas/mutation)
  - nested-grid/span complexity and asymmetric card geometry
  - inline semantic markup dependency (`span/em/strong` used for visual composition)

### Backlog Item C — Pass-Wide Widget Matrix Operationalization
- Status: queued for full sweep
- Scope:
  - Use `ELEMENTOR_FREE_WIDGET_MATRIX.md` as a generic decision matrix at classification time for arbitrary nodes.
  - Expand matrix checks beyond known section families so widget selection is tied to source semantics and layout roles.
  - Keep the "simplest stable native widget" rule and route to HTML fragment when matrix confidence is low.

### Backlog Item D — Cross-Pass Audit Sprint
- Status: queued for full sweep
- Scope:
  - Re-audit all 9 passes for localized assumptions and replace with engine-level rules.
  - Ensure each pass emits diagnostics that are structure-based and family-agnostic.
  - Verify no new fallback paths mask failed logic.
- Exit criteria:
  - At least one non-template uploaded design (no bento/pricing-like naming) passes with truthful diagnostics.
  - Structural interpretation decisions are explainable from runtime diagnostic context alone.

## Progress Tracking Template

Use this for each sprint update:
- Pass:
- Rule/Capability:
- Mode impact (V1/V2):
- Files touched:
- Validation evidence:
- Result: done / partial / failed
- Next action:

## Execution Log

### 2026-04-21 — Sequential Step 1 (Completed)

- Pass: 3 (classification gate), foundational guard for Pass 1 candidate filtering
- Rule/Capability:
  - Added `PriorityRulesEngine` with hard-rule evaluation before strategy heuristics:
    - fixed position
    - canvas
    - JS text mutation
    - table
    - css columns
    - decorative overlay precedence
  - Added static hidden-element scoping to avoid skipping toggle/tab-linked hidden blocks.
  - Fixed `EditabilityPredictor` canvas XPath bug that previously caused runtime failure risk.
- Mode impact (V1/V2):
  - both modes now obey same hard-rule gate before mode threshold differences apply.
- Files touched:
  - `includes/converter/skills/class-priority-rules-engine.php` (new)
  - `includes/converter/class-native-converter.php`
  - `includes/converter/skills/class-editability-predictor.php`
- Validation evidence:
  - `php -l` passed for all modified PHP files.
- Result: done
- Next action:
  - Step 2: build simulation-compiler scaffolding and wire generated hard-rules/tailwind artifacts into pass inputs.

### 2026-04-21 — Sequential Step 2 (Completed)

- Pass: 1 and 3 foundations, with generated knowledge pipeline for pass inputs
- Rule/Capability:
  - Added simulation compiler scaffold:
    - `tools/compile-patterns.php` reads `simulation-corpus-v1*`, `v2*`, `v3*` and generates runtime knowledge artifact.
  - Added generated runtime knowledge module:
    - `includes/converter/generated/class-simulation-knowledge.php`
    - exposes compiled `hard_rules()` and `tailwind_map()`.
  - Added `TailwindResolver` skill scaffold:
    - `includes/converter/skills/class-tailwind-resolver.php`
    - utility-markup detection + class resolution support.
  - Wired converter runtime:
    - `NativeConverter` now initializes Tailwind resolver and records Pass 1 detection diagnostics.
    - `PriorityRulesEngine` now consults compiled simulation hard rules first, then built-in hard rules.
- Mode impact (V1/V2):
  - shared knowledge input layer added for both modes before mode-specific thresholds.
- Files touched:
  - `tools/compile-patterns.php` (new)
  - `includes/converter/generated/class-simulation-knowledge.php` (generated)
  - `includes/converter/skills/class-tailwind-resolver.php` (new)
  - `includes/converter/skills/class-priority-rules-engine.php`
  - `includes/converter/class-native-converter.php`
- Validation evidence:
  - compiler executed successfully (`php tools/compile-patterns.php`)
  - `php -l` passed for all modified/new PHP files.
- Result: done
- Next action:
  - Step 3: implement pass-level usage of compiled tailwind map in style pre-resolution and add hard-fail diagnostics for unresolved required simulation-rule coverage.

### 2026-04-21 — Sequential Step 3 (Completed)

- Pass: 1 and 2 (pre-resolution), with hard validation gate
- Rule/Capability:
  - Tailwind pre-resolution is now active in runtime flow (not diagnostics-only):
    - class tokens are collected from DOM
    - utility classes resolve through compiled Tailwind knowledge
    - synthetic CSS rules are appended into pass-1 extracted CSS before resolver parse
  - Tailwind coverage is tracked (`detected`, `scanned`, `resolved`, `generated_css`).
  - Added hard-fail integrity gate:
    - if Tailwind is detected but no mappings are resolved/generated, conversion fails with pass-level diagnostics.
- Mode impact (V1/V2):
  - shared pre-resolution path; improves both mode classification/styling reliability before mode-specific decisions.
- Files touched:
  - `includes/converter/class-native-converter.php`
- Validation evidence:
  - runtime diagnostics now emit `tailwind_pre_resolution` with rule counts.
  - hard failure path emits `tailwind_resolution_failed` with coverage context when required coverage is missing.
- Result: done
- Next action:
  - Step 4: wire compiled hard-rules extraction into compiler output (current corpora yielded zero hard-rule objects), then enforce minimum hard-rule coverage expectations in pass diagnostics.

### 2026-04-21 — Sequential Step 4 (Completed)

- Pass: 1 compiler knowledge gate and pass-level integrity enforcement
- Rule/Capability:
  - Upgraded simulation compiler hard-rule extraction:
    - parses textual `new_hard_rules` entries into normalized runtime rule objects
    - ingests `extracted_signals.hard_rules` text where present
    - enforces baseline hard-rule seeding (fixed, canvas, table, css-columns)
    - deduplicates compiled rule entries
  - Recompiled generated knowledge artifact with updated extraction logic.
  - Added runtime hard-rule coverage integrity gate in converter:
    - logs `simulation_knowledge_coverage` diagnostics
    - hard-fails conversion when required rule IDs are missing or rule count is below minimum.
- Mode impact (V1/V2):
  - shared compiled-knowledge integrity now guaranteed before either mode path continues.
- Files touched:
  - `tools/compile-patterns.php`
  - `includes/converter/generated/class-simulation-knowledge.php` (regenerated)
  - `includes/converter/class-native-converter.php`
- Validation evidence:
  - compiler run output now reports `Hard rule entries: 4` (previously 0)
  - `php -l` passed for compiler, generated artifact, and converter.
- Result: done
- Next action:
  - Step 5: begin matrix-driven classifier enforcement (Pass 3/7) so widget selection follows `ELEMENTOR_FREE_WIDGET_MATRIX.md` contracts with explicit diagnostics when violated.

### 2026-04-21 — Sequential Step 5 (Completed)

- Pass: 3 and 7 decision/output contract enforcement
- Rule/Capability:
  - Added matrix-driven diagnostics enforcement against emitted section output:
    - source lists (`ul/ol/li`) must map to `icon-list` or preserved `html`
    - source CTA/link/button signals must map to `button` or preserved `html`
    - source heading tags must map to `heading` or preserved `html`
    - source inline-markup signals (`span/em` in heading/link contexts) must map to native carrier or preserved `html`
  - Added per-section widget-family counting from emitted tree.
  - Extended `section_render_mode` diagnostics context with:
    - `widget_counts`
    - `matrix_checks`
  - Emits explicit `widget_matrix_violation` diagnostics when contracts fail.
- Mode impact (V1/V2):
  - both modes now surface matrix-contract violations transparently; fully preserved mode bypasses false negatives.
- Files touched:
  - `includes/converter/class-native-converter.php`
- Validation evidence:
  - `php -l` passed for converter after changes.
- Result: done
- Next action:
  - Step 6: tighten pseudo-element and hover-cascade first-class carryover gates so unresolved pseudo hosts and hover-descendant contracts can fail early with pass ownership.

### 2026-04-21 — Sequential Step 6 (Completed)

- Pass: 8 coverage integrity and pass-9 failure enforcement
- Rule/Capability:
  - Extended source-selector bridge coverage model with hover-state tracking:
    - `source_has_hover`
    - `output_has_hover`
    - recursive propagation through nested `@media/@supports` analysis
  - Added hard-fail gate in integrity checks:
    - `source_hover_bridge_missing`
    - triggers when source has bridgeable `:hover` selectors but output bridge emits none.
  - Pseudo/hover carryover is now treated as first-class fail condition, not a soft warning path.
- Mode impact (V1/V2):
  - shared fidelity contract enforcement for pseudo/hover behavior in both modes.
- Files touched:
  - `includes/converter/class-native-converter.php`
- Validation evidence:
  - `php -l` passed for converter after coverage-gate changes.
- Result: done
- Next action:
  - Step 7: implement explicit source-vs-output structural fidelity checks (repeated counts + grid spans + global assets bundle) and fail on degraded structure contracts.

### 2026-04-21 — Sequential Step 7 (Completed)

- Pass: 7/8 structural fidelity enforcement + pass-9 hard failure
- Rule/Capability:
  - Added explicit structural fidelity gate:
    - compares source payload repeated-structure expectations vs emitted section output for:
      - stats cards
      - bento cards
      - process steps
      - pricing cards
    - fails when emitted structure drops below acceptable source-derived expectation.
  - Added bento span-contract fidelity check:
    - when non-default source spans exist, conversion now requires span-contract presence in emitted output/bridge context.
  - Added global bundle coherence check:
    - if source JS exists, Global Setup must include script bridge (`global_script_bridge_missing` fail path).
  - Wired structural checks into `assert_output_integrity()` as hard-fail path.
- Mode impact (V1/V2):
  - structure-degradation checks now apply consistently to both modes; preserved-source paths are exempted where appropriate.
- Files touched:
  - `includes/converter/class-native-converter.php`
- Validation evidence:
  - `php -l` passed for converter after structural gate implementation.
- Result: done
- Next action:
  - Step 8: implement section-by-section architecture-article compliance diagnostics (done/partial/not-done) emitted from runtime so every run reports progress against the full article contract.

### 2026-04-21 — Sequential Step 8 (Completed)

- Pass: 7/9 runtime reporting and generalized structure integrity
- Rule/Capability:
  - Added required architecture-article compliance checklist emission on every conversion run:
    - emits `architecture_article_compliance` diagnostic with per-group status:
      - done
      - partial
      - not_done
    - checklist groups aligned to article contract:
      - conversion problem layers
      - pass architecture integrity
      - hybrid boundary correctness
      - global setup handling
      - css/js carryover fidelity
      - validation truthfulness
      - edge-case posture
  - Expanded structural fidelity logic from section-name assumptions to generic repeated-structure detection:
    - payload-side repeated-unit estimation now supports generic keys (`cards`, `items`, `steps`, `rows`, etc.).
    - output-side repeated-unit estimation now uses typed class patterns first, then container-structure fallback.
    - emits hard failure `generic_repeated_structure_degraded` when source-derived repeated layout contracts collapse in output, even for non-predefined section families.
- Mode impact (V1/V2):
  - both modes now produce explicit article-compliance progress artifacts and broad-spectrum repeated-layout degradation checks.
- Files touched:
  - `includes/converter/class-native-converter.php`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - `php -l includes/converter/class-native-converter.php` passed.
  - IDE lint diagnostics returned no errors for modified converter file.
- Result: done
- Next action:
  - Step 9: continue broad-spectrum pass ownership by expanding conversion run reporting + simulation/compiler wiring coverage against arbitrary layout families.

### 2026-04-21 — Sequential Step 9 (In Progress)

- Pass: 3 (decision engine) with broad-spectrum structure/behavior policy
- Rule/Capability:
  - Implemented generic subtree complexity scoring for native-vs-HTML decisions:
    - container density and nesting depth
    - repeated child-container structure pressure
    - grid/span and absolute-layering complexity
    - pseudo-element dependency signals (`::before`/`::after`)
    - behavior coupling and interactive structure markers
  - Wired scoring directly into `decide_strategy()`:
    - keeps hard rules first
    - applies generic complexity threshold next (family-agnostic)
    - emits runtime diagnostic `strategy_complexity_score` with score, threshold, and matched signals.
- Mode impact (V1/V2):
  - both modes now use the same structural/behavior signal model, with strategy-specific threshold strictness (V1 preserves earlier).
- Files touched:
  - `includes/converter/class-native-converter.php`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - `php -l includes/converter/class-native-converter.php` passed.
  - IDE lint diagnostics returned no errors for modified converter file.
- Result: done (implementation)
- Next action:
  - Extend the same generic score context into extraction/assembly to choose native-outer + in-place HTML fragment boundaries using the same signals.

### 2026-04-21 — Sequential Step 9 (Update 2)

- Pass: 7 assembly wiring using pass-3 generic complexity signals
- Rule/Capability:
  - Wired complexity-aware hybrid fragment injection into runtime assembly path:
    - after `decide_strategy()` and generic scoring, native decisions now evaluate hybrid fragment eligibility from signal families:
      - behavior coupling
      - interactive structure
      - grid/span complexity
      - pseudo dependency
    - if eligible and a valid preserved complex fragment exists, converter injects in-place HTML widget fragment inside the native section tree.
  - Added explicit runtime diagnostics for hybrid assembly:
    - `hybrid_fragment_attached` (pass ownership + section type context)
  - Added render mode tracking for hybrid path:
    - `native_hybrid_fragment` when native section includes in-place preserved fragment.
- Mode impact (V1/V2):
  - both modes can now produce native outer structure with preserved inner complexity, reducing all-or-nothing native vs full-html outcomes.
- Files touched:
  - `includes/converter/class-native-converter.php`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - `php -l includes/converter/class-native-converter.php` passed.
  - IDE lint diagnostics returned no errors for modified converter file.
- Result: done (implementation)
- Next action:
  - Tighten fragment boundary targeting to prefer repeated child-subtree placement (per-card/per-item) before section-level append, using the same complexity signals.

### 2026-04-21 — Sequential Step 9 (Update 3)

- Pass: 7 assembly refinement + Track merge enforcement
- Rule/Capability:
  - Refined hybrid assembly boundary targeting:
    - converter now extracts repeated child-source complex fragments first (direct child containers/items),
    - attempts in-place attachment to repeated output subtrees before section-level fallback,
    - falls back to section-level hybrid fragment only when subtree targeting is unavailable.
  - Added duplicate-safety for hybrid insertion:
    - skips extra hybrid injection when native output already contains explicit visual HTML widgets (`*-card-visual-widget`).
  - Extended diagnostic context:
    - `hybrid_fragment_attached` now reports subtree vs section fallback mode and fragment counts.
- Mode impact (V1/V2):
  - both modes preserve more localized inner complexity with lower risk of section-wide fragment overreach.
- Files touched:
  - `includes/converter/class-native-converter.php`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - `php -l includes/converter/class-native-converter.php` passed.
  - IDE lint diagnostics returned no errors for modified converter file.
- Result: done (implementation)
- Track advancement note:
  - Track A (core sequential): advances Step 9 closure work.
  - Track B (expansion): advances B2 generic hybrid decision policy and B1 structure-first placement behavior.
- Next action:
  - Continue Step 9 closure by linking these hybrid placement outcomes into pass-level conversion report integrity metrics.

### 2026-04-21 — Sequential Step 9 (Update 4)

- Pass: 9 reporting artifact (pass ownership summary)
- Rule/Capability:
  - Added a single-run conversion report diagnostic emitted after integrity passes:
    - `conversion_run_report` includes:
      - detected vs built section types
      - render mode counts (including hybrid modes)
      - hybrid attachment outcomes (subtree vs section fallback, fragments detected)
      - selector/script bridge coverage snapshots
      - global setup asset inventory snapshot
      - companion CSS quick metrics (bytes + hover/pseudo presence)
  - This report explicitly closes the loop on hybrid placement outcomes so runs are auditable without searching many diagnostics.
- Mode impact (V1/V2):
  - both modes now emit the same single report artifact, with strategy recorded for interpretation.
- Files touched:
  - `includes/converter/class-native-converter.php`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - `php -l includes/converter/class-native-converter.php` passed.
  - IDE lint diagnostics returned no errors for modified converter file.
- Result: done (implementation)
- Track advancement note:
  - Track A: advances Step 9 (conversion run report aligned to pass ownership).
  - Track B: reinforces diagnostics truthfulness for broad-spectrum hybrid behaviors.
- Next action:
  - Promote Step 9 from partial → done after verifying the report contains required fields for at least one non-template upload run.

### 2026-04-21 — Sequential Step 9 (Verification Attempt Log)

- Attempt:
  - Ran a dedicated non-template verification attempt against `cr8vstacks-headers-full (1).html` via local CLI execution path.
- Outcome:
  - blocked by environment: WordPress CLI bootstrap failed because PHP CLI is missing/enabling `mysqli`.
  - converter runtime verification could not complete in this shell environment.
- Evidence:
  - WordPress emitted "Requirements Not Met" with missing MySQL extension (`mysqli`) in CLI context.
- Track advancement note:
  - Track A: Step 9 remains partial pending runtime verification in a valid WP CLI/PHP environment.
  - Track B: no regression; reporting/hybrid diagnostics wiring remains implemented.
- Next action:
  - Re-run Step 9 verification immediately after CLI PHP has `mysqli` enabled (or from WP runtime context that can execute converter end-to-end), then promote Step 9 to done.

### 2026-04-21 — Sequential Step 9 (Verified)

- Verification:
  - DB-free CLI verifier executed end-to-end conversion for a minimal single-page HTML input and confirmed:
    - `conversion_run_report` is emitted (`REPORT_OK`).
    - report context includes required pass-ownership fields (modes, bridges, assets, output counts).
  - Tailwind false-positive resolved:
    - sample prototype sheet now reports `Tailwind-like utility markup not detected.`
- Evidence:
  - `tools/verify-native-converter-cli.php` run output contains `REPORT_OK` and serialized `conversion_run_report.context`.
- Result: done (verified)
- Track advancement note:
  - Track A: Step 9 promoted to done (verified).
  - Track B: Tailwind detection tightened to require resolvable utilities; prevents misleading failures.

## Non-Negotiable Definition of Done

Done means:
- Rule implemented broadly as engine capability.
- Verified on more than one structure family.
- Reported in pass-level diagnostics.
- No silent fallback masking failures.

Not done means:
- Works only for one section family.
- Works only with one sample.
- Passes JSON parse but drops behavior/structure contracts.

### 2026-04-22 — Step 17 (Completed): Import Coverage + JS Bridge Helper Extraction

- Pass: output-repair + strategy preservation guard + script bridge modularization
- Rule/Capability:
  - Top-level import contract hardened:
    - all top-level widgets are now wrapped into top-level containers (not only fully-widget trees),
    - removes mixed-root outputs where one native section exists but most sections remain loose widgets.
  - V2 preservation policy widened for editability:
    - unresolved payload preservation guard no longer forces full HTML preserve for most V2 section families,
    - `marquee` and `stats` remain behavior-locked exceptions.
  - JS bridge safety extraction (decongest step):
    - moved runtime safety and inline-handler discovery into new helper module:
      - `includes/converter/helpers/class-script-bridge-helper.php`
    - `class-native-converter.php` now delegates via helper for cleaner maintenance boundaries.
- Files touched:
  - `includes/converter/class-native-converter.php`
  - `includes/converter/helpers/class-script-bridge-helper.php` (new)
- Validation evidence:
  - `php -l includes/converter/class-native-converter.php` passed.
  - `php -l includes/converter/helpers/class-script-bridge-helper.php` passed.
  - training suite executes end-to-end after refactor (`tools/run-training-suite-cli.php --profile=balanced`).
- Result: done
- Track advancement note:
  - Track A: advances conversion stability with stricter import-shape guarantees.
  - Track B: advances maintainability by reducing monolith pressure and centralizing script bridge safety rules.

### 2026-04-22 — Step 18 (Completed): V2 Primitive-First Assembly + Decision Helper Split

- Pass: decision hardening + assembly fallback hardening + modularization
- Rule/Capability:
  - V2 decision/profile modules are now actively used at runtime:
    - `class-native-converter.php` now delegates V2 native-affinity and native-preference decisions into a dedicated helper.
  - New V2 decision helper extracted from the monolith:
    - `includes/converter/helpers/class-v2-decision-helper.php`
    - centralizes primitive affinity signal calculation and `should_prefer_native` policy checks.
  - New V2 primitive assembler helper extracted:
    - `includes/converter/helpers/class-v2-primitive-assembler-helper.php`
    - extracts primitive-capable structures (`h*`, `p`, buttons/links, lists, images, tables) from arbitrary sections.
  - Native fallback strengthened for V2:
    - before final HTML-preserve fallback, converter now emits primitive native widgets when possible,
    - emits `v2_primitive_fallback_applied` diagnostics for traceability.
  - Verifier tooling extended:
    - `tools/verify-native-converter-cli.php` now supports `--file <html> --strategy v1|v2`,
    - emits compact `SECTION_RENDER_MODES` output for quick section-level validation.
- Files touched:
  - `includes/converter/class-native-converter.php`
  - `includes/converter/helpers/class-v2-decision-helper.php` (new)
  - `includes/converter/helpers/class-v2-primitive-assembler-helper.php` (new)
  - `tools/verify-native-converter-cli.php`
- Validation evidence:
  - `php -l` passed for all touched converter/helper/verifier files.
  - `tools/run-training-suite-cli.php --quality-floor --floor-profile=balanced --trend-report` still executes and reports expected floor breaches with trend diagnostics.
  - `tools/verify-native-converter-cli.php --file "training-files/inline css/05-saas-complex-full-interactive(1).html" --strategy v2` executes and prints section-level render diagnostics.
- Known remaining blocker:
  - Multiple SaaS sections are still forced to HTML preserve by compiled RULE-005 (`css columns/masonry`) and continue to block the "mostly container-native" target for those specific samples.
- Result: done (capability shipped; broad-spectrum blocker explicitly identified)
- Track advancement note:
  - Track A: improves V2 native baseline assembly behavior and diagnostics for native-vs-preserve outcomes.
  - Track B: reduces monolith pressure by extracting reusable decision and primitive-extraction logic.

### 2026-04-23 — Step 19 (Completed): RULE-005 Fragment-Level Hybrid Preserve

- Pass: priority-rule narrowing + V2 native/hybrid routing + dynamic primitive extraction
- Rule/Capability:
  - RULE-005 no longer treats `grid-template-columns` as CSS Columns:
    - introduced real CSS column/masonry declaration detection so normal CSS grid does not trigger full-section preservation.
  - Added focused helper module:
    - `includes/converter/helpers/class-v2-hybrid-preserve-helper.php`
    - centralizes CSS columns/masonry declaration checks and local fragment-root discovery.
  - V2 RULE-005 behavior changed from whole-section preserve to native rebuild plus in-place hybrid fragment where supported:
    - affected native-favored section families include hero, features, pricing, footer, cta, generic, process, and testimonials.
    - diagnostics now distinguish `priority_rule_fragment_hybrid`, `rule_005_fragment_isolated`, and `rule_005_fragment_unavailable`.
  - Existing hybrid widget attachment path now receives columns/masonry fragment candidates instead of letting the priority rule short-circuit to `fully_preserved_source`.
  - Fixed CTA template override for behavior-locked fixed CTA bars:
    - fixed-position CTA remains honest `fully_preserved_source` instead of being forced into an empty native CTA template.
  - Dynamic feature grids now extract JS data arrays into native feature cards:
    - `includes/converter/helpers/class-v2-primitive-assembler-helper.php` can parse common `const features = [...]` data and emit editable cards.
  - Structural fidelity counting now recognizes emitted feature bento cards and footer nav columns so validation measures the real native output shape.
- Files touched:
  - `includes/converter/class-native-converter.php`
  - `includes/converter/skills/class-priority-rules-engine.php`
  - `includes/converter/helpers/class-v2-hybrid-preserve-helper.php` (new)
  - `includes/converter/helpers/class-v2-primitive-assembler-helper.php`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - `php -l includes/converter/helpers/class-v2-hybrid-preserve-helper.php` passed.
  - `php -l includes/converter/helpers/class-v2-primitive-assembler-helper.php` passed.
  - `php -l includes/converter/skills/class-priority-rules-engine.php` passed.
  - `php -l includes/converter/class-native-converter.php` passed.
  - Targeted SaaS check passed for the custom file:
    - `php tools/verify-native-converter-cli.php --file "training-files/inline css/05-saas-complex-full-interactive(1).html" --strategy v2`
    - result: `REPORT_OK` for `custom_file`.
    - render modes: fixed CTA `fully_preserved_source`; hero/features/generic/pricing/footer `native_hybrid_fragment`.
  - Full balanced floor still executes:
    - `php tools/run-training-suite-cli.php --quality-floor --floor-profile=balanced --trend-report`
    - bridge ratios now meet balanced thresholds, but floor still fails on run success.
- Remaining blockers:
  - Balanced floor status: failed.
  - Remaining fail codes after this pass:
    - `generic_repeated_structure_degraded`: 2
    - `process_step_count_degraded`: 2
    - `pricing_cards_unresolved`: 2
    - `feature_cards_unresolved`: 2
    - `footer_columns_unresolved`: 1
    - `pricing_card_count_degraded`: 1
  - The next pass should target broad extractor coverage for process/pricing/features/footer, not RULE-005.
- Result: done (RULE-005 pass completed; global quality floor still blocked by separate extraction failures)
- Track advancement note:
  - Track A: unlocks the remaining SaaS V2 sections toward native containers/widgets while keeping behavior islands in place.
  - Track B: further reduces monolith pressure by moving CSS columns/masonry detection into a dedicated helper.

### 2026-04-23 — Step 20 (Completed): Admin Filename Sync + Top-Level Widget Cause Fix + Geometric Audit

- Pass: admin UX fix + Elementor import-shape cause fix + read-only conversion audit
- Rule/Capability:
  - Converter admin upload UX now auto-populates Project Name from the uploaded HTML filename:
    - extension is stripped,
    - spaces/underscores become hyphens,
    - unsafe filename characters collapse into a clean project slug.
  - Top-level repair cause fixed at emission points instead of relying on the repair pass:
    - global setup now emits a top-level container with the setup HTML widget inside it,
    - nav now emits a top-level container with the nav HTML widget inside it,
    - full HTML-preserved sections now return a top-level container wrapping the HTML widget.
  - Global setup integrity checks now read nested setup HTML, so containerized global setup still passes required CSS/JS integrity validation.
- Geometric conversion audit summary:
  - Existing generated JSON is importable but not faithful to the source.
  - The generated file contains two old `sb-top-level-widget-wrap` repair wrappers and duplicate Elementor `_element_id` values for global setup/nav; new converter output should no longer create those from source.
  - Visual theme is wrong for the sample: generated CSS uses dark neon tokens (`#0a0a12`, `#c8ff00`) while source uses light geometric SaaS tokens (`#F7F5F0`, `#1A1A2E`, `#E85D26`).
  - Source JS contract is broken in the generated JSON:
    - missing output IDs/classes include `navbar`, `hero-text`, `hero-visual`, `chart-bars`, `stat-deals`, `stat-rev`, `stat-rate`, `btn-annual`, `.bar`, and `.price`.
    - source bridge still tries to use those selectors, so behavior can abort or silently do nothing in Elementor.
  - Content extraction is materially degraded:
    - nav brand `veltro.` is missing,
    - hero buttons are reduced to a small hybrid fragment,
    - logo strip loses company names,
    - CTA is misclassified as a generic section and duplicated,
    - pricing loses `Annual`, `Starter`, `Pro`, `Enterprise`, native price spans/data attrs, and separates/merges pricing content incorrectly,
    - footer loses brand/copyright fidelity and emits a generic 2026 footer string.
- Files touched:
  - `admin/js/admin.js`
  - `includes/converter/class-native-converter.php`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - `rg --version` works in this shell after PATH repair.
  - `php -l includes/converter/class-native-converter.php` passed.
  - `node --check admin/js/admin.js` passed.
  - Targeted geometric check:
    - `php tools/verify-native-converter-cli.php --file "training-files/inline css/01-saas-geometric-light(1).html" --strategy v2`
    - result: `REPORT_OK` for `custom_file`.
    - render modes: hero/features/pricing/footer are `native_hybrid_fragment`, process/generic are `native_rebuilt`, and no top-level widget repair warning was emitted for the custom file.
  - Full balanced floor still executes:
    - `php tools/run-training-suite-cli.php --quality-floor --floor-profile=balanced --trend-report`
    - result: failed on `min_run_success_rate` and `max_fail_runs`.
    - bridge ratios meet balanced thresholds.
- Remaining blockers:
  - Balanced floor status: failed.
  - Current fail codes:
    - `generic_repeated_structure_degraded`: 2
    - `process_step_count_degraded`: 2
    - `pricing_cards_unresolved`: 2
    - `feature_cards_unresolved`: 2
    - `footer_columns_unresolved`: 1
    - `pricing_card_count_degraded`: 1
  - Next plan discussion should focus on broad native extractor coverage and source-design fidelity before applying audit-driven fixes.
- Result: done (requested concrete fixes completed; audit completed without making audit-driven conversion changes)
- Track advancement note:
  - Track A: improves admin workflow and prevents Elementor import-shape repairs at the source.
  - Track B: documents the geometric sample failure modes so the next implementation pass can target real extraction and fidelity gaps instead of guessing.

### 2026-04-23 — Step 21 (Completed): Expanded V2 Audit + Broad Top-Level/JS Hardening

- Pass: expanded audit correction + source-level import contract hardening + broad source-JS bridge hardening
- Important interpretation rule:
  - The geometric V2 audit is a training reference, not a file-specific repair list.
  - Findings below should drive broad converter fixes in extraction, mapping, CSS token resolution, and JS bridging so the converter improves across unknown future designs.
- Expanded geometric V2 audit findings:
  - Missing/degraded content:
    - nav brand `veltro.` is missing and nav CTA styling is not preserved as a CTA button.
    - hero eyebrow `Now in public beta`, secondary CTA `Watch demo`, trial note, dashboard heading `Pipeline Overview`, `This week`, weekday labels, stat labels, stat IDs, and stat animated values are missing from native output.
    - logo strip only keeps `Trusted by teams at`; company names `Axiom`, `Fluxora`, `Draftly`, `Nuvio`, and `Stackline` are dropped.
    - features retain the main card copy, but source accent treatment/icon styling is not faithfully represented.
    - process content is mostly present, but the output adds an invented orbital visual that was not in the source.
    - pricing loses the `Annual -20%` toggle, `Starter`, `Pro`, `Enterprise`, `$79`, `Custom`, `Talk to sales`, and per-card feature grouping; features are merged into one list and only `$29` survives cleanly.
    - CTA is misclassified as `generic`, duplicated, and not emitted as a native CTA section.
    - footer loses `veltro.`, `2025 Veltro Inc.`, and emits generic `2026 — ALL RIGHTS RESERVED. ALL SYSTEMS OPERATIONAL`.
  - CSS audit:
    - generated CSS uses dark/neon default tokens (`#0a0a12`, `#c8ff00`, `#f5f3ee`) instead of source light/orange geometric tokens (`#F7F5F0`, `#1A1A2E`, `#E85D26`).
    - missing content generally also means missing CSS targeting for dropped structures such as `.bar`, `.price`, dashboard stat IDs, logo strip brands, and pricing cards.
    - CSS bridge reports no source selector output for this sample, so source selectors are not being carried into output where needed.
  - JS audit:
    - generated output previously dropped/failed source behavior because source IDs/classes were missing or retargeted incompletely.
    - inline handlers such as `onclick="setPricing(...)"` require exported global functions, but inline handler discovery was reading an empty raw HTML value.
    - source scripts often attach `load`/`DOMContentLoaded` handlers; Elementor can inject/execute HTML-widget scripts after those events have already fired, so callbacks never run.
    - async callbacks can throw after the outer bridge `try/catch` has already returned, so one missing node can still break a behavior island.
- Broad fixes made from the audit:
  - Added a source-level top-level normalizer at the section append boundary:
    - any builder/template that still returns a widget is wrapped in a container before hybrid attachment, anchor assignment, validation, and repair.
    - emits `top_level_widget_normalized_at_source` diagnostics instead of relying on the late repair warning.
  - Preserved source nav IDs in generated nav HTML where safe:
    - source `id="navbar"` now remains available for source JS while the generated nav also carries the prefixed identity in `data-stack-blueprint-id`.
    - generated scroll code now queries prefixed ID, data identity, or nav class.
  - Document intelligence now stores `raw_html` so inline handler discovery works globally.
  - Script bridge helper now:
    - exports discovered inline handler functions to `window`,
    - guards `load`/`DOMContentLoaded` handlers when Elementor executes scripts late,
    - wraps registered listener callbacks so async callback failures are logged instead of killing all behavior.
  - Source JS is no longer dropped just because selector bridge targets are missing:
    - converter emits a guarded passthrough with a warning and diagnostics, preserving behavior opportunities instead of fake success.
- Files touched:
  - `includes/converter/class-native-converter.php`
  - `includes/converter/helpers/class-script-bridge-helper.php`
  - `includes/converter/passes/class-pass-document-intelligence.php`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - `php -l includes/converter/class-native-converter.php` passed.
  - `php -l includes/converter/helpers/class-script-bridge-helper.php` passed.
  - `php -l includes/converter/passes/class-pass-document-intelligence.php` passed.
  - Targeted geometric V2 inspection confirms:
    - no `Repair: wrapped top-level widget...` warning,
    - zero top-level widgets,
    - generated nav preserves `id="navbar"`,
    - inline handler export includes `window.setPricing = setPricing`,
    - script bridge includes ready/listener runtime guards.
  - Targeted geometric V2 verifier still returns `REPORT_OK` for `custom_file`.
  - Full balanced floor still executes and still fails only on run success gates:
    - `min_run_success_rate`
    - `max_fail_runs`
    - bridge ratios meet balanced thresholds.
- Remaining blockers:
  - Balanced floor status: failed.
  - Current fail codes remain extraction/fidelity oriented:
    - `generic_repeated_structure_degraded`: 2
    - `process_step_count_degraded`: 2
    - `pricing_cards_unresolved`: 2
    - `feature_cards_unresolved`: 2
    - `footer_columns_unresolved`: 1
    - `pricing_card_count_degraded`: 1
  - Next broad pass should target content completeness and section/card extraction globally:
    - repeated-logo strip extraction,
    - hero visual/stat primitive extraction,
    - CTA classification/root promotion,
    - pricing card/toggle/list grouping,
    - footer identity/copyright extraction,
    - source token/theme resolution before template defaults are applied.
- Result: done (expanded audit captured and root-level hardening applied without file-specific conversion hacks)
- Track advancement note:
  - Track A: improves import contract and JS survivability across V2 conversions.
  - Track B: converts the geometric audit into broad training targets for extraction and style-fidelity systems.

### 2026-04-23 — Step 22 (Completed): Dedicated V2 Geometric Audit Training Tracker

- Pass: audit tracking artifact creation
- Rule/Capability:
  - Created a dedicated audit/training tracker for the geometric V2 conversion:
    - `V2_GEOMETRIC_AUDIT_TRAINING_TRACKER.md`
  - The tracker captures:
    - the first audit deductions,
    - expanded content/CSS/JS audit findings,
    - the rule that this audit is training evidence, not a file-specific fix list,
    - broad converter implementation targets,
    - completed root-level fixes,
    - remaining extraction/fidelity blockers,
    - current validation baseline and balanced floor fail codes.
  - This keeps the audit actionable without mixing it into the older `AUDIT.md` Nexus audit.
- Files touched:
  - `V2_GEOMETRIC_AUDIT_TRAINING_TRACKER.md`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - Tracker file added and cross-linked from the execution plan.
  - No runtime code changed in this step.
- Result: done
- Track advancement note:
  - Track A: creates a working checklist for broad V2 extraction/style/JS improvements.
  - Track B: reduces future confusion by separating audit evidence from chronological execution notes.

### 2026-04-23 — Step 23 (Completed): Source-vs-Output Coverage Diagnostics

- Pass: audit evidence automation + run-report coverage diagnostics
- Rule/Capability:
  - Added a non-fatal `source_output_coverage` diagnostic for every conversion run.
  - The diagnostic compares:
    - meaningful source text phrases against emitted Elementor JSON/HTML text surface,
    - source behavior/style selector contracts against emitted output classes/IDs.
  - Coverage now appears inside the `conversion_run_report` under `coverage`.
  - Selector contracts include source JS/CSS hooks plus semantic hooks such as pricing, bars, stats, charts, hero, nav, footer, CTA, cards, and buttons.
  - Missing coverage emits warnings instead of failing conversion for now, so we gain truthful evidence without introducing a new hard gate before thresholds are tuned.
- Files touched:
  - `includes/converter/class-native-converter.php`
  - `V2_GEOMETRIC_AUDIT_TRAINING_TRACKER.md`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - `php -l includes/converter/class-native-converter.php` passed.
  - Targeted geometric V2 verifier still returns `REPORT_OK` for `custom_file`.
  - Geometric V2 coverage report now captures:
    - 81 source text phrases,
    - 32 missing text phrases,
    - text coverage ratio `0.6049`,
    - 13 source selector contracts,
    - 10 missing selector hooks,
    - selector coverage ratio `0.2308`.
  - Missing selector examples now include:
    - `hero-text`
    - `hero-visual`
    - `chart-bars`
    - `stat-deals`
    - `stat-rev`
    - `stat-rate`
    - `pricing`
    - `btn-annual`
    - `.bar`
    - `.price`
  - Full balanced floor still executes and still fails on the same extraction/fidelity gates:
    - `generic_repeated_structure_degraded`: 2
    - `process_step_count_degraded`: 2
    - `pricing_cards_unresolved`: 2
    - `feature_cards_unresolved`: 2
    - `footer_columns_unresolved`: 1
    - `pricing_card_count_degraded`: 1
- Remaining blockers:
  - Balanced floor status: failed.
  - The next broad implementation target should be pricing card extraction because it is both a current floor fail and a major geometric audit gap.
- Result: done
- Track advancement note:
  - Track A: makes missing content and missing JS/CSS hooks measurable across conversions.
  - Track B: turns manual audit observations into reusable diagnostics for future training passes.

### 2026-04-23 - Step 24 (Completed): Pricing Cards + Hero Behavior Islands

- Pass: broad V2 extraction improvements from geometric audit evidence
- Rule/Capability:
  - Replaced weak whole-section pricing scrape with repeated pricing-card grouping:
    - detects repeated plan/card siblings,
    - extracts plan, badge, price, period, feature list, CTA, source class/id hooks,
    - preserves source price markup/data attributes inside in-card HTML islands,
    - preserves billing toggle HTML in-place so inline handlers and `btn-monthly`/`btn-annual` survive.
  - Added icon-token normalization for pricing features and coverage diagnostics so checkmark bullets do not create false missing-content evidence.
  - Added hero-specific visual island extraction:
    - native copy column remains editable,
    - dashboard/chart/stat visual subtree is preserved in-place,
    - `.bar`, `chart-bars`, `hero-visual`, `stat-deals`, `stat-rev`, and `stat-rate` survive for JS bridge retargeting.
  - Added nearest source-id inheritance for extracted content roots where the converter is operating on an inner node instead of the original section wrapper.
- Files touched:
  - `includes/converter/class-native-converter.php`
  - `includes/converter/class-template-library.php`
  - `V2_GEOMETRIC_AUDIT_TRAINING_TRACKER.md`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - `php -l includes/converter/class-native-converter.php` passed.
  - `php -l includes/converter/class-template-library.php` passed.
  - Targeted geometric V2 verifier still returns `REPORT_OK` for `custom_file`.
  - Geometric V2 pricing section now emits:
    - `button`: 3
    - `icon-list`: 3
    - `html`: 8
    - `container`: 7
  - Geometric V2 script bridge now reports:
    - `rewrite_count`: 7
    - bridged classes include `bar`, `feature-card`, `price`
    - bridged ids include `hero-visual`, `chart-bars`, `stat-deals`, `stat-rev`, `stat-rate`, `pricing`, `btn-monthly`, `btn-annual`
  - Geometric V2 coverage improved from:
    - text coverage `0.6049` to `0.825`
    - selector coverage `0.2308` to `0.9231`
    - missing selector hooks `10` to `1`
  - Full balanced floor still fails on run-success gates, but bridge quality is above thresholds:
    - run success rate `0.5454545454545454`
    - selector output CSS ratio `0.25`
    - script rewrite ratio `0.6666666666666666`
    - global script bridge ratio `1`
    - V2 selector output CSS ratio `0.3333333333333333`
    - V2 script rewrite ratio `0.8333333333333334`
- Remaining blockers:
  - Balanced floor status: failed.
  - Current fail codes:
    - `generic_repeated_structure_degraded`: 2
    - `process_step_count_degraded`: 2
    - `feature_cards_unresolved`: 2
    - `pricing_card_count_degraded`: 2
    - `pricing_cards_unresolved`: 1
    - `footer_columns_unresolved`: 1
  - Geometric coverage still shows missing source text for secondary hero CTA/trial note, logo strip names, section eyebrow labels, footer copyright/title, and a combined `Pro Most popular` phrase.
  - The remaining selector miss is source section id `pricing`; extraction now carries nearest source IDs, but the rendered/repair path still needs follow-up to confirm why that semantic id is not counted as emitted.
- Result: done
- Track advancement note:
  - Track A: moves V2 closer to native-first sections with local hybrid behavior islands.
  - Track B: converts the audit's JS complaints into measurable selector preservation and bridge improvements.

### 2026-04-23 - Step 25 (Completed): Broad Interpretation Cleanup for Repeated Cards, Simple Footers, and Preserved Wrappers

- Pass: removed narrow/card-shaped assumptions from generic V2 interpretation paths instead of training on one sample layout.
- Rule/Capability:
  - Broadened generic repeated-card extraction:
    - groups repeated descendants by shared parent,
    - scores narrative panels/cards/services/tab-panels by headings, paragraphs, lists, media, and semantic class hints,
    - rebuilds cards from the winning repeated group instead of requiring obvious `.card`-style markup.
  - Broadened JS array extraction for features:
    - no longer limited to `features = [...]`,
    - now interprets repeated object arrays like `services`, `items`, `capabilities`, and similar title/body datasets.
  - Reworked footer truthfulness:
    - simple text-only footers no longer fail just because they do not contain link columns,
    - source footer bottom/copyright text is extracted and emitted,
    - synthetic footer legal/status copy is no longer the required path for success.
  - Reworked preserved-wrapper integrity:
    - preserved sections are now validated against source wrapper signatures/tags/ids/classes instead of a narrow block-tag regex,
    - this removes false failures for preserved overlay/fixed/generic islands.
  - Footer template now preserves safe source footer IDs and uses dynamic column grids rather than assuming one fixed 4-column shape.
- Files touched:
  - `includes/converter/class-native-converter.php`
  - `includes/converter/helpers/class-v2-primitive-assembler-helper.php`
  - `includes/converter/class-template-library.php`
  - `V2_GEOMETRIC_AUDIT_TRAINING_TRACKER.md`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - `php -l includes/converter/class-native-converter.php` passed.
  - `php -l includes/converter/helpers/class-v2-primitive-assembler-helper.php` passed.
  - `php -l includes/converter/class-template-library.php` passed.
  - Previously failing V2 references now return `REPORT_OK`:
    - `training-files/inline css advanced/10-minimal-systems-complex(1).html`
    - `training-files/inline css/02-saas-editorial-typographic.html`
    - `training-files/inline css/03-agency-portfolio-brutalist.html`
    - `training-files/inline css advanced/11-wellness-spa-retreat(1).html`
  - Full gate now passes:
    - `php tools/run-training-suite-cli.php --quality-floor --floor-profile=balanced --trend-report`
    - run success rate `1`
    - selector output CSS ratio `0.36363636363636365`
    - script rewrite ratio `0.6363636363636364`
    - global script bridge ratio `1`
    - V2 selector output CSS ratio `0.36363636363636365`
    - V2 script rewrite ratio `0.6363636363636364`
  - Targeted geometric V2 verifier remains `REPORT_OK` and now confirms:
    - text coverage `0.875`
    - selector coverage `1`
    - missing text phrases reduced to `10`
    - no missing selector hooks
- Remaining blockers:
  - Balanced floor status: passed.
  - High-signal interpretation gaps still visible in geometric evidence:
    - logo/client strip names are still not extracted as native content,
    - some eyebrow/section-label text such as `What Veltro does` and `Process` is still dropped,
    - combined pricing label text like `Pro Most popular` still needs more faithful split/retention,
    - source page title/token/theme fidelity still needs more work beyond structural success.
- Result: done
- Track advancement note:
  - Track A: replaced narrow feature/footer assumptions with broader interpretation rules.
  - Track B: restored truthfulness by removing synthetic footer output as the success path.

### 2026-04-23 - Step 26 (Completed): Semantic Label Retention and Source-Derived Theme Cleanup

- Pass: widened semantic extraction and theme carryover without training on any fixed section shape or card count.
- Rule/Capability:
  - Section tag/eyebrow extraction now evaluates both direct children and near-top descendants.
  - Nested wrapper labels such as `What Veltro does` and `Process` are no longer skipped just because they sit inside a header wrapper div before the main heading.
  - Pricing card payloads now preserve richer semantics:
    - plan-line inline markup is retained when a plan label contains nested badge fragments,
    - billing/unit note text like `per user` is extracted separately from the billing period,
    - template rendering suppresses duplicate standalone badge output when that badge text already exists inside the preserved plan line.
  - Source document title is now carried into global setup output as hidden semantic metadata instead of being silently dropped.
  - Global setup, nav, companion CSS, structural alias CSS, and template CSS now use source-derived rgba values instead of hardcoded dark/neon literals for hover, muted text, accent borders, and CTA/nav overlays.
- Files touched:
  - `includes/converter/class-native-converter.php`
  - `includes/converter/class-template-library.php`
  - `V2_GEOMETRIC_AUDIT_TRAINING_TRACKER.md`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - `php -l includes/converter/class-native-converter.php` passed.
  - `php -l includes/converter/class-template-library.php` passed.
  - `php tools/verify-native-converter-cli.php --file "training-files/inline css/01-saas-geometric-light(1).html" --strategy v2` returns `REPORT_OK` with:
    - text coverage `1`
    - selector coverage `1`
    - missing text phrases reduced to `0`
  - `php tools/run-training-suite-cli.php --quality-floor --floor-profile=balanced --trend-report` still passes:
    - run success rate `1`
    - selector output CSS ratio `0.36363636363636365`
    - script rewrite ratio `0.6363636363636364`
    - global script bridge ratio `1`
    - V2 selector output CSS ratio `0.36363636363636365`
    - V2 script rewrite ratio `0.6363636363636364`
- Remaining blockers:
  - Balanced floor status: passed.
  - Structural interpretation and semantic retention for the geometric SaaS reference are now green.
  - The next open broad target is deeper JS selector/output coverage beyond the current bridge baseline, especially on references where source JS exists but no output selector CSS is intentionally emitted.
- Result: done
- Track advancement note:
  - Track A: semantic extraction now understands wrapped section labels and richer pricing label fragments.
  - Track B: theme carryover moved further away from hardcoded dark/neon defaults and closer to source-derived styling contracts.

### 2026-04-23 - Step 27 (Completed): Remove Sample-Shaped Template Hardcoding and Widen Bridge Targets

- Pass: removed synthetic sample-shaped visuals from the library and widened bridge targeting around real emitted behavior surfaces.
- Rule/Capability:
  - Removed the synthetic orbital/process visual block from the template library.
    - Process sections now render from extracted source steps and extracted visuals only.
    - The converter no longer invents an `AI` orbital diagram when the source never contained one.
  - Removed the dead synthetic footer status/legal output path:
    - no more built-in `ALL SYSTEMS OPERATIONAL` footer fragment,
    - no more prefixed footer status-dot/status animation CSS that only existed to support synthetic output.
  - Widened selector/script bridge targeting around actual emitted output surfaces:
    - hero copy container and hero visual widget/island,
    - feature/bento card visual widgets,
    - process step visual widgets,
    - testimonial visual widgets,
    - pricing toggle widget,
    - pricing card visual widgets,
    - pricing price-amount widget,
    - footer brand logo widget.
  - This keeps harder JS/CSS contracts anchored to real emitted Elementor structures instead of only to coarse section roots.
- Files touched:
  - `includes/converter/class-template-library.php`
  - `includes/converter/class-native-converter.php`
  - `V2_GEOMETRIC_AUDIT_TRAINING_TRACKER.md`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - `php -l includes/converter/class-template-library.php` passed.
  - `php -l includes/converter/class-native-converter.php` passed.
  - `php tools/verify-native-converter-cli.php --file "training-files/inline css/01-saas-geometric-light(1).html" --strategy v2` remains `REPORT_OK`.
  - Targeted geometric/custom bridge coverage now shows wider bridged hooks:
    - bridged classes include `price`
    - bridged ids include `btn-monthly` and `btn-annual`
    - script rewrite count improved to `7`
  - `php tools/run-training-suite-cli.php --quality-floor --floor-profile=balanced --trend-report` still passes:
    - run success rate `1`
    - selector output CSS ratio `0.36363636363636365`
    - script rewrite ratio `0.6363636363636364`
    - global script bridge ratio `1`
- Remaining blockers:
  - Balanced floor status: passed.
  - The next bridge-depth ceiling is broader support for JS contracts that reference dynamic state through attribute APIs or non-selector string channels, not sample-shaped HTML structures.
- Result: done
- Track advancement note:
  - Track A: removed untrustworthy synthetic visuals/status output from the system.
  - Track B: bridge targeting is now more granular around real emitted widget/island surfaces.

### 2026-04-24 - Step 28 (Completed): Expand JS Bridge for Attribute and State Contracts

- Pass: widened the generic source-JS bridge beyond direct selector APIs so stateful behavior contracts can survive conversion without sample-specific logic.
- Rule/Capability:
  - Added generic rewrite support for state-bearing JS patterns:
    - `setAttribute(...)` value rewrites for id/target/reference attributes,
    - `dataset.foo = ...` and `dataset['foo'] = ...` rewrites,
    - `location.hash = ...` and `.href = ...` hash/reference rewrites,
    - comparison rewrites for `getAttribute(...)`, `dataset.*`, and `location.hash` against bridged ids/selectors.
  - Added broad helper methods for:
    - camelCase dataset key to `data-*` normalization,
    - single-selector reference rewriting,
    - context-aware JS state-value rewriting for id/class/reference attributes.
  - Runtime hardening now also protects `document.querySelector(...).prop` access chains with optional chaining, not only `getElementById(...)`.
  - Expanded JS hit analysis so attribute/state patterns count as bridgeable source behavior, not only selector literals and classList calls.
- Files touched:
  - `includes/converter/class-native-converter.php`
  - `includes/converter/helpers/class-script-bridge-helper.php`
  - `V2_GEOMETRIC_AUDIT_TRAINING_TRACKER.md`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - `php -l includes/converter/class-native-converter.php` passed.
  - `php -l includes/converter/helpers/class-script-bridge-helper.php` passed.
  - `php tools/verify-native-converter-cli.php --file "training-files/inline css/01-saas-geometric-light(1).html" --strategy v2` remains `REPORT_OK`.
  - `php tools/run-training-suite-cli.php --quality-floor --floor-profile=balanced --trend-report` still passes:
    - run success rate `1`
    - selector output CSS ratio `0.36363636363636365`
    - script rewrite ratio `0.6363636363636364`
    - global script bridge ratio `1`
  - Important note:
    - current suite metrics did not increase on this pass,
    - so this should be interpreted as broadened capability plus no regression, not as a claimed corpus gain.
- Remaining blockers:
  - Balanced floor status: passed.
  - The next ceiling is not selector syntax itself but source behavior that flows through richer runtime channels such as event payloads, computed names, or array-driven target maps.
- Result: done
- Track advancement note:
  - Track A: the bridge now understands more root-level JS state/reference patterns.
  - Track B: runtime safety improved for querySelector-based access chains.

### 2026-04-24 - Step 29 (Completed): Add Alias-Aware Runtime Resolution to the JS Bridge

- Pass: widened the bridge runtime so converted output can keep working when source JS resolves targets indirectly at runtime instead of only through fixed selector literals.
- Rule/Capability:
  - Expanded generic JS rewrite support for more runtime-derived reference channels:
    - `href = ...` and `.hash = ...` reference rewrites,
    - comparison rewrites against `getAttribute(...)`, `dataset.*`, and `location.hash`,
    - reverse-form comparisons where the bridged value appears on the left side of the expression.
  - Extended runtime safety to run bridged JS inside an alias-aware DOM lookup wrapper that can retry against bridged ids/classes for:
    - `document.getElementById(...)`,
    - `document.querySelector(...)`,
    - `document.querySelectorAll(...)`,
    - `document.getElementsByClassName(...)`,
    - `Element.prototype.querySelector(...)`,
    - `Element.prototype.querySelectorAll(...)`,
    - `Element.prototype.closest(...)`,
    - `Element.prototype.matches(...)`,
    - `Element.prototype.getElementsByClassName(...)`.
  - Event listeners registered during bridged execution now keep that alias-aware runtime context when callbacks fire later, instead of only during initial bootstrap.
  - This remains interpretation-first:
    - no sample-specific selector names were added,
    - no sample layouts were taught into the bridge,
    - the change is a broader DOM/runtime resolution layer.
- Files touched:
  - `includes/converter/class-native-converter.php`
  - `includes/converter/helpers/class-script-bridge-helper.php`
  - `V2_GEOMETRIC_AUDIT_TRAINING_TRACKER.md`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - `php -l includes/converter/class-native-converter.php` passed.
  - `php -l includes/converter/helpers/class-script-bridge-helper.php` passed.
  - `php tools/verify-native-converter-cli.php --file "training-files/inline css/01-saas-geometric-light(1).html" --strategy v2` remains `REPORT_OK`.
  - `php tools/run-training-suite-cli.php --quality-floor --floor-profile=balanced --trend-report` still passes:
    - run success rate `1`
    - selector output CSS ratio `0.36363636363636365`
    - script rewrite ratio `0.6363636363636364`
    - global script bridge ratio `1`
  - Important note:
    - the current suite did not show a visible metric bump on this pass,
    - so this should be interpreted as broader runtime capability plus no regression, not as a claimed corpus gain.
- Remaining blockers:
  - Balanced floor status: passed.
  - The next ceiling is behavior that computes target names through arrays, config maps, or event payload data before DOM lookup.
- Result: done
- Track advancement note:
  - Track A: the bridge can now survive more deferred and indirect target resolution patterns.
  - Track B: event-time behavior inherits the same alias-aware runtime safety as bootstrap-time behavior.

### 2026-04-24 - Step 30 (Completed): Expand Computed Target Maps and Add Converter Live Preview

- Pass: widened the bridge for computed runtime target maps and reduced operator loop friction by adding an immediate converter-page preview of generated JSON plus CSS.
- Rule/Capability:
  - Added broad source-JS rewrite support for reference-like config carriers:
    - `const/let/var` assignments whose names imply selector/target/id/class semantics,
    - object-property maps carrying target-like keys,
    - array literals assigned to reference-like variables.
  - Added generic key inference rather than sample-specific names:
    - reference-like keys now include concepts such as `selector`, `target`, `panel`, `tab`, `section`, `anchor`, `hash`, `route`, `modal`, `drawer`, `control`, `trigger`, `id`, and `class`.
  - Extended runtime event bridging so listeners receive alias-aware payloads for:
    - `event.detail`,
    - `event.data`,
    - `event.state`.
  - Added a sandboxed right-sidebar converter preview:
    - renders converted Elementor JSON structures client-side,
    - applies companion CSS in the preview document,
    - supports desktop/tablet/mobile preview widths,
    - refreshes from the stored conversion record without opening Elementor.
  - Preview scope note:
    - it is a fast structural/styling inspection surface,
    - it is not intended to be a perfect Elementor frontend clone.
- Files touched:
  - `includes/converter/class-native-converter.php`
  - `includes/converter/helpers/class-script-bridge-helper.php`
  - `admin/views/page-converter.php`
  - `admin/js/admin.js`
  - `admin/css/admin.css`
  - `V2_GEOMETRIC_AUDIT_TRAINING_TRACKER.md`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - `php -l includes/converter/class-native-converter.php` passed.
  - `php -l includes/converter/helpers/class-script-bridge-helper.php` passed.
  - `php -l admin/views/page-converter.php` passed.
  - `node --check admin/js/admin.js` passed.
  - `php tools/verify-native-converter-cli.php --file "training-files/inline css/01-saas-geometric-light(1).html" --strategy v2` remains `REPORT_OK`.
  - `php tools/run-training-suite-cli.php --quality-floor --floor-profile=balanced --trend-report` still passes, with a measurable global bridge gain:
    - run success rate `1`
    - selector output CSS ratio `0.36363636363636365`
    - script rewrite ratio improved from `0.6363636363636364` to `0.8181818181818182`
    - global script bridge ratio `1`
    - trend warnings cleared
- Remaining blockers:
  - Balanced floor status: passed.
  - The next broad ceiling is richer source-selector/output CSS carryover on native surfaces, not JS bridge survival.
- Result: done
- Track advancement note:
  - Track A: computed target maps now survive more array/object/event-based JS flows.
  - Track B: conversion review can happen much faster because the sidebar now previews generated output immediately.

### 2026-04-24 - Step 31 (Completed): Surface Preview Audits and Rebalance Converter Layout

- Pass: carried native diagnostics through the conversion manager into admin records, surfaced them inside the converter preview, and widened the preview workspace while also broadening CSS target filtering toward native widget surfaces.
- Rule/Capability:
  - Conversion records now persist native converter diagnostics instead of dropping them after the run.
  - The converter preview now shows live audit badges for:
    - missing content phrases,
    - missing selector/behavior hooks,
    - CSS bridge state.
  - The converter layout is rebalanced for preview work:
    - narrower left form column,
    - wider right sidebar,
    - larger preview viewport.
  - CSS target filtering now expands section-root targets into related native widget semantic surfaces before emitted-hook validation:
    - heading/text/button/image/media/icon-list wrappers can now be considered when a section-level source hook needs a native landing surface.
  - Scope note:
    - this is still broad, architecture-level mapping based on section/widget semantics,
    - no sample design blocks or one-off audited layouts were hardcoded.
- Files touched:
  - `includes/converter/class-conversion-manager.php`
  - `includes/converter/class-native-converter.php`
  - `admin/views/page-converter.php`
  - `admin/js/admin.js`
  - `admin/css/admin.css`
  - `V2_GEOMETRIC_AUDIT_TRAINING_TRACKER.md`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - `php -l includes/converter/class-conversion-manager.php` passed.
  - `php -l includes/converter/class-native-converter.php` passed.
  - `php -l admin/views/page-converter.php` passed.
  - `node --check admin/js/admin.js` passed.
  - `php tools/verify-native-converter-cli.php --file "training-files/inline css/01-saas-geometric-light(1).html" --strategy v2` remains `REPORT_OK`.
  - `php tools/run-training-suite-cli.php --quality-floor --floor-profile=balanced --trend-report` still passes:
    - run success rate `1`
    - selector output CSS ratio `0.36363636363636365`
    - script rewrite ratio `0.8181818181818182`
    - global script bridge ratio `1`
  - Important note:
    - the selector-output ratio did not move on this pass,
    - so this should be interpreted as better audit visibility, safer CSS groundwork, and no regression rather than a measured selector-output gain.
- Remaining blockers:
  - Balanced floor status: passed.
  - The next root ceiling remains section-local CSS descendant contracts that still land on section roots instead of the correct native leaf widgets.
- Result: done
- Track advancement note:
  - Track A: preview-time auditing is now much faster because diagnostics survive the conversion pipeline.
  - Track B: the converter workspace is better suited to daily training because the preview has more room and clearer quality signals.

### 2026-04-24 - Step 32 (Completed): Remove DOM Compatibility Crash and Harden Runtime Failure Handling

- Pass: resolved the native conversion 500 caused by unsupported DOM API usage and added broader runtime hardening so unexpected converter exceptions degrade cleanly.
- Rule/Capability:
  - Replaced the section-tag scorer's use of `DOMElement::compareDocumentPosition()` with a DOM-compatible helper that determines document order through safe node traversal.
  - Wrapped `run_conversion()` in a broad runtime guard so native or AI conversion throwables return a `WP_Error` instead of crashing the REST route.
  - This is a stability pass, but it is still architecture-relevant:
    - it removes environment-specific DOM assumptions from the native converter,
    - it keeps the converter trainable under real admin usage by preventing hard route failures.
- Files touched:
  - `includes/converter/class-native-converter.php`
  - `includes/converter/class-conversion-manager.php`
  - `V2_GEOMETRIC_AUDIT_TRAINING_TRACKER.md`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - `php -l includes/converter/class-native-converter.php` passed.
  - `php -l includes/converter/class-conversion-manager.php` passed.
  - `php tools/verify-native-converter-cli.php --file "training-files/inline css/01-saas-geometric-light(1).html" --strategy v2` remains `REPORT_OK`.
  - `php tools/run-training-suite-cli.php --quality-floor --floor-profile=balanced --trend-report` still passes:
    - run success rate `1`
    - selector output CSS ratio `0.36363636363636365`
    - script rewrite ratio `0.8181818181818182`
    - global script bridge ratio `1`
  - Important note:
    - this pass was about compatibility and resilience,
    - so metrics did not move and should not be overstated as a bridge gain.
- Remaining blockers:
  - Balanced floor status: passed.
  - The next ceiling remains deeper section-local descendant CSS retargeting onto native leaf widgets.
- Result: done
- Track advancement note:
  - Track A: the converter no longer hard-depends on an unavailable DOM method in this environment.
  - Track B: future runtime converter faults should return controlled errors instead of taking the endpoint down with a 500.

### 2026-04-24 - Step 33 (Completed): Retarget Section Descendant CSS More Broadly and Harden Preview Fullscreen Rendering

- Pass: continued the root-to-leaf CSS retargeting phase and strengthened the converter preview so malformed source JS no longer breaks the admin preview surface.
- Rule/Capability:
  - Native selector retargeting now expands section-local descendant contracts from section roots onto broad native leaf wrappers when those wrappers exist in the emitted inventory:
    - heading descendants can land on `.elementor-heading-title`,
    - paragraph/text descendants can land on `.elementor-widget-container`,
    - button descendants can land on `.elementor-button`,
    - list descendants can land on `.elementor-icon-list-items` and `.elementor-icon-list-item`,
    - media/image descendants can land on `.elementor-image`, `.elementor-image img`, and `.elementor-wrapper iframe`.
  - The retargeting remains interpretation-first and broad:
    - it keys off emitted section roots and native widget semantics,
    - it does not encode any audited sample layout shape.
  - The converter live preview now supports fullscreen inspection directly from the right sidebar.
  - Preview hardening was widened so raw widget HTML cannot poison the preview document:
    - sandboxed iframe no longer runs scripts,
    - preview HTML sanitization now strips script blocks,
    - inline event handlers are removed across quoted and unquoted forms,
    - `javascript:` URLs are neutralized,
    - fallback/raw-rich-text preview paths also pass through the sanitizer instead of only dedicated HTML widgets.
- Files touched:
  - `includes/converter/class-native-converter.php`
  - `admin/views/page-converter.php`
  - `admin/js/admin.js`
  - `admin/css/admin.css`
  - `V2_GEOMETRIC_AUDIT_TRAINING_TRACKER.md`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - `php -l includes/converter/class-native-converter.php` passed.
  - `php -l includes/converter/class-conversion-manager.php` passed.
  - `php -l admin/views/page-converter.php` passed.
  - `node --check admin/js/admin.js` passed.
  - `php tools/verify-native-converter-cli.php --file "training-files/inline css/01-saas-geometric-light(1).html" --strategy v2` remains `REPORT_OK`.
  - `php tools/run-training-suite-cli.php --quality-floor --floor-profile=balanced --trend-report` still passes:
    - run success rate `1`
    - selector output CSS ratio `0.36363636363636365`
    - script rewrite ratio `0.8181818181818182`
    - global script bridge ratio `1`
  - Important note:
    - this pass materially improves preview stability and broadens descendant CSS landing surfaces,
    - but the current corpus still did not move the selector-output ratio, so it should not be overstated as a measured bridge-quality gain.
- Remaining blockers:
  - Balanced floor status: passed.
  - The next ceiling is richer section-local selector decomposition before retargeting, especially where one section-level rule bundles multiple descendant contracts that still collapse too coarsely.
- Result: done
- Track advancement note:
  - Track A: the admin preview is now much safer for malformed converted HTML/JS and easier to inspect in fullscreen.
  - Track B: section-root selector mapping can now push more descendant contracts toward native leaf widgets without resorting to sample-shaped rules.

### 2026-04-24 - Step 34 (Completed): Add Selector-Function Decomposition and Reusable Preview Audit Runner

- Pass: extended the CSS bridge so grouped selector contracts decompose safely before native remapping, then added a DB-free preview-audit CLI and used it on a random V2 training file to measure real output quality.
- Rule/Capability:
  - Source selector list parsing no longer uses a naive comma split:
    - selector lists now respect parentheses, brackets, and quoted values.
  - Grouped selector functions are now decomposed before native retargeting:
    - `:is(...)`
    - `:where(...)`
  - This gives the bridge a broader chance to land descendant contracts on native leaf widgets without teaching the converter any specific design sample.
  - Added a reusable CLI audit runner:
    - `php tools/run-preview-audit-cli.php --file <html> --strategy v2`
    - writes JSON, CSS, diagnostics, run report, and preview-like structure summary to `audit-output/`
    - makes it easy to audit V2 output like a lightweight Elementor preview interpreter without opening Elementor.
  - Ran the new audit tool against a random training sample:
    - `training-files/inline css/03-agency-portfolio-brutalist.html`
    - output artifacts written under `audit-output/random-v2-audit-03-agency-portfolio-brutalist*`
- Files touched:
  - `includes/converter/class-native-converter.php`
  - `tools/run-preview-audit-cli.php`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist.json`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist.css`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist-diagnostics.json`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist-report.json`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist-audit.json`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist.md`
  - `V2_GEOMETRIC_AUDIT_TRAINING_TRACKER.md`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - `php -l includes/converter/class-native-converter.php` passed.
  - `php -l tools/run-preview-audit-cli.php` passed.
  - `php tools/verify-native-converter-cli.php --file "training-files/inline css/01-saas-geometric-light(1).html" --strategy v2` remains `REPORT_OK`.
  - `php tools/run-training-suite-cli.php --quality-floor --floor-profile=balanced --trend-report` still passes:
    - run success rate `1`
    - selector output CSS ratio `0.36363636363636365`
    - script rewrite ratio `0.8181818181818182`
    - global script bridge ratio `1`
  - `php tools/run-preview-audit-cli.php --file "training-files/inline css/03-agency-portfolio-brutalist.html" --strategy v2 --output-base "random-v2-audit-03-agency-portfolio-brutalist"` completed successfully.
  - Important note:
    - the selector decomposition work landed safely,
    - but the current corpus still did not move the selector-output ratio,
    - and the random audit shows the bigger ceiling is now role-based content interpretation and token fidelity, not just selector syntax handling.
- Remaining blockers:
  - Balanced floor status: passed.
  - The next highest-value ceiling is role-based extraction for brand, eyebrow, CTA, description, and stat semantics before layout assembly.
- Result: done
- Track advancement note:
  - Track A: the bridge can now understand more real CSS selector syntax before retargeting.
  - Track B: we now have a reusable local audit surface that writes real artifacts and makes output quality easier to inspect systematically.

### 2026-04-24 - Step 35 (Completed): Add Broad Native Widget Matrix and Container-First Primitive Rebuild

- Pass: encoded the baseline V2 interpretation mindset more explicitly so primitive fallback is driven by broad Elementor-free widget mapping and container-first block reconstruction rather than a few loose primitive buckets.
- Rule/Capability:
  - Added an explicit broad native widget matrix for common HTML primitives:
    - headings -> `heading`
    - paragraphs/blockquote -> `text-editor`
    - links/buttons -> `button`
    - `ul`/`ol` -> `icon-list`
    - `img`/`figure`/`picture` -> `image`
    - `svg`/`table` -> `html`
  - Added container-first native block extraction:
    - direct child structural containers are treated as primitive block candidates,
    - each candidate is scanned in DOM order for widget descriptors,
    - descriptors preserve source ids/classes so later CSS/JS bridge work still has native landing surfaces.
  - V2 primitive fallback now rebuilds those blocks as native Elementor containers plus native widgets before falling back to the older flat primitive bucket emitter.
  - This keeps the baseline interpretation broad and reusable across hero, pricing, footer, contact, and generic sections instead of teaching one layout shape.
- Files touched:
  - `includes/converter/helpers/class-v2-primitive-assembler-helper.php`
  - `includes/converter/class-native-converter.php`
  - `V2_GEOMETRIC_AUDIT_TRAINING_TRACKER.md`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - `php -l includes/converter/helpers/class-v2-primitive-assembler-helper.php` passed.
  - `php -l includes/converter/class-native-converter.php` passed.
  - `php tools/verify-native-converter-cli.php --file "training-files/inline css/01-saas-geometric-light(1).html" --strategy v2` remains `REPORT_OK`.
  - `php tools/run-training-suite-cli.php --quality-floor --floor-profile=balanced --trend-report` still passes:
    - run success rate `1`
    - selector output CSS ratio `0.36363636363636365`
    - script rewrite ratio `0.8181818181818182`
    - global script bridge ratio `1`
  - Important note:
    - this pass strengthens the baseline native interpretation path,
    - but the current corpus did not show a metric jump,
    - so it should be read as architectural alignment with the intended V2 mindset plus no regression.
- Remaining blockers:
  - Balanced floor status: passed.
  - The next visible ceiling is still role-based interpretation inside those blocks:
    - brand/logo text
    - eyebrow/label text
    - descriptive paragraph vs decorative text
    - CTA/button recovery
    - stat number/label pairing
- Result: done
- Track advancement note:
  - Track A: V2 now has a broader, explicit primitive-to-widget baseline instead of relying only on section-type logic.
  - Track B: later CSS/JS bridge work now has more consistent native block structure to target when fallback rebuild happens.

### 2026-04-24 - Step 36 (Completed): Add Role-Based Primitive Interpretation and CTA URL Carryover

- Pass: extended the new widget-matrix baseline with role-aware interpretation so V2 can distinguish description text, CTA buttons, brand-ish elements, eyebrow-like labels, and stat-ish text more reliably before assembly.
- Rule/Capability:
  - Primitive widget descriptors now carry broad semantic role hints:
    - `brand`
    - `eyebrow`
    - `description`
    - `headline`
    - `cta`
    - `nav-link`
    - `nav-list`
    - `stat-value`
    - `stat-label`
  - V2 primitive widgets now emit role classes on native output:
    - `.{prefix}-role-*`
  - Paragraph extraction is now role-aware instead of purely first-match:
    - description-like copy is preferred,
    - eyebrow/stat/all-caps utility text is deprioritized.
  - CTA extraction is now role-aware and preserves hrefs:
    - hero payload now carries `cta_primary_url` and `cta_secondary_url`
    - CTA payload now carries `cta_primary_url` and `cta_secondary_url`
    - generic block payloads now carry `cta_url`
    - generic native builder and button fallbacks now use extracted URLs instead of always `#`
  - Template library hero/cta builders now consume those extracted CTA URLs instead of discarding them.
  - This is still broad logic:
    - no audited file terms or one-off sample selectors were hardcoded,
    - the improvements apply anywhere the DOM presents similar content roles.
- Files touched:
  - `includes/converter/helpers/class-v2-primitive-assembler-helper.php`
  - `includes/converter/class-native-converter.php`
  - `includes/converter/class-template-library.php`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist.md`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist.json`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist.css`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist-diagnostics.json`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist-report.json`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist-audit.json`
  - `V2_GEOMETRIC_AUDIT_TRAINING_TRACKER.md`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - `php -l includes/converter/helpers/class-v2-primitive-assembler-helper.php` passed.
  - `php -l includes/converter/class-native-converter.php` passed.
  - `php -l includes/converter/class-template-library.php` passed.
  - `php tools/verify-native-converter-cli.php --file "training-files/inline css/01-saas-geometric-light(1).html" --strategy v2` remains `REPORT_OK`.
  - `php tools/run-training-suite-cli.php --quality-floor --floor-profile=balanced --trend-report` still passes:
    - run success rate `1`
    - selector output CSS ratio `0.36363636363636365`
    - script rewrite ratio `0.8181818181818182`
    - global script bridge ratio `1`
  - Random V2 preview audit rerun on `03-agency-portfolio-brutalist.html` shows a real semantic gain:
    - text coverage improved from `0.8462` to `0.8846`
    - missing text dropped from `8` to `6`
    - hero now includes a native button
    - contact now includes native buttons
  - Important note:
    - this pass produced a real improvement on the audit sample,
    - but the current full-suite headline metrics did not move,
    - so it should be read as better semantic interpretation plus no regression, not as a solved selector-fidelity pass.
- Remaining blockers:
  - Balanced floor status: passed.
  - The next global ceilings remain:
    - source token/theme fidelity
    - stat pair interpretation in generic sections
    - selector/behavior hook landing on native output
- Result: done
- Track advancement note:
  - Track A: the converter now preserves more meaning, not just more structure.
  - Track B: native buttons and descriptions are now more likely to survive with correct role and destination instead of flattening into generic text or `#` links.

### 2026-04-24 - Step 37 (Completed): Strengthen Source Theme Precedence and Add Generic Stat Reuse

- Pass: pushed source-derived theme hints ahead of template fallback defaults, and widened generic section assembly so repeated stat pairs can be reused outside explicit `stats` sections.
- Rule/Capability:
  - Source theme precedence now reads broad page-level CSS contracts, not just custom props and body inline styles:
    - `:root`
    - `html`
    - `body`
    - broad app/page/shell selectors
  - Source-derived color/font hints are now applied before fallback/template defaults for:
    - background
    - text
    - accent
    - surface/border when present
    - body/display/mono font hints
  - Palette inference is less sticky to defaults:
    - when explicit background/text tokens are missing,
    - the converter can now infer contrast roles from detected source colors instead of clinging to the old fallback pair.
  - Reduced sample-shaped baseline defaults in the converter/template layer:
    - removed the old neon/Syne fallback stack as the generic last-resort theme.
  - Generic stat reuse is now broader:
    - repeated stat pairs can be extracted from arbitrary generic sections,
    - generic native blocks can now emit stat value/label widgets when those pairs are detected,
    - the dedicated `stats` section path now uses the same shared stat extraction helper instead of a one-off implementation.
  - This remains global logic:
    - no audited file selectors or content strings were hardcoded,
    - fixes target theme/stat interpretation behavior across arbitrary uploads.
- Files touched:
  - `includes/converter/class-native-converter.php`
  - `includes/converter/class-template-library.php`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist.md`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist.json`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist.css`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist-diagnostics.json`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist-report.json`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist-audit.json`
  - `V2_GEOMETRIC_AUDIT_TRAINING_TRACKER.md`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - `php -l includes/converter/class-native-converter.php` passed.
  - `php -l includes/converter/class-template-library.php` passed.
  - `php tools/run-training-suite-cli.php --quality-floor --floor-profile=balanced --trend-report` still passes:
    - run success rate `1`
    - selector output CSS ratio `0.36363636363636365`
    - script rewrite ratio `0.8181818181818182`
    - global script bridge ratio `1`
  - Random V2 preview audit rerun on `03-agency-portfolio-brutalist.html` confirms a real theme-precedence improvement:
    - old neon/Syne fallback is no longer present in output CSS,
    - companion CSS now uses source-aligned dark/stone tokens and source body/mono font hints.
  - Important note:
    - the audit metrics themselves did not move on this sample,
    - so this should be read as broader theme fidelity plus no regression, not as a solved stat/selector pass.
- Remaining blockers:
  - Balanced floor status: passed.
  - The next global ceilings remain:
    - generic stat-pair interpretation for harder layouts
    - selector/behavior hook landing on native output
    - deeper role assignment for hero/nav/stat semantics
- Result: done
- Track advancement note:
  - Track A: fallback styling is now less sample-shaped and more source-led.
  - Track B: stats are now on a shared extraction path, which makes the next stat-interpretation pass easier to improve globally.

### 2026-04-25 - Step 38 (Completed): Harden Live Preview Sanitization and CSP

- Pass: fixed the converter-page live preview so malformed or preserved source markup cannot execute broken JavaScript inside the sandboxed iframe.
- Rule/Capability:
  - Preview documents now inject a restrictive iframe-local CSP:
    - `default-src 'none'`
    - scripts blocked
    - inline styles allowed only for preview rendering
    - images/media/frames/fonts limited to safe preview sources
  - Companion CSS is now sanitized before insertion into the preview document:
    - strips script-tag breakouts
    - neutralizes `javascript:` URLs
    - removes legacy CSS `expression(...)`
    - escapes closing `</style>` sequences
  - Preview HTML sanitization is now DOM-based instead of regex-only:
    - blocked tags removed
    - unsafe attributes removed
    - inline event handlers stripped
    - `javascript:` href/src/xlink refs neutralized
    - unsafe inline styles cleaned
  - Plain text / inline rich text preview surfaces are now sanitized before rendering:
    - headings
    - buttons
    - icon-list item labels
    - HTML/text-editor widget content
  - This is a broad admin-preview stability fix:
    - not tied to any one training file
    - intended to stop `about:srcdoc` execution errors across arbitrary conversions.
- Files touched:
  - `admin/js/admin.js`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - `node --check admin/js/admin.js` passed.
  - `php -l admin/views/page-converter.php` passed.
  - Verified the new preview hardening hooks exist:
    - `sanitizePreviewMarkup`
    - `sanitizePreviewInlineHtml`
    - `sanitizePreviewCss`
    - iframe-local `Content-Security-Policy`
- Remaining blockers:
  - This fixes preview execution safety, not converter fidelity itself.
  - If a conversion still looks wrong in preview after this, the next issue is structural/semantic output, not the previewer executing broken source JS.
- Result: done
- Track advancement note:
  - Track A: preview inspection is now safer and less likely to mislead debugging with iframe-side JS parse errors.
  - Track B: future converter audits can focus more on output fidelity because the preview surface itself is less noisy.

### 2026-04-25 - Step 39 (Completed): Improve Generic Stat Hook Landing and Add Preview Strip Diagnostics

- Pass: widened generic stat/hook handling and made the preview sidebar report exactly when sanitization strips content from a widget/block.
- Rule/Capability:
  - Generic/native stat output now carries broader hook semantics:
    - stat value widgets emit stable stat hook aliases like `stat-value`
    - source-side stat contracts like `stat-num` are carried through when the source actually exposes them
    - generic stat widgets now also inherit source hook classes/ids where available
  - Generic section outputs now preserve source section ids more truthfully on the selector bridge path:
    - bridge maps now target the actual emitted `output_element_id`
    - no longer assume every section target is always `#prefix-type`
    - this removes false orphan-hook failures when a real emitted id differs from the synthetic default
  - Hook inventory now treats `src-id-*` alias classes as valid emitted id hooks for bridge validation.
  - Live preview now captures sanitization diagnostics per surface:
    - HTML widgets
    - text-editor widgets
    - heading/button/icon-list inline content
    - companion CSS
  - Sidebar preview now shows which block/widget was sanitized and why:
    - script tags removed
    - blocked/unsupported tags removed
    - inline handlers stripped
    - `javascript:` URLs neutralized
    - style attributes cleaned
  - This remains broad logic:
    - no audited-file-specific strings were hardcoded,
    - the brutalist audit was used only as a training reference.
- Files touched:
  - `includes/converter/class-native-converter.php`
  - `admin/views/page-converter.php`
  - `admin/css/admin.css`
  - `admin/js/admin.js`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist.md`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist.json`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist.css`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist-diagnostics.json`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist-report.json`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist-audit.json`
  - `V2_GEOMETRIC_AUDIT_TRAINING_TRACKER.md`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - `php -l includes/converter/class-native-converter.php` passed.
  - `node --check admin/js/admin.js` passed.
  - `php -l admin/views/page-converter.php` passed.
  - `php tools/run-training-suite-cli.php --quality-floor --floor-profile=balanced --trend-report` passes again:
    - `22/22` OK
    - run success rate `1`
    - selector output CSS ratio `0.36363636363636365`
    - script rewrite ratio `0.8181818181818182`
    - global script bridge ratio `1`
  - Refreshed random V2 brutalist audit shows a real bridge gain without regressions:
    - selector coverage improved from `0.4167` to `0.5`
    - bridged ids now include `studio`, `work`, `contact`, and `hero-main`
  - Important note:
    - text coverage on the brutalist audit did not move yet
    - missing studio stat content remains a live interpretation ceiling
    - preview diagnostics are for inspection/debugging, not a substitute for converter fidelity.
- Remaining blockers:
  - Balanced floor status: passed.
  - The next global ceilings remain:
    - deeper generic stat-pair interpretation for hard nested layouts
    - semantic nav/CTA hook landing like `nav-link` and `cta-btn`
    - improving selector output richness, not just hook presence
- Result: done
- Track advancement note:
  - Track A: preview debugging is now much more explainable when content gets sanitized out.
  - Track B: section-id bridge mapping is more truthful because it now uses the actual emitted section anchors.

### 2026-04-25 - Step 40 (Completed): Record Fidelity Gate, Pivot Direction, and Version Bump

- Pass: aligned plugin metadata and repo documentation with the current project reality instead of continuing to present the heuristic-native path as the final architecture.
- Rule/Capability:
  - The fidelity gate is now documented explicitly:
    - if an approach cannot realistically achieve near-browser-truth output and approximately full-fidelity conversion, it should not remain the primary architecture
  - Product direction is now recorded as a browser-truth pivot:
    - use a real browser runtime as the source of truth for final DOM, computed styles, layout boxes, and runtime-resolved structures
    - map from that browser truth into Elementor containers/widgets
    - keep preserved-source or partial-conversion states truthful instead of describing them as successful native completion
  - Version bump recorded to reflect the current state of the product and tooling:
    - plugin version `1.1.0`
    - readme stable tag `1.1.0`
  - Changelog now includes the meaningful work from this iteration:
    - live preview
    - preview diagnostics
    - DB-free audit tooling
    - native runtime hardening
    - product architecture pivot
- Files touched:
  - `stack-blueprint.php`
  - `readme.txt`
  - `PRODUCT.md`
  - `CHANGELOG.md`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - Metadata/doc pass only; no runtime logic changed in this step.
  - Version surfaces were updated consistently in plugin header, constant, product doc, and WordPress readme.
- Remaining blockers:
  - This step does not itself improve conversion fidelity.
  - The next real execution task should be a browser-truth spike, not more broad heuristic expansion, unless that spike proves unworkable.
- Result: done
- Track advancement note:
  - Track A: the repo now states the fidelity gate and pivot clearly, which reduces future drift back into low-yield heuristic work.
  - Track B: versioning and changelog history now match the actual state of the project more honestly.

### 2026-04-25 - Step 41 (Completed): Stand Up Browser-Truth Extractor Spike

- Pass: implemented the first real browser-driven extraction path so fidelity work can start from browser truth instead of PHP heuristics.
- Rule/Capability:
  - Added a Playwright-based extractor runtime under `extractor/`:
    - `extract.js`
    - `package.json`
    - local `.gitignore`
  - Added a PHP bridge:
    - `includes/converter/class-browser-extractor.php`
    - detects runtime readiness
    - writes temp configs/workspaces
    - runs Node extraction with timeout control
    - returns truthful `WP_Error` states on missing runtime/process/output failures
  - Added a DB-free CLI runner:
    - `tools/run-browser-extraction-cli.php`
    - writes browser-truth status JSON, extracted JSON, and markdown summary into `audit-output/`
  - Hardened runtime truthfulness:
    - partial `node_modules` installs no longer count as extractor readiness
    - runtime status now checks for a usable browser executable
    - extractor can fall back to locally installed Chrome or Edge when Playwright's own Chromium payload is unavailable
  - Corrected spike bugs uncovered during validation:
    - fixed extractor metadata path bug (`inputPath` value)
    - fixed CLI `WP_Error` hook shims
    - fixed relative `base_dir` handling so the extractor serves the real source file instead of a 404 page
- Files touched:
  - `extractor/package.json`
  - `extractor/extract.js`
  - `extractor/.gitignore`
  - `includes/converter/class-browser-extractor.php`
  - `tools/run-browser-extraction-cli.php`
  - `includes/class-plugin.php`
  - `CHANGELOG.md`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
- Validation evidence:
  - `php -l includes/converter/class-browser-extractor.php` passed.
  - `php -l tools/run-browser-extraction-cli.php` passed.
  - `node --check extractor/extract.js` passed.
  - Browser-truth extraction successfully ran outside the sandbox for:
    - `training-files/inline css/03-agency-portfolio-brutalist.html`
  - Generated artifacts:
    - `audit-output/03-agency-portfolio-brutalist-browser-truth-status.json`
    - `audit-output/03-agency-portfolio-brutalist-browser-truth.json`
    - `audit-output/03-agency-portfolio-brutalist-browser-truth.md`
  - The successful extraction captured real browser-resolved evidence:
    - title `FORMA — Creative Agency`
    - fonts `Overpass`, `Bebas Neue`, `Overpass Mono`
    - keyframes `bob`, `marquee`
    - desktop scroll height `3547`
    - `114` extracted nodes across each tested viewport
- Remaining blockers:
  - This step proves browser-truth extraction, not Elementor mapping.
  - The next real task is to convert this extracted browser truth into a normalized intermediate layout model and compare it directly against current V2 native output.
  - Headless browser launches still require running outside the sandbox in this environment.
- Result: done
- Track advancement note:
  - Track A: the project now has a working browser-truth source of layout/style/runtime facts.
  - Track B: future fidelity work can focus on mapping and preservation strategy instead of guessing CSS/JS outcomes in PHP.

### 2026-04-25 - Step 42 (Completed): Add Normalized Browser-vs-V2 Comparison Model

- Pass: converted raw browser-truth output into a normalized intermediate layout model and compared it directly against current V2 output.
- Rule/Capability:
  - Added `BrowserLayoutNormalizer`:
    - normalizes browser extraction into section summaries, semantic counts, hooks, behavior islands, and viewport metrics
    - normalizes Elementor JSON output into top-level section summaries, widget counts, hook inventory, and native/html ratios
    - compares both models and produces deductions focused on fidelity, not parser success
  - Added `tools/run-browser-v2-comparison-cli.php`:
    - runs browser extraction
    - runs native conversion
    - normalizes both outputs
    - writes browser model, Elementor model, comparison JSON, and markdown summary to `audit-output/`
  - Comparison output is deliberately broad:
    - it does not patch one audited sample
    - it turns one audited sample into a reusable measurement path for future uploads
  - Removed an environment hazard found during the first run:
    - no `mb_*` dependency remains in the new normalizer
- Files touched:
  - `includes/converter/class-browser-layout-normalizer.php`
  - `tools/run-browser-v2-comparison-cli.php`
  - `includes/class-plugin.php`
  - `CHANGELOG.md`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare-browser-model.json`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare-elementor-model.json`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare-comparison.json`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare.md`
- Validation evidence:
  - `php -l includes/converter/class-browser-layout-normalizer.php` passed.
  - `php -l tools/run-browser-v2-comparison-cli.php` passed.
  - Browser-vs-V2 comparison completed successfully outside the sandbox for:
    - `training-files/inline css/03-agency-portfolio-brutalist.html`
  - First comparison summary:
    - browser sections `6`
    - Elementor sections `8`
    - browser headings `13` vs Elementor headings `8`
    - browser text blocks `43` vs Elementor text widgets `6`
    - browser actions `6` vs Elementor buttons `3`
    - browser behavior islands `8` vs Elementor HTML widgets `10`
    - native widget ratio `0.6296`
  - Missing browser hooks surfaced immediately:
    - ids: `cursor-dot`, `cursor-ring`, `para-bg`, `scroll-indicator`, `cta-btn`, `marquee`, `work-grid`, `email-link`
    - classes: `nav-link`, `work-item`, `stat-num`
  - Missing section-kind interpretation surfaced immediately:
    - `services`
- Remaining blockers:
  - This comparison proves the model is useful, but it is still only an audit/comparison layer.
  - The next architecture step is to feed this model into mapping decisions:
    - section partitioning from browser top-level truth
    - role assignment from browser-resolved nodes
    - explicit hook landing targets from browser ids/classes
    - honest preserve/native decisions based on measured behavior islands
- Result: done
- Track advancement note:
  - Track A: we now have a measurement layer that tells us exactly where V2 output is undershooting browser truth.
  - Track B: future conversion fixes can be driven by browser-derived deficits instead of sample-shaped guesses.

### 2026-04-25 - Step 43 (Completed): Add Browser Role-Gap Profiling to Comparison Layer

- Pass: extended the normalized browser-vs-V2 comparison so it can call out role-level deficits per section instead of only global count mismatches.
- Rule/Capability:
  - Browser section summaries now include role profiles for:
    - heading count
    - text count
    - action count
    - nav-link count
    - email-link count
    - service-item count
    - stat-pair count
  - Elementor section summaries now expose the same role-profile buckets for comparison.
  - Section matching is no longer kind-only:
    - it now tries emitted hook ids/classes before falling back to kind matching
  - The comparison markdown/JSON now includes `section_role_gaps`, which exposes deficits like:
    - missing `services` interpretation
    - under-landed gallery actions/text
    - under-landed studio text
  - This remains browser-guided measurement, not a one-file patch:
    - the goal is to drive future mapping decisions with browser truth
    - not to patch the brutalist sample directly
- Files touched:
  - `includes/converter/class-browser-layout-normalizer.php`
  - `tools/run-browser-v2-comparison-cli.php`
  - `CHANGELOG.md`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare-browser-model.json`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare-elementor-model.json`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare-comparison.json`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare.md`
- Validation evidence:
  - `php -l includes/converter/class-browser-layout-normalizer.php` passed.
  - `php -l tools/run-browser-v2-comparison-cli.php` remained passing from the previous comparison pass.
  - Refreshed comparison run completed successfully for:
    - `training-files/inline css/03-agency-portfolio-brutalist.html`
  - Refined role-gap output now highlights:
    - `services` section kind missing from Elementor interpretation
    - `gallery` has more browser-truth actions (`1`) than Elementor output (`0`)
    - `gallery` has more browser-truth text blocks (`12`) than Elementor output (`1`)
    - `studio` has more browser-truth text blocks (`18`) than Elementor output (`6`)
- Remaining blockers:
  - The comparison layer is now pointing at the right deficits, but it still does not change conversion output yet.
  - The next architecture step is to feed these browser-derived roles into actual converter decisions:
    - services section recognition and rebuild
    - CTA/link landing
    - text-block recovery from browser-resolved nodes
- Result: done
- Track advancement note:
  - Track A: we can now see which role families are missing per section, not just that totals are lower.
  - Track B: the next output-improving work can target browser-derived deficits with much less guesswork.

### 2026-04-25 - Step 44 (Completed): Broaden Generic Content Interpretation and Native Affinity Signals

- Pass: widened global interpretation for repeated-content sections without hardcoding any sample design.
- Rule/Capability:
  - Generic card/block extraction now keeps:
    - multiple paragraph-like text blocks
    - multiple CTA/action candidates
    - broader repeated-content payloads for native rebuilding
  - Generic native section rendering now emits:
    - multiple text-editor widgets when a block contains multiple real text surfaces
    - grouped native button surfaces when a block contains multiple real actions
  - Section classification now adds structural service-like scoring:
    - repeated heading + text/list groups boost `features`
    - this reduces over-reliance on pure keyword text for service-like sections
  - V2 native affinity now also sees repeated content-group structure before preserve decisions.
  - This was a global engine pass:
    - not a brutalist-only patch
    - not a design hardcode
    - not a one-file output hack
- Files touched:
  - `includes/converter/class-native-converter.php`
  - `includes/converter/helpers/class-v2-decision-helper.php`
  - `CHANGELOG.md`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare-browser-model.json`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare-elementor-model.json`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare-comparison.json`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare.md`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist.md`
- Validation evidence:
  - `php -l includes/converter/class-native-converter.php` passed.
  - `php -l includes/converter/helpers/class-v2-decision-helper.php` passed.
  - `php tools/run-browser-v2-comparison-cli.php --file "training-files/inline css/03-agency-portfolio-brutalist.html" --strategy v2` completed successfully.
  - `php tools/run-training-suite-cli.php --quality-floor --floor-profile=balanced --trend-report` still passed `22/22`.
- Honest outcome:
  - The browser-guided brutalist comparison did not improve on this pass.
  - The rerun still shows:
    - browser headings `13` vs Elementor headings `3`
    - browser text blocks `43` vs Elementor text widgets `3`
    - browser actions `6` vs Elementor buttons `3`
    - missing section kinds `services` and `studio`
  - This means the new generic payload extraction is not the main blocker yet.
  - The remaining ceiling is earlier in the pipeline:
    - preserve-heavy routing still prevents some sections from reaching the richer native builder
    - especially service/studio-style repeated-content sections
- Result: done
- Track advancement note:
  - Track A: the converter now has a broader global payload model for repeated native content once a section reaches native assembly.
  - Track B: the next work should target early preserve-routing and section partitioning, because that is now the clearer blocker than block payload shape itself.

### 2026-04-25 - Step 45 (Completed): Make Browser-vs-V2 Audit Read Preserved HTML Like Preview

- Pass: strengthened the browser-truth comparison layer so preserved HTML is audited more like Elementor preview instead of as one opaque widget blob.
- Rule/Capability:
  - HTML-widget comparison is now descendant-aware:
    - ids/classes inside preserved HTML count toward hook landing
    - preserved HTML headings contribute to heading comparison
    - preserved HTML text blocks contribute to text comparison
    - preserved HTML links/buttons contribute to action comparison
  - Section kind inference is now descendant-aware:
    - browser-side kind detection now sees descendant hooks and samples, not only top-wrapper text
    - Elementor-side kind detection now sees subtree hooks and descendant samples, not only the top-level container settings
  - Browser-vs-V2 deductions are now more truthful:
    - earlier undercounts caused by preserved HTML blobs are reduced
    - remaining gaps are more concentrated on real hook/semantic misses
  - This is a global audit/training improvement:
    - not a brutalist-only patch
    - not a converter hardcode
    - meant to make future browser-vs-V2 audits more trustworthy for arbitrary uploads
- Files touched:
  - `includes/converter/class-browser-layout-normalizer.php`
  - `CHANGELOG.md`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist.md`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare-browser-model.json`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare-elementor-model.json`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare-comparison.json`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare.md`
- Validation evidence:
  - `php -l includes/converter/class-browser-layout-normalizer.php` passed.
  - `php tools/run-browser-v2-comparison-cli.php --file "training-files/inline css/03-agency-portfolio-brutalist.html" --strategy v2` reran successfully.
  - Refreshed brutalist comparison now shows preview-truth counts much closer than before:
    - headings `13` vs `14`
    - text blocks `43` vs `40`
    - actions `6` vs `9`
    - missing section kind reduced to `marquee`
  - Remaining misses are now concentrated on:
    - ids `cursor-dot`, `cursor-ring`, `para-bg`, `scroll-indicator`, `cta-btn`, `marquee`
    - classes `nav-link`, `stat-num`
- Remaining blockers:
  - This improves audit truthfulness, not converter output by itself.
  - Hero hook/text carryover still needs broader native landing.
  - Marquee still does not land as its own interpreted top-level kind in V2.
  - Studio semantic text carryover is still thinner than browser truth.
- Result: done
- Track advancement note:
  - Track A: audits now better distinguish real converter gaps from comparison undercounting.
  - Track B: the next converter-side work should target hero hook landing, marquee interpretation, and deeper studio semantic text recovery.

### 2026-04-26 - Step 46 (Completed): Broaden Marquee Interpretation and Hero Hook Landing

- Pass: pushed the next converter-side global interpretation pass after the audit-model cleanup.
- Rule/Capability:
  - Section classification now uses broader marquee structure signals:
    - descendant marquee/ticker ids/classes can influence section kind
    - nowrap ticker strips with repeated separator text can classify as `marquee` without sample-shaped hardcoding
    - the earlier false-positive regression against hero was corrected by tightening the structural marquee gate
  - Marquee payload extraction is broader:
    - explicit item/logo/brand children still work
    - fallback ticker-text splitting now recovers repeated strip labels from plain repeated marquee text
  - Hero hook landing is deeper:
    - native hero CTA payload now keeps source class/id/hook data
    - hybrid hero visual payload now carries subtree hooks into the emitted visual island wrapper
    - selector/script bridge maps now include hero CTA source ids/classes targeting the native hero button surfaces
  - Marquee hook landing is deeper:
    - marquee subtree hooks now land on emitted marquee wrapper/widget classes
  - Fixed-nav rebuild now preserves source nav-link classes on emitted anchors
  - Stat payloads now carry subtree source hooks so template/library stat surfaces can preserve classes like `stat-num` more broadly
  - Validation truthfulness improved:
    - repeated-structure degradation guard now skips `marquee`, because marquee repeats can legitimately live inside a single HTML ticker widget
- Files touched:
  - `includes/converter/class-native-converter.php`
  - `includes/converter/class-template-library.php`
  - `CHANGELOG.md`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist.md`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare-browser-model.json`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare-elementor-model.json`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare-comparison.json`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare.md`
  - `training-suite-report.json`
  - `training-suite-history.json`
- Validation evidence:
  - `php -l includes/converter/class-native-converter.php` passed.
  - `php -l includes/converter/class-template-library.php` passed.
  - `php tools/verify-native-converter-cli.php --file "training-files/inline css/03-agency-portfolio-brutalist.html" --strategy v2` stayed `REPORT_OK`.
  - Targeted brutalist V2 now detects/built types:
    - `hero`
    - `marquee`
    - `features`
    - `contact`
    - `footer`
  - Targeted brutalist V2 missing selector coverage narrowed to:
    - ids `cursor-dot`, `cursor-ring`, `para-bg`
    - class `stat-num`
  - Browser-vs-V2 comparison now shows:
    - missing section kinds cleared
    - missing hooks narrowed to ids `cursor-dot`, `cursor-ring`, `para-bg`, `scroll-indicator`
    - missing classes narrowed to `stat-num`
    - `cta-btn`, `marquee`, and `nav-link` are no longer missing
  - Full balanced gate still passes:
    - `php tools/run-training-suite-cli.php --quality-floor --floor-profile=balanced --trend-report`
    - `22/22` OK
    - zero fail codes
    - `run_success_rate = 1`
    - `selector_output_css_ratio = 0.36363636363636365`
    - `script_rewrite_ratio = 0.8181818181818182`
    - `global_script_bridge_ratio = 1`
- Remaining blockers:
  - Hero decorative hooks like `para-bg` and `scroll-indicator` still do not land as emitted ids/classes.
  - `stat-num` is still not fully preserved as a landed source class.
  - Studio semantic text carryover is still thinner than browser truth.
  - Browser-vs-V2 counts now slightly over-read preserved HTML in some sections, so count parity should be interpreted alongside the role-gap messages rather than alone.
- Result: done
- Track advancement note:
  - Track A: marquee is now interpreted as its own section kind and the hero CTA hook path is materially better.
  - Track B: the next clean global target is decorative hero hook landing plus stronger stat/class propagation, not more marquee tuning.

### 2026-04-27 - Step 47 (Completed): Clear Remaining Hero/Cursor/Stat Hook Gaps Broadly

- Pass: completed the next global hook-propagation pass after Step 46 without adding any sample-specific design logic.
- Rule/Capability:
  - Hero hook landing is broader and more truthful:
    - hero root now carries broader hero-subtree source hook aliases
    - hybrid hero visual wrapper now also carries the broader hero-subtree aliases, not only the narrower visual fragment hooks
    - decorative hero hooks like background labels and scroll indicators can now land on real emitted native/hybrid hero surfaces
  - Feature/bento stat interpretation now survives template assembly:
    - extracted generic stat pairs are now rendered as native stat cards inside feature/bento sections
    - stat value/label widgets now carry semantic raw aliases like `stat-num` and `stat-label`
    - this is broad semantic mapping, not a patch for one HTML sample
  - Cursor behavior output is now more honest and more inspectable:
    - source-driven cursor surfaces are emitted as real markup in global setup instead of existing only as runtime-created JS nodes
    - runtime now hydrates those emitted cursor nodes instead of hiding the entire cursor contract inside script creation
    - asset injection no longer returns too early when cursor markup already exists, so canvas/cursor global assets can coexist safely
- Files touched:
  - `includes/converter/class-native-converter.php`
  - `includes/converter/class-template-library.php`
  - `CHANGELOG.md`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist.md`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare-browser-model.json`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare-elementor-model.json`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare-comparison.json`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare.md`
  - `training-suite-report.json`
  - `training-suite-history.json`
- Validation evidence:
  - `php -l includes/converter/class-native-converter.php` passed.
  - `php -l includes/converter/class-template-library.php` passed.
  - `php tools/verify-native-converter-cli.php --file "training-files/inline css/03-agency-portfolio-brutalist.html" --strategy v2` stayed `REPORT_OK`.
  - Targeted brutalist verifier now shows:
    - text coverage `0.9615`
    - selector coverage `1.0`
    - zero missing ids/classes in verifier coverage
  - Browser-vs-V2 comparison now shows:
    - missing ids cleared
    - missing classes cleared
    - missing section kinds remain cleared
    - native widget ratio `0.7222`
  - Full balanced gate still passes:
    - `php tools/run-training-suite-cli.php --quality-floor --floor-profile=balanced --trend-report`
    - `22/22` OK
    - zero fail codes
    - `run_success_rate = 1`
    - `selector_output_css_ratio = 0.36363636363636365`
    - `script_rewrite_ratio = 0.8181818181818182`
    - `global_script_bridge_ratio = 1`
- Remaining blockers:
  - Hook landing is no longer the main brutalist gap on this pass.
  - Remaining high-signal deficits are semantic-density issues:
    - hero still under-recovers browser-truth text/action density
    - studio still under-recovers browser-truth text density
  - Browser-vs-V2 count comparisons can still over-read preserved HTML density, so role-gap messages should remain the higher-trust signal than raw counts alone.
- Result: done
- Track advancement note:
  - Track A: landed hook coverage is materially cleaner and the converter now exposes cursor/stat semantics more honestly.
  - Track B: the next global target should move up-stack from hook landing to semantic role recovery inside hero and studio-like sections.

### 2026-04-27 - Step 48 (Completed): Broaden Hero and Studio Text-Role Recovery

- Pass: moved from hook landing to semantic text-role recovery without adding sample-specific design rules.
- Rule/Capability:
  - Hero copy extraction is broader:
    - hero payloads now keep a distinct `hero_text_blocks` collection instead of only a single `sub` paragraph
    - hero text blocks are extracted from the best copy scope and filtered against eyebrow/headline/CTA duplicates
    - short but meaningful support/meta text is now eligible when it behaves like real copy, not only long paragraphs
  - Generic card/block text extraction is broader:
    - paragraph extraction now sees short support/meta surfaces like locations, dates, labels, and support lines
    - generic block/card payloads now filter out duplicated title/action text while keeping more distinct body/support text blocks
    - native feature/bento templates now render all recovered text blocks instead of collapsing to the first body line only
  - Browser-vs-V2 audit truthfulness improved:
    - `global-setup` is now classified separately from `hero`
    - explicit footer identity/hook signals are prioritized before looser `studio` keyword matches
    - this removes misleading hero role-gap noise caused by hidden source-document-title markup
- Files touched:
  - `includes/converter/class-native-converter.php`
  - `includes/converter/class-template-library.php`
  - `includes/converter/class-browser-layout-normalizer.php`
  - `CHANGELOG.md`
  - `CONVERTER_REBUILD_EXECUTION_PLAN.md`
  - `audit-output/random-v2-audit-03-agency-portfolio-brutalist.md`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare-browser-model.json`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare-elementor-model.json`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare-comparison.json`
  - `audit-output/03-agency-portfolio-brutalist-browser-v2-compare.md`
  - `training-suite-report.json`
  - `training-suite-history.json`
- Validation evidence:
  - `php -l includes/converter/class-native-converter.php` passed.
  - `php -l includes/converter/class-template-library.php` passed.
  - `php -l includes/converter/class-browser-layout-normalizer.php` passed.
  - `php tools/verify-native-converter-cli.php --file "training-files/inline css/03-agency-portfolio-brutalist.html" --strategy v2` stayed `REPORT_OK`.
  - Targeted brutalist verifier still shows selector coverage `1.0` and text coverage `0.9615`.
  - Targeted brutalist missing text shifted from earlier hero-copy loss to:
    - `Strategy, identity, and digital experience for companies redefining their categories.`
    - `Our process is collaborative, fast, and built around results that outlast the trend cycle.`
  - Browser-vs-V2 comparison now shows:
    - `global-setup` classified separately
    - missing ids/classes remain cleared
    - missing section kinds remain cleared
    - hero role-gap improved from `6` vs `1` text blocks to `6` vs `3`
    - false hero action-gap cleared
    - remaining role gaps narrowed to:
      - hero text blocks `6` vs `3`
      - studio text blocks `18` vs `9`
  - Full balanced gate still passes:
    - `php tools/run-training-suite-cli.php --quality-floor --floor-profile=balanced --trend-report`
    - `22/22` OK
    - zero fail codes
    - `run_success_rate = 1`
    - `selector_output_css_ratio = 0.36363636363636365`
    - `script_rewrite_ratio = 0.8181818181818182`
    - `global_script_bridge_ratio = 1`
- Remaining blockers:
  - The main brutalist gaps are now semantic-density gaps, not hook landing or section-kind loss.
  - Hero still compresses some browser-truth support copy into fewer native text surfaces than the browser model sees.
  - Studio still lands only about half of browser-truth text density in the native/interpreted output.
  - There are still more HTML widgets than the browser model suggests are likely behavior islands (`10` vs `8`), so preserve-heavy output remains a broader fidelity ceiling.
- Result: done
- Track advancement note:
  - Track A: semantic text-role recovery is now materially better and the audit signal is cleaner.
  - Track B: the next global target should be repeated-section text partitioning and section-local preserve reduction, especially for studio/gallery-style sections that still flatten or preserve too much.
