<?php

declare(strict_types=1);

namespace Drupal\piv_futureverse\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Serialization\Json;

/**
 * Form for filtering journal poems in admin interface.
 */
final class JournalPoemsForm extends FormBase {

  /**
   * Constructs a new JournalPoemsFilterForm object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $database,
    protected EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('entity_field.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'journal_poems_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['filters'] = [
      '#type' => 'fieldset',
    ];

    $form['filters']['row1'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['filters-row-1']],
    ];

    $form['filters']['row2'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['filters-row-2']],
    ];

    // Build year to month mapping for JavaScript.
    $year_month_mapping = $this->buildYearMonthMapping();

    // Journal year filter.
    $journal_years = $this->entityTypeManager
      ->getStorage('journal_year')
      ->loadMultiple();
    $year_options = ['' => $this->t('- Any -')];
    foreach ($journal_years as $year) {
      $year_options[$year->id()] = $year->label();
    }
    $form['filters']['row1']['journal_year'] = [
      '#type' => 'select',
      '#title' => $this->t('Journal Year'),
      '#options' => $year_options,
      '#default_value' => $form_state->getValue('journal_year', ''),
      '#attributes' => [
        'id' => 'journal-year-select',
        'data-year-month-mapping' => Json::encode($year_month_mapping),
      ],
    ];

    // Journal month filter.
    $form['filters']['row1']['journal_month'] = [
      '#type' => 'select',
      '#multiple' => TRUE,
      '#title' => $this->t('Journal Month'),
      '#options' => ['' => $this->t('- Any -')] + $this->getJournalMonthOptions(),
      '#default_value' => $form_state->getValue('journal_month', ''),
      '#attributes' => [
        'id' => 'journal-month-select',
      ],
    ];

    // Acceptance level filter.
    $options = $this->getAcceptanceLevelOptions();
    $form['filters']['row1']['acceptance_level'] = [
      '#type' => 'select',
      '#title' => $this->t('Acceptance Level'),
      '#options' => $options,
      '#default_value' => $form_state->getValue('acceptance_level', []),
      '#multiple' => TRUE,
      '#size' => 4,
      '#access' => count($options) > 0,
    ];

    $form['filters']['row2']['futureverse_shortlisted'] = [
      '#type' => 'select',
      '#title' => $this->t('Shortlisted for Futureverse'),
      '#options' => [
        '' => $this->t('- Any -'),
        '1' => $this->t('Yes'),
        '0' => $this->t('No'),
      ],
      '#default_value' => $form_state->getValue('futureverse_shortlisted', ''),
    ];

    $form['filters']['row2']['poet_bio_enriched'] = [
      '#type' => 'select',
      '#title' => $this->t('Poet Bio Enriched'),
      '#options' => [
        '' => $this->t('- Any -'),
        '1' => $this->t('Yes'),
        '0' => $this->t('No'),
      ],
      '#default_value' => $form_state->getValue('poet_bio_enriched', ''),
    ];

    // Has Futureverse application filter.
    $form['filters']['row2']['has_futureverse_application'] = [
      '#type' => 'select',
      '#title' => $this->t('Has Futureverse Application'),
      '#options' => [
        '' => $this->t('- Any -'),
        '1' => $this->t('Yes'),
        '0' => $this->t('No'),
      ],
      '#default_value' => $form_state->getValue('has_futureverse_application', ''),
    ];

    $form['filters']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
    ];

    $form['filters']['reset'] = [
      '#type' => 'link',
      '#title' => $this->t('Reset'),
      '#url' => Url::fromRoute('piv_futureverse.journal_poems'),
      '#attributes' => ['class' => ['button']],
    ];

    // Get filtered poems and build table.
    if ($form_state->isProcessingInput()) {
      $poems = $this->getFilteredPoems($form_state);
      $form['table'] = $this->buildTable($poems);
    }

    // Add pagination.
    $form['pager'] = [
      '#type' => 'pager',
    ];

    // Add JavaScript for filtering.
    $form['#attached']['library'][] = 'piv_futureverse/journal_filter';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setRebuild(TRUE);
  }

  /**
   * Gets filtered poems based on form state.
   */
  protected function getFilteredPoems(FormStateInterface $form_state): array {
    // Get filter values.
    $journal_month = array_filter($form_state->getValue('journal_month', []));
    $journal_year = $form_state->getValue('journal_year');
    $acceptance_level = array_filter($form_state->getValue('acceptance_level', []));
    $poet_bio_enriched = $form_state->getValue('poet_bio_enriched');
    $has_futureverse_application = $form_state->getValue('has_futureverse_application');
    $futureverse_shortlisted = $form_state->getValue('futureverse_shortlisted');

    // Build main query.
    $query = $this->database->select('node_field_data', 'n')
      ->fields('n', ['nid', 'title', 'uid', 'created'])
      ->condition('n.type', 'journal_poem')
      ->orderBy('n.created', 'DESC');

    $query->leftJoin('node__piv_teacher_first_name', 'nfn', 'nfn.entity_id = n.nid');
    $query->leftJoin('node__piv_teacher_last_name', 'nln', 'nln.entity_id = n.nid');
    $query->addField('nfn', 'piv_teacher_first_name_value', 'piv_teacher_first_name');
    $query->addField('nln', 'piv_teacher_last_name_value', 'piv_teacher_last_name');

    // Acceptance level.
    $query->leftJoin('node__field_acceptance_level', 'al', 'n.nid = al.entity_id AND al.deleted = 0');
    $query->addField('al', 'field_acceptance_level_value', 'field_acceptance_level');
    if (count($acceptance_level)) {
      $query->condition('al.field_acceptance_level_value', array_keys($acceptance_level), 'IN');
    }

    // Shortlisted for futureverse.
    $query->leftJoin('node__field_shortlisted_for_futurevers', 'sf', 'n.nid = sf.entity_id AND sf.deleted = 0');
    $query->addField('sf', 'field_shortlisted_for_futurevers_value', 'field_shortlisted_for_futurevers');
    if ($futureverse_shortlisted == '1') {
      $query->condition('sf.field_shortlisted_for_futurevers_value', 1);
    }
    elseif ($futureverse_shortlisted == '0') {
      // Many journal poems doesn't have a value on this field, so we
      // need to check for null too.
      $or = $query->orConditionGroup()
        ->condition('sf.field_shortlisted_for_futurevers_value', 0)
        ->isNull('sf.field_shortlisted_for_futurevers_value');
      $query->condition($or);
    }

    // Left join on month and year so we can reuse to filter by on other
    // items.
    $query->leftJoin('journal_month__field_journal_poems', 'jmr', 'n.nid = jmr.field_journal_poems_target_id');
    $query->leftJoin('journal_month_field_data', 'jm', 'jm.id = jmr.entity_id');
    $query->leftJoin('journal_year__field_journal_months', 'jyr', 'jm.id = jyr.field_journal_months_target_id');
    $query->leftJoin('journal_year_field_data', 'jy', 'jy.id = jyr.entity_id');
    $query->addField('jy', 'label', 'journal_year_label');
    $query->addField('jy', 'id', 'journal_year_id');
    $query->addField('jm', 'label', 'journal_month_label');

    // Always left join poet bio and futureverse application data for display.
    if ($poet_bio_enriched !== '1') {
      $query->leftJoin('poet_bio', 'pb_display', 'n.uid = pb_display.uid');
      $query->leftJoin('poet_bio__field_journal_year', 'pbjy_display', 'pb_display.id = pbjy_display.entity_id AND pbjy_display.field_journal_year_target_id = jy.id');
    }
    else {
      // Reuse existing aliases for display when filtering.
      $query->addField('pb', 'id', 'poet_bio_id');
    }

    if ($has_futureverse_application !== '1') {
      $query->leftJoin('futureverse_application', 'fa_display', 'n.uid = fa_display.uid');
      $query->leftJoin('futureverse_application__field_journal_year', 'fajy_display', 'fa_display.id = fajy_display.entity_id AND fajy_display.field_journal_year_target_id = jy.id');
    }
    else {
      // Reuse existing aliases for display when filtering.
      $query->addField('fa', 'id', 'futureverse_application_id');
    }

    // Add display fields for poet bio and futureverse application.
    if ($poet_bio_enriched !== '1') {
      $query->addField('pb_display', 'id', 'poet_bio_id');
    }

    if ($has_futureverse_application !== '1') {
      $query->addField('fa_display', 'id', 'futureverse_application_id');
    }

    // Filter by journal month/year.
    if (count($journal_month)) {
      $query->condition('jm.id', array_values($journal_month), 'IN');
    }
    if ($journal_year) {
      $query->condition('jy.id', $journal_year);
    }

    // Filter by futureverse application.
    if ($has_futureverse_application == '1') {
      $query->innerJoin('futureverse_application', 'fa', 'n.uid = fa.uid');
      $query->innerJoin('futureverse_application__field_journal_year', 'fajy', 'fa.id = fajy.entity_id AND fajy.field_journal_year_target_id = jy.id');
    }
    elseif ($has_futureverse_application == '0') {
      $query->leftJoin('futureverse_application', 'fa', 'n.uid = fa.uid');
      $query->leftJoin('futureverse_application__field_journal_year', 'fajy', 'fa.id = fajy.entity_id AND fajy.field_journal_year_target_id = jy.id');
      $query->isNull('fajy.field_journal_year_target_id');
    }

    // Filter by poet bio enriched.
    if ($poet_bio_enriched == '1') {
      $query->innerJoin('poet_bio', 'pb', 'n.uid = pb.uid');
      $query->innerJoin('poet_bio__field_journal_year', 'pbjy', 'pb.id = pbjy.entity_id AND pbjy.field_journal_year_target_id = jy.id');
    }
    elseif ($poet_bio_enriched == '0') {
      $query->leftJoin('poet_bio', 'pb', 'n.uid = pb.uid');
      $query->leftJoin('poet_bio__field_journal_year', 'pbjy', 'pb.id = pbjy.entity_id AND pbjy.field_journal_year_target_id = jy.id');
      $query->isNull('pbjy.field_journal_year_target_id');
    }

    // Add pagination.
    $query = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender');
    $query->limit(50);

    $results = $query->execute()->fetchAll();
    return $results;
  }

