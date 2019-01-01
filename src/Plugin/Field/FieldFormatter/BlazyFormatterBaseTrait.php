<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

/**
 * A Trait common for blazy image, video and file ER formatters.
 */
trait BlazyFormatterBaseTrait {

  /**
   * An entity manager object.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyFormatterManager
   */
  protected $blazyManager;

  /**
   * The blazy oembed service.
   *
   * @var \Drupal\blazy\BlazyOEmbed
   * @todo remove default null post Blazy 8.2.x full release.
   */
  protected $blazyOembed = NULL;

  /**
   * Returns the blazy manager.
   */
  public function blazyManager() {
    return $this->blazyManager;
  }

  /**
   * Returns the blazy oEmbed service.
   *
   * @todo remove null check post Blazy 8.2.x full release.
   */
  public function blazyOembed() {
    if (is_null($this->blazyOembed)) {
      $this->blazyOembed = \Drupal::service('blazy.oembed');
    }
    return $this->blazyOembed;
  }

  /**
   * Builds the settings.
   */
  public function buildSettings() {
    $settings              = $this->getSettings();
    $settings['plugin_id'] = $this->getPluginId();

    return $settings;
  }

  /**
   * Returns the blazy admin service.
   */
  public function admin() {
    return \Drupal::service('blazy.admin.formatter');
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return $this->admin()->getSettingsSummary($this->getScopedFormElements());
  }

}
