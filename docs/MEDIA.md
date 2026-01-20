
***
## <a name="svg"></a> SVG

Install **SVG Sanitizer** via Composer (see the [COMPOSER](#composer) section):

```bash
composer require enshrined/svg-sanitize
```
Read more about [SVG Sanitizer](https://github.com/darylldoyle/svg-sanitizer)

Blazy intentionally does not ship this dependency in its own `composer.json` for security and maintenance reasons. If **SVG Sanitizer** is not installed, the **Inline SVG** option will be disabled.

Since version 2.17, the formatter **Blazy Image with VEF (deprecated)** has been repurposed to support SVG files. It is now named **Blazy File**.

Drupal core’s Image widget does not support SVG files. To upload SVGs, use a **File**  field instead:

1. [/admin/structure/types/manage/page/fields](/admin/structure/types/manage/page/fields)

   * **Add a new field → Reference → File** for simple needs
   * Enable the **Description field** for SVG captions
   * Alternatively, choose **Reference → Other → File** for more complex needs
`/admin/structure/types/manage/page/fields`

2. [/admin/structure/types/manage/page/fields](/admin/structure/types/manage/project/page)

   * Select **Blazy File** and adjust configuration as needed

The **Blazy File** formatter can also be used for standard images when the SVG extension is available. Otherwise, use **Blazy Image**. The two formatters are kept separate to expose SVG-specific form options where appropriate.

This represents the most basic SVG support available in Drupal core without installing additional modules. Blazy can render SVGs either as inline SVG or as embedded SVG via `<img>`.

For more robust solutions, consider modules such as SVG Image Field, SVG Image, and related projects.

**FYI**
* SVG Image overrides core formatters and widgets globally, which can make it difficult to uninstall on sites with existing image fields.
* Blazy works well with SVG Image and similar modules.
* SVG form options are inspired by the SVG Image Field module. To honor this, **Blazy File** supports its field type, enabling grids and various Blazy features, including SVG carousels.
* SVG `<title>` element support is inspired by SVG Formatter.
* If an SVG appears smaller than expected, try applying width: 100% via CSS.

---
## <a name="webp"> </a>WEBP
* Drupal 9.2 supports WEBP conversion via **Convert WEBP** on the Image Styles administration page.
* Drupal 11 supports **Convert to AVIF**, with WEBP as a fallback.

If support for older browsers is required, Blazy provides a WEBP polyfill in the Blazy UI under **No JavaScript**. Be sure to leave it **unchecked**.

**Benefits**
* Modern browsers continue using clean `<img>` markup without being forced into unnecessary `<picture>` elements for all WEBP images.
* Older browsers receive a `<picture>` fallback only when WEBP is unsupported.
