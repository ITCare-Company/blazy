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
      'count' => $this->t('Number of regions (max @max, excluding Background).
      Typically matches the number of grids for Native Grid or Flexbox.
      Change the limit at <a href=":url">Blazy UI → Max region count</a>.
      Regions beyond the limit are hidden.', [
        ':url' => $url,
        '@max' => $max,
      ]),
      'colors' => $this->t('Custom text and overlay colors. If CSS framework color classes (e.g. Bootstrap) are used in <b>Classes</b>, they may override these values—use one or the other. Leave defaults (black, opacity 1) to defer to the framework. Overlay options require <b>Use CSS background</b> or <b>Styles → Media</b> to be enabled. Applies to text elements (<code>p</code>).'),
      'ete' => $this->t('Expand the background edge-to-edge. Works best with <b>Max width</b> and wide themes without sidebars. Parent containers must not use <code>overflow: hidden</code>, or content may be cropped. Try Bartik to validate issues.'),
      'padding' => $this->t('CSS padding value, e.g. <code>3rem</code> or <code>15px 30px</code>. Leave empty when using a CSS framework and apply padding via <b>Classes</b> instead.'),
      'max_width' => $this->t('Max width of the <b>b-layout</b> container. Useful for revealing background images. Accepts CSS values such as <code>82%</code> or <code>1270px</code>. For responsive values, use space-separated pairs: <br><code>0px:98% 768px:90% 1270px:82%</code><br>Affected by parent container widths of this layout wrapper. Try Bartik to validate issues'),
      'gapless' => $this->t('Flexbox and Native Grid only. Removes gaps or margins between items.'),
      'media' => $this->t('Learn how to use media as background <a href=":help">here</a>.', [
        ':help' => $help,
      ]),
      'mlfe' => $this->t('Requires the <a href=":url2">Media Library Form Element</a> module.', [
        ':url2' => 'https://www.drupal.org/project/media_library_form_element',
      ]),
      'link' => $this->t('<b>Supported types</b>: Link or plain text URL. Used by <b>Media switcher → Image linked by Link field</b> to wrap images. Formatter output must be a plain URL. For per-region links, the field should exist on relevant media bundles (image, video, remote video).'),
      'classes' => $this->t('Space-separated classes, e.g. <code>bg-dark text-white</code>. CSS framework classes are supported (e.g. <code>p-sm-2 p-md-5</code>).'),
      'align_items' => $this->t('Flexbox and Native Grid only. Controls item alignment on the cross/block axis. Using <code>start</code> may affect CSS backgrounds. <a href=":url">Learn more</a>.', [
        ':url' => 'https://developer.mozilla.org/en-US/docs/Web/CSS/align-items',
      ]),
      'grid_auto_rows' => $this->t('Native Grid only. Accepted values: <code>auto</code>, <code>min-content</code>, <code>max-content</code>, <code>minmax()</code>. Example: <code>minmax(80px, auto)</code>. Defaults to <code>var(--bn-row-height-native)</code> or <code>80px</code>. <a href=":url">Learn more</a>.', [
        ':url' => 'https://developer.mozilla.org/en-US/docs/Web/CSS/grid-auto-rows',
      ]),
      'attributes' => $this->t('Comma-separated attributes, e.g. <code>role|main,data-key|value</code>.'),
      'subclasses' => $this->t('Space-separated classes, e.g. <code>bg-dark text-white</code>.'),
      'row_classes' => $this->t('Space-separated row classes, e.g. <code>align-items-stretch no-gutters</code>.'),
    ];
  }

}
