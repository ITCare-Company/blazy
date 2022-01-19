
***
# <a name="changes"></a>NOTABLE CHANGES
* _Blazy 2.6_:
   + [Drupal 10 ready](https://drupal.org/node/3254692).
   + `dBlazy.js` removed many old IEs fallback. Some were moved into polyfill
     which can be ditched via Blazy UI to abandon IE supports. Should you need
     to support more, please find and include polyfill into your theme globally.
   + Old bLazy is now a [fallback for IO](https://drupal.org/node/3258851) to
     have a single source of truth to minimize competitions and complications.
   + [Decoupled lazyload JavaScript](https://drupal.org/node/3257512). Now Blazy
     works without JavaScript within/without JavaScript browsers.
     Even [AMP](https://drupal.org/node/3101810) pages.
   + [Massive optimization](https://drupal.org/node/3257511). Please report any
     uncovered regressions, or issues for quick fixes. Thanks.
