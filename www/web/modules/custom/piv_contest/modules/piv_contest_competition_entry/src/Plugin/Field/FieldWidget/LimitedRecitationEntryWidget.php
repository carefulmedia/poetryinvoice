<?php

namespace Drupal\piv_contest_competition_entry\Plugin\Field\FieldWidget;

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
use Symfony\Component\DependencyInjection\ContainerInterface;

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

  protected $routeMatch;

  protected $translationManager;


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
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   * @param int $delta
   * @param array $element
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $response = parent::formElement($items, $delta, $element, $form, $form_state);

    $entities = $form_state->get(['inline_entity_form', $this->getIefId(), 'entities']);

    $max_number_recitations = $this->getMaxNumberOfRecitations();
    if (count($entities) >= $max_number_recitations) {
      unset($response['actions']['ief_add']);
    }

    $recitationsText = $this->translationManager->formatPlural(
      $max_number_recitations,
      '1 Recitation',
      '@count Recitations',
    );

    $response['#element_validate'][] = [$this, 'validateMaxNumber'];

    $response['#field_title'] = $recitationsText;
    $response['#description'] = t('You can add a maximum of @max', ['@max' => $recitationsText]);

    return $response;
  }

  private function getMaxNumberOfRecitations(): int {
    $competition = $this->routeMatch->getParameter('competition');
    $competition_entry = $this->routeMatch->getParameter('competition_entry');
    $stream = $this->routeMatch->getParameter('stream');

    // This is on add.
    if ($stream) {
      $stream_id = $stream->id();
    } else {
      // This is on edit.
      $stream_id = $competition_entry->field_stream->target_id;
    }

    $stream_paragraphs = $competition->field_competition_streams->referencedEntities();
    foreach ($stream_paragraphs as $item) {
      if ($item->id() !== $stream_id) {
        continue;
      }

      $number_languages = count($item->field_stream_languages);
      $number_languages = $number_languages === 0 ? 1 : $number_languages;

      return $number_languages * (int) $item->field_min_recitations->value;
    }

    return 1;
  }

  public function validateMaxNumber(array $elements, FormStateInterface $form_state, array $form) {
    $entities = $form_state->getValue(['field_recitations', 'entities']);
    $max_number_recitations = $this->getMaxNumberOfRecitations();
    if (count($entities) > $max_number_recitations) {
      $form_state->setError($elements['entities'], t('the maximum number of recitations is @max_number', ['@max_number' => $max_number_recitations]));
    }
  }
}
