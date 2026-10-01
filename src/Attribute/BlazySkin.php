<?php

declare(strict_types=1);

namespace Drupal\blazy\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a Skin attribute object.
 *
 * @see \Drupal\blazy\Annotation\BlazySkin
 * @see \Drupal\blazy\Skin\SkinManagerBase
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class BlazySkin extends Plugin {

  /**
   * Constructs a BlazySkin attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The label of the plugin.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
  ) {}

}
