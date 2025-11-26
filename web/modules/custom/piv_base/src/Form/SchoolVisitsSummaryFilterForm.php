<?php

namespace Drupal\piv_base\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter form for School Visits Summary.
 */
class SchoolVisitsSummaryFilterForm extends FormBase {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a SchoolVisitsSummaryFilterForm object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager, Connection $database) {
    $this->entityFieldManager = $entity_field_manager;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'school_visits_summary_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Calculate default date range
    $default_dates = $this->getDefaultDateRange();
    
    // Check if this is a fresh load (not a rebuild from submit/reset)
    $is_fresh_load = !$form_state->isRebuilding();
    
    // Get stored values from form state (for rebuilds after validation or submit)
    // Only use defaults on fresh load, otherwise use form_state values
    if ($is_fresh_load) {
      // First time loading the form - use defaults
      $current_language = \Drupal::languageManager()->getCurrentLanguage()->getId();
      $language_filter = $current_language;
      $date_from = $default_dates['date_from'];
      $date_to = $default_dates['date_to'];
      $grades = [];
    }
    else {
      // Form is rebuilding (after submit or reset) - use form_state values
      $language_filter = $form_state->getValue('language');
      $date_from = $form_state->getValue('date_from');
      $date_to = $form_state->getValue('date_to');
      $grades = $form_state->getValue('grades', []);
    }

    $form['#attributes'] = ['class' => ['school-visits-filter-form']];

    $form['filters'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Filters'),
      '#collapsible' => FALSE,
    ];

    // Language filter
    $form['filters']['language'] = [
      '#type' => 'radios',
      '#title' => $this->t('Language'),
      '#options' => [
        'all' => $this->t('All'),
        'en' => $this->t('English'),
        'fr' => $this->t('French'),
      ],
      '#default_value' => $language_filter,
      '#required' => TRUE,
    ];

