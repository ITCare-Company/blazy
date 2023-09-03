<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

/**
 * Plugin implementation of the `Blazy File` or `Blazy Image`.
 *
 * Since 2.17, sub-modules can re-use this if similar to ::buildElements():
 * \Drupal\gridstack\Plugin\Field\FieldFormatter\GridStackFileFormatterBase
 * \Drupal\mason\Plugin\Field\FieldFormatter\MasonFileFormatterBase.
 *
 * @see \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFileFormatter
 * @see \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyImageFormatter
 */
class BlazyFormatterBlazy extends BlazyFileSvgFormatterBase {

  /**
   * {@inheritdoc}
   */
  protected static $namespace = 'blazy';

  /**
   * {@inheritdoc}
   */
  protected static $itemId = 'content';

  /**
   * {@inheritdoc}
   */
  protected static $itemPrefix = 'blazy';

  /**
   * {@inheritdoc}
   *
   * @todo make it caption similar to sub-modules for easy 3.x migrations.
   */
  protected static $captionId = 'captions';

  /**
   * {@inheritdoc}
   */
  protected function buildElements(array &$build, $files, $langcode) {
    $this->formatter->hashtag($build);

    $settings = $build['#settings'];
    $limit    = $this->getViewLimit($settings);

    foreach ($this->getElements($build, $files) as $delta => $element) {
      // If a Views display, bail out if more than Views delta_limit.
      // @todo figure out why Views delta_limit doesn't stop us here.
      if ($limit > 0 && $delta > $limit - 1) {
        break;
      }

      // Since 2.17, match sub-modules `items` for easy swap later to DRY.
      $build['items'][] = $element;
    }
  }

}
