# Simulation Corpus — Developer Implementation Report
## All 3 Rounds | 46 Simulations | Ready to Code

**For:** Lead Developer  
**Purpose:** Implement the pattern library, classifier rules, and companion CSS logic derived from simulation rounds 1–3  
**Source files:** `simulation-corpus-v1.json`, `simulation-corpus-v2.json`, `simulation-corpus-v3.json`

---

## How to Read This Report

Each section maps directly to a PHP class or JSON config file in the plugin. Every rule has a simulation ID so you can trace it back to the source data. Implement in priority order — P1 items have the highest accuracy impact.

**Priority scale:**
- **P1** — Blocks correct output. Implement before any testing.
- **P2** — Significant accuracy improvement. Implement in sprint 1.
- **P3** — Edge case coverage. Implement in sprint 2.
- **P4** — Polish and completeness. Can ship without these initially.

---

## 1. HARD RULES ENGINE
**File:** `src/Classifier/PriorityRulesEngine.php`  
**Runs before:** Pass 3 (Content Classification)  
**Behaviour:** If a hard rule matches, return immediately. No confidence cascade. No fallback.

### Rules to Implement

**[P1] RULE-001: position:fixed → HTML_WIDGET**
```
Trigger: resolved CSS position === 'fixed' on any element
Action: HTML_WIDGET classification, confidence 1.0
Reason: Elementor containers establish stacking contexts that trap position:fixed
Source: SIM-026
Exception: check RULE-011 first (decorative overlay test) — position:fixed on an overlay with pointer-events:none → DECORATIVE_OVERLAY not HTML_WIDGET
```

**[P1] RULE-002: <canvas> element → HTML_WIDGET_CANVAS**
```
Trigger: element tagName === 'canvas'
Action: HTML_WIDGET_CANVAS — moved to Global Setup body injection, not a template section
Confidence: 1.0
Source: SIM-016 derivative
```

**[P1] RULE-003: JS text mutation → HTML_WIDGET_ANIMATED**
```
Trigger: A <script> block in the same section contains querySelector/getElementById AND 
         (.textContent = OR .innerHTML = OR .innerText =) targeting this element's class or ID
Action: HTML_WIDGET_ANIMATED, confidence 1.0
Reason: JS overwrites Elementor's rendered content at runtime — native widget will be overwritten
Source: SIM-016 (stat counters with count-up animation)
```

**[P1] RULE-004: <table> element → HTML_WIDGET**
```
Trigger: element tagName === 'table'
Action: HTML_WIDGET, confidence 1.0
Reason: Elementor Free has no native table widget
Source: SIM-076
Report note: "Table detected. Content is editable in HTML widget source. Each row = <tr>, each cell = <td>."
```

**[P1] RULE-005: CSS Columns layout → HTML_WIDGET**
```
Trigger: resolved CSS property 'columns' is set AND value is not 'auto' OR 'column-count' is set
Action: HTML_WIDGET, confidence 1.0
Reason: CSS Columns (masonry-style) has no Elementor container equivalent
Source: SIM-079
Report note: "CSS Columns/masonry layout preserved as HTML widget. Elementor has no native equivalent."
```

**[P1] RULE-006: HTML_WIDGET_COPY_VERBATIM**
```
Trigger: After any element is classified as HTML_WIDGET or HTML_WIDGET_ANIMATED
Action: Copy entire innerHTML of that element verbatim into the widget HTML field.
        STOP descending into its children for further classification.
        Do NOT apply display:none SKIP rule to any descendant.
Reason: Prevents partial classification of HTML widget internals (tabs, accordions, filters)
Source: SIM-080
```

**[P2] RULE-007: Decorative overlay → SKIP + companion CSS ::before**
```
Trigger: ALL of the following must be true:
  - position: absolute OR position: fixed
  - No text content (textContent.trim() === '')
  - No interactive children (<a>, <button>, input)
  - pointer-events: none OR no JS click/hover listeners
  - (z-index < 1 OR inset:0 OR width:100%+height:100%)
Action: SKIP from JSON. Extract CSS to parent element's companion CSS as ::before pseudo-element.
Reason: Gradient meshes, noise overlays, decorative lines are visual chrome, not content
Source: SIM-081 (gradient mesh), SIM-069 (timeline decorative line)
NOTE: Run this check BEFORE RULE-001. A decorative overlay with position:fixed should hit this rule, not the HTML_WIDGET rule.
```

**[P2] RULE-008: Tab/Accordion/Filter system → HTML_WIDGET**
```
Trigger: ANY of:
  a) Element has data-tab, data-panel, data-filter, data-category attribute
  b) Element is <button> that is sibling of a display:none <div>
  c) Associated <script> contains style.display toggle referencing sibling elements
Action: HTML_WIDGET for the entire component (nav buttons + content panels together)
Reason: Show/hide toggling is JS-driven and inseparable from the content
Source: SIM-070 (tabs), SIM-071 (accordion), SIM-077 (filter chips)
CRITICAL: display:none siblings of these trigger elements must NOT be skipped
```

