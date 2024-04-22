<?php

namespace Drupal\blazy\internals;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides internal content utilities.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 */
class Content {

  /**
   * Returns a message if access to view the entity is denied.
   */
  public static function denied($entity): array {
    if (!$entity instanceof EntityInterface) {
      return [];
    }

    if (!$entity->access('view')) {
      $parameters = [
        '@label' => $entity->getEntityType()->getSingularLabel(),
        '@id' => $entity->id(),
        '@langcode' => $entity->language()->getId(),
        '@title' => $entity->label(),
      ];
      $restricted_access_label = $entity->access('view label')
       ? new FormattableMarkup('@label @id (@title)', $parameters)
       : new FormattableMarkup('@label @id', $parameters);
      return ['#markup' => $restricted_access_label];
    }
    return [];
  }

  /**
   * A helper to gradually migrate sub-modules content into theme_blazy().
   */
  public static function toContent(
    array &$data,
    $unset = FALSE,
    array $keys = ['content', 'box', 'slide'],
  ): array {
    $result = [];
    foreach ($keys as $key) {
      $value = $data[$key] ?? $data["#$key"] ?? [];
      if ($value) {
        $result = $value;
        break;
      }
      if ($unset) {
        unset($data[$key]);
      }
    }
    return $result;
  }

  /**
   * Returns the common content item.
   */
  public static function toHtml($content, $tag = 'div', $class = NULL): array {
    if ($class) {
      $attributes = is_array($class) ? $class : ['class' => [$class]];
      $output = [
        '#type' => 'html_tag',
        '#tag' => $tag,
        '#attributes' => $attributes,
      ];

      // Allows empty IFRAME, etc. tags.
      if (!is_null($content)) {
        $content = is_string($content) ? ['#markup' => $content] : $content;
        $output['content'] = $content;
      }

      return $output;
    }
    return $content ?: [];
  }

}
