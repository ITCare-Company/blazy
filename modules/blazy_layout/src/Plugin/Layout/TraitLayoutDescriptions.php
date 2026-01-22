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
      'settings' => $this->t('Use Blazy Image/ Media formatters to have background or even nested grids when creating blocks. Reload the page if some options do not update CSS/preview after saving this modal form.'),
      'hero' => $this->t("Choose the delta of region if it should be treated as Hero. Normally the largest media background. Leave it as is if this layout is placed under a Hero, or this region is overlayed by a Hero. Hero media should only exist once per page like Page Title, see <a href=':url'>Building heroes</a>.", [
        ':url' => '/admin/help/blazy_ui#heroes',
      ]),
      'label' => $this->t('The human-readable region name for theming.'),
    ];
  }

}
