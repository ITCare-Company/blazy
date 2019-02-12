
# ABOUT BLAZY
Provides integration with bLazy to lazy load and multi-serve images to save
bandwidth and server requests. The user will have faster load times and save
data usage if they don't browse the whole page.

## REQUIREMENTS
1. bLazy library:
   * [Download bLazy](https://github.com/dinbror/blazy)
   * Extract it as is, rename **blazy-master** to **blazy**, so the assets are:

      + **/sites/../libraries/blazy/blazy.min.js**

## INSTALLATION
1. MANUAL:
Install the module as usual, more info can be found on:
https://www.drupal.org/docs/8/extending-drupal-8/installing-drupal-8-modules

2. COMPOSER:
There are various ways to install third party bower/npm asset libraries. Check
out any below suitable to your workflow:
https://www.drupal.org/project/blazy/issues/3021902
https://www.drupal.org/project/slick/issues/2907371
Or jump here:
https://www.drupal.org/project/slick/issues/2907371#comment-12882235

It is up to you to decide which works best. Composer is not designed to manage
JS, CSS or HTML framework assets. It is for PHP. Then come Composer plugins,
and other workarounds to make Composer workflow easier. As many alternatives,
it is not covered here. Please find more info on the above-mentioned issues.


## RECOMMENDED
* [Markdown](http://dgo.to/markdown)

  To make reading this README a breeze at [Blazy help](/admin/help/blazy)


## FEATURES
* Supports core Image.
* Supports Picture.
* Supports Colorbox/ Photobox/ PhotoSwipe, also multimedia lightboxes.
* Multi-serving images for configurable breakpoints, almost similar to core
  Responsive image, only less complex.
* CSS background lazyloading, see Mason, GridStack, and Slick carousel.
* IFRAME urls via custom coded, via Media.
* Supports inline images and iframes with lightboxes, and grid or CSS3 Masonry
  via Blazy Filter. Enable Blazy filter at **/admin/config/content/formats**,
  and check out instruction at **/filter/tips**.
* Blazy Grid formatter for Image, Media and Text with multi-value.  
* Delay loading for below-fold images until 100px (configurable) before they are
  visible at viewport.
* A simple effortless CSS loading indicator.
* It doesn't take over all images, so it can be enabled as needed via Blazy
  formatter, or its supporting modules.


## OPTIONAL FEATURES
* Views fields for File Entity and Media integration, see Slick Browser.
* Views style plugin Blazy Grid for Grid Foundation or CSS3 Masonry.
* Field formatters: Blazy with Media integration.


## USAGES
Be sure to enable Blazy UI which can be uninstalled at production later.

* Go to Manage display page, e.g.:
  [Admin page displays](/admin/structure/types/manage/page/display)

* Find **Blazy** formatter under **Manage display**.

* Go to [Blazy UI](/admin/config/media/blazy) to manage few global options,
  including enabling support to bring Picture into blazy-related formatters.


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
Slick Lightbox, Blazy PhotoSwipe. Any lightbox supported by Blazy can use Blazy,
or Slick formatters if applicable instead. We do not have separate formatters
when its prime functionality is embedding a lightbox, or superceded by Blazy.

Blazy provides a versatile and reusable formatter for a few known lightboxes
with extra advantages: lazyloading, grid, multi-serving images, Picture,
CSS background, captioning, etc. Including making those lightboxes available for
free at Views Field for File entity, and Blazy Filter for inline images.

## SIMILAR MODULES
[Lazyloader](https://www.drupal.org/project/lazyloader)


## CURRENT DEVELOPMENT STATUS
Please stay optimistic that things are broken till we have a BETA, or RC.

A full release should be reasonable after proper feedbacks from the community,
some code cleanup, and optimization where needed. Patches are very much welcome.

Alpha, Beta, DEV releases are for developers only. Beware of possible breakage.


## UPDATE SOP:
Visit any of the following URLs when updating Blazy, or its related modules.
Please ignore any documentation if already aware of Drupal site building. This
is for the sake of completed documentation for those who may need it.

1. [Performance](/admin/config/development/performance)  
  Unless an update is required, clearing cache should fix most issues.
  * Hit **Clear all caches** button once the new Blazy in place.
  * Regenerate CSS and JS as the latest fixes may contain changes to the assets.
    Ignore below if you are aware, and found no asset changes from commits.
    Normally clearing cache suffices when no asset changes are found.
      * Uncheck CSS and JS aggregation options under Bandwidth optimization.
      * Save.
      * [Ignorable] See one of Blazy related pages if display is expected.
      * [Ignorable] Only clear cache if needed.
      * Check both options again.
      * Save again.
      * [Ignorable] Press F5, or CMD/ CTRL + R to refresh browser cache if
        needed.

2. [Admin status](/admin/reports/status)

  Check for any pending update, and run /update.php from browser address bar.

3. If Twig templates are customized, compare against the latest.


## PROGRAMATICALLY
See blazy.api.php (WIP) for details.


## PERFORMANCE TIPS:
* If breakpoints provided with tons of images, using image styles with ANY crop
  is recommended to avoid image dimension calculation with individual images.
  The image dimensions will be set once, and inherited by all images as long as
  they contain word crop. If using scaled image styles, regular calculation
  applies.


## TROUBLESHOOTING AND KNOWN ISSUES
Resizing is not supported. Just reload the page.


### 1. VIEWS INTEGRATION
Blazy provides a simple Views field for File Entity, and Media.

When using Blazy formatter within Views, check **Use field template** under
**Style settings**, if trouble with Blazy Formatter as a stand alone Views
output.

On the contrary, uncheck **Use field template**, when Blazy formatter
is embedded inside another module such as Slick so to pass the renderable
array to work with accordingly.

This is a Views common gotcha with field formatter, so be aware of it.
If confusing, just toggle **Use field template**, and see the output. You'll
know which works.


### 2. BLAZY GRID WITH SINGLE VALUE FIELD
This is no issue at D8. Blazy Grid formatter is designed for multi-value fields.
Unfortunately no handy way to disable formatters for single value at D7. So
the formatter is available even for single value, but not actually
functioning. Please ignore it till we can get rid of it at D7, if possible,
without extra legs.

### 3. MIN-WIDTH
If the images appear to be shrink within a **floating** container, add
some expected width or min-width to the parent container via CSS accordingly.
Non-floating image parent containers aren't affected.

### 4. MIN-HEIGHT
Add a min-height CSS to individual element to avoid layout reflow if not using
**Aspect ratio** or when **Aspect ratio** is not supported such as with
Picture. Picture has its own. Otherwise some collapsed image containers will
defeat the purpose of lazyloading. When using CSS background, the container
may also be collapsed.

### 5. SOLUTIONS
Both layout reflow and lazyloading delay issues are actually taken care of
if **Aspect ratio** option is enabled in the first place.

Adjust, and override blazy CSS files accordingly.


### ROADMAP/ TODO
[x] Adds a basic configuration to load the library, probably an image formatter.
    2/24/2016
[x] Media entity image/video, and Video embed field lazyloading, if any.
    10/25/2016
    Added both simple Blazy Media formatter and Views field Media Entity.
[x] Makes a solid lazyloading solution for IMG, DIV, IFRAME tags.
    4/9/2017
    Added IFRAME (Blazy Video), apart from existing IMG/ DIV (CSS background).
[?] Core Media integration
[?] Optimization and solidification.


## AUTHOR/MAINTAINER/CREDITS
gausarts

[Contributors](https://www.drupal.org/node/2663268/committers)


## READ MORE
See the project page on drupal.org:

[Blazy module](http://drupal.org/project/blazy)

See the bLazy docs at:

* [Blazy library](https://github.com/dinbror/blazy)
* [Blazy website](http://dinbror.dk/blazy/)
