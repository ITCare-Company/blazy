
# ABOUT BLAZY
Provides integration with bLazy to lazy load and multi-serve images to save
bandwidth and server requests. The user will have faster load times and save
data usage if they don't browse the whole page.

## REQUIREMENTS
1. bLazy library:
   * [Download bLazy](https://github.com/dinbror/blazy)
   * Extract it as is, rename **blazy-master** to **blazy**, so the assets are:

      + **/libraries/blazy/blazy.min.js**

## INSTALLATION
1. **MANUAL:**

   Install the module as usual, more info can be found on:
   https://www.drupal.org/docs/8/extending-drupal-8/installing-drupal-8-modules

2. **COMPOSER:**

   There are various ways to install third party bower/npm asset libraries.
   Check out any below suitable to your workflow:

   * https://www.drupal.org/project/blazy/issues/3021902
   * https://www.drupal.org/project/slick/issues/2907371
   * https://www.drupal.org/project/slick/issues/2907371#comment-12882235

   It is up to you to decide which works best. Composer is not designed to
   manage JS, CSS or HTML framework assets. It is for PHP. Then come Composer
   plugins, and other workarounds to make Composer workflow easier. As many
   alternatives, it is not covered here. Please find more info on the
   above-mentioned issues.


## USAGES
Be sure to enable Blazy UI which can be uninstalled at production later.

* Go to Manage display page, e.g.:
  [Admin page displays](/admin/structure/types/manage/page/display)

* Find **Blazy** formatter under **Manage display**.

* Go to [Blazy UI](/admin/config/media/blazy) to manage few global options,
  including enabling support to bring core Responsive image into blazy-related
  formatters.


## RECOMMENDED
* [Markdown](http://dgo.to/markdown)

  To make reading this README a breeze at [Blazy help](/admin/help/blazy_ui)


## FEATURES
* Supports core Image.
* Supports core Responsive image.
* Supports Colorbox/ Photobox/ PhotoSwipe, also multimedia lightboxes.
* Multi-serving images for configurable breakpoints, almost similar to core
  Responsive image, only less complex.
* CSS background lazyloading, see Mason, GridStack, and Slick carousel.
* IFRAME urls via custom coded, via Media.
* Supports inline images and iframes with lightboxes, and grid or CSS3 Masonry
  via Blazy Filter. Enable Blazy filter at **/admin/config/content/formats**,
  and check out instructions at **/filter/tips**.
* Field formatters: Blazy with Media integration.
* Blazy Grid formatter for Image, Media and Text with multi-value.
* Delay loading for below-fold images until 100px (configurable) before they are
  visible at viewport.
* A simple effortless CSS loading indicator.
* It doesn't take over all images, so it can be enabled as needed via Blazy
  formatter, or its supporting modules.


## OPTIONAL FEATURES
* Views fields for File Entity and Media integration, see Slick Browser.
* Views style plugin Blazy Grid for Grid Foundation or CSS3 Masonry.


## MODULES THAT INTEGRATE WITH OR REQUIRE BLAZY
* [Blazy PhotoSwipe](http://dgo.to/blazy_photoswipe)
* [GridStack](http://dgo.to/gridstack)
* [Intense](http://dgo.to/intense)
* [Mason](http://dgo.to/mason)
* [Slick](http://dgo.to/slick)
* [Slick Lightbox](http://dgo.to/slick_lightbox)
* [Slick Views](http://dgo.to/slick_views)
* [Slick Media](http://dgo.to/slick_media)
* [Slick Video](http://dgo.to/slick_video)
* [Slick Browser](http://dgo.to/slick_browser)
* [Jumper](http://dgo.to/jumper)
* [Zooming](http://dgo.to/zooming)

Most duplication efforts from the above modules will be merged into
\Drupal\blazy\Dejavu or anywhere else namespace.

**What dups?**

The most obvious is the removal of formatters from Intense, Zooming,
Slick Lightbox, Blazy PhotoSwipe, and other (quasi-)lightboxes. Any lightbox
supported by Blazy can use Blazy, or Slick formatters if applicable instead.
We do not have separate formatters when its prime functionality is embedding
a lightbox, or superceded by Blazy.

Blazy provides a versatile and reusable formatter for a few known lightboxes
with extra advantages:

lazyloading, grid, multi-serving images, Responsive image,
CSS background, captioning, etc.

Including making those lightboxes available for free at Views Field for
File entity, Media and Blazy Filter for inline images.

If you are developing lightboxes and using Blazy, I would humbly invite you
to give Blazy a try, and consider joining forces with Blazy, and help improve it
for the above-mentioned advantages. We are also continuously improving and
solidifying the API to make advanced usages a lot easier, and DX friendly.
Currently, of course, not perfect, but have been proven to play nice with at
least 6 lightboxes, and likely more.


## SIMILAR MODULES
[Lazyloader](https://www.drupal.org/project/lazyloader)


## CURRENT DEVELOPMENT STATUS
Please stay optimistic that things are broken till we have a BETA, or RC.

A full release should be reasonable after proper feedbacks from the community,
some code cleanup, and optimization where needed. Patches are very much welcome.

Alpha, Beta, DEV releases are for developers only. Beware of possible breakage.

However if it is broken, unless an update is provided, running `drush cr` during
DEV releases should fix most issues as we add new services, or change things.
If you don't drush, before any module update, always open:

[Performance](/admin/config/development/performance)

And so you are ready to hit **Clear all caches** if any issue.


## PROGRAMATICALLY
See blazy.api.php (WIP) for details.


## PERFORMANCE TIPS:
* If breakpoints provided with tons of images, using image styles with ANY crop
  is recommended to avoid image dimension calculation with individual images.
  The image dimensions will be set once, and inherited by all images as long as
  they contain word crop. If using scaled image styles, regular calculation
  applies.


## AUTHOR/MAINTAINER/CREDITS
gausarts

[Contributors](https://www.drupal.org/node/2663268/committers)


## READ MORE
See the project page on drupal.org:

[Blazy module](http://drupal.org/project/blazy)

See the bLazy docs at:

* [Blazy library](https://github.com/dinbror/blazy)
* [Blazy website](http://dinbror.dk/blazy/)
