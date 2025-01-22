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
    // Only consider keys that exists in the source, thats because the
    // original values will have empty values not populated by the
    // script. No fields accept multiple entries.
    foreach ($original_value[0] as $key => $value) {
      if (empty($source_value[0][$key])) {
        continue;
      }
      if (strtolower($source_value[0][$key]) != strtolower($value ?? "")) {
        return FALSE;
      }
    }
    return TRUE;
  }

}

