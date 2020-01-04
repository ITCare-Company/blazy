<?php

namespace Drupal\Tests\blazy\Unit;

use Drupal\blazy\BlazyBreakpoint;

/**
 * @coversDefaultClass \Drupal\blazy\BlazyBreakpoint
 *
 * @group blazy
 */
class BlazyBreakpointUnitTest extends BlazyUnitTest {

  /**
   * Test \Drupal\blazy\BlazyBreakpoint::widthFromDescriptors.
   *
   * @param string $data
   *   The input data which can be string, or integer.
   * @param mixed|bool|int $expected
   *   The expected output.
   *
   * @covers ::widthFromDescriptors
   * @dataProvider providerTestWidthFromDescriptors
   */
  public function testWidthFromDescriptors($data, $expected) {
    $result = BlazyBreakpoint::widthFromDescriptors($data);
    $this->assertSame($result, $expected);
  }

  /**
   * Provide test cases for ::testWidthFromDescriptors().
   */
  public function providerTestWidthFromDescriptors() {
    return [
      [1024, 1024],
      ['1024', 1024],
      ['769w', 769],
      ['640w 2x', 640],
      ['2x 640w', 640],
      ['xYz123', FALSE],
    ];
  }

  /**
   * Test \Drupal\blazy\BlazyBreakpoint::isCrop().
   *
   * @covers ::isCrop
   * @dataProvider providerIsCrop
   */
  public function testIsCrop($image_style_id, $expected) {
    $is_cropped = BlazyBreakpoint::isCrop($image_style_id);

    $this->assertEquals($expected, !empty($is_cropped));
  }

  /**
   * Provider for ::testIsCrop.
   */
  public function providerIsCrop() {
    return [
      'Cropped image style' => [
        'blazy_crop',
        TRUE,
      ],
      'Non-cropped image style' => [
        'large',
        FALSE,
      ],
    ];
  }

  /**
   * Test \Drupal\blazy\BlazyBreakpoint::cleanUpBreakpoints().
   *
   * @covers ::cleanUpBreakpoints
   * @dataProvider providerTestCleanUpBreakpoints
   */
  public function testCleanUpBreakpoints($breakpoints, $expected_breakpoints, $blazy, $expected_blazy) {
    $settings['blazy'] = $blazy;
    $settings['breakpoints'] = $breakpoints;

    BlazyBreakpoint::cleanUpBreakpoints($settings);
    $this->assertEquals($expected_breakpoints, $settings['breakpoints']);

    // Verify that Blazy is activated by breakpoints.
    $this->assertEquals($expected_blazy, $settings['blazy']);
  }

  /**
   * Provider for ::testCleanUpBreakpoints().
   */
  public function providerTestCleanUpBreakpoints() {
    return [
      'empty' => [
        [],
        [],
        FALSE,
        FALSE,
      ],
      'not so empty' => [
        $this->getEmptyBreakpoints(),
        [],
        FALSE,
        FALSE,
      ],
      'mixed empty' => [
        $this->getDataBreakpoints(),
        $this->getDataBreakpoints(TRUE),
        FALSE,
        TRUE,
      ],
      'mixed empty blazy enabled first' => [
        $this->getDataBreakpoints(),
        $this->getDataBreakpoints(TRUE),
        FALSE,
        TRUE,
      ],
    ];
  }

}
