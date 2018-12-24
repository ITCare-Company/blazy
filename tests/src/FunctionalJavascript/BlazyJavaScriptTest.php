<?php

namespace Drupal\Tests\blazy\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\blazy\Traits\BlazyUnitTestTrait;
use Drupal\Tests\blazy\Traits\BlazyCreationTestTrait;

/**
 * Tests the Blazy JavaScript using PhantomJS.
 *
 * @group blazy
 */
class BlazyJavaScriptTest extends WebDriverTestBase {

  use BlazyUnitTestTrait;
  use BlazyCreationTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'field',
    'filter',
    'image',
    'node',
    'text',
    'blazy',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->setUpVariables();

    $this->entityManager          = $this->container->get('entity.manager');
    $this->entityFieldManager     = $this->container->get('entity_field.manager');
    $this->formatterPluginManager = $this->container->get('plugin.manager.field.formatter');
    $this->blazyAdmin             = $this->container->get('blazy.admin');
    $this->blazyManager           = $this->container->get('blazy.manager');
    $data['settings']['ratio']    = '16:9';

    $this->setUpContentTypeTest($this->bundle);
    $this->setUpFormatterDisplay($this->bundle, $data);
    $this->setUpContentWithItems($this->bundle);
  }

  /**
   * Test the Blazy element from loading to loaded states.
   */
  public function testFormatterDisplay() {
    $session = $this->getSession();
    $image_path = $this->getImagePath(TRUE);

    $this->drupalGet('node/' . $this->entity->id());

    // Capture the loading moment.
    $this->createScreenshot($image_path . '/1_blazy_loading.png');

    // Wait a moment.
    $session->wait(3000);

    // Trigger Blazy to load images by scrolling down window.
    $session->executeScript('window.scrollTo(0, document.body.scrollHeight);');

    // Wait for the loaded images, at least one will do dependent on viewport.
    // @see https://www.drupal.org/project/drupal/issues/2892440
    $loaded = $this->assertSession()->waitForElementVisible('css', '.b-loaded');
    $this->assertNotNull($loaded, 'Blazy image is loaded, one or more.');

    // Wait a moment.
    $session->wait(10000);

    // Capture the loaded moment, only images within viewport are loaded here.
    // The screenshots are at sites/default/files/simpletest/blazy.
    $this->createScreenshot($image_path . '/2_blazy_loaded.png');
  }

}
