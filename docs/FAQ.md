<br>
***
***
<br>
# <a name="faq"></a>FAQ

## CURRENT DEVELOPMENT STATUS
A full release should be reasonable after proper feedbacks from the community,
some code cleanup, and optimization where needed. Patches are very much welcome.


## PROGRAMATICALLY
[blazy.api.php](https://git.drupalcode.org/project/blazy/blob/8.x-2.x/blazy.api.php)


## PERFORMANCE TIPS:
* If breakpoints provided with tons of images, using image styles with ANY crop
  is recommended to avoid image dimension calculation with individual images.
  The image dimensions will be set once, and inherited by all images as long as
  they contain word crop. If using scaled image styles, regular calculation
  applies.
<br>
