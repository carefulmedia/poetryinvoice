<?php

namespace Drupal\piv_base\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for School Visits Summary report.
 */
class SchoolVisitsSummaryController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a SchoolVisitsSummaryController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $database, $form_builder) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('form_builder')
    );
  }

  /**
   * Builds the school visits summary page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return array
   *   A render array.
   */
  public function build(Request $request) {
    // Get filter parameters from query string.
    // Use all() to get the entire query bag, then access grades as array.
    $query_params = $request->query->all();
    
    // Default language to current site language if not specified
    $current_language = $this->languageManager()->getCurrentLanguage()->getId();
    $language_filter = $request->query->get('language', $current_language);
    
    // Calculate default date range: August 1st (past) to June 30th (future)
    $default_dates = $this->getDefaultDateRange();
    
    $filters = [
      'language' => $language_filter,
      'date_from' => $request->query->get('date_from', $default_dates['date_from']),
      'date_to' => $request->query->get('date_to', $default_dates['date_to']),
      'grades' => isset($query_params['grades']) && is_array($query_params['grades']) 
        ? $query_params['grades'] 
        : [],
    ];

    // Get aggregated data using database query.
    $summary_data = $this->getSummaryData($filters);

    // Build the filter form.
    $filter_form = $this->formBuilder->getForm('Drupal\piv_base\Form\SchoolVisitsSummaryFilterForm');

    $build = [
      'filter_form' => $filter_form,
      'summary' => [
        '#theme' => 'piv_school_visits_summary',
        '#data' => $summary_data,
        '#filters' => $filters,
      ],
      '#cache' => [
        'contexts' => ['url.query_args'],
        'tags' => ['node_list:pal_pir_school_visit'],
        'max-age' => 3600,
      ],
    ];

    return $build;
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
      $query->condition('g.field_grades_value', $filters['grades'], 'IN');
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