---

## 2. DISPLAY:NONE SKIP RULE — SCOPING FIX
**File:** `src/Classifier/ElementClassifier.php`  
**Priority: P1**

The current (broken) behaviour: any element with `display:none` → SKIP.

**Correct behaviour — three-case logic:**

```php
public function shouldSkipElement(DOMElement $el, StyleMap $styles): bool {
    
    // Case 1: Inside an HTML_WIDGET boundary → NEVER skip (RULE-006)
    if ($this->isInsideHtmlWidgetBoundary($el)) {
        return false;
    }
    
    // Case 2: Part of a tab/accordion/filter system → NEVER skip (RULE-008)
    if ($this->isPartOfToggleSystem($el)) {
        return false;
    }
    
    // Case 3: Truly static hidden element (no JS reference) → skip
    $isHidden = $styles->get($el, 'display') === 'none' 
             || $styles->get($el, 'visibility') === 'hidden';
    $hasJsReference = $this->scriptReferencesElement($el);
    $hasToggleAttribute = $el->hasAttribute('data-tab') 
                       || $el->hasAttribute('data-panel')
                       || $el->hasAttribute('data-filter')
                       || $el->hasAttribute('data-category');
    
    return $isHidden && !$hasJsReference && !$hasToggleAttribute;
}
```

---

## 3. TAILWIND RESOLVER
**File:** `src/PrePass/TailwindResolver.php`  
**Priority: P1 — without this, Tailwind HTML scores 31% accuracy**

### Detection

```php
public function isTailwindHtml(DOMDocument $dom): bool {
    // Check 1: Element has 6+ classes with known Tailwind prefixes
    // Check 2: Presence of arbitrary value syntax: text-[...], bg-[...]
    // Check 3: Presence of modifier prefixes: hover:, before:, sm:, md:, lg:
    // If 2+ checks pass → Tailwind detected
}
```

### Static Utility Map
Implement as a PHP array constant. Key entries from simulation corpus:

