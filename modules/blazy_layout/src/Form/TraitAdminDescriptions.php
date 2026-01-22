<?php

namespace Drupal\blazy_layout\Form;

/**
 * A description Trait to declutter, and focus more on form elements.
 */
trait TraitAdminDescriptions {

  /**
   * Provides unified description for easy edit and declutter forms.
   */
  protected function description(array $data = []): array {
    $max = $data['max'] ?? 0;
    $url = $data['url'] ?? '';
    $help = $data['help'] ?? '';

    return [
      'count' => $this->t('The amount of regions (max @max, excluding Background region), normally matches the amount of grids specific for Native Grid and Flexbox. Visit <a href=":url">Blazy UI > Max region count</a> to change the allowed maximum region amount: @max. Regions beyond that will be hidden.', [
        ':url' => $url,
        '@max' => $max,
      ]),
      'colors' => $this->t('If you input CSS framework cosmetic color classes (Bootstrap, etc.) under <b>Classes</b> option above, it might conflict or be overriden. Just choose one of them. Leave them to default (color #000000/ black, and opacity 1) values to use CSS framework. Useful if colors are not provided by frameworks. Overlay options require Blazy Image/Media block with <b>Use CSS Background</b> enabled, or <b>Styles &gt; Media</b>, to exist in the region. Text with <code>P</code> tag.'),
      'ete' => $this->t('If enabled, the main background will span edge to edge. Works better with <b>Max width</b> option, and themes with wide content region and without sidebars. Requires parent selectors without <code>overflow: hidden</code> rules, else cropped. Try Bartik if any issues.'),
      'padding' => $this->t('Valid CSS padding value, e.g.: <code>3rem or 15px 30px</code>. Leave empty if using CSS framework like Bootstrap, etc. Input padding as classes in the relevant <b>Classes</b> option instead.'),
      'max_width' => $this->t('The max-width of the <b>b-layout</b> container. Useful to reveal the background image, if padding is cumbersome. Valid CSS max-width value, e.g.: <code>82% or 1270px</code>. To have a mobile up max-width, use a colon-separated media query <small>WINDOW_MIN_WIDTH:LAYOUT_MAX_WIDTH</small> pair with spaces, e.g.: <br><code>0px:98% 768px:90% 1270px:82%</code><br>Affected by parent container widths of this layout wrapper. Try Bartik if any issues.'),
      'gapless' => $this->t('Flexbox and Native grid only. Remove gaps or margins to make it gapless.'),
      'media' => $this->t('Read more how to use media as background <a href=":help">here</a>.', [
        ':help' => $help,
      ]),
      'mlfe' => $this->t('Requires <a href=":url2">Media library form element</a> module.', [
        ':url2' => 'https://www.drupal.org/project/media_library_form_element',
      ]),
      'link' => $this->t('<b>Supported types</b>: Link or plain Text containing URL. It will be used for <b>Media switcher &gt; Image linked by Link field</b> so that the image is wrapped by this Link value, only if its formatter/ output is plain text URL. This Link field should exist at the media bundles: image, video and remote_video, so to get unique link per region.'),
      'classes' => $this->t('Use space: <code>bg-dark text-white</code>. May use CSS framework classes like Bootstrap, e.g.: <code>p-sm-2 p-md-5</code>'),
      'align_items' => $this->t('Flexbox and Native Grid only. Try <code>start</code> to have floating elements, but might break Blazy CSS background. The CSS align-items property sets the align-self value on all direct children as a group. In Flexbox, it controls the alignment of items on the Cross Axis. In Grid Layout, it controls the alignment of items on the Block Axis within their grid area. <a href=":url">Read more</a>', [
        ':url' => 'https://developer.mozilla.org/en-US/docs/Web/CSS/align-items',
      ]),
      'grid_auto_rows' => $this->t('Native Grid only. Accepted values: auto, min-content, max-content, minmax. Spefiic for minmax, it requires additional arguments, e.g.: minmax(80px, auto). Default to use the CSS rule <code>var(--bn-row-height-native)</code> or 80px. <a href=":url">Read more</a>', [
        ':url' => 'https://developer.mozilla.org/en-US/docs/Web/CSS/grid-auto-rows',
      ]),
      'attributes' => $this->t('Use comma: role|main,data-key|value'),
      'subclasses' => $this->t('Use space: bg-dark text-white. May use CSS framework classes like Bootstrap, e.g.: <code>p-sm-2 p-md-5</code>'),
      'row_classes' => $this->t('Use space: align-items-stretch no-gutters'),
    ];
  }

}
