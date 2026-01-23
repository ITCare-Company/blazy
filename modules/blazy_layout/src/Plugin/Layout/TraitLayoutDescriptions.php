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
    return [
      'settings' => $this->t('Use Blazy Image/Media formatters to enable backgrounds and nested grids in blocks. Reload the page if CSS or previews do not update after saving this modal.'),
      'hero' => $this->t("Select the region delta to mark it as the Hero. Typically the largest background media. Leave unchanged if the layout is already below a Hero, or if this region is overlaid by one. Hero media should appear only once per page, similar to Page Title. See <a href=':url'>Building heroes</a>.", [
        ':url' => '/admin/help/blazy_ui#heroes',
      ]),
      'label' => $this->t('Human-readable region label, used for theming.'),
    ];
  }

}