```php
const UTILITY_MAP = [
    // Layout
    'flex' => 'display:flex',
    'grid' => 'display:grid',
    'block' => 'display:block',
    'hidden' => 'display:none',
    'flex-col' => 'flex-direction:column',
    'flex-row' => 'flex-direction:row',
    'flex-wrap' => 'flex-wrap:wrap',
    'flex-nowrap' => 'flex-wrap:nowrap',
    'items-start' => 'align-items:flex-start',
    'items-center' => 'align-items:center',
    'items-end' => 'align-items:flex-end',
    'items-stretch' => 'align-items:stretch',
    'justify-start' => 'justify-content:flex-start',
    'justify-center' => 'justify-content:center',
    'justify-end' => 'justify-content:flex-end',
    'justify-between' => 'justify-content:space-between',
    'justify-around' => 'justify-content:space-around',
    'self-start' => 'align-self:flex-start',
    'self-center' => 'align-self:center',
    'self-end' => 'align-self:flex-end',
    'self-stretch' => 'align-self:stretch',
    // Sizing
    'w-full' => 'width:100%',
    'h-full' => 'height:100%',
    'min-h-screen' => 'min-height:100vh',
    'min-h-full' => 'min-height:100%',
    'max-w-full' => 'max-width:100%',
    // Grid
    'grid-cols-1' => 'grid-template-columns:repeat(1,minmax(0,1fr))',
    'grid-cols-2' => 'grid-template-columns:repeat(2,minmax(0,1fr))',
    'grid-cols-3' => 'grid-template-columns:repeat(3,minmax(0,1fr))',
    'grid-cols-4' => 'grid-template-columns:repeat(4,minmax(0,1fr))',
    'grid-cols-6' => 'grid-template-columns:repeat(6,minmax(0,1fr))',
    'grid-cols-12' => 'grid-template-columns:repeat(12,minmax(0,1fr))',
    'col-span-1' => 'grid-column:span 1/span 1',
    'col-span-2' => 'grid-column:span 2/span 2',
    'col-span-3' => 'grid-column:span 3/span 3',
    'col-span-4' => 'grid-column:span 4/span 4',
    'col-span-5' => 'grid-column:span 5/span 5',
    'col-span-6' => 'grid-column:span 6/span 6',
    'col-span-full' => 'grid-column:1/-1',
    'row-span-1' => 'grid-row:span 1/span 1',
    'row-span-2' => 'grid-row:span 2/span 2',
    'row-span-3' => 'grid-row:span 3/span 3',
    'row-span-4' => 'grid-row:span 4/span 4',
    'row-span-5' => 'grid-row:span 5/span 5',
    // Typography
    'font-thin' => 'font-weight:100',
    'font-light' => 'font-weight:300',
    'font-normal' => 'font-weight:400',
    'font-medium' => 'font-weight:500',
    'font-semibold' => 'font-weight:600',
    'font-bold' => 'font-weight:700',
    'font-extrabold' => 'font-weight:800',
    'font-black' => 'font-weight:900',
    'italic' => 'font-style:italic',
    'not-italic' => 'font-style:normal',
    'text-left' => 'text-align:left',
    'text-center' => 'text-align:center',
    'text-right' => 'text-align:right',
    'tracking-tighter' => 'letter-spacing:-0.05em',
    'tracking-tight' => 'letter-spacing:-0.025em',
    'tracking-normal' => 'letter-spacing:0',
    'tracking-wide' => 'letter-spacing:0.025em',
    'tracking-wider' => 'letter-spacing:0.05em',
    'tracking-widest' => 'letter-spacing:0.1em',
    'leading-none' => 'line-height:1',
    'leading-tight' => 'line-height:1.25',
    'leading-snug' => 'line-height:1.375',
    'leading-normal' => 'line-height:1.5',
    'leading-relaxed' => 'line-height:1.625',
    'leading-loose' => 'line-height:2',
    'text-transparent' => 'color:transparent',
    'text-white' => 'color:#ffffff',
    'text-black' => 'color:#000000',
    'uppercase' => 'text-transform:uppercase',
    'lowercase' => 'text-transform:lowercase',
    'capitalize' => 'text-transform:capitalize',
    // Position
    'relative' => 'position:relative',
    'absolute' => 'position:absolute',
    'fixed' => 'position:fixed',
    'sticky' => 'position:sticky',
    'inset-0' => 'top:0;right:0;bottom:0;left:0',
    'pointer-events-none' => 'pointer-events:none',
    'overflow-hidden' => 'overflow:hidden',
    'overflow-auto' => 'overflow:auto',
    // Misc
    'border-b' => 'border-bottom-width:1px;border-bottom-style:solid',
    'border-t' => 'border-top-width:1px;border-top-style:solid',
    'border' => 'border-width:1px;border-style:solid',
    'rounded' => 'border-radius:4px',
    'rounded-lg' => 'border-radius:8px',
    'rounded-xl' => 'border-radius:12px',
    'rounded-full' => 'border-radius:9999px',
    'opacity-0' => 'opacity:0',
    'opacity-50' => 'opacity:0.5',
    'opacity-100' => 'opacity:1',
    'transition' => 'transition:all 0.15s ease',
    'transition-all' => 'transition:all 0.15s ease',
    'whitespace-nowrap' => 'white-space:nowrap',
    'cursor-pointer' => 'cursor:pointer',
    'select-none' => 'user-select:none',
];
```

### Arbitrary Value Resolver

```php
public function resolveArbitraryValue(string $prefix, string $value): ?string {
    $map = [
        'text'     => "font-size:{$value}",
        'bg'       => "background-color:{$value}",
        'w'        => "width:{$value}",
        'h'        => "height:{$value}",
        'max-w'    => "max-width:{$value}",
        'min-h'    => "min-height:{$value}",
        'p'        => "padding:{$value}",
        'px'       => "padding-left:{$value};padding-right:{$value}",
        'py'       => "padding-top:{$value};padding-bottom:{$value}",
        'pt'       => "padding-top:{$value}",
        'pb'       => "padding-bottom:{$value}",
        'pl'       => "padding-left:{$value}",
        'pr'       => "padding-right:{$value}",
        'm'        => "margin:{$value}",
        'mx'       => "margin-left:{$value};margin-right:{$value}",
        'my'       => "margin-top:{$value};margin-bottom:{$value}",
        'gap'      => "gap:{$value}",
        'tracking' => "letter-spacing:{$value}",
        'leading'  => "line-height:{$value}",
        'top'      => "top:{$value}",
        'bottom'   => "bottom:{$value}",
        'left'     => "left:{$value}",
        'right'    => "right:{$value}",
        'z'        => "z-index:{$value}",
        'opacity'  => "opacity:{$value}",
    ];
    return $map[$prefix] ?? null;
}

// Handle opacity modifier: text-white/55 → color:rgba(255,255,255,0.55)
public function resolveOpacityModifier(string $colorClass, string $opacity): ?string {
    $opacityDecimal = (int)$opacity / 100;
    if ($colorClass === 'text-white') return "color:rgba(255,255,255,{$opacityDecimal})";
    if ($colorClass === 'text-black') return "color:rgba(0,0,0,{$opacityDecimal})";
    if ($colorClass === 'bg-white')   return "background-color:rgba(255,255,255,{$opacityDecimal})";
    if ($colorClass === 'bg-black')   return "background-color:rgba(0,0,0,{$opacityDecimal})";
    return null;
}
```