  /**
   * Gets journal month options.
   */
  protected function getJournalMonthOptions(): array {
    $months = $this->entityTypeManager->getStorage('journal_month')->loadMultiple();
    $options = [];
    foreach ($months as $month) {
      $options[$month->id()] = $month->label();
    }
    return $options;
  }

  /**
   * Builds year to month mapping array.
   *
   * Based on the field_journal_months field on journal_year entities.
   */
  protected function buildYearMonthMapping(): array {
    $mapping = [];
    $journal_years = $this->entityTypeManager->getStorage('journal_year')->loadMultiple();

    foreach ($journal_years as $year) {
      $mapping[$year->id()] = [];
      if ($year->hasField('field_journal_months') && !$year->get('field_journal_months')->isEmpty()) {
        foreach ($year->get('field_journal_months')->referencedEntities() as $month) {
          $mapping[$year->id()][] = (string) $month->id();
        }
      }
    }

    return $mapping;
  }

  /**
   * Gets acceptance level options from field configuration.
   */
  private function getAcceptanceLevelOptions(): array {
    $field_definitions = $this->entityFieldManager->getFieldDefinitions('node', 'journal_poem');
    if (isset($field_definitions['field_acceptance_level'])) {
      $field_storage_definition = $field_definitions['field_acceptance_level']->getFieldStorageDefinition();
      $settings = $field_storage_definition->getSettings();
      if (isset($settings['allowed_values'])) {
        return $settings['allowed_values'];
      }
    }
    return [];
  }