    // Date range filters.
    $form['filters']['date_range'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['date-range-container']],
    ];

    $form['filters']['date_range']['date_from'] = [
      '#type' => 'date',
      '#title' => $this->t('From Date'),
      '#default_value' => $date_from,
      '#description' => $this->t('Filter visits created from this date.'),
    ];

    $form['filters']['date_range']['date_to'] = [
      '#type' => 'date',
      '#title' => $this->t('To Date'),
      '#default_value' => $date_to,
      '#description' => $this->t('Filter visits created up to this date.'),
    ];

    // Grades filter.
    $form['filters']['grades'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Grade(s)'),
      '#options' => $this->getGradeOptions(),
      '#default_value' => is_array($grades) ? $grades : [],
      '#description' => $this->t('Select one or more grades to filter by.'),
    ];

    // Actions.
    $form['filters']['actions'] = [
      '#type' => 'actions',
    ];

    $form['filters']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply Filters'),
      '#button_type' => 'primary',
    ];

    $form['filters']['actions']['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset'),
      '#submit' => ['::resetForm'],
      '#limit_validation_errors' => [],
    ];

    // Display results if form has been submitted and has no validation errors
    if ($form_state->isSubmitted() && !$form_state->hasAnyErrors()) {
      $form['results'] = $this->buildResults($form_state);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $date_from = $form_state->getValue('date_from');
    $date_to = $form_state->getValue('date_to');
    $language = $form_state->getValue('language');
    $grades = $form_state->getValue('grades');

    // Validate language field.
    if (empty($language)) {
      $form_state->setErrorByName('language', $this->t('Please select a language filter.'));
    }
    elseif (!in_array($language, ['all', 'en', 'fr'])) {
      $form_state->setErrorByName('language', $this->t('Invalid language selection.'));
    }

    // Validate date fields.
    if (!empty($date_from) && strtotime($date_from) === FALSE) {
      $form_state->setErrorByName('date_from', $this->t('Invalid "From Date" format.'));
    }

    if (!empty($date_to) && strtotime($date_to) === FALSE) {
      $form_state->setErrorByName('date_to', $this->t('Invalid "To Date" format.'));
    }

    // Validate that 'from' date is not after 'to' date.
    if (!empty($date_from) && !empty($date_to)) {
      $from_timestamp = strtotime($date_from);
      $to_timestamp = strtotime($date_to);

      if ($from_timestamp !== FALSE && $to_timestamp !== FALSE && $from_timestamp > $to_timestamp) {
        $form_state->setErrorByName('date_from', $this->t('The "From Date" must be before or equal to the "To Date".'));
      }
    }

    // Validate grades (if provided).
    if (!empty($grades) && is_array($grades)) {
      $valid_grades = array_keys($this->getGradeOptions());
      foreach (array_filter($grades) as $grade) {
        if (!in_array($grade, $valid_grades)) {
          $form_state->setErrorByName('grades', $this->t('Invalid grade selection: @grade', ['@grade' => $grade]));
          break;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Just rebuild the form to show results
    // The results will be displayed in buildForm() after validation
    $form_state->setRebuild(TRUE);
  }

  /**
   * Form submission handler for the reset button.
   */
  public function resetForm(array &$form, FormStateInterface $form_state) {
    // Calculate defaults for a fresh reset
    $default_dates = $this->getDefaultDateRange();
    $current_language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    
    // Clear all form values
    $form_state->setUserInput([]);
    
    // Set fresh default values
    $form_state->setValues([
      'language' => $current_language,
      'date_from' => $default_dates['date_from'],
      'date_to' => $default_dates['date_to'],
      'grades' => [],
    ]);
    
    // Rebuild the form
    $form_state->setRebuild(TRUE);
  }

  /**
   * Gets the available grade options.
   *
   * This method retrieves the allowed values from the field_grades field
   * on the pal_pir_school_visit content type.
   *
   * @return array
   *   An array of grade options.
   */
  protected function getGradeOptions() {
    try {
      $field_definitions = $this->entityFieldManager
        ->getFieldDefinitions('node', 'pal_pir_school_visit');

      if (isset($field_definitions['field_grades'])) {
        $field_storage = $field_definitions['field_grades']->getFieldStorageDefinition();
        $settings = $field_storage->getSettings();

        // Check if it's a list field with allowed values.
        if (isset($settings['allowed_values']) && !empty($settings['allowed_values'])) {
          return $settings['allowed_values'];
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('piv_base')->error('Error loading grade options: @message', ['@message' => $e->getMessage()]);
    }

    // Fallback: Default grade options if field config is not available.
    return [
      'Kindergarten' => $this->t('K - 2'),
      'Grades 1-3' => $this->t('3 & 4'),
      'Grades 4-6' => $this->t('5 & 6'),
      'Grades 7 & 8 / Sec 1 & 2' => $this->t('7-9 / Sec. 1-3'),
      'Grades 9-12 / Sec 3-5 / CEGEP 1' => $this->t('10-12 / Sec. 4 & 5 / CEGEP 1'),
    ];
  }

  /**
   * Calculates the default date range for the school year.
   *
   * Returns the most recent August 1st in the past and the next June 30th.
   *
   * @return array
   *   Array with 'date_from' and 'date_to' in Y-m-d format.
   */
  protected function getDefaultDateRange() {
    $now = new \DateTime();
    $current_year = (int) $now->format('Y');
    $current_month = (int) $now->format('n');
    
    // If we're in January-July, the school year started last August
    // If we're in August-December, the school year started this August
    if ($current_month < 8) {
      $start_year = $current_year - 1;
      $end_year = $current_year;
    }
    else {
      $start_year = $current_year;
      $end_year = $current_year + 1;
    }
    
    return [
      'date_from' => $start_year . '-08-01',
      'date_to' => $end_year . '-06-30',
    ];
  }

  /**
   * Builds the results table based on form filters.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   A render array with the results.
   */
  protected function buildResults(FormStateInterface $form_state) {
    $filters = [
      'language' => $form_state->getValue('language'),
      'date_from' => $form_state->getValue('date_from'),
      'date_to' => $form_state->getValue('date_to'),
      'grades' => array_filter($form_state->getValue('grades', [])),
    ];

    $summary_data = $this->getSummaryData($filters);

    return [
      '#theme' => 'piv_school_visits_summary',
      '#data' => $summary_data,
      '#filters' => $filters,
    ];
  }

  /**
   * Gets aggregated summary data for school visits.
   *
   * @param array $filters
   *   Array of filter parameters.
   *
   * @return array
   *   Array of summary data grouped by language and province.
   */
  protected function getSummaryData(array $filters) {
    // Build the query using Database API for better performance.
    $query = $this->database->select('node_field_data', 'n');
    $query->addField('n', 'langcode', 'language');
    
    // Join to get the visit type field (in_person vs virtual).
    // Field: field_in_person_or_teleconferenc
    // Values: 1 = In person ($500), 2 = Virtual ($250)
    $query->leftJoin('node__field_in_person_or_teleconferenc', 'vt', 'n.nid = vt.entity_id AND vt.deleted = 0');
    
    // Count requests: in_person (1) = 2, virtual (2) or null = 1
    $query->addExpression(
      "SUM(CASE WHEN vt.field_in_person_or_teleconferenc_value = 1 THEN 2 ELSE 1 END)",
      'total_requests'
    );
    
    // Join to get the booked status field.
    $query->leftJoin('node__field_booked_', 'fb', 'n.nid = fb.entity_id AND fb.deleted = 0');
    
    // Count booked: only count if booked=1, and in_person (1) = 2, virtual (2) = 1
    $query->addExpression(
      "SUM(CASE WHEN fb.field_booked__value = 1 THEN " .
      "  CASE WHEN vt.field_in_person_or_teleconferenc_value = 1 THEN 2 ELSE 1 END " .
      "ELSE 0 END)",
      'total_booked'
    );
    
    // Join to get the paid status field.
    $query->leftJoin('node__field_paid_', 'fp', 'n.nid = fp.entity_id AND fp.deleted = 0');
    
    // Count paid: only count if paid=1, and in_person (1) = 2, virtual (2) = 1
    $query->addExpression(
      "SUM(CASE WHEN fp.field_paid__value = 1 THEN " .
      "  CASE WHEN vt.field_in_person_or_teleconferenc_value = 1 THEN 2 ELSE 1 END " .
      "ELSE 0 END)",
      'total_paid'
    );
    
    // Join to get the user (teacher) who created the visit.
    $query->leftJoin('users_field_data', 'u', 'n.uid = u.uid');
    
    // Join to get the school reference from the user.
    $query->leftJoin('user__field_school', 'us', 'u.uid = us.entity_id AND us.deleted = 0');
    
    // Join to get the school node.
    $query->leftJoin('node_field_data', 'school', 'us.field_school_target_id = school.nid');
    
    // Join to get the school's address.
    $query->leftJoin('node__field_address', 'addr', 'school.nid = addr.entity_id AND addr.deleted = 0');
    $query->addField('addr', 'field_address_administrative_area', 'province');
    
    // Base conditions.
    $query->condition('n.type', 'pal_pir_school_visit');
    $query->condition('n.status', 1);
    
    // Apply language filter.
    if (!empty($filters['language']) && $filters['language'] !== 'all') {
      $query->condition('n.langcode', $filters['language']);
    }
    
    // Apply date range filter.
    if (!empty($filters['date_from'])) {
      $date_from = strtotime($filters['date_from'] . ' 00:00:00');
      if ($date_from !== FALSE) {
        $query->condition('n.created', $date_from, '>=');
      }
    }
    if (!empty($filters['date_to'])) {
      $date_to = strtotime($filters['date_to'] . ' 23:59:59');
      if ($date_to !== FALSE) {
        $query->condition('n.created', $date_to, '<=');
      }
    }
    
    // Apply grades filter if provided.
    if (!empty($filters['grades']) && is_array($filters['grades'])) {
      $query->leftJoin('node__field_grades', 'g', 'n.nid = g.entity_id AND g.deleted = 0');
      $query->condition('g.field_grades_value', array_values($filters['grades']), 'IN');
    }
    
    // Group by language and province.
    $query->groupBy('n.langcode');
    $query->groupBy('addr.field_address_administrative_area');
    
    // Order by language and province.
    $query->orderBy('n.langcode', 'ASC');
    $query->orderBy('addr.field_address_administrative_area', 'ASC');
    
    $results = $query->execute()->fetchAll();
    
    // Format the results.
    $formatted_data = [];
    foreach ($results as $row) {
      $formatted_data[] = [
        'language' => $this->formatLanguage($row->language),
        'province' => $this->formatProvince($row->province),
        'total_requests' => (int) $row->total_requests,
        'total_booked' => (int) $row->total_booked,
        'total_paid' => (int) $row->total_paid,
      ];
    }
    
    return $formatted_data;
  }

  /**
   * Formats language code to readable label.
   *
   * @param string $langcode
   *   The language code.
   *
   * @return string
   *   The formatted language label.
   */
  protected function formatLanguage($langcode) {
    $languages = [
      'en' => 'English',
      'fr' => 'French',
    ];
    return $languages[$langcode] ?? $langcode;
  }

  /**
   * Formats province code to readable label.
   *
   * @param string $province_code
   *   The province code.
   *
   * @return string
   *   The formatted province label.
   */
  protected function formatProvince($province_code) {
    if (empty($province_code)) {
      return $this->t('Unknown');
    }
    
    // Canadian provinces mapping.
    $provinces = [
      'AB' => 'Alberta',
      'BC' => 'British Columbia',
      'MB' => 'Manitoba',
      'NB' => 'New Brunswick',
      'NL' => 'Newfoundland and Labrador',
      'NS' => 'Nova Scotia',
      'NT' => 'Northwest Territories',
      'NU' => 'Nunavut',
      'ON' => 'Ontario',
      'PE' => 'Prince Edward Island',
      'QC' => 'Quebec',
      'SK' => 'Saskatchewan',
      'YT' => 'Yukon',
    ];
    
    return $provinces[$province_code] ?? $province_code;
  }

}