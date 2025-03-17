<?php

namespace Drupal\piv_base\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\StringFormatter;

/**
 * Plugin implementation of the 'strip_tags_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "strip_tags_formatter",
 *   label = @Translation("Strip Tags"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class StripTagsFormatter extends StringFormatter {

  /**
   * {@inheritdoc}
   */
  protected function viewValue(FieldItemInterface $item) {
    return [
      '#type' => 'inline_template',
      '#template' => '{{ value|nl2br }}',
      '#context' => ['value' => strip_tags($item->value)],
    ];
  }

}