### Modifier Prefix Handling

```php
public function resolveModifier(string $modifier, string $baseClass): ?string {
    switch ($modifier) {
        case 'hover':
            // Collect for companion CSS as :hover rule
            $this->hoverRules[] = ['class' => $this->currentElement, 'css' => $this->resolve($baseClass)];
            return null; // not an inline style
        case 'before':
        case 'after':
            // Collect for companion CSS as ::before/::after
            $this->pseudoRules[] = ['element' => $modifier, 'css' => $this->resolve($baseClass)];
            return null;
        case 'sm':  return null; // @media (min-width:640px) → responsive CSS
        case 'md':  return null; // @media (min-width:768px)
        case 'lg':  return null; // @media (min-width:1024px)
        default:    return null;
    }
}
```

---

## 4. CSS PROPERTY FINGERPRINTER
**File:** `src/Classifier/CSSPropertyFingerprinter.php`  
**Priority: P1 — required for obfuscated HTML (22% → 65%)**

Runs when vocabulary-based classification returns confidence < 0.5. Classifies by resolved CSS property profile.

```php
const FINGERPRINTS = [
    'button' => [
        'required' => [
            ['property' => 'background-color', 'condition' => 'not_transparent'],
            ['property' => 'padding', 'condition' => 'all_sides_gte_8px'],
        ],
        'supporting' => [
            ['property' => 'cursor', 'value' => 'pointer', 'weight' => 0.3],
            ['property' => 'font-weight', 'condition' => 'gte_600', 'weight' => 0.2],
            ['property' => 'letter-spacing', 'condition' => 'gt_0', 'weight' => 0.15],
            ['property' => 'display', 'value' => 'inline-flex', 'weight' => 0.2],
        ],
        'base_confidence' => 0.85,
        'classification' => 'BUTTON_WIDGET',
    ],
    'ghost_link' => [
        'required' => [
            ['property' => 'border-bottom-width', 'condition' => 'gt_0'],
            ['property' => 'background-color', 'condition' => 'transparent_or_absent'],
            ['tag' => 'a'],
        ],
        'supporting' => [
            ['property' => 'color', 'condition' => 'has_opacity_lt_0.7', 'weight' => 0.2],
            ['property' => 'font-family', 'condition' => 'monospace', 'weight' => 0.2],
        ],
        'base_confidence' => 0.8,
        'classification' => 'TEXT_EDITOR', // ghost link = text editor with CSS class
    ],
    'eyebrow_label' => [
        'required' => [
            ['property' => 'font-size', 'condition' => 'lte_13px'],
            ['property' => 'letter-spacing', 'condition' => 'gte_0.1em'],
        ],
        'supporting' => [
            ['property' => 'text-transform', 'value' => 'uppercase', 'weight' => 0.3],
            ['property' => 'font-family', 'condition' => 'monospace', 'weight' => 0.25],
            ['dom_position' => 'immediately_before_heading', 'weight' => 0.4],
        ],
        'base_confidence' => 0.82,
        'classification' => 'HTML_WIDGET', // eyebrow needs ::before line decoration
    ],
    'hero_container' => [
        'required' => [
            ['property' => 'min-height', 'condition' => 'gte_80vh'],
            ['property' => 'display', 'value' => 'flex'],
        ],
        'supporting' => [
            ['property' => 'flex-direction', 'value' => 'column', 'weight' => 0.3],
            ['property' => 'justify-content', 'value' => 'flex-end', 'weight' => 0.3],
            ['dom_position' => 'first_major_section', 'weight' => 0.4],
            ['contains_tag' => 'h1', 'weight' => 0.4],
        ],
        'base_confidence' => 0.88,
        'classification' => 'SECTION_CONTAINER', // hero section
    ],
    'display_heading' => [
        'required' => [
            ['property' => 'font-size', 'condition' => 'gte_60px'],
            ['property' => 'font-weight', 'condition' => 'gte_700'],
        ],
        'supporting' => [
            ['tag' => ['h1', 'h2', 'h3'], 'weight' => 0.4],
            ['property' => 'letter-spacing', 'condition' => 'lt_0', 'weight' => 0.2],
            ['property' => 'line-height', 'condition' => 'lt_1.2', 'weight' => 0.2],
        ],
        'base_confidence' => 0.87,
        'classification' => 'HEADING_WIDGET',
    ],
    'subtitle' => [
        'required' => [
            ['property' => 'font-size', 'condition' => 'range_14_20px'],
            ['property' => 'color', 'condition' => 'has_opacity_lt_0.7'],
        ],
        'supporting' => [
            ['property' => 'max-width', 'condition' => 'set', 'weight' => 0.2],
            ['property' => 'font-weight', 'condition' => 'lte_400', 'weight' => 0.15],
            ['tag' => 'p', 'weight' => 0.2],
            ['dom_position' => 'follows_heading', 'weight' => 0.3],
        ],
        'base_confidence' => 0.78,
        'classification' => 'TEXT_EDITOR',
    ],
    'avatar_circle' => [
        'required' => [
            ['property' => 'border-radius', 'condition' => 'gte_50_percent_or_gte_40px'],
            ['property' => 'width', 'condition' => 'equals_height'],
        ],
        'supporting' => [
            ['property' => 'background-color', 'condition' => 'set', 'weight' => 0.3],
            ['property' => 'display', 'value' => 'flex', 'weight' => 0.2],
            ['text_content_length' => 'lte_3', 'weight' => 0.4], // initials
        ],
        'base_confidence' => 0.85,
        'classification' => 'HTML_WIDGET', // avatar + surrounding author block
    ],
    'ordinal_marker' => [
        'required' => [
            ['text_content_pattern' => '^0?[1-9][0-9]?$'], // 01-99
            ['property' => 'font-size', 'condition' => 'gte_36px'],
        ],
        'supporting' => [
            ['property' => 'color', 'condition' => 'has_opacity_lt_0.4', 'weight' => 0.5],
            ['property' => 'opacity', 'condition' => 'lt_0.4', 'weight' => 0.5],
        ],
        'base_confidence' => 0.88,
        'classification' => 'TEXT_EDITOR',
        'editability_override' => 3, // decorative number, low editability
    ],
];
```

