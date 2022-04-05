<?php

namespace Drupal\piv_contest_judging_session\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\EntityOwnerInterface;


/**
 * Class JudgesAutocomplete
 *
 * @FieldWidget(
 *   id = "competition_judges_autocomplete",
 *   label = @Translation("Judges Autocomplete"),
 *   description = @Translation("Autocomplete for competition judges."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class JudgesAutocomplete extends EntityReferenceAutocompleteWidget {
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $entity = $items->getEntity();
    $referenced_entities = $items->referencedEntities();

    $competition = \Drupal::routeMatch()->getParameter('competition');
    if (!$competition) {
      $build_info = $form_state->getBuildInfo();
      $session = $build_info['callback_object']->getEntity();
      $competition = $session->field_competition->entity;
    }

    // Append the match operation to the selection settings.
    $selection_settings = $this->getFieldSetting('handler_settings') + [
      'match_operator' => $this->getSetting('match_operator'),
      'match_limit' => $this->getSetting('match_limit'),
    ];
    if ($competition) {
      $selection_settings['competition_id'] = $competition->id();
    }

    $element += [
      '#type' => 'entity_autocomplete',
      '#target_type' => $this->getFieldSetting('target_type'),
      '#selection_handler' => 'judges_selection:user',
      '#selection_settings' => $selection_settings,
      // Entity reference field items are handling validation themselves via
      // the 'ValidReference' constraint.
      '#validate_reference' => FALSE,
      '#maxlength' => 1024,
      '#default_value' => $referenced_entities[$delta] ?? NULL,
      '#size' => $this->getSetting('size'),
      '#placeholder' => $this->getSetting('placeholder'),
    ];

    return ['target_id' => $element];
  }

}
