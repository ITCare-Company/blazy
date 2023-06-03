<?php

namespace Drupal\blazy\Views;

/**
 * Provides common views style plugin interface.
 */
interface BlazyStylePluginInterface {

  /**
   * Returns the blazy manager.
   */
  public function blazyManager();

  /**
   * Returns an individual row/element content.
   */
  public function buildElement(array &$element, $row, $index);

  /**
   * Returns the first Blazy formatter found, to save image dimensions once.
   *
   * Given 100 images on a page, Blazy will call
   * ImageStyle::transformDimensions() once rather than 100 times and let the
   * 100 images inherit it as long as the image style has CROP in the name.
   */
  public function getFirstImage($row): array;

  /**
   * Returns the renderable array of field containing rendered and raw data.
   */
  public function getFieldRenderable($row, $index, $field_name = '', $multiple = FALSE): array;

  /**
   * Returns available fields for select options.
   */
  public function getDefinedFieldOptions(array $defined_options = []): array;

  /**
   * Returns the string values for the expected Title, ET label, List, Term.
   *
   * @todo re-check this, or if any consistent way to retrieve string values.
   */
  public function getFieldString($row, $field_name, $index, $clean = TRUE): array;

  /**
   * Returns the rendered field, either string or array.
   */
  public function getFieldRendered($index, $field_name = '', $restricted = FALSE): array;

  /**
   * Returns the modified renderable image_formatter to support lazyload.
   */
  public function getImageRenderable(array &$settings, $row, $index): array;

  /**
   * Checks if we can work with this formatter, otherwise no go if flattened.
   */
  public function getImageArray($row, $index, $field_image = ''): array;

  /**
   * Get the image item to work with out of this formatter.
   *
   * All this mess is because Views may render/flatten images earlier.
   */
  public function getImageItem($image): ?object;

  /**
   * Returns the rendered caption fields.
   */
  public function getCaption($index, array $settings = []): array;

  /**
   * Returns the rendered layout fields.
   */
  public function getLayout(array &$settings, $index): void;

}
