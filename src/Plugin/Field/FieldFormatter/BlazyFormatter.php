<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

/**
 * Plugin for the Blazy image formatter.
 *
 * @todo this is the legacy image support, and likely deprecated.
 *
 * @FieldFormatter(
 *   id = "blazy",
 *   label = @Translation("Blazy"),
 *   field_types = {"image"}
 * )
 */
class BlazyFormatter extends BlazyFileFormatterBase {

  use BlazyFormatterTrait;

}
