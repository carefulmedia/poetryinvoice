<?php

namespace Drupal\piv_base\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\inline_entity_form\Plugin\Field\FieldWidget\InlineEntityFormComplex;

/**
 * Defines the 'piv_base_inline_entity_form_open' field widget.
 *
 * @FieldWidget(
 *   id = "piv_base_inline_entity_form_open",
 *   label = @Translation("Inline entity form - Open"),
 *   field_types = {
 *     "entity_reference",
 *     "entity_reference_revisions",
 *   },
 *   multiple_values = true
 * )
 */
class InlineEntityFormOpenWidget extends InlineEntityFormComplex {

  /**
   * {@inheritdoc}
   */
  public static function buildEntityFormActions(array $element) {
    // Override to not add a submit button.
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareFormState(FormStateInterface $form_state, FieldItemListInterface $items, $translating = FALSE) {
    $widget_state = $form_state->get(['inline_entity_form', $this->iefId]);
    if (empty($widget_state)) {
      $widget_state = [
        'instance' => $this->fieldDefinition,
        'form' => NULL,
        'delete' => [],
        'entities' => [],
      ];
      // Store the $items entities in the widget state, for further
      // manipulation.
      foreach ($items->referencedEntities() as $delta => $entity) {
        // Display the entity in the correct translation.
        if ($translating) {
          $entity = TranslationHelper::prepareEntity($entity, $form_state);
        }
        $widget_state['entities'][$delta] = [
          'entity' => $widget_state['entities'][$delta]['entity'] ?? $entity,
          'weight' => $delta,
          'form' => NULL,
          'needs_save' => $entity->isNew(),
        ];
      }
    }
    // Make sure form is always edit.
    foreach ($widget_state['entities'] as &$data) {
      $data['form'] = 'edit';
    }
    $form_state->set(['inline_entity_form', $this->iefId], $widget_state);
  }

}
