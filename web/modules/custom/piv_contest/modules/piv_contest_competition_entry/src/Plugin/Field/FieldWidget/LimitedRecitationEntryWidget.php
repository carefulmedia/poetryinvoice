<?php

namespace Drupal\piv_contest_competition_entry\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Tags;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\inline_entity_form\Plugin\Field\FieldWidget\InlineEntityFormComplex;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\piv_contest_recitation\Entity\Recitation;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Render\Element;

/**
 * Plugin implementation of the 'limited_recitation_entry_widget' widget.
 *
 * @FieldWidget(
 *   id = "limited_recitation_entry_widget",
 *   label = @Translation("Limited Recitation Entry "),
 *   description = @Translation("An Inline Entity Form that limits the number of recitations based on competition entry"),
 *   field_types = {
 *     "entity_reference",
 *     "entity_reference_revisions",
 *   },
 *   multiple_values = true
 * )
 */
class LimitedRecitationEntryWidget extends InlineEntityFormComplex {

  use StringTranslationTrait;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The translation manager service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationManager
   */
  protected $translationManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    EntityTypeManagerInterface $entity_type_manager,
    EntityDisplayRepositoryInterface $entity_display_repository,
    ModuleHandlerInterface $module_handler,
    SelectionPluginManagerInterface $selection_manager,
    RouteMatchInterface $routeMatch,
    TranslationManager $translationManager
  ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $third_party_settings,
      $entity_type_bundle_info,
      $entity_type_manager,
      $entity_display_repository,
      $module_handler,
      $selection_manager,
    );

    $this->routeMatch = $routeMatch;
    $this->translationManager = $translationManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository'),
      $container->get('module_handler'),
      $container->get('plugin.manager.entity_reference_selection'),
      $container->get('current_route_match'),
      $container->get('string_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $response = parent::formElement($items, $delta, $element, $form, $form_state);
    $entities = $form_state->get([
      'inline_entity_form',
      $this->getIefId(),
      'entities',
    ]);

    $max_number_recitations = $this->getMaxNumberOfRecitations();
    if (count($entities) >= $max_number_recitations) {
      unset($response['actions']['ief_add']);
    }

    $recitationsText = $this->translationManager->formatPlural(
      $max_number_recitations,
      '1 recitation',
      '@count recitations',
    );

    $response['#element_validate'][] = [$this, 'validateMaxNumber'];

    $title = $this->t('@recitation_text required.', ['@recitation_text' => $recitationsText]);
    $response['#field_title'] = $title;
    $response['#description'] = $this->t('You can add a maximum of @max', ['@max' => $recitationsText]);

    foreach (Element::children($response['entities']) as $delta) {
      if (isset($response['entities'][$delta]['form'])) {
        $response['entities'][$delta]['form']['#prefix'] = '<div class="recitation-widget-modal-wrapper"><div class="recitation-widget-modal">';
        $response['entities'][$delta]['form']['#suffix'] = '</div></div>';
      }
    }
    $response['#attached']['library'][] = 'piv_contest_competition_entry/limited-recitation-entry-widget';
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareFormState(FormStateInterface $form_state, FieldItemListInterface $items, $translating = FALSE) {
    parent::prepareFormState($form_state, $items, $translating);

    /** @var array $entities */
    $entities = $form_state->get([
      'inline_entity_form',
      $this->getIefId(),
      'entities',
    ]);

    $widget_state = $form_state->get(['inline_entity_form', $this->iefId]);

    $stream = $this->getStreamParagraph();

    if (!$stream) {
      return;
    }

    $max_number_recitations = $this->getNumberOfRecitationsPerLanguage();
    $languages = $stream->field_stream_languages->getValue();
    $total_per_language = [];
    foreach ($languages as $language) {
      $total_per_language[$language['target_id']] = 0;
    }

    $startIndex = 0;
    foreach ($entities as $item) {
      $entity = $item['entity'];
      $total_per_language[$entity->language()->getId()]++;
      $startIndex++;
    }

    foreach ($total_per_language as $language => $numberItems) {
      // Add missing items per language.
      for ($i = $numberItems; $i < $max_number_recitations; $i++) {
        $widget_state['entities'][] = [
          'entity' => Recitation::create([
            'bundle' => 'default',
            'langcode' => $language,
            'field_stream_language' => $language,
          ]),
          'weight' => $startIndex++,
          'form' => NULL,
          'needs_save' => TRUE,
        ];
      }
    }

    $form_state->set(['inline_entity_form', $this->iefId], $widget_state);
  }

  /**
   * Get the maximum number of recitations.
   */
  private function getMaxNumberOfRecitations(): int {
    $stream = $this->getStreamParagraph();
    if (!$stream) {
      return 1;
    }

    $number_languages = count($stream->field_stream_languages);
    $number_languages = $number_languages === 0 ? 1 : $number_languages;

    return $number_languages * (int) $stream->field_min_recitations->value;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state): void {
    if ($this->isDefaultValueWidget($form_state)) {
      $items->filterEmptyItems();
      return;
    }
    $triggering_element = $form_state->getTriggeringElement();
    if (empty($triggering_element['#ief_submit_trigger'])) {
      return;
    }

    $field_name = $this->fieldDefinition->getName();
    $parents = array_merge($form['#parents'], [$field_name, 'form']);
    $ief_id = $this->makeIefId($parents);
    $this->setIefId($ief_id);
    $widget_state = &$form_state->get(['inline_entity_form', $ief_id]);

    $values = $widget_state['entities'];
    // If the inline entity form is still open, then its entity hasn't
    // been transferred to the IEF form state yet.
    if (empty($values) && !empty($widget_state['form'])) {
      if ($widget_state['form'] == 'add') {
        $element = NestedArray::getValue($form, [$field_name, 'widget', 'form']);
        $entity = $element['inline_entity_form']['#entity'];
        $values[] = ['entity' => $entity];
      }
      elseif ($widget_state['form'] == 'ief_add_existing') {
        $parent = NestedArray::getValue($form, [$field_name, 'widget', 'form']);
        $element = $parent['entity_id'] ?? [];
        if (!empty($element['#value'])) {
          $options = [
            'target_type' => $element['#target_type'],
            'handler' => $element['#selection_handler'],
          ] + $element['#selection_settings'];
          /** @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface $handler */
          $handler = $this->selectionManager->getInstance($options);
          $input_values = $element['#tags'] ? Tags::explode($element['#value']) : [$element['#value']];

          foreach ($input_values as $input) {
            $match = EntityAutocomplete::extractEntityIdFromAutocompleteInput($input);
            if ($match === NULL) {
              // Try to get a match from the input string when the user didn't
              // use the autocomplete but filled in a value manually.
              $entities_by_bundle = $handler->getReferenceableEntities($input, '=');
              $entities = array_reduce($entities_by_bundle, function ($flattened, $bundle_entities) {
                return $flattened + $bundle_entities;
              }, []);
              $params = [
                '%value' => $input,
                '@value' => $input,
              ];
              if (empty($entities)) {
                $form_state->setError($element, $this->t('There are no entities matching "%value".', $params));
              }
              elseif (count($entities) > 5) {
                $params['@id'] = key($entities);
                // Error if there are more than 5 matching entities.
                $form_state->setError($element, $this->t('Many entities are called %value. Specify the one you want by appending the id in parentheses, like "@value (@id)".', $params));
              }
              elseif (count($entities) > 1) {
                // More helpful error if there are only a few matching entities.
                $multiples = [];
                foreach ($entities as $id => $name) {
                  $multiples[] = $name . ' (' . $id . ')';
                }
                $params['@id'] = $id;
                $form_state->setError($element, $this->t('Multiple entities match this reference; "%multiple". Specify the one you want by appending the id in parentheses, like "@value (@id)".', ['%multiple' => implode('", "', $multiples)] + $params));
              }
              else {
                // Take the one and only matching entity.
                $values += [
                  'target_id' => key($entities),
                ];
              }
            }
            else {
              $values += [
                'target_id' => $match,
              ];
            }
          }
        }
      }
    }
    // Sort values by weight.
    uasort($values, '\Drupal\Component\Utility\SortArray::sortByWeightElement');
    // Let the widget massage the submitted values.
    $values = $this->massageFormValues($values, $form, $form_state);
    // Assign the values and remove the empty ones.
    $items->setValue($values);
    $items->filterEmptyItems();
  }

  /**
   * Get the stream paragraph.
   */
  private function getStreamParagraph(): ?Paragraph {
    $competition = $this->routeMatch->getParameter('competition');
    $competition_entry = $this->routeMatch->getParameter('competition_entry');
    $stream = $this->routeMatch->getParameter('stream');

    if (!$competition_entry && !$stream) {
      return NULL;
    }

    // This is on add.
    if ($stream) {
      $stream_id = $stream->id();
    }
    else {
      // This is on edit.
      $stream_id = $competition_entry->field_stream->target_id;
    }

    if (!$competition) {
      $competition = $competition_entry->field_competition->entity;
    }

    if (!$competition->field_competition_streams) {
      return NULL;
    }

    $stream_paragraphs = $competition->field_competition_streams->referencedEntities();
    foreach ($stream_paragraphs as $item) {
      if ($item->id() !== $stream_id) {
        continue;
      }

      return $item;
    }

    return NULL;
  }

  /**
   * Get recitations per language.
   */
  private function getNumberOfRecitationsPerLanguage(): int {
    $stream = $this->getStreamParagraph();
    if (!$stream) {
      return 1;
    }

    return $stream->field_min_recitations->value;
  }

  /**
   * Validade the competition entry recitations.
   */
  public function validateMaxNumber(array $elements, FormStateInterface $form_state, array $form): void {
    // Check poems are unique per entry.
    $poem_ids = [];
    foreach (Element::children($elements['entities']) as $delta) {
      $recitation = $elements['entities'][$delta]['#entity'] ?? NULL;
      $poem_id = $recitation ? $recitation->field_poem->target_id ?? NULL : NULL;
      if ($poem_id && in_array($poem_id, $poem_ids)) {
        $form_state->setError($elements, $this->t('Each recitation should be for a different poem.'));
      }
      $poem_ids[] = $poem_id;
    }

    // Check maximum number of recitations.
    $entities = $form_state->getValue(['field_recitations', 'entities']);
    $max_number_recitations = $this->getMaxNumberOfRecitations();
    if (count($entities) > $max_number_recitations) {
      $form_state->setError($elements, $this->t('the maximum number of recitations is @max_number', ['@max_number' => $max_number_recitations]));
    }
  }

}
