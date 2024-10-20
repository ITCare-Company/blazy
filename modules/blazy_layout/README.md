
# ABOUT BLAZY LAYOUT

Provides a single layout with dynamic regions for Layout Builder.

## INSTALLATION
Install the module as usual, more info can be found on:

[Installing Drupal 8 Modules](https://drupal.org/node/1897420)


## USAGE / CONFIGURATION
* Visit Layout builder pages (`/node/123/layout`), and add a Blazy Layout.
* Two ways to add Media background (image, local video, remote video):
  + **Add block > Choose a block > Content fields**, and choose Blazy formatter
    with **Use background** enabled. A Media/ Image field must exist in the
    active entity/content type.
  + Install [Media library form element](https://www.drupal.org/project/media_library_form_element).
    This is alternative to core **Layout Builder Expose All Field Blocks** which
    was deprecated, also a more efficient solution to the first option above to
    avoid creating useless/ unused Media fields. This background is available
    for all regions, including the main layout. If provided, be sure to **NOT**
    enable **Use background** option for Blazy formatters to avoid multiple and conflicting backgrounds.
    The option is available at:

    **Blazy layout > [Global|Region] > Settings > Styles > Media**

  To have a custom hi-res image for (local|remote) video, relevant for
  `Use player` option, simply re-use the existing `field_media_image` into each
  bundle.


## KNOWN ISSUES/ LIMITATIONS
* This module does not provide a CSS framework integration, instead using the
  existing grid solutions with few tweaks to support regular floating elements
  commonly seen at one-dimensional layouts.


# AUTHOR/MAINTAINER/CREDITS
* [Gaus Surahman](https://www.drupal.org/user/159062)
* CHANGELOG.txt for helpful souls with their patches, suggestions and reports.


## READ MORE
See the project page on drupal.org for more updated info:

[Blazy module](https://drupal.org/project/blazy)
