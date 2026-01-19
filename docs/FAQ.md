
---
## <a name="faq"></a>FAQ

### CURRENT DEVELOPMENT STATUS
A stable production release is anticipated following comprehensive community feedback, final code sanitation, and performance optimization. We maintain an open-contribution model—technical patches and audits are highly encouraged.

### PROGRAMMATIC INTERFACE
For deep-level integration and custom implementation, refer to the
[blazy.api.php](https://git.drupalcode.org/project/blazy/blob/3.0.x/blazy.api.php) documentation.

---

### BLAZY (Namespace) VS. B-LAZY (Class)
`blazy` identifies the module namespace; `b-lazy` is the designated CSS selector for the lazy-loading engine.

- **The `.blazy` Wrapper:** This is applied to the **top-level container**
(e.g., `.field`, `.view`, `.item-list`). It acts as the configuration hub where script options are injected via the `[data-blazy]` attribute to override global behaviors on a per-instance basis, only if needed.
- **The `.b-lazy` Target:** This is applied to the **individual asset** (IMG, VIDEO, DIV). While typically a child of `.blazy`, it is the specific node the engine monitors for intersection.

### BLAZY:DONE VS. BIO:DONE EVENTS
- **`blazy:done`**: Dispatched upon the successful loading of an **individual element**.
- **`bio:done`**: Dispatched when an **entire collection** has completed its lifecycle.

> **Deprecation Warning:** Since 2.17, we have moved to
**colonized event names** (e.g., `blazy:done.MYMODULE`). The legacy dotted notation (`blazy.done`) is deprecated and will be removed in **3.x**. If using the provided listeners, transition your event listeners immediately to ensure long-term stability.

---

### WHAT IS THE `.blazy` CSS CLASS FOR?
The `.blazy` class serves as a **performance boundary**. By limiting the
script’s scan to specific containers rather than the global DOM, we achieve:
1.  **Scope Control:** The engine ignores irrelevant nodes, reducing main-thread execution time.
2.  **Architectural Flexibility:** This allows multiple containers on a single page to have unique features—such as one utilizing multi-breakpoint images and another serving image-to-iframe media—without logic collisions.

### WHY NOT `BLAZY__LAZY` (BEM)?
`b-lazy` is the native selector for the underlying JS logic. We prioritize **functional standards over naming conventions**. Respecting the engine's defaults ensures maximum performance and a smaller footprint by avoiding unnecessary abstraction layers.

---

### NATIVE LAZY-LOADING & CHROME BEHAVIOR
Blazy library last release was v1.8.2 (2016/10/25).

While [Native Lazy-loading](https://web.dev/native-lazy-loading/) is supported
by modern browsers (Chrome 76+, 2019/01), current implementations often use a massive pre-fetch threshold (e.g., [8000px](https://cs.chromium.org/chromium/src/third_party/blink/renderer/core/frame/settings.json5?l=971-1003&rcl=e8f3cf0bbe085fee0d1b468e84395aad3ebb2cad)). Blazy remains essential as an intelligent fallback and a tool for precision orchestration where native thresholds are too aggressive or blunt.

**Note:** If lazy-loading appears non-functional in modern browsers, check the **Network tab**. The browser may have pre-fetched the asset based on its
internal heuristics. Blazy ensures the asset is handled correctly once the threshold is actually met.

- **Update 2020-04-24:** Added logic to only trigger lazy-loading once the initial viewport asset is confirmed loaded, preventing bandwidth contention, see [#3120696](https://drupal.org/node/3120696)

---

### ANIMATE.CSS INTEGRATION
The `.media` container is the primary target for animations (leveraging
[animate.css](https://github.com/daneden/animate.css)). This ensures a unified transition regardless of the asset type (Picture, Image, or Rich Media).

See [GridStack](https://drupal.org/project/gridstack) 2.6+ for the `animate.css`
samples at **Layout Builder** pages.

The **Blur** effect can be replaced with `animate.css`. Be sure to include the library if your theme accordingly.

#### Implementation Strategy:
1.  **Global Logic**: Register your classes via `hook_blazy_image_effects_alter` to make them available in the Blazy UI.
2.  **Granular Control**: Use `hook_blazy_settings_alter` to programmatically inject the `fx` setting based on specific context.

```php
/**
 * Implements hook_preprocess_blazy().
 */
function MYTHEME_preprocess_blazy(&$variables) {
  $settings = &$variables['settings'];
  $blazies = $settings['blazies'];

  // Scope the animation to a specific entity or field context.
  if ($blazies->get('entity.id') == 123 && $blazies->get('field.name') == 'field_media_animated') {
    $prefix = 'data-b-';

    // Inject animation attributes directly into the container.
    $variables['attributes'][$prefix . 'animation'] = $blazies->get('fx') ?: 'wobble';
    $variables['attributes'][$prefix . 'animation-duration'] = '3s';
    $variables['attributes'][$prefix . 'animation-delay'] = '.3s';
    $variables['attributes'][$prefix . 'animation-iteration-count'] = 'infinite';
  }
}
```

### <a name="theme-blazy"> </a> THEME_BLAZY(): THE SINGLE SOURCE OF TRUTH
As of 2.17, `theme_blazy()` has replaced the redundant internal logic of various sub-modules (`theme_slick_slide()`, `theme_splide_slide()`, etc.). It is not replacing their established `theme_ITEM()`, just their contents when
we all have dups with IMAGE/MEDIA + CAPTIONS constructs.

**The "Why":**
- **DRY Execution:**

  Dramatically reduces code duplication—a core tenet of our architecture.
- **Unified Enhancements:**

  New features (like hover effects or SVG description support) are deployed once and instantly inherited across the entire ecosystem.
- **Streamlined Maintenance:**

  Bug fixes in the central engine immediately stabilize all integrating modules.

**Migration Path:**
If you are currently overriding `theme_ITEM()` templates, migrate your logic before the 3.x release:

  - **Preprocessing:** Use `THEME_preprocess_blazy()`.

  - **Captions:** Utilize `hook_blazy_caption_alter()`.

  - **State Management:** Use the `settings.blazies` object to steer HTML changes conditionally.

  - **Last Resort:** Override `blazy.html.twig`. Note that even the core
    author avoids this—the provided hooks are 100% sufficient for custom architectural requirements.
