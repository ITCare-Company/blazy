<?php

namespace Drupal\blazy_layout\Plugin\Layout;

/**
 * Provides standardized layout-level descriptions for Blazy layouts.
 *
 * Centralizes help text to keep layout plugins concise while maintaining
 * clear guidance for site builders.
 */
trait TraitLayoutDescriptions {

  /**
   * Returns a list of layout configuration descriptions.
   */
  protected function description(array $data = []): array {
    $scope = $data['css_scope'] ?? '';
    $ui_url = $data['ui_url'] ?? '';
    $blazy_help = $data['blazy_help'] ?? '';

    $css_scope = '';
    $ui_url_desc = '';

    if ($scope = $data['css_scope'] ?? '') {
      $css_scope = ' ' . $this->t(
        'under @scope',
        [
          '@scope' => $scope,
        ]
      );
    }

    if ($ui_url) {
      $ui_url_desc = $this->t(
        'Requires the "Allow custom inline CSS for Blazy" option to be enabled at @url.',
        [
          '@url' => $ui_url,
        ]
      ) . ' ';
    }

    return [
      'settings' => $this->t(
        'Use Blazy Image or Media formatters to enable background media and nested grids within blocks. Reload the page if CSS or previews do not update after saving this modal.'
      ),

      'hero' => $this->t(
        'Select the region delta to mark it as the Hero—typically the largest background media. Leave empty if the layout already appears below a Hero, or if this region is overlaid by another. Hero media should appear only once per page, similar to a Page Title. See <a href=":url">Building heroes</a>.',
        [
          ':url' => '/admin/help/blazy_ui#heroes',
        ]
      ),

      'custom_css' => $this->t(
        "@ui_urlThis CSS is injected directly into the page <code>&lt;head&gt;</code> and applied at render time.
<ul>
  <li>Use scoped selectors only@css_scope</li>
  <li>Avoid targeting global elements (<code>html</code>, <code>body</code>)</li>
  <li>External imports and remote URLs are ignored</li>
  <li>Leave empty to avoid unnecessary layout instability</li>
</ul>
Incorrect CSS may break layout rendering or affect unrelated components. This option is intended primarily to mitigate <a href=':cls'>CLS issues</a> when the provided <code>min-height</code> utility classes (<b>xxs xs sm md lg xl xxl x2l x3l x4l x5l</b>) are insufficient.",
        [
          '@css_scope' => $css_scope,
          '@ui_url' => $ui_url_desc,
          ':cls' => $blazy_help . '#cls',
        ]
      ),

      'label' => $this->t(
        'Human-readable region label, primarily used for theming.'
      ),
    ];
  }

}
