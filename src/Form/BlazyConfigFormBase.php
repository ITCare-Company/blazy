<?php

namespace Drupal\blazy\Form;

use Drupal\Component\Utility\UrlHelper;
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
   * The available paths to check for.
   *
   * @var array
   */
  protected $validatedPaths = [];

  /**
   * The allowed tags can be NULL for default, or array.
   *
   * @var mixed
   */
  protected $allowedTags = NULL;

  /**
   * Whether to allow tags.
   *
   * @var bool
   */
  protected $stripTags = TRUE;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
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

    $paths = $this->validatedPaths;
    $options = $this->validatedOptions;
    $options = array_merge($options, $paths);

    if ($options) {
      foreach ($options as $option) {
        if ($form_state->hasValue($option)) {
          // Not effective, best is to validate output, yet better than misses.
          $value = $form_state->getValue($option);

          if ($value) {
            if (is_string($value)) {
              if ($this->stripTags) {
                $value = strip_tags($value, $this->allowedTags);
              }
              if ($paths && in_array($option, $paths)) {
                $value = UrlHelper::filterBadProtocol($value);
              }
              $value = Xss::filter($value, $this->allowedTags);
            }
            elseif (is_array($value)) {
              if ($this->stripTags) {
                $value = array_map(function ($val) {
                  return $val ? strip_tags($val, $this->allowedTags) : '';
                }, $value);
              }

              // $value = array_map(function ($val) {
              // return $val ? Xss::filter($val, $this->allowedTags) : '';
              // }, $value);
              array_walk($value, function (&$val, $key) use ($option, $paths) {
                if ($val) {
                  $check = $paths && in_array($option, $paths);
                  if ($check || $key == 'io_fallback') {
                    $val = UrlHelper::filterBadProtocol($val);
                  }
                  Xss::filter($val, $this->allowedTags);
                }
              });
            }
          }
          $form_state->setValue($option, $value);
        }
      }
    }
  }

}