---

## 5. GRID PROCESSOR
**File:** `src/Layout/GridProcessor.php`  
**Priority: P1 — required for bento grid conversion**

### Method 1: Simplify grid to fr values
```
Input: grid-template-columns value, array of child span values
Purpose: Convert "repeat(12, 1fr)" + spans [5,4,3] → "5fr 4fr 3fr"

Algorithm:
  1. Collect unique span values from children: e.g. [5, 4, 3]
  2. Check if sum of unique values = total column count: 5+4+3 = 12 ✓
  3. If yes → use unique values as fr proportions: "5fr 4fr 3fr"
  4. If no → find most common column grouping by GCD
  5. Fallback: equal fr columns based on max span value
```

### Method 2: Span to explicit placement
```
Input: ordered array of child elements with grid-column/row span values
Output: {grid_column_start, grid_column_end, grid_row_start, grid_row_end} per child

Algorithm:
  - Track current_col = 1, current_row = 1
  - For each child:
      col_span = parse 'span N' from grid-column, default 1
      row_span = parse 'span N' from grid-row, default 1
      If current_col + col_span - 1 > total_cols: wrap (current_col=1, current_row++)
      Assign: col_start=current_col, col_end=current_col+col_span (exclusive)
              row_start=current_row, row_end=current_row+row_span (exclusive)
      Advance: current_col += col_span
```

### Method 3: Parse fr values from any grid-template-columns string
```
Input: grid-template-columns CSS value string
Output: Elementor grid_columns_fr string

Cases to handle:
  "repeat(3, 1fr)"           → "1fr 1fr 1fr"
  "repeat(4, 1fr)"           → "1fr 1fr 1fr 1fr"
  "2fr 1fr 1fr 1fr"          → "2fr 1fr 1fr 1fr" (pass through)
  "1fr 2fr"                  → "1fr 2fr"
  "repeat(auto-fill, ...)"   → convert to closest fixed count, add to report
  "200px 1fr 1fr"            → "1fr 2fr 2fr" (approximate, note in report)
```

### Method 4: Row-reverse handling
```
Input: flex-direction: row-reverse on container
Output: decision + JSON modification

If child widths are equal (both flex:1 or both 50%):
  → Swap child order in JSON elements array
  → Set flex-direction: row in settings (normal row after swap)
  → Companion CSS: .nx-[class] { /* visual result same as row-reverse */ }

If child widths are unequal:
  → Keep child order as-is in JSON
  → Set flex-direction: row in settings
  → Companion CSS: .nx-[class] { flex-direction: row-reverse !important; }
```

---

## 6. COMPANION CSS GENERATOR — NEW RULES
**File:** `src/CSS/CompanionCSSGenerator.php`  
**Priority: P1 for first 4 rules, P2 for rest**

Add these generation rules to the CSS generator. Each triggers when the corresponding CSS property or pattern is detected.

### [P1] Single-side border
```
Trigger: Only one of border-top/right/bottom/left is set on an element
Action:
  1. In Elementor JSON settings: set border_border: "none"
  2. In companion CSS: .nx-[class] { border-[side]: [width] [style] [color]; }
Source: SIM-075
```

