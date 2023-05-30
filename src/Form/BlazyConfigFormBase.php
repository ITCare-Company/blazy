<?php

namespace Drupal\blazy\Form;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines blazy admin settings form base.
 */
abstract class BlazyConfigFormBase extends ConfigFormBase {

  /**
   * The library discovery service.
   *
   * @var \Drupal\Core\Asset\LibraryDiscoveryInterface
   */
  protected $libraryDiscovery;

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyManagerInterface
   */
  protected $manager;

  /**
   * The available options to check for.
   *
   * @var array
   */
  protected $validatedOptions = [];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /**
     * @var \Drupal\blazy_ui\Form\BlazySettingsForm
     */
    $instance = parent::create($container);
    $instance->libraryDiscovery = $container->get('library.discovery');
    $instance->manager = $container->get('blazy.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    if ($options = $this->validatedOptions) {
      foreach ($options as $option) {
        if ($form_state->hasValue($option)) {
          // Not effective, best is to validate output, yet better than misses.
          $value = Xss::filter($form_state->getValue($option));
          $form_state->setValue($option, $value);
        }
      }
    }
  }

}
