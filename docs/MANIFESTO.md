
***
## <a name="manifesto"> </a>THE BLAZY MANIFESTO
*A performance system, not a shortcut.*

Blazy is intentionally opinionated by design.

This is not accidental, and not a limitation — it is a requirement for building
performant media delivery at scale.

---

### 1. Blazy Is Not a Feature Module

Blazy is not “just lazy-loading”.

Lazy-loading is a [**primitive**](#why-cwv).
Blazy is a [**coordination layer**](#resource-manager).

Native lazy-loading is necessary — but not sufficient.

Blazy exists to solve the **coordination problem** between [layout](#layouts),
[media](#media-architecture), [JavaScript](#javascript), and
[rendering order](#cls) interact under [real content](#content-architecture),
[real ditors](/filter/tips), and [real production constraints](#optimization)
— the exact areas where CWV regressions usually occur.

If your site only renders static images in isolation, Blazy may indeed feel
unnecessary. That is not its target environment.

---

### 2. Native Features Are Embraced, Not Threatening

Native lazy-loading is a good thing.

Blazy adopted it early, integrates it deliberately, and constrains it where it
would harm CWV metrics such as LCP or CLS using
[**selective enhancement**](#architecture).

Native features solve *specific problems*.
Blazy exists because **frontend systems rarely fail in isolation**.

Blazy does not compete with native behavior.
It **embraces** and **orchestrates** it early since incubation.

---

### 3. Performance Is a Structural Problem

Most performance regressions are not bugs.
They are emergent behavior from **unconstrained configuration**.

Common causes include:
- Missing dimensions
- Unstable layout containers
- Late media discovery of critical assets
- Over-eager JavaScript execution
- Conflicting [rendering strategies](#big-bees)
- Non-optimized images
- Failing to defer IFRAME and HTML third-party contents
- “It worked on my machine”

Blazy encodes [**performance constraints**](#optimization) directly into its
architecture to prevent these failures before they occur.

Blazy encodes guardrails because documentation alone does not stop regressions.

---

### 4. Layout Is Performance Policy

Blazy treats layout as a first-class performance concern.

Layout decisions directly affect:
- CLS
- LCP
- Media discovery
- Rendering order
- JavaScript execution

[**Blazy Layout**](#layouts) exists to make these tradeoffs explicit and
controllable.

If layout feels “philosophically unrelated” to lazy-loading,
that usually means the performance cost has not been measured yet.

---

### 5. Configuration Is Not Neutral

Every option has consequences.

Blazy makes those consequences explicit through:
- Scoped configuration
- Contextual warnings
- Limited “power” features
- Documentation that explains *why*, not just *how*

This may feel restrictive to some users.
It is protective for production systems.

---

### 6. Defaults Favor Stability Over Surprise

Blazy’s defaults are conservative on purpose:

- Layout stability
- Predictable rendering
- Safe editor behavior
- Performance regressions are expensive to debug
- CWV failures are often invisible until too late
- Less accidental misuse

Power features remain available — but require intent.
They are opt-in.
They come with warnings.
This is intentional.

---

### 7. Opinionated Does Not Mean Inflexible

Blazy allows you to:
- Bypass processing
- Render vanilla markup
- Disable features selectively
- Integrate with other systems

However, each comes with documented tradeoffs.

Blazy will not silently sacrifice performance for convenience.

---

### 8. Complexity Is Not an Accident

Blazy supports:
- Sliders
- Grids (including native grid)
- Lightboxes
- Media players
- CSS backgrounds
- Inline SVG
- Vanilla output
- Custom CSS via [**Blazy Layout**](#layouts)

These are not exotic features.
They are **standard frontend requirements**.

Coordinating them without harming CWV is inherently complex.
Blazy documents that complexity instead of hiding it.

---

### 9. This System Was Built Under Constraints

Blazy evolved under real client pressure:
- Short timelines
- Mixed responsibilities (modules, themes, implementation)
- Performance benchmarks under production load

Early versions reflect survival.
Later versions reflect refinement.

The current ecosystem is the distilled result of years of iteration,
measurement, and correction — often informed by patches and data from others.

---

### 10. Benchmarks Matter More Than Opinions

Blazy’s architecture is shaped by measurement, not preference.

As of this writing, given [LCP requirements](#heroes) with
**multi-value fields**:
- Lighter page weight
- Faster JavaScript execution
- Lower memory footprint
- More stable CWV metrics under sliders and media-heavy layouts

This includes comparisons against alternative implementations,
including Splide|Slick-based solutions.

Productive performance discussions require shared grounding in technical
context, reproducible methodology, and clearly defined constraints.

Minimum requirements:

- **Objective Benchmarking:**

  Technical findings using "apples-to-apples" comparisons under identical CWV
  protocols and environmental parity.

- **Evidence over Anecdote:**

  Subjective observation and misconfiguration must not substitute for measured
  results. "Exploiting" self-inflicted bottlenecks—such as mocking performance
  while neglecting or refusing to enable caching or asset aggregation—is
  considered anecdotal, not evidence-based. Anecdotal lags are possible, but not
  hard evidence. While we appreciate and have given due credit with a sincere
  gratitude for a well-crafted "hilarious failure" and the comedy found in the
  architectural struggle, we now aim to move beyond surface-level tropes.

- **Resolved Issue Isolation:**

  The provided configuration and
  [Strategic Optimization Checklist](#optimization) exist to ensure resolved
  issues remain isolated.

Claims without [reproduction steps](#contribution) or metrics are not
actionable.
Evaluation without proper [isolation](#cls) and [configuration](#optimization)
is improper.
They are treated accordingly.

---

### 11. On Criticism

Constructive feedback is welcome.
Evidence-backed reports are appreciated.
Patches are celebrated.
Successful contributions and data-driven disagreements are given
[due credit](https://www.drupal.org/node/2663268/committers) with sincere
gratitude.

**Unsubstantiated claims**, **subjective critiques** and **legacy anecdotes**
without configuration, output, metrics, or reproduction steps do not improve the
project. They are unexpected noise. When reaching over 120 dBA
(*the threshold of professional pain*), they cease to be collaborative
feedback and become static that must be filtered. Silence in such cases should
be understood as a boundary, not a dismissal.

> *“Blazy is too complex.”*

Blazy addresses [layout stability](#layouts), [media discovery](#optimization), [JavaScript cost](#javascript), and [CWV constraints](#why-cwv) simultaneously.

Performance at scale is inherently complex.
Blazy documents that complexity instead of hiding it.

If a simpler configuration works for your use case, Blazy supports that as well.

---

> *“This should work automatically.”*

Automatic behavior is the primary cause of CLS and LCP regressions.

Blazy [intentionally avoids global automation](#architecture) where it would
introduce unpredictable rendering or layout instability.

[Explicit configuration](#optimization) is a design choice to protect production
sites.

---

> *“This breaks my layout.”*

Blazy enforces constraints that expose existing layout issues
(e.g. *missing dimensions, unstable containers, unsafe overrides*).

Layout instability is normally caused by missing
[**Aspect ratio**](#aspect-ratio), or dimensions for external sister site
assets.

Other than that, please provide:
- Configuration screenshots
- Output HTML
- Browser metrics (CLS/LCP)
- Steps to reproduce
- **Console** tab errors by pressing F12

We don't question your report, we require evidence for resolutions.
Without evidence and reproducible steps, the cause cannot be evaluated.

---

> *“Other modules don’t require this.”*

Some modules optimize for convenience, others stability.

Blazy optimizes for:
- Predictable rendering
- CWV safety
- Production resilience

Different tools serve different priorities. We value diverse perspectives.

---

> *“This is over-engineered.”*

Every guardrail in Blazy exists because a real site failed without it.

Performance issues rarely appear in demos.
They appear in production, under load, with real content and editors.

---

> *“Blazy caused my CWV regression.”*

Blazy does not alter browser metrics arbitrarily.

If a regression occurred, it can be traced to:
- Configuration changes
- Layout changes
- Media priority changes
- JavaScript behavior

Please include measurements and reproduction steps and confront it against
[configurations](#optimization) so it can be investigated.

---

> *“Why not just lazy-load everything?”*

Lazy-loading everything is a known anti-pattern for LCP and CLS.

Blazy treats lazy loading as a tool — not a default — and limits its use
where it would harm performance. It has been [carefully thought](#architecture)
years before modern metrics like **Core Web Vitals** arrives. We are not
"four-chin tellers", we understand the pattern.

---

> *“Blazy is not perfect! It has many mistakes.”*

Absolutely.

When starting a new project, we expect contributors to correct our mistakes.

Please use the provided project issues, you will be very much appreciated for
revealing the mistakes or bugs under [contribution guildelines](#contribution).
Many contributors have recieved
[due credit](https://www.drupal.org/node/2663268/committers) for guiding us.

---

### A Friendly Closing Note

Blazy is opinionated because performance is fragile.

It exists to make good outcomes repeatable,
bad outcomes harder to achieve,
and tradeoffs visible, not hidden.

The goal is not to restrict users —
the goal is to **make good performance the natural outcome**.

---

> Opinionated systems prevent problems.
> Unopinionated systems document them afterward.

---
<a href="#top">Back to top &uarr;</a>
---