### [P1] align-self on child widget
```
Trigger: align-self property on an element that becomes a native widget
Action: Companion CSS targeting the Elementor wrapper
  .nx-[class].elementor-widget { align-self: [value]; }
  AND
  .nx-[class] .elementor-widget-container { align-self: [value]; }
Source: SIM-072
```

### [P1] calc() in padding/margin
```
Trigger: padding or margin value contains calc()
Action:
  1. In Elementor JSON: set the closest fixed px value as fallback
     (extract the largest px component from the calc expression)
  2. In companion CSS: .nx-[class] { padding: [original calc()]; }
     OR margin equivalent
Source: SIM-069
```

### [P1] Gradient text trio
```
Trigger: background-clip: text OR -webkit-background-clip: text present
Action: ALWAYS write all 3 properties together in companion CSS:
  .nx-[class] {
    background: [gradient value];
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }
Never write only one or two of these — they are inseparable
Source: SIM-055
```

### [P1] image aspect-ratio and object-fit
```
Trigger: aspect-ratio OR object-fit OR object-position on an img or its container
Action: Companion CSS on the IMAGE_WIDGET class
  .nx-[class] img {
    aspect-ratio: [value];      /* if present */
    object-fit: [value];        /* if present */
    object-position: [value];   /* if present */
    width: 100%;                /* always include for responsive */
  }
Source: SIM-074
```

### [P2] Hover cascade (parent:hover affects child)
```
Trigger: CSS rule contains :hover on parent selector AND targets a descendant
  e.g. ".process-step:hover .step-title { color: #c8ff00 }"
Action: Companion CSS using Elementor-aware selectors:
  For heading widget: .nx-[parent]:hover .nx-[child] .elementor-heading-title { [property]: [value]; }
  For text editor: .nx-[parent]:hover .nx-[child] .elementor-widget-text-editor { [property]: [value]; }
  For generic: .nx-[parent]:hover .nx-[child] { [property]: [value]; }
Source: SIM-046
```

### [P2] Pseudo-element extractor
```
Trigger: CSS rule with selector ending in ::before or ::after
Action: Add to companion CSS under the element's nx- class
  .nx-[class]::before { [all properties from the rule]; }
Special: 'content' property value containing brand text → preserve exactly as-is
Source: SIM-041 (CTA watermark), SIM-069 (timeline line), SIM-073 (eyebrow line)
```

### [P2] CSS animation cross-reference
```
Trigger: element has animation-name property value
Action:
  1. Find @keyframes [animation-name] declaration anywhere in document
  2. If found AND keyframes contain translateX → MARQUEE pattern → HTML_WIDGET
  3. If found AND keyframes contain opacity/scale only → may stay native, move to companion CSS
  4. If found AND keyframes contain translateY/rotate/complex → HTML_WIDGET_ANIMATED
Source: SIM-031 (marquee)
```

### [P3] Variable font handler
```
Trigger: font-variation-settings property present
Action:
  - Extract 'wght' axis value → use as font-weight in Elementor settings
  - All other axes (slnt, ital, wdth, etc.) → companion CSS:
    .nx-[class] .elementor-heading-title { font-variation-settings: [original value]; }
Source: SIM-056
```

### [P3] background-attachment: fixed mobile fix
```
Trigger: background-attachment: fixed
Action: Companion CSS with @supports wrapper to prevent mobile issues:
  @supports not (-webkit-overflow-scrolling: touch) {
    .nx-[class] { background-attachment: fixed; }
  }
Source: SIM-057
```

---

## 7. PATTERN LIBRARY
**File:** `config/PatternLibrary.json`  
**Priority: P2**

Compiled from all 46 simulations. Key pattern entries:

### Patterns and Their Minimum Confidence Thresholds

| Pattern | Min Confidence | Key Required Signal | Source Sims |
|---|---|---|---|
| hero | 0.65 | has_h1_tag (0.9) + largest_font_on_page (0.85) | SIM-001–005 |
| bento_grid | 0.70 | display_grid + children_different_spans (0.9) | SIM-006–007 |
| pricing | 0.75 | has_currency_symbol (0.95) + has_feature_list (0.8) | SIM-011 |
| stats_animated | 0.70 | data_target_attribute (0.9) + intersectionobserver (0.85) | SIM-016 |
| testimonials | 0.70 | quotation_marks (0.9) + person_name_and_title (0.8) | SIM-021 |
| fixed_nav | 1.0 | position_fixed (hard rule) | SIM-026 |
| marquee | 0.75 | keyframe_translateX (0.9) + overflow_hidden (0.85) | SIM-031 |
| footer | 0.75 | copyright_text (0.9) + multi_column_grid (0.85) | SIM-036 |
| cta_section | 0.70 | contrast_background (0.85) + heading_plus_buttons (0.8) | SIM-041 |
| process_steps | 0.70 | numbered_sequential_items (0.9) + title_desc_pairs (0.8) | SIM-046 |
| icon_feature_grid | 0.70 | icon_plus_heading_plus_text per cell (0.9) | SIM-067 |
| alternating_rows | 0.75 | image_text_pairs + row_reverse_alternate (0.85) | SIM-068 |
| timeline | 0.75 | alternating_left_right + date_labels (0.85) | SIM-069 |
| tabs | 0.80 | data_tab_attribute + display_none_panels (0.95) | SIM-070 |
| accordion | 0.75 | button_sibling_hidden_div + click_toggle (0.9) | SIM-071 |
| split_screen | 0.75 | 2_equal_cols + different_backgrounds (0.9) | SIM-072 |
| team_grid | 0.75 | photo_name_title per card (0.9) | SIM-074 |
| pull_quote | 0.70 | blockquote_tag + large_italic_font (0.8) | SIM-075 |
| filter_chips | 0.80 | data_filter_attribute + matching_content (0.95) | SIM-077 |

