
***
## <a name="changes"> </a>NOTABLE CHANGES
Always check out release notes, if any issues with the latest changes.

- _Blazy 4.0.0_, 2026-01-31:

  * Initial 4.x works with initial tentative hints for those concerned.

  * The `@blazy` and `@blazy.manager.base` are decoupled from `@blazy.base`.

  * The `@blazy` service now contains core infrastructural services and methods
    common to all Blazy ecosystem. Its static methods were deprecated and
    removed into `\Drupal\blazy\BlazyApi` since 3.0.18. Its property is `$core`.

  * The `@blazy.base` is marked for deprecations in 3.0.18, and is removed in
    later 4.0.3 or 5.0.0 at the latest. If extending, use `@blazy.manager.base`
    instead. Its methods were moved into and now become aliases of
    `BlazyInterface` and `WithInterface` to avoid issues with inheritance and
    circular references, relevant for the D11 Hook arguments.
    Both classes are made `final` to satify the purpose.

    Within `BlazyManagerBaseInterface`, calling `$this->core->method()` or
    `$this->with->method()` is more future-proof than current
    duplicate methods with short-hand `$this->method()`. Non-essential and
    non-frequently-called duplicate methods (including `BlazyBase` and
    `BlazyBaseInterface` - 4.x BC only) are being deprecated and
    removed in favor of `::core()` and `::with()` method equivalents.

  * Media component services are marked for deprecations in 3.0.18, and is
    removed in later 4.0.3 or 5.0.0 at the latest. Use `@blazy.media_render`
    instead:

    - `@blazy.file` -> `@blazy.file_renderer`
    - `@blazy.svg` -> `@blazy.svg_renderer`
    - `@blazy.media` -> `@blazy.media_renderer`
    - `@blazy.oembed` -> `@blazy.oembed_renderer`
    - `@blazy.entity` -> `@blazy.entity_renderer`

    Public access in 4.x is available via `@blazy.media_render` coordinating
    layer. Direct access to the deprecated services in 4.x may result in errors.

  * The following namespaces are moved into `src/Infra` fo organization:

    - `Drupal\blazy\Asset` => `Drupal\blazy\Infra`
    - `Drupal\blazy\Config` => `Drupal\blazy\Infra\Config`
    - `Drupal\blazy\Field` => `Drupal\blazy\Infra\Field`
    - `Drupal\blazy\Views` => `Drupal\blazy\Infra\Views`

  * Settings array is now cloned into `BlazySettings::config` for convenient
    customizations and operations. See
    [**blazy.api.php**](https://git.drupalcode.org/project/blazy/blob/4.0.x/blazy.api.php)
    for details.

- _Blazy 3.0.0_, 2023/09/18:
  * Initial works.
