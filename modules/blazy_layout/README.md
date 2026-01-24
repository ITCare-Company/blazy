
# BLAZY LAYOUT: THE ARCHITECT’S CHOICE

**Blazy Layout** provides a single layout template with dynamic regions for
**Layout Builder** (LB). This isn't just another layout handler; it is a
sophisticated re-imagining of the established Blazy Grid system. By
transforming dynamic grid options into responsive layout variants, it eliminates
the redundant need for dozens of individual templates while providing a
**CLS-zero** strategy utilizing its simple configuration.

We have mastered the art of the "One"—a single, noble template capable of
infinite expression.

> In an era of over-engineering, **Blazy Layout** honors the original intent of
> the web: _clean, fast, and infinitely adaptable_.

## REQUIREMENTS
* Core Layout Discovery.
* [Media library form element](https://www.drupal.org/project/media_library_form_element) to have builtin Media library integration
  (Optional).

## INSTALLATION
* [Installing Drupal Modules](https://drupal.org/node/1897420).

## CONFIGURATION
* **Integration:**

    Navigate to your **Layout Builder** default configuration
    (`/admin/structure/types/manage/page/display/default/layout`) or any
    administrative variant (`/node/123/layout`).

* **Selection:**

    When adding a new section, choose **Blazy dynamic layout**.

* **Adjustment:**

    Read the provided descriptions and adjust any relevant options accordingly.

### MASTERING THE DYNAMIC LAYOUT
Whether you require Flexbox, CSS3 Columns, or Native Grid—including their
Masonry counterparts—**Blazy Layout** delivers a "one for all" solution. It is hyper-efficient, leveraging modern browser capabilities with a remarkably small
CSS footprint and lean markups to produce limitless, high-performance results.

While Grid impresses a box, and Column a pillar depending on the selected layout
engine, they refer to a region or sub-section in layout terminology.

* **Layout Definition:**

  + Select **Region count** to define the allowed amount of regions.
  + Utilize the three core Grid options to define your region structure.
    Whether you need a simple inline flow (one-dimensional), a complex 2D grid
    (two-dimensional), or a stacked mobile-first design, a single baseline
    handles it all.

* **Hero Friendly:**

  Defining a region as a Hero allows you to make **Layout Builder** as the
  primary layout manager beyond regular hard-coded regions and traditional
  block placement in templates. Two Hero builders:

  + Place a dynamic slider or static media Views block and treat it as a
    [Hero](/admin/help/blazy_ui#heroes).

    **Benefits:** single and multi-value field are supported.

  + Assign a Hero delta directly into **Hero region** option, and upload a Hero
    media ((Responsive) img, Picture, Media player, Video, Audio).

    **Benefits:** a single, prominent and optimized Hero without Views overhead

* **Universal Application:**

  Effortlessly apply CSS backgrounds, solid colors, or transparent washes to the
  entire layout or specific sub-sections to create a striking visual
  foundation.

* **Atmospheric Overlays & Contrast:**

  For truly "eye-catching" depth, utilize the **RGBA Overlay** option. This
  allows you to stack semi-transparent color filters over your images, working
  in tandem with **Headline and Text color** options to ensure perfect contrast
  and brand consistency. A layout is only as good as its legibility. By
  controlling the overlay and the text color within a single interface, you
  aren't just building a page—you are composing a masterpiece of readability.

* **Instant Feedback:**

  Design at the speed of thought. A **Live Preview** is integrated directly into
  the configuration UI, providing immediate visual confirmation as you fine-tune
  your colors, backgrounds, and typography. Be sure the section, blocks and
  probably background images are added first to the page, otherwise nothing to
  see.

* **Perspective:**

  Engage the **Edge-to-Edge** option to allow your layout to span the full
  horizontal width of the viewport.

* **Composition:**

  Place regular content blocks over your defined layout regions.

* **Custom CSS (advanced):**

  The CSS is injected directly into the page `<head>` and applied at
  render time.
  + Provide a scoped selector at [Blazy UI](/admin/config/media/blazy)
    after enabling **Allow custom inline CSS for Blazy layout**.
  + Avoid targeting global elements (`html`, `body`)
  + External imports and remote URLs are ignored
  + Leave empty to avoid unnecessary layout instability

   Incorrect CSS may break layout rendering or affect unrelated components. This
   option is intended primarily to mitigate
   [**CLS issues**](/admin/help/blazy_ui#cls) when the provided `min-height`
   utility classes (**xxs xs sm md lg xl xxl x2l x3l x4l x5l**) are
   insufficient.

* **The Result:**

  Experience the power of a single, refined layout engine that offers
  unlimited possibilities with unparalleled efficiency.

### MEDIA BACKGROUND
Three ways to add Media (image or local|remote video) as CSS background:

1. **With builtin Media library (Recommended):**

   * Install [Media library form element](https://www.drupal.org/project/media_library_form_element).

   * This is alternative to core **Layout Builder Expose All Field Blocks**
     which was deprecated, also a more efficient solution than the first two
     options above to avoid creating useless/ unused Media fields. This
     background is available for all regions, including the main layout. If
     provided, be sure to **NOT** enable **Use CSS background** option for
     other Blazy formatters if provided within the same region to avoid
     multiple and conflicting backgrounds.

   * Select image/media at **Layout Builder** page under:

     **Blazy layout > [Global|Region] > Settings > Styles > Media**

   * Repeat for any other regions as needed.

   * **Benefits**: No fields or blocks are created, just re-use, or create,
     media. This is the most efficient solution for simple backgrounds.

2. **With active entity/Content type:**

   * Add a _multi-value_ Media/ Image field in the active entity/Content type.
   * Upload some images/media (matching the amount of regions which should
     have backgrounds) into the field. If the region total is 10, and you need
     3 backgrounds, just upload 3 items, not 10.
   * At LB: **Add block > Choose a block > Content fields**.
   * Choose **Blazy formatter**, and enable **Use CSS background** option.
   * Use **By delta** option starting from 0 to map field items to any regions
     rather than creating multiple fields for multiple regions.
   * Repeat for any region which may require backgrounds. Adjust **By delta**,
     no need to match one to one delta from field items to regions.
   * FYI, this offers more options, but might be overwhelmed for background
     purposes.

3. **With Block content type**:

   * [/admin/structure/block-content](/admin/structure/block-content), add
      a dedicated background type, says **Background**.

   * [/admin/structure/block-content/manage/background/fields](/admin/structure/block-content/manage/background/fields),
     add a Media field says **Media**, choose Image, Video and Remote video.
     Multi-value is better for carousel re-use.

   * [/admin/structure/block-content/manage/background/display](/admin/structure/block-content/manage/background/display), choose Blazy formatter, and enable
      **Use CSS background**.

   * Create as many as blocks for background: [/block/add/background](/block/add/background), or on the fly using LB **Create content block**.

   * At LB, either way:

     * **Add block > Create content block**.
     * **Add block > Choose a block > Content block**


#### The following is applicable to background options above:
* **Custom Hi-Res Image:**

  To have a custom hi-res image/poster for (local|remote) video:

  + Visit bundle pages:

    * [Remote video](/admin/structure/media/manage/remote_video/fields)
    * [Video](/admin/structure/media/manage/video/fields)

  + Re-use the existing `field_media_image` into each bundle.

    The same principle is applicable to non-background (Document, Audio, etc.)
    when being used with/without background purposes. Normally you would select
    this field under **Blazy formatter > Main stage** to be sure.

  + Select `Media switcher > Image to iframe` option.

* **Linkable Media:**

  To have unique linkable media:

  + add a Link or Text field to the Media bundles (not Content type or Node),
  + select it under **Link** option,
  + choose **Media switcher > Image linked by Link field**.


## KNOWN ISSUES/ LIMITATIONS
* This module does not provide a CSS framework integration aka framework
  agnostic. Instead using the existing grid solutions with few tweaks to support
  regular floating elements commonly seen at one-dimensional layouts. However,
  any CSS framework cosmetic rules can be utilized via the provided **Classes**
  options.
* Background images are not draggable, simply replace and reuse them.

## AUTHOR/MAINTAINER/CREDITS
* [Gaus Surahman](https://www.drupal.org/user/159062)
* CHANGELOG.txt for helpful souls with their patches, suggestions and reports.


## READ MORE
See the project page on drupal.org for more updated info:

[Blazy module](https://drupal.org/project/blazy)
