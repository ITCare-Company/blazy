<?php

namespace Drupal\blazy\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * Provides base form for a entity instance configuration form.
 */
abstract class BlazyEntityFormBase extends EntityForm implements BlazyEntityFormBaseInterface {

  /**
   * Defines the nice name.
   *
   * @var string
   */
  protected static $niceName = 'Slick';

  /**
   * Defines machine name.
   *
   * @var string
   */
  protected static $machineName = 'slick';

  /**
   * The blazy admin service.
   *
   * @var \Drupal\blazy\Form\BlazyAdminInterface
   */
  protected $admin;

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyManagerInterface
   */
  protected $manager;

  /**
   * The form elements.
   *
   * @var array
   */
  protected $formElements;

  /**
   * The form grid elements.
   *
   * @var array
   */
  protected $formGrids = [
    'settings',
    ['options', 'settings'],
    ['respond', 'settings'],
    ['breakpoints', 'responsive'],
    ['responsives', 'responsive'],
  ];

  /**
   * {@inheritdoc}
   */
  public function admin() {
    return $this->admin;
  }

  /**
   * {@inheritdoc}
   */
  public function manager() {
    return $this->manager;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $this->attributes($form);

    // Change page title for the duplicate operation.
    if ($this->operation == 'duplicate') {
      $form['#title'] = $this->t('<em>Duplicate %name optionset</em>: @label', [
        '%name' => static::$niceName,
        '@label' => $this->entity->label(),
      ]);
      $this->entity = $this->entity->createDuplicate();
    }

    // Change page title for the edit operation.
    if ($this->operation == 'edit') {
      $form['#title'] = $this->t('<em>Edit %name optionset</em>: @label', [
        '%name' => static::$niceName,
        '@label' => $this->entity->label(),
      ]);
    }

    $this->finalize($form);

    return parent::form($form, $form_state);
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::save().
   *
   * @todo revert #1497268, or use config_update instead.
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;

    // Satisfy phpstan.
    if (!method_exists($entity, 'set')) {
      return parent::save($form, $form_state);
    }

    // Prevent leading and trailing spaces in entity names.
    $entity->set('label', Html::escape(trim($entity->label())))
      ->set('id', $entity->id());

    $status        = $entity->save();
    $label         = $entity->label();
    $edit_link     = $entity->toLink($this->t('Edit'), 'edit-form')->toString();
    $entity_type   = $entity->getEntityType();
    $config_prefix = '';

    // Satisfy phpstan.
    if (method_exists($entity_type, 'getConfigPrefix')) {
      $config_prefix = $entity_type->getConfigPrefix();
    }

    $message = ['@config_prefix' => $config_prefix, '%label' => $label];
    $notice  = [
      '@config_prefix' => $config_prefix,
      '%label' => $label,
      'link' => $edit_link,
    ];

    if ($status == SAVED_UPDATED) {
      // If we edited an existing entity.
      // @todo #2278383.
      $this->messenger()->addMessage($this->t('@config_prefix %label has been updated.', $message));
      $this->logger(static::$machineName)->notice('@config_prefix %label has been updated.', $notice);
    }
    else {
      // If we created a new entity.
      $this->messenger()->addMessage($this->t('@config_prefix %label has been added.', $message));
      $this->logger(static::$machineName)->notice('@config_prefix %label has been added.', $notice);
    }

    $form_state->setRedirectUrl($entity->toUrl('collection'));
    return parent::save($form, $form_state);
  }

  /**
   * Setup form attributes.
   */
  protected function finalize(array &$form): void {
    $admin_css = $this->manager->config('admin_css', 'blazy.settings');
    if ($admin_css) {
      $this->toGrid($form);
      $form['#attached']['library'][] = 'blazy/admin';
    }
  }

  /**
   * Setup form attributes.
   */
  protected function attributes(array &$form): void {
    if (!isset($form['#attributes'])) {
      $form['#attributes'] = [];
    }

    $attrs = &$form['#attributes'];
    $name = str_replace('_', '-', static::$machineName);

    $classes = ['form'];
    // @todo remove slick after sub-modules.
    foreach (['blazy', 'slick', 'optionset', $name] as $key) {
      $classes[] = 'form--' . $key;
    }

    $classes[] = 'b-tooltip';

    // Add some BEM orders for consistency.
    if (isset($attrs['class'])) {
      $attrs['class'] = array_merge($classes, $attrs['class']);
    }
  }

  /**
   * Setup form grids.
   */
  protected function toGrid(array &$form): void {
    if ($grids = $this->formGrids) {
      foreach ($grids as $keys) {
        if (is_string($keys)) {
          if (isset($form[$keys])) {
            $this->toNativeGrid($form[$keys]);
          }
        }
        else {
          if (is_array($keys)) {
            $key1 = $keys[0] ?? NULL;
            $key2 = $keys[1] ?? NULL;

            $check = FALSE;
            foreach ($keys as $key) {
              if (isset($form[$key])) {
                $children = Element::children($form[$key]);
                $child = reset($children);

                if ($child) {
                  $children = Element::children($form[$key][$child]);
                  foreach ($children as $k) {
                    if (isset($form[$key][$child][$k]['settings'])) {
                      $formsets = &$form[$key][$child][$k]['settings'];
                      $this->toNativeGrid($formsets);
                      $check = TRUE;
                    }
                  }
                }
              }
            }

            if (!$check) {
              if (isset($form[$key1][$key2])) {
                $formsets = &$form[$key1][$key2];
                $this->toNativeGrid($formsets);
              }
            }
          }
        }
      }
    }
  }

  /**
   * Setup form grids.
   */
  private function toNativeGrid(array &$form): array {
    $children = Element::children($form);
    $total    = count($children);
    $options  = [
      'count'   => $total,
      'classes' => 'b-nativegrid--form',
    ];

    $check   = $this->manager->initNativeGrid($options);
    $attrs   = $check['attributes'];
    $sets    = $check['settings'];
    $classes = implode(' ', $attrs['class']);

    foreach ($attrs['class'] as $key => $value) {
      $form['#attributes']['class'][] = $value;
    }

    foreach ($children as $delta => $key) {
      if (!isset($form[$key]['#wrapper_attributes']['class'])) {
        $form[$key]['#wrapper_attributes']['class'] = [];
      }

      $wrapper_attrs = &$form[$key]['#wrapper_attributes'];
      $content_attrs = [];

      $blazy = $sets['blazies']->reset($sets);
      $blazy->set('delta', $delta);
      $dummies['class'] = [];

      $this->manager->gridItemAttributes($dummies, $content_attrs, $sets);
      $wrapper_attrs = $this->manager->merge($wrapper_attrs, $dummies);
      $wrapper_attrs['class'][] = 'grid--admin';
    }

    $form['grid_start'] = [
      '#markup' => '<div class="' . $classes . '">',
      '#weight' => -120,
    ];

    $form['grid_end'] = [
      '#markup' => '</div>',
      '#weight' => 120,
    ];
    return $check;
  }

}