  /**
   * Builds the poems table.
   */
  protected function buildTable(array $results): array {
    $header = [
      'title' => $this->t('Poem Title'),
      'author' => $this->t('Author'),
      'journal_year' => $this->t('Journal Year'),
      'journal_month' => $this->t('Journal Month'),
      'acceptance_level' => $this->t('Acceptance Level'),
      'shortlisted' => $this->t('Shortlisted for futureverse'),
      'poet_bio' => $this->t('Poet Bio'),
      'futureverse_app' => $this->t('Futureverse Application'),
    ];

    $rows = [];
    foreach ($results as $result) {
      $name = trim("{$result->piv_teacher_first_name} {$result->piv_teacher_last_name}");
      $rows[] = [
        'title' => Link::createFromRoute($result->title, 'entity.node.canonical', [
          'node' => $result->nid,
        ]),
        'author' => empty($result->uid)
          ? $name
          : Link::createFromRoute($name, 'entity.user.canonical', [
            'user' => $result->uid,
          ]),
        'journal_year' => $result->journal_year_id
          ? Link::createFromRoute($result->journal_year_label, 'entity.journal_year.canonical', [
            'journal_year' => $result->journal_year_id,
          ])
          : '',
        'journal_month' => $result->journal_month_label,
        'acceptance_level' => $result->field_acceptance_level,
        'shortlisted' => !empty($result->field_shortlisted_for_futurevers) ? $this->t('Yes') : $this->t('No'),
        'poet_bio' => $result->poet_bio_id
          ? Link::createFromRoute('Poet Bio', 'entity.poet_bio.canonical', [
            'poet_bio' => $result->poet_bio_id,
          ])
          : '',
        'futureverse_app' => $result->futureverse_application_id
          ? Link::createFromRoute('Futureverse Application', 'entity.futureverse_application.canonical', [
            'futureverse_application' => $result->futureverse_application_id,
          ])
          : '',
      ];
    }

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No journal poems found.'),
      '#attributes' => ['class' => ['admin-list']],
    ];
  }

}
