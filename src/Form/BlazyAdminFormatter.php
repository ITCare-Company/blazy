<?php

namespace Drupal\blazy\Form;

/**
 * Provides admin form specific to Blazy admin formatter.
 */
class BlazyAdminFormatter extends BlazyAdminFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(array &$form, array $definition): void {
    $scopes = $this->toScopes($definition);

    $this->openingForm($form, $definition);
    $this->basicImageForm($form, $definition);

    if ($scopes->form('grid') && !isset($form['grid'])) {
      // Blazy doesn't need complex grid with multiple groups.
      if ($scopes->get('namespace') == 'blazy') {
        $scopes->set('is.grid_simple', TRUE);
      }

      $this->gridForm($form, $definition);
    }

    if ($scopes->form('fieldable') && !isset($form['image'])) {
      $this->fieldableForm($form, $definition);
    }

    $this->closingForm($form, $definition);
  }

  /**
   * {@inheritdoc}
   */
  public function openingForm(array &$form, array &$definition): void {
    $scopes    = $this->toScopes($definition);
    $namespace = $scopes->get('namespace', 'blazy');

    if ($scopes->is('vanilla')) {
      $form['vanilla'] = [
        '#type'        => 'checkbox',
        '#title'       => $this->t('Vanilla @namespace', ['@namespace' => $namespace]),
        '#description' => $this->t('<strong>Check</strong>:<ul><li>To render individual item as is as without extra logic.</li><li>To disable 99% @module features, and most of the mentioned options here, such as layouts, et al.</li><li>When the @module features can not satisfy the need.</li><li>Things may be broken! You are on your own.</li></ul><strong>Uncheck</strong>:<ul><li>To get consistent markups and its advanced features -- relevant for the provided options as @module needs to know what to style/work with.</li></ul>', ['@module' => $namespace]),
        '#weight'      => -112,
        '#enforced'    => TRUE,
        '#attributes'  => ['class' => ['form-checkbox--vanilla']],
        '#wrapper_attributes' => [
          'class' => [
            'form-item--full',
            'form-item--tooltip-bottom',
          ],
        ],
      ];
    }

    if ($optionsets = $scopes->data('optionsets')) {
      $form['optionset'] = [
        '#type'        => 'select',
        '#title'       => $this->t('Optionset'),
        '#options'     => $optionsets,
        '#enforced'    => TRUE,
        '#description' => $this->t('Enable the optionset UI module to manage the optionsets.'),
        '#weight'      => -108,
      ];
    }

    parent::openingForm($form, $definition);
  }

  /**
   * {@inheritdoc}
   */
  public function fieldableForm(array &$form, array $definition): void {
    $scopes = $this->toScopes($definition);
    $data = $scopes->get('data');

    if (isset($data['images'])) {
      $form['image'] = $this->baseForm($definition)['image'];
    }

    if (isset($data['thumbnails'])) {
      $form['thumbnail'] = [
        '#type'        => 'select',
        '#title'       => $this->t('Thumbnail image'),
        '#options'     => $scopes->data('thumbnails'),
        '#description' => $this->t('Leave empty to not use thumbnail pager.'),
      ];
    }

    if (isset($data['overlays'])) {
      $form['overlay'] = [
        '#type'        => 'select',
        '#title'       => $this->t('Overlay media'),
        '#options'     => $scopes->data('overlays'),
        '#description' => $this->t('Overlay is displayed over the main stage.'),
      ];
    }

    if (isset($data['titles'])) {
      // Ensures to not override Views content/ entity title, just formatters.
      if ($scopes->data('images') && !$scopes->is('_views')) {
        $scopes->set('data.titles.title', $this->t('Image Title'));
      }
      $form['title'] = [
        '#type'        => 'select',
        '#title'       => $this->t('Title'),
        '#options'     => $scopes->data('titles'),
        '#description' => $this->t('If provided, it will be wrapped with H2. Also supported the basic non-field Image title'),
      ];
    }

    if (isset($data['links'])) {
      $form['link'] = [
        '#type'        => 'select',
        '#title'       => $this->t('Link'),
        '#options'     => $scopes->data('links'),
        '#description' => $this->t('Link to content: Read more, View Case Study, etc.'),
      ];
    }

    // Allows empty options to raise awareness of this option.
    if (isset($data['classes'])) {
      $form['class'] = [
        '#type'        => 'select',
        '#title'       => $this->t('Item class'),
        '#options'     => $scopes->data('classes'),
        '#description' => $this->t('If provided, individual item will have this class, e.g.: to have different background with transparent images. Be sure its formatter is Key or Label. Accepted field types: list text, string (e.g.: node title), term/entity reference label.'),
        '#weight'      => 6,
      ];
    }

    if (isset($form['caption'])) {
      $form['caption']['#description'] = $this->t('Enable any of the following fields as captions. These fields are treated and wrapped as captions.');
    }

    if ($scopes->is('_views')) {
      if (isset($form['overlay'])) {
        $form['overlay']['#description'] .= ' ' . $this->t('Be sure to CHECK "Use field template" under its formatter if using Slick field formatter.');
      }
    }
    else {
      if (isset($form['caption'])) {
        $form['caption']['#description'] .= ' ' . $this->t('Be sure to make them visible at their relevant Manage display.');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function closingForm(array &$form, array $definition): void {
    $scopes = $this->toScopes($definition);

    if ($scopes->is('caches')) {
      $form['cache'] = [
        '#type'        => 'select',
        '#title'       => $this->t('Cache'),
        '#options'     => $this->getCacheOptions(),
        '#weight'      => 98,
        '#enforced'    => TRUE,
        '#description' => $this->t('Ditch all the logic to cached bare HTML. <ol><li><strong>Permanent</strong>: cached contents will persist (be displayed) till the next cron runs.</li><li><strong>Any number</strong>: expired by the selected expiration time, and fresh contents are fetched till the next cache rebuilt.</li></ol>A working cron job is required to clear stale cache. At any rate, cached contents will be refreshed regardless of the expiration time after the cron hits. <br />Leave it empty to disable caching.<br /><strong>Warning!</strong> Be sure no useless/ sensitive data such as Edit links as they are rendered as is regardless permissions. No permissions are changed, just ugly. Only enable it when all is done, otherwise cached options will be displayed while changing them.'),
      ];

      if ($scopes->is('_views')) {
        $form['cache']['#description'] .= ' ' . $this->t('Also disable Views cache (<strong>Advanced &gt; Caching</strong>) temporarily _only if trouble to see updated settings.');
      }
    }

    parent::closingForm($form, $definition);
  }

}
