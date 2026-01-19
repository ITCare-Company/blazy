
---
## <a name="optimization"></a>Strategic Optimization Checklist

Proper configuration ensures the module works for you, not against you. Use this checklist to audit your implementation for maximum performance and technical integrity.

---

### 1. Essential Environment Hygiene

- **Production Clean-up:**
    Always **uninstall Blazy UI** in production; configuration belongs in code
    or exported features. A clean production environment is a performant one.

- **Global Performance:**
    Ensure Drupal’s core **CSS/JS aggregation** and caching are active at
    `/admin/config/development/performance`. Without this, software-level optimizations are moot.

### 2. Media Architecture & Privacy

- **Lazyload HTML:**

    For third-party embeds (Instagram, Pinterest, etc.), enable
    **Lazyload HTML** in the Blazy UI. Offloading heavy third-party scripts prevents main-thread blocking and preserves host page performance.

- **Lazyload IFRAME:**

    Using the **Media Switcher** option with a static image preview is the primary defense against heavy third-party scripts. By intercepting iframe requests until user interaction, the main thread remains responsive during initial page load.

    1. **Image to IFRAME (Two-Click Loader):**

       The gold standard for **GDPR/ePrivacy compliance**. It blocks third-party tracking until active user engagement.
    2. **Image to Lightbox:**

       Offloads scripts into a **conceptual background thread**—a destroyable, isolated execution path—protecting the host page’s performance.
    3. **Image Linked to Content|by Link field:**

       The most aggressive strategy. It **completely removes** external scripts from the current page, delegating them to dedicated URLs.

### 3. Precision Engineering for Media

- **Prevent Layout Shift (CLS):**

    The **Aspect Ratio** is our primary defense against
    **Cumulative Layout Shift (CLS)**. By reserving space before media loads, we prevent container collapse and page jumps.

    - **Strategy:**

       Use image styles with a **"crop"** effect. Select the **Aspect ratio** in the formatter UI and enable **Modern CSS aspect-ratio** in Blazy
       settings.
    - **Fluid Logic:**

      While native lazy loading handles basic shifts, Blazy’s **adaptive intelligence** provides robust fallbacks for legacy environments and fluid containers.

- **Loading Priority (LCP):**

    Use **Preload** and **Loading Priority** options for "above-the-fold" assets to optimize **Largest Contentful Paint (LCP)**. Treat hero media as a priority, not an afterthought.

- **Responsive Standards:**

    Prioritize **Core Responsive Image** whenever storage permits. If storage is a constraint, utilize modern formats like **WebP** or **AVIF** to maintain visual fidelity at a fraction of the weight along with a versatile design.

### 4. Interactive Scalability

- **Scalability for Galleries:**

  For massive datasets, favor **Blazy Grid + Lightbox** (Colorbox, PhotoSwipe, etc.). This is objectively more efficient than a slider-only implementation for static viewing.

- **The DOM Diet: Eradicating Divitis:**

  To achieve a high-performance render, we must treat the DOM tree with the precision of a minimalist. Excessive nesting is technical debt. Follow these protocols to ensure lean, semantic markup:

  1. Field & View Configuration

     - **Remove Wrapper Classes:**

       Always enable **"Remove field/view wrapper CSS classes."**. It is only useful for custom DOM diet.

     - **Uncheck "Use theme field":**

       If provided, keep this disabled unless a specific architectural requirement dictates otherwise. This prevents the system from injecting default, heavy-handed wrappers.

  2. Template-Level Purging

     We embrace the **@mortendk** DOM diet. If a `div` doesn't have a semantic
     or structural purpose, it is bloat.

     - **Implementation:**

       Use specialized Twig templates—`block--no-wrapper.html.twig` or `views--no-wrapper.html.twig`—to strip the container to its core components conditionally, whenever possible. And use the **field/view wrapper CSS classes** for more contextual styling.

     - **Result:**

       Contextual styling becomes cleaner, inheritance is more predictable, and the browser spends less time traversing the tree.

> _Every line of HTML you don't write is a line you don't have to debug. Shave the bloat._

### 5. Automated Intelligence & Modern Standards

- **Native Lazyload:**

    Favor native browser lazy-loading to reduce main-thread execution by
    enabling the **No JavaScript + polyfills**, when targeting modern sites.

- **Noscript Compatibility:**

    While `<noscript>` provides a fallback, it adds HTML weight. If your target audience is modern sites or performance-critical, disable this fallback to shave off every possible byte.

- **Fine-Tuning:**

    Audit your settings at [Blazy UI](/admin/config/media/blazy), submodules'
    administrative UI pages, and Media formatters. The administrative UI is your
    cockpit for precision tuning.
