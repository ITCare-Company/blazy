<?php

namespace Drupal\blazy\Form;

use Drupal\Core\Url;

/**
 * Provides admin form specific to Blazy admin formatter.
 */
class BlazyAdminFormatter extends BlazyAdminFormatterBase {

  /**
   * Defines re-usable form elements.
   */
  public function buildSettingsForm(array &$form, $definition = []) {
    $definition['namespace'] = 'blazy';
    $definition['responsive_image'] = TRUE;

    $this->openingForm($form, $definition);
    $this->imageStyleForm($form, $definition);
    $this->mediaSwitchForm($form, $definition);

    if (!empty($definition['breakpoints'])) {
      $this->breakpointsForm($form, $definition);
    }

    if (isset($form['responsive_image_style'])) {
      $form['responsive_image_style']['#description'] = $this->t('Only expects multi-serving IMG, but not PICTURE element. Not compatible with below breakpoints, aspect ratio, yet. However it can still lazyload by checking <strong>Responsive image</strong> option via Blazy UI. Leave empty to disable.');

      if ($this->blazyManager()->getModuleHandler()->moduleExists('blazy_ui')) {
        $form['responsive_image_style']['#description'] .= ' ' . $this->t('<a href=":url" target="_blank">Enable lazyloading Responsive image</a>.', [':url' => Url::fromRoute('blazy.settings')->toString()]);
      }
    }

    $this->closingForm($form, $definition);
  }

}
