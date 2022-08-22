<?php

namespace Drupal\piv_base\Element;

use Drupal\Core\Render\Element\Select;

/**
 * Copy of the select type.
 *
 * Renders the select type with some customizations.
 *
 * @RenderElement("piv_select")
 */
class PivSelect extends Select {

  /**
   * Alters the select to add the library and a custom class.
   */
  public static function preRenderSelect($element) {
    $element = parent::preRenderSelect($element);
    $element['#attributes']['class'][] = 'piv-select';
    $element['#attached']['library'][] = 'piv_base/piv_select';
    return $element;
  }

}
