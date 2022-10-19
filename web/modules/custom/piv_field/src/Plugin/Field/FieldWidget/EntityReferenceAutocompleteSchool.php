<?php

namespace Drupal\piv_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;


/**
 * @FieldWidget(
 *   id = "entity_reference_autocomplete_school",
 *   label = @Translation("Autocomplete School"),
 *   description = @Translation("An autocomplete text field with an option to filter by Postal Code."),
 *   field_types = {
 *     "entity_reference_school"
 *   }
 * )
 */
class EntityReferenceAutocompleteSchool extends EntityReferenceAutocompleteWidget {

  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $widget = parent::formElement($items, $delta, $element, $form, $form_state);

    $widget['postal_code'] = [
      '#title' => $this->t('Filter by Postal Code?'),
      '#type' => 'checkbox',
      '#description' => $this->t('Default search will use the School title, check this option to search by the Postal Code instead.'),
      '#default_value' => isset($items[$delta]) ? $items[$delta]->postal_code : 1,
    ];

    return $widget;
  }
}
