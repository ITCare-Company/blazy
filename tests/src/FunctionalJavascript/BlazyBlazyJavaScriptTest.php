<?php

namespace Drupal\Tests\blazy\FunctionalJavascript;

/**
 * Tests the Blazy bLazy JavaScript using PhantomJS, or Chromedriver.
 *
 * @group blazy
 */
class BlazyBlazyJavaScriptTest extends BlazyJavaScriptTestBase {

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
