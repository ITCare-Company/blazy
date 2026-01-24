
***
## <a name="manifesto"> </a>THE BLAZY MANIFESTO
*A performance system, not a shortcut.*

Blazy is intentionally opinionated by design.

This is not accidental, and not a limitation — it is a requirement for building
performant media delivery at scale.

---

#### 1. Blazy Is Not a Feature Module

Blazy is not “just lazy-loading”.

Lazy-loading is a [**primitive**](#why-cwv).
Blazy is a [**coordination layer**](#resource-manager).

It exists to manage how [layout](#layouts), [media](#media-architecture),
[JavaScript](#granularity), and [rendering order](#cls) interact under
[real content](#content-architecture), [real ditors](/filter/tips), and
[real production constraints](#optimization).

If your site only renders static images in isolation, Blazy may indeed feel
unnecessary. That is not its target environment.

---

#### 2. Native Features Are Embraced, Not Threatening

Native lazy-loading is a good thing.

Blazy adopted it early, integrates it deliberately, and constrains it where it
would harm CWV metrics such as LCP or CLS using **selective enhancement**.

Native features solve *specific problems*.
Blazy exists because **frontend systems rarely fail in isolation**.

Blazy does not compete with native behavior.
It **embraces** and **orchestrates** it early since incubation.

---

#### 3. Performance Is a Structural Problem

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

#### 4. Layout Is Performance Policy

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

#### 5. Configuration Is Not Neutral

Every option has consequences.

Blazy makes those consequences explicit through:
- Scoped configuration
- Contextual warnings
- Limited “power” features
- Documentation that explains *why*, not just *how*

This may feel restrictive to some users.
It is protective for production systems.

---

#### 6. Defaults Favor Stability Over Surprise

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

#### 7. Opinionated Does Not Mean Inflexible

Blazy allows you to:
- Bypass processing
- Render vanilla markup
- Disable features selectively
- Integrate with other systems

However, each comes with documented tradeoffs.

Blazy will not silently sacrifice performance for convenience.

---

#### 8. Complexity Is Not an Accident

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

#### 9. This System Was Built Under Constraints

Blazy evolved under real client pressure:
- Short timelines
- Mixed responsibilities (modules, themes, implementation)
- Performance benchmarks under production load

Early versions reflect survival.
Later versions reflect refinement.

The current ecosystem is the distilled result of years of iteration,
measurement, and correction — often informed by patches and data from others.

---

#### 10. Benchmarks Matter More Than Opinions

Blazy’s architecture is shaped by measurement, not preference.

As of this writing, given [LCP requirements](#heroes) and beyond core Native
layloading (`IMG` and `IFRAME`), across real implementations and comparisons:
- Lighter page weight
- Faster JavaScript execution
- Lower memory footprint
- More stable CWV metrics

Claims without [reproduction steps](#contribution) or metrics are not
actionable.
Evaluation without proper [isolation](#cls) and [configuration](#optimization)
is improper.
They are treated accordingly.

---

#### 11. On Criticism

Constructive feedback is welcome.
Evidence-backed reports are appreciated.
Patches are celebrated.
Successful contributions and data-driven disagreements are given
[due credit](https://www.drupal.org/node/2663268/committers) with sincere
gratitude.

General claims without configuration, output, metrics, or reproduction steps
do not improve the project. They are unexpected noise. When reaching over 120
dBA (the threshold of professional pain), they cease to be collaborative
feedback and become static that must be filtered. Silence in such cases should
be understood as a boundary, not a dismissal.

---

#### A Friendly Closing Note

Blazy is opinionated because performance is fragile.

It exists to make good outcomes repeatable,
bad outcomes harder to achieve,
and tradeoffs visible, not hidden.

The goal is not to restrict users —
the goal is to **make good performance the natural outcome**.

---

> Opinionated systems prevent problems.
> Unopinionated systems document them afterward.
