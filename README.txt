
ABOUT
Provides integration with bLazy to lazy load and multi-serve images to save
bandwidth and server requests. The user will have faster load times and save
data usage if they don't browse the whole page.


REQUIREMENTS
- bLazy library:
  o Download bLazy from https://github.com/dinbror/blazy
  o Extract it as is, rename "blazy-master" to "blazy", so the assets are at:

    /libraries/blazy/blazy.min.js

INSTALLATION
Install the module as usual, more info can be found on:
http://drupal.org/documentation/install/modules-themes/modules-7


USAGES
Currently the module does nothing on its own, other than library definition.
Only install it if required by a module.

For custom usages, add a class "b-lazy" along with a "data-src" attribute
referring to an expected image or iframe URL to any supported element:
IMG, DIV, IFRAME.
And load the blazy library accordingly.


MODULES THAT INTEGRATE WITH BLAZY
o Mason, a WIP


ROADMAP/TODO
o Adds a basic configuration to load the library, probably an image formatter.


WHY ANOTHER LAZYLOAD?
The only reason for this module to exist is no D8 lazyload module found as of
this writing:
https://www.drupal.org/search/site/lazyload

This might be deprecated soon once the existing solutions are ported, or kept if
there is good reason to continue living. Currently no solid reason other than
none of D8 lazyload modules, and that it is recommended by a WIP module Mason.


SIMILAR MODULES
https://www.drupal.org/project/lazyload
https://www.drupal.org/project/lazyloader


AUTHOR/MAINTAINER/CREDITS
gausarts


READ MORE
See the project page on drupal.org: http://drupal.org/project/blazy.

See the bLazy docs at:
o https://github.com/dinbror/blazy
o http://dinbror.dk/blazy/
