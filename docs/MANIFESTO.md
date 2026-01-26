
***
## <a name="manifesto"> </a>THE BLAZY MANIFESTO
*A performance system, not a shortcut.*

Blazy is intentionally opinionated by design.

This is not accidental, and not a limitation — it is a requirement for building
performant **media delivery** at scale. However, as just one inhabitant of the
page, Blazy is not aware of the **entire page ecosystem’s performance**.
Understanding this limited scope helps set clear expectations for what Blazy
can and cannot do.

---

### 1. Blazy & Native Lazy-Loading

Blazy is not “just lazy-loading”.

JavaScript-based lazy-loading was originally introduced as a
[**primitive**](#why-cwv) for backward and forward compatibility, and was later
superseded in part by native lazy-loading.

Native lazy-loading is **necessary** — but it is also **scoped by design**.

Blazy operates as a [**coordination layer**](#resource-manager).

It adopted native lazy-loading early, integrates it deliberately, and constrains
it where unconstrained usage would harm **Core Web Vitals** (such as LCP or
CLS), using [**selective enhancement**](#architecture).

---

#### Blazy Is Not a Feature Module

Blazy exists to solve the **coordination problem** between
[layout](#layouts),
[media](#media-architecture),
[JavaScript](#javascript),
and [rendering order](#cls) as they interact under
[real content](#content-architecture),
[real editors](/filter/tips),
and [real production constraints](#optimization)
— the exact areas where CWV regressions most often occur.

If a site renders only static images in isolation, Blazy may indeed feel
unnecessary.
That is not its target environment.

---

#### Native Feature Is Embraced, Not Threatening

Native features solve *specific problems*.

Blazy exists because **frontend systems rarely fail in isolation**.

Blazy does not compete with native behavior.
It **embraces**, **orchestrates**, and builds upon it, including native
lazy-loading, since its early incubation.

---

#### Blazy Existence & Scope

Blazy exists to align with how **Core Web Vitals** are actually measured and how
browsers behave in real-world conditions — not just for images, but across media
types and rendering strategies.

It coordinates adaptive priority, decoding and loading strategies, selective
preloading, AMP and sandboxed modes, backward compatibility,
[LCP element coordination](#heroes), and a **CLS-zero** strategy via
[Blazy Layout](#layouts).

Native lazy-loading in core intentionally covers a
**narrow, declarative subset** of use cases, primarily `IMG` and `IFRAME`. As of
this writing, it does not extend to `VIDEO`, `AUDIO`, or third-party `HTML`
embeds, nor does it manage script weight, media player initialization, or layout
reservation.

**Blazy addresses these adjacent concerns by:**
- unifying lazy-loading across media types,
- deferring or replacing heavyweight iframes with lighter media switchers
  (players, posters, lightbox integrations, links to content or by Link field),
- blocking third-party scripts until user intent is explicit.

These tradeoffs — media substitution, script suppression, layout stability,
and LCP protection — are intentionally outside the scope of native lazy-loading,
which is **non-opinionated by design**.

For clarity: native lazy-loading is not insufficient; it is
**intentionally scoped**.
Blazy exists for the concerns it does not attempt to solve.

---

#### On Blazy Removal

Blazy is not mandatory.

If a completed and maintained alternative reaches feature parity and replaces
its current scope — whether in Slick or elsewhere — Blazy can be removed.

Until such an implementation exists, Blazy remains the maintained solution for
these concerns.

---

### 2. Performance Is a Structural Problem

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

Performance is structural, not cosmetic.

Blazy approaches performance as a **structural problem**:
- Layout defines performance
- Dimensions define stability
- Priority defines loading order
- Constraints define predictability

Optimizing after the fact is fragile.
Blazy instead **prevents entire classes of performance regressions by design**.

Blazy encodes [**performance constraints**](#optimization) directly into its
architecture to prevent these failures before they occur.

Blazy encodes guardrails because documentation alone does not stop regressions.

---

### 3. Layout Is Performance Policy

Blazy treats layout as a first-class performance concern.

Layout decisions directly affect:
- CLS
- LCP
- Media discovery
- Rendering order
- JavaScript execution

[**Blazy Layout**](#layouts) exists to make these tradeoffs explicit and
controllable:
- Prevent layout instability
- Provide predictable rendering
- Enable grid-aware media handling
- Integrate with CWV-safe constraints

**Blazy Layout** is not decoration.
It is intentionally opinionated.

If layout feels “philosophically unrelated” to lazy-loading,
that usually means the performance cost has not been measured yet.

---

### 4. Configuration Is Not Neutral

Every option has consequences.

Blazy makes those consequences explicit through:
- Scoped configuration
- Contextual warnings
- Limited “power” features
- Documented unsafe configurations
- Scoped and restricted inline CSS
- Global overrides are discouraged
- Unsupported “Vanilla” modes by design
- Documentation that explains *why*, not just *how*

Configuration is power — and responsibility.
Blazy exposes configuration because real sites are complex.
Blazy will not silently sabotage performance.
If performance degrades, the system explains why.

This may feel restrictive to some users.
It is protective for production systems.

---

### 5. Defaults Favor Stability Over Surprise

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

### 6. Opinionated Does Not Mean Inflexible

Blazy allows you to:
- Bypass processing
- Render vanilla markup
- Disable features selectively
- Integrate with other systems

However, each comes with documented tradeoffs.

Blazy will not silently sacrifice performance for convenience.

---

### 7. Complexity Is Not an Accident

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

### 8. JavaScript Is a Cost, Not a Feature

Sliders and lightboxes are inherently expensive — large images, third-party
videos, library sizes and so on.

Blazy does not pretend otherwise.

Instead, it provides:

- Deferred loading
- Visibility constraints
- Native Grid alternatives
- On-demand loading
- Explicit warnings when complexity increases

When JavaScript is required, it is introduced **intentionally** in granular
delivery and intelligent exclusion.

---

### 9. LCP Is Treated as a Singular Event

Largest Contentful Paint is not a toggle.
It is a **decision**.

Blazy:

- Treats [hero media](#heroes) as a first-class concept
- Limits `slider` and `unlazy` loading to prevent abuse
- Ties preload behavior to late-discovered critical assets
- Discourages multiple competing “heroes”

This aligns with browser heuristics, not design trends.

---

### 10. This System Was Built Under Constraints

Blazy evolved under real client pressure:
- Short timelines
- Mixed responsibilities (modules, themes, implementation)
- Performance benchmarks under production load

Early versions reflect survival.
Later versions reflect refinement.

The current ecosystem is the distilled result of years of iteration,
measurement, and correction — often informed by patches and data from others.

---

### 11. Benchmarks Matter More Than Opinions

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

**Minimum requirements:**

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

### 12. On Criticism

Constructive feedback is welcome.
Evidence-backed reports are appreciated.
Patches are celebrated.
Successful contributions and data-driven disagreements are given
[due credit](https://www.drupal.org/node/2663268/committers) with sincere
gratitude.

**Unsubstantiated claims**, **subjective critiques**, and **legacy anecdotes**
without configuration, output, metrics, or reproduction steps do not improve the
project. They introduce unactionable signal. When repetition reaches an
unproductive threshold (*analogous to sustained overload*), it ceases to be
collaborative feedback and becomes static that must be filtered.
Silence in such repeated cases should be understood as a boundary on engagement,
not a dismissal of contributors.

---

#### 12.a. On Perceived Scope and Complexity

> *“Blazy is a big mess, bloated, and extremely complex.”*

This perception is understandable when Blazy is evaluated outside its intended
scope. We also recognize that handling this level of complexity is constrained
by time, available resources, and the limits of our collective knowledge.

Blazy is not designed as a single-purpose feature or a minimal helper. It exists
to address **coordination problems** across layout, media, JavaScript execution,
and rendering order under real-world content and production constraints. That
coordination introduces structure and configuration that may appear complex when
viewed in isolation.

---

> *“Blazy is a big mess”*

Blazy addresses [layout stability](#layouts),
[media discovery](#optimization),
[JavaScript cost](#javascript),
and [Core Web Vitals constraints](#why-cwv) simultaneously.

Performance at scale is inherently complex.
Blazy documents that complexity rather than hiding it.

If a simpler configuration is sufficient for a given use case, Blazy supports
that as well.

---

> *“Blazy is bloated”*

What may be perceived as “bloat” is primarily the result of:
- supporting multiple media types (images, video, audio, third-party embeds),
- maintaining backward and forward compatibility,
- protecting **Core Web Vitals** such as CLS and LCP across diverse
  environments,
- making tradeoffs explicit rather than implicit.

Blazy does not aim to be minimal for all use cases.
If a site requires only basic image lazy-loading, simpler solutions may be more
appropriate.

---

> *“Blazy is extremely complex”*

The complexity in Blazy is intentional and reflects the complexity of the
problems it addresses.

Different tools serve different scopes. Blazy operates where performance issues
emerge from interactions between systems rather than isolated features.

Diverse perspectives are valid, and choosing not to use Blazy is a reasonable
decision when its scope does not match a project’s needs.

---
> *“Blazy is over-engineered.”*

Every guardrail in Blazy exists because a real site failed without it.

Performance issues rarely appear in demos.
They appear in production, under load, with real content and editors.

---

> *“Other modules don’t require this.”*

Some modules optimize for convenience or KISS principle, others stability.

Blazy optimizes for:
- Predictable rendering
- CWV safety
- Production resilience

Different tools serve different priorities. We value diverse perspectives.

---

#### 12.b. On Perceived Failures and Expectation Mismatch

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
years before modern metrics like **Core Web Vitals** arrives.

---

#### 12.c. On Quality and Ongoing Maintenance

> *“Blazy is not perfect! It has many mistakes.”*

Absolutely.

When starting or maintaining a project, we expect contributors to help identify
and correct mistakes.

Please use the project issue queue; you are very much appreciated for revealing
mistakes or bugs under the [contribution guidelines](#contribution).
Many contributors have received
[due credit](https://www.drupal.org/node/2663268/committers) for guiding and
improving the project.

---

### A Friendly Closing Note

Blazy is opinionated because performance is delicate, and small decisions
matter.

Blazy does not promise perfect Lighthouse scores, nor does it attempt to control
or measure the performance of an entire page. Lighthouse reflects whole-page
behavior; Blazy is a **media-level solution**, addressing only media delivery
and media-induced layout shifts as one part of a larger page ecosystem.

Blazy exists to make good outcomes repeatable,
bad outcomes harder to achieve,
and tradeoffs visible—not hidden.

The goal is not restriction—
the goal is to **make good performance the natural outcome**.

---

> Opinionated systems prevent problems.
> Unopinionated systems document them afterward.

---
<a href="#top">Back to top &uarr;</a>
---
