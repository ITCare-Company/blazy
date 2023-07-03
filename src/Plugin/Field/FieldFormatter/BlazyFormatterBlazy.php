<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the `Blazy File` or `Blazy Image` for Blazy only.
 *
 * @see \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFileFormatter
 * @see \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyImageFormatter
 */
class BlazyFormatterBlazy extends BlazyFileFormatterBase {

  /**
   * {@inheritdoc}
   *
   * @see https://www.php.net/manual/en/reserved.keywords.php
   */
  protected $namespace = 'blazy';

  /**
   * {@inheritdoc}
   */
  protected $itemId = 'blazy';

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $files = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($files)) {
      return [];
    }

    return $this->commonViewElements($items, $langcode, $files);
  }

  /**
   * {@inheritdoc}
   */
  protected function buildElements(array &$build, $files, $langcode) {
    foreach ($this->getElements($build, $files) as $delta => $element) {
      $build[$delta] = $this->formatter->getBlazy($element);
    }
  }

}
