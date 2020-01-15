<?php

namespace Drupal\Tests\blazy\FunctionalJavascript;

/**
 * Tests the Blazy IO JavaScript using PhantomJS, or Chromedriver.
 *
 * @group blazy
 */
class BlazyIoJavaScriptTest extends BlazyJavaScriptTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->scriptLoader = 'io';

    // Enable IO support.
    $this->container->get('config.factory')->getEditable('blazy.settings')->set('io.enabled', TRUE)->save();
    $this->container->get('config.factory')->clearStaticCache();
  }

  /**
   * Test the Blazy element from loading to loaded states.
   */
  public function testFormatterDisplay() {
    $this->prepareJsTestPage();

    // Ensures Blazy is not loaded on page load.
    // @todo recheck since this appears to be randomly failing since D8.7.
    // Likely the images are not having enough vertical space to be below the
    // fold. This appears to be no issues with BlazyFilter.
    $this->assertSession()->elementNotExists('css', '.b-loaded');

    $this->doTestFormatterDisplay();
  }

}
