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

    // Ajax wrapper.
    $widget['#prefix'] = "<div id='school-reference-wrapper'>";
    $widget['#suffix'] = "</div>";

    // Postal code checkbox.
    $widget['postal_code'] = [
      '#title' => $this->t('Filter by Postal Code?'),
      '#type' => 'checkbox',
      '#description' => $this->t('Default search will use the School title, check this option to search by the Postal Code instead.'),
      '#default_value' => 0,
      '#ajax' => [
        'callback' => [$this, 'postalCodeCallback'],
        'event' => 'change',
        'wrapper' => 'school-reference-wrapper',
      ]
    ];

    // Foce the custom Postal code Handler...
    $widget['target_id']['#selection_handler'] = 'default:piv_school';

    return $widget;
  }

  /**
   * Set the value of Postal Code.
   */
  public function postalCodeCallback(array &$form, FormStateInterface $form_state) {
    $postal_code = $form_state->getValue('field_school_ref')[0]['postal_code'] ?? 0;
    $form['field_school_ref']['widget'][0]['target_id']['#value'] = '';
    if ($postal_code == 1) {
      $form['field_school_ref']['widget'][0]['target_id']['#value'] = 'pcode: ';
    }
    $form_state->setRebuild();

    return $form['field_school_ref'];
  }
}
