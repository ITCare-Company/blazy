<?php

namespace Drupal\Tests\blazy\Kernel\Views;

use Drupal\Core\Form\FormState;
use Drupal\views\Views;

/**
 * Test Blazy Views integration.
 *
 * @coversDefaultClass \Drupal\blazy\Views\BlazyStylePluginBase
 *
 * @group blazy
 */
class BlazyViewsFileTest extends BlazyViewsTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['test_blazy_entity', 'test_blazy_entity_2'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);

    $this->entityFieldName = 'field_entity_test';
    $this->entityPluginId  = 'blazy_entity_test';
    $this->targetBundle    = 'bundle_target_test';
    $this->targetBundles   = [$this->targetBundle];
  }

  /**
   * Build contents.
   */
  private function buildContents() {
    $this->setUpRealImage();

    $bundle = $this->bundle;
    $settings['image_settings'] = [
      'image_style'  => 'blazy_crop',
      'media_switch' => 'blazy_test',
      'ratio'        => 'fluid',
      'view_mode'    => 'default',
    ];

    $this->setUpContentWithEntityReference($settings);

    // Create referencing entity.
    $this->referencingEntity = $this->createReferencingEntity();

    $data['fields'] = $this->getDefaultFields();

    // Create contents.
    $this->setUpContentTypeTest($bundle, $data);

    $data['settings'] = $this->getFormatterSettings();
    $display = $this->setUpFormatterDisplay($bundle, $data);

    $display->setComponent('field_image', [
      'type'     => 'blazy',
      'settings' => $settings['image_settings'],
      'label'    => 'hidden',
    ]);

    $display->save();

    $this->setUpContentWithItems($bundle);
  }

  /**
   * Make sure that the HTML list style markup is correct.
   */
  public function testBlazyViewsForm() {
    $this->buildContents();

    $view = Views::getView('test_blazy_entity_2');
    $this->executeView($view);
    $view->setDisplay('default');

    $style_plugin = $view->style_plugin;
    $style_plugin->options['grid'] = 0;

    $form = [];
    $form_state = new FormState();
    $style_plugin->buildOptionsForm($form, $form_state);
    $this->assertArrayHasKey('closing', $form);

    $style_plugin->submitOptionsForm($form, $form_state);

    $view->destroy();
  }

}
