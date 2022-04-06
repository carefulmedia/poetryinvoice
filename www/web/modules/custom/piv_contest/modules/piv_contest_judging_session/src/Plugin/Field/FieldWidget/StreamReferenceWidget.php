<?php

namespace Drupal\piv_contest_judging_session\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'limited_recitation_entry_widget' widget.
 *
 * @FieldWidget(
 *   id = "stream_reference",
 *   label = @Translation("Stream Reference"),
 *   description = @Translation("Stream reference"),
 *   field_types = {
 *     "entity_reference_revisions",
 *   },
 *   multiple_values = false
 * )
 */
class StreamReferenceWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $session = \Drupal::routeMatch()->getParameter('judging_session');
    $competition = \Drupal::routeMatch()->getParameter('competition');

    // Not present on creation yet.
    if (!$session && !$competition) {
      return [];
    }

    if (!$competition) {
      $competition = $session->field_competition->entity;
      if (!$competition) {
        return [];
      }
    }

    $options = [];

    foreach ($competition->field_competition_streams as $stream_field) {
      $stream = $stream_field->entity;
      $options[$stream->id()] = $stream->field_label->value;
    }

    $element += [
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => (int) $items->target_id,
      '#required' => TRUE,
    ];

    return ['target_id' => $element];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $key => $value) {
      if ($value['target_id']) {
        $entity = \Drupal::entityTypeManager()->getStorage('paragraph')->load($value['target_id']);
        // Add the current revision ID.
        $values[$key]['target_revision_id'] = $entity->getRevisionId();
      }
      // The entity_autocomplete form element returns an array when an entity
      // was "autocreated", so we need to move it up a level.
      if (is_array($value['target_id'])) {
        unset($values[$key]['target_id']);
        $values[$key] += $value['target_id'];
      }
    }
    return $values;
  }

}
