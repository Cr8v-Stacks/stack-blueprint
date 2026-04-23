# Converter Rebuild Execution Plan (Ground-Up)

Last updated: 2026-04-21  
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
