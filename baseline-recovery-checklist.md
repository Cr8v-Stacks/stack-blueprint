# Baseline Recovery Checklist

Sprint 1 recovery baseline for the native converter.

## Locked Baseline
- `testupgradev5-elementor.json`
- `testupgradev5-elementor.css`
- `nexus-landing.html`

## Comparison Fixtures
- `testolodo-elementor.json`
- `my-project1-elementor.json`

## Pass Conditions
- No placeholder leakage such as `{{heading}}` or `{{text}}`
- No unresolved Google Fonts `var(--font-...)` tokens in Global Setup
- No empty top-level section shells for generated container sections
- No unsafe generic prefixes such as `card`, `cad`, `nl`, `wp`, `el`
- No duplicate `_element_id` values in final output

## Sprint 1 Scope
- Make server-side prefix generation authoritative
- Reject unsafe prefixes instead of silently accepting them
- Fail loudly on known-bad output states
- Preserve `testupgradev5` behavior while tightening validation

## Not In Sprint 1
- Tailwind resolution
- CSS fingerprint skills
- Simulation corpus integration
- Correction UI
- Interactive repair flow
