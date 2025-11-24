<?php

namespace Drupal\piv_base\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

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
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a SchoolVisitsSummaryFilterForm object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager, RequestStack $request_stack) {
    $this->entityFieldManager = $entity_field_manager;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('request_stack')
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
    $request = $this->requestStack->getCurrentRequest();
    $query_params = $request->query->all();
    
    // Get current filter values from query parameters.
    $date_from = $request->query->get('date_from');
    $date_to = $request->query->get('date_to');
    $grades = isset($query_params['grades']) && is_array($query_params['grades']) 
      ? $query_params['grades'] 
      : [];

    $current_language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $language_filter = $request->query->get('language', $current_language);

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
    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply Filters'),
      '#button_type' => 'primary',
    ];

    $form['actions']['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset'),
      '#submit' => ['::resetForm'],
      '#limit_validation_errors' => [],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $date_from = $form_state->getValue('date_from');
    $date_to = $form_state->getValue('date_to');

    // Validate that 'from' date is not after 'to' date.
    if (!empty($date_from) && !empty($date_to)) {
      $from_timestamp = strtotime($date_from);
      $to_timestamp = strtotime($date_to);

      if ($from_timestamp > $to_timestamp) {
        $form_state->setErrorByName('date_from', $this->t('The "From Date" must be before or equal to the "To Date".'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $date_from = $form_state->getValue('date_from');
    $date_to = $form_state->getValue('date_to');
    $grades = array_filter($form_state->getValue('grades'));
    $language = $form_state->getValue('language');

    // Build query parameters.
    $query_params = [];

    if (!empty($language)) {
      $query_params['language'] = $language;
    }

    if (!empty($date_from)) {
      $query_params['date_from'] = $date_from;
    }

    if (!empty($date_to)) {
      $query_params['date_to'] = $date_to;
    }

    if (!empty($grades)) {
      $query_params['grades'] = array_values($grades);
    }

    // Redirect to the same page with query parameters.
    $form_state->setRedirect('piv_base.school_visit_summary', [], [
      'query' => $query_params,
    ]);
  }

  /**
   * Form submission handler for the reset button.
   */
  public function resetForm(array &$form, FormStateInterface $form_state) {
    // Redirect to the same page without any query parameters.
    $form_state->setRedirect('piv_base.school_visit_summary');
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

}