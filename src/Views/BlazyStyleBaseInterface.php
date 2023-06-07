<?php

namespace Drupal\blazy\Views;

/**
 * Provides base views style plugin interface.
 */
interface BlazyStyleBaseInterface {

  /**
   * Returns the blazy manager.
   */
  public function blazyManager();

  /**
   * Returns the string values for the expected Title, ET label, List, Term.
   *
   * @todo re-check this, or if any consistent way to retrieve string values.
   */
  public function getFieldString($row, $field_name, $index, $clean = TRUE): array;

}
