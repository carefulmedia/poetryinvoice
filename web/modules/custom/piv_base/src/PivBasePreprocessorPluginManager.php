<?php

namespace Drupal\piv_base;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\geocoder_field\PreprocessorPluginManager;

/**
 * Overrides PreprocessorPluginManager.
 */
class PivBasePreprocessorPluginManager extends PreprocessorPluginManager {

  /**
   * Check if the source and the original fields are the same.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $source_field
   *   The Source Field.
   * @param \Drupal\Core\Field\FieldItemListInterface $original_field
   *   The Original Field.
   *
   * @return bool
   *   The check result.
   */
  public function sourceFieldIsSameOfOriginal(FieldItemListInterface $source_field, FieldItemListInterface $original_field) {
    $source_value = $source_field->getValue();
    $original_value = $original_field->getValue();

    if (isset($source_value[0]) && !isset($source_value[0]['value']) && isset($source_value[0]['target_id'])) {
      foreach ($source_value as $i => $value) {
        $source_value[$i] = $value['target_id'] ?? '';
      }
    }
    if (isset($original_value[0]) && !isset($original_value[0]['value']) && isset($original_value[0]['target_id'])) {
      foreach ($original_value as $i => $value) {
        $original_value[$i] = $value['target_id'] ?? '';
      }
    }

    $source_postal = $source_value[0]['postal_code'] ?? NULL;
    $original_postal = $original_value[0]['postal_code'] ?? NULL;
    return $source_postal == $original_postal;
  }

}