---

## 8. SVG ISOLATION RULE
**File:** `src/Classifier/ElementClassifier.php`  
**Priority: P2 — prevents icon grids losing editability**

```
Trigger: Inline <svg> inside a container with both width AND height <= 64px
Action:
  1. Classify the small container as HTML_WIDGET (icon only)
  2. Do NOT propagate HTML_WIDGET to the parent card
  3. Continue classifying parent card and text siblings as native widgets

Inverse rule (do NOT isolate):
  - SVG width or height > 64px → classify whole container as HTML_WIDGET_COMPLEX
  - SVG has click event → classify whole container as HTML_WIDGET_COMPLEX
  - SVG is the only child of a full-width container → HTML_WIDGET_COMPLEX

Source: SIM-067 (icon feature grid), SIM-074 (team grid social icons)
```

---

## 9. ELEMENTOR VIDEO BACKGROUND
**File:** `src/JSONAssembler/ContainerBuilder.php`  
**Priority: P2**

```
Trigger: <video autoplay muted loop> as position:absolute child of a section
  AND has sibling content layer with position:relative and z-index > 0

Action:
  1. Do NOT create HTML_WIDGET for the video
  2. Add video background settings to the PARENT container in JSON:
     background_background: "video"
     background_video_link: [extract from <source src="...">]
     background_play_once: "no"
  3. Classify the content sibling as native container with native widgets
  4. Report: "Upload video to WordPress Media Library and update URL in Background settings"

Source: SIM-058
```

---

## 10. GLOBAL SETUP WIDGET — CDN AUTO-INJECTION
**File:** `src/GlobalSetup/GlobalSetupBuilder.php`  
**Priority: P2**

The Global Setup HTML widget must auto-include CDN scripts when these libraries are detected:

| Library | Detection | CDN URL |
|---|---|---|
| GSAP | `gsap.to(` OR `gsap.from(` OR `TweenLite` | `https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js` |
| GSAP ScrollTrigger | `ScrollTrigger` | `https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js` |
| Lottie | `lottie.loadAnimation(` OR `<lottie-player` | `https://cdnjs.cloudflare.com/ajax/libs/bodymovin/5.12.2/lottie.min.js` |
| Three.js | `new THREE.` OR `import * as THREE` | `https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js` |

Script tags are added to Global Setup `<head>` section in order of dependency.

Report entry when CDN injected: "GSAP detected — CDN script added to Global Setup. Animation will function on published page. Test on frontend, not editor preview."

---

## 11. BODY-LEVEL ELEMENTS LIST
**File:** `src/GlobalSetup/BodyLevelElementsList.php`  
**Priority: P1 — prevents fixed-position rendering bugs**

These element types must ALWAYS be body-injected via `document.body.appendChild()` in the Global Setup script, never placed as Elementor sections:

```php
const BODY_LEVEL_PATTERNS = [
    // Detection pattern => injection type
    'canvas'                    => 'CANVAS_INJECTION',      // particle bg
    'custom_cursor'             => 'CURSOR_INJECTION',      // cursor dot+ring
    'preloader'                 => 'PRELOADER_INJECTION',   // splash screen
    'floating_chat'             => 'SKIP',                  // recommend plugin
    'cookie_banner'             => 'SKIP',                  // recommend plugin
];

// Detection for preloader:
// position:fixed + z-index >= 9000 + (window.addEventListener('load') hides it)
// id/class contains: preloader, loader, splash, intro-screen, loading-screen

// Detection for cookie banner:
// id/class contains: cookie, consent, gdpr, ccpa, privacy-banner
// Contains accept/decline/reject buttons
// Action: SKIP from template entirely, add to report:
// "Cookie consent banner excluded. Use CookieYes, Complianz, or GDPR Cookie Consent plugin."
```

