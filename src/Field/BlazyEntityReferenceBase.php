<?php

namespace Drupal\blazy\Field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\blazy\BlazyDefault;

/**
 * Base class for all entity reference formatters with field details.
 *
 * The most robust formatter at field level, more than BlazyEntityMediaBase, to
 * support nested/ overlayed formatters like seen at Slick/ Splide Paragraphs
 * formatters which is not supported at BlazyEntityMediaBase to avoid
 * complication -- embedding entities within Media, although fine and possible.
 *
 * @see \Drupal\slick\Plugin\Field\FieldFormatter\SlickEntityReferenceFormatterBase
 */
abstract class BlazyEntityReferenceBase extends BlazyEntityMediaBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return BlazyDefault::extendedSettings() + BlazyDefault::gridSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    if (isset($element['layout'])) {
      $layout_description = $element['layout']['#description'];
      $element['layout']['#description'] = $this->t('Create a dedicated List (text - max number 1) field related to the caption placement to have unique layout per slide with the following supported keys: top, right, bottom, left, center, center-top, etc. Be sure its formatter is Key.') . ' ' . $layout_description;
    }

    if (isset($element['overlay']['#description'])) {
      $element['overlay']['#description'] .= ' ' . $this->t('The formatter/renderer is managed by the child formatter.');
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildElementExtra(array &$element, $entity, $langcode) {
    parent::buildElementExtra($element, $entity, $langcode);

    $this->formatter->hashtag($element);

    $settings = &$element['#settings'];
    $_class   = $settings['class'];
    $_layout  = $settings['layout'];

    // Layouts can be builtin, or field, if so configured.
    if (!empty($_layout)) {
      $layout = $_layout;
      if (strpos($layout, 'field_') !== FALSE && isset($entity->{$layout})) {
        $layout = BlazyField::getString($entity, $layout, $langcode);
      }
      $settings['layout'] = $layout;
    }

    // Classes, if so configured.
    if (!empty($_class) && isset($entity->{$_class})) {
      $settings['class'] = BlazyField::getString($entity, $_class, $langcode);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getCaption(array &$element, $entity, $langcode) {
    parent::getCaption($element, $entity, $langcode);

    $settings  = $this->formatter->toHashtag($element);
    $view_mode = $settings['view_mode'] ?? 'full';
    $_link     = $settings['link'];
    $_overlay  = $settings['overlay'];

    // Link, if so configured.
    if (!empty($_link) && isset($entity->{$_link})) {
      $links = BlazyField::view($entity, $_link, $view_mode);
      $formatter = $links['#formatter'] ?? 'x';

      // Only simplify markups for known formatters registered by link.module.
      if ($links && in_array($formatter, ['link'])) {
        $links = [];
        foreach ($entity->{$_link} as $link) {
          $links[] = $link->view($view_mode);
        }
      }
      $element['caption']['link'] = $links;
    }

    // Overlay, like slider or video over slider, if so configured.
    if (!empty($_overlay) && isset($entity->{$_overlay})) {
      // @todo remove after another check, if not needed down here.
      $denied = $this->formatter->denied($entity);
      $overlay = $entity->get($_overlay)->view($view_mode);
      $element['caption']['overlay'] = $denied ?: $overlay;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginScopes(): array {
    $parent   = parent::getPluginScopes();
    $_strings = ['text', 'string', 'list_string'];
    $strings  = $this->getFieldOptions($_strings);
    $_links   = ['text', 'string', 'link'];

    return [
      'classes' => $strings,
      'images'  => $this->getFieldOptions(['image']),
      'layouts' => $strings,
      'links'   => $this->getFieldOptions($_links),
      'vanilla' => TRUE,
    ] + $parent;
  }

}
