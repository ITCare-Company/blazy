<?php

namespace Drupal\blazy_layout\Plugin\Layout;

/**
 * A description Trait to declutter, and focus more on form elements.
 */
trait TraitLayoutDescriptions {

  /**
   * Provides unified description for easy edit and declutter forms.
   */
  protected function description(array $data = []): array {
    $scope = $data['css_scope'] ?? '';
    $ui_url = $data['ui_url'] ?? '';

    $css_scope = '';
    $ui_url_desc = '';
    if ($scope = $data['css_scope'] ?? '') {
      $css_scope = ' ' . $this->t("under @scope", [
        '@scope' => $scope,
      ]);
    }
    if ($ui_url) {
      $ui_url_desc = $this->t('Requires "Allow custom inline CSS for Blazy" option to be enabled at @url.', [
        '@url' => $ui_url,
      ]) . ' ';
    }

    return [
      'settings' => $this->t('Use Blazy Image/Media formatters to enable backgrounds and nested grids in blocks. Reload the page if CSS or previews do not update after saving this modal.'),
      'hero' => $this->t("Select the region delta to mark it as the Hero. Typically the largest background media. Leave it empty if the layout is already below a Hero, or if this region is overlaid by one. Hero media should appear only once per page, similar to Page Title. See <a href=':url'>Building heroes</a>.", [
        ':url' => '/admin/help/blazy_ui#heroes',
      ]),
      'custom_css' => $this->t("@ui_urlThis CSS is injected directly into the page <code>&lt;head&gt;</code> and applied at render time.
<ul>
<li>Use scoped selectors only@css_scope</li>
<li>Avoid targeting global elements (html, body)</li>
<li>External imports and remote URLs are ignored</li>
<li>Leave it empty to avoid unnecessary layout instability</li>
</ul>
Incorrect CSS can break layout rendering or affect unrelated components. Only useful to fix CLS issues whenever the provided <code>min-height</code>: <b>xxs xs sm md lg xl xxl x2l x3l x4l x5l</b> CSS classes are too limited.",
      [
        '@css_scope' => $css_scope,
        '@ui_url' => $ui_url_desc,
      ]),
      'label' => $this->t('Human-readable region label, used for theming.'),
    ];
  }

}