---

## 12. ASSET REPORT GENERATOR
**File:** `src/Output/AssetReportGenerator.php`  
**Priority: P2 — third output file alongside JSON and CSS**

Generate a third output file: `[project]-asset-report.md`

Track these asset types during conversion:

```php
const ASSET_TYPES = [
    'external_image' => [
        'detection' => 'img[src] starting with http OR //  AND not fonts.googleapis.com',
        'report_message' => 'Download and upload to WordPress Media Library. Update src in {location}.',
    ],
    'svg_image' => [
        'detection' => 'img[src] ending with .svg',
        'report_message' => 'SVG file — ensure SVG support is enabled in WordPress (plugin: SVG Support or Safe SVG). Upload to Media Library.',
    ],
    'local_video' => [
        'detection' => 'video > source[src] not starting with http',
        'report_message' => 'Upload video to WordPress Media Library. Update URL in container Background > Video settings.',
    ],
    'lottie_json' => [
        'detection' => 'lottie.loadAnimation path: OR src: value ending in .json',
        'report_message' => 'Upload Lottie JSON file to WordPress uploads directory. Update path in HTML widget.',
    ],
    'local_font' => [
        'detection' => '@font-face src: url() pointing to local file',
        'report_message' => 'Upload font files to theme directory. Update @font-face src in companion CSS.',
    ],
];
```

Report format:

```markdown
# Asset Report — [Project Name]

## Action Required

### Images (N files)
| File | Used In | Action |
|------|---------|--------|
| hero-bg.jpg | Hero section, background | Upload to Media Library, update URL |

### Videos (N files)
...

### Fonts (N files)
...

## No Action Required
- Google Fonts: loaded automatically via Global Setup
- External CDN scripts: [list] — added to Global Setup automatically
```

---

## 13. POST-IMPORT VALIDATION PAGE
**File:** `src/Admin/PostImportValidator.php`  
**Priority: P3**

WordPress admin page at `wp-admin/?page=nexus-converter-validate`

Checklist shown after import:

```
□ Template imported (check via Elementor Template API)
□ Global Setup widget present as first element
□ Companion CSS added (check if .nx-reveal rule exists in current CSS)
□ Google Fonts loading (test request to fonts.googleapis.com URL from template)
□ Element IDs present (check major sections have _element_id set)
□ No Pro-only widgets (check widgetType against free whitelist)
□ Asset report reviewed (show outstanding items from asset report)
```

Each failed item shows a one-click fix or direct link to where to fix it.

---

## 14. IMPLEMENTATION ORDER (SPRINT PLAN)

### Sprint 1 — Core Accuracy (P1 items)
1. Hard Rules Engine (Rules 001–008)
2. Display:none SKIP rule scoping fix
3. Tailwind Resolver with static map + arbitrary value resolver
4. HTML_WIDGET_COPY_VERBATIM rule

### Sprint 2 — Pattern Library + CSS (P2 items)
5. CSS Property Fingerprinter (6 fingerprints)
6. Grid Processor (4 methods)
7. Companion CSS: single-side border, align-self, calc(), gradient text trio, image CSS
8. SVG Isolation Rule
9. Pattern Library JSON (all 19 patterns)
10. Body-Level Elements List
11. Elementor Video Background native converter

### Sprint 3 — Coverage + Polish (P3 items)
12. Companion CSS: hover cascade, pseudo-element extractor, animation cross-reference
13. CDN Auto-Injection (GSAP, Lottie, Three.js)
14. Asset Report Generator
15. Post-Import Validation Page
16. Modifier prefix handling in Tailwind Resolver (hover:, before:, sm:, md:, lg:)

### Sprint 4 — Hardening
17. Row-reverse handler (reorder vs companion CSS)
18. Variable font handler
19. background-attachment:fixed mobile fix
20. Ordinal marker editability override
21. Round 4 simulations (accordion edge cases, auto-fill grid, masonry variants)

---

## 15. TESTING CHECKLIST

Each simulation in the corpus becomes a test case. For each:

1. Input: the `input_html_pattern` from the simulation JSON
2. Expected output: the `corrected_output` structure
3. Pass criterion: widget types match, CSS classes present, confidence score within 0.1 of target

Run `php artisan test:simulations` after each sprint to measure accuracy against the full corpus.

Target accuracy at each sprint end:
- After Sprint 1: 85% on semantic, 75% on Tailwind, 65% on obfuscated
- After Sprint 2: 91% on semantic, 82% on Tailwind, 67% on obfuscated
- After Sprint 3: 93% on semantic, 84% on Tailwind, 68% on obfuscated

---

*All rule source references (SIM-XXX) trace back to the simulation JSON files. When a rule produces unexpected output, find the simulation, check the corrected_output section, and the fix is there.*
