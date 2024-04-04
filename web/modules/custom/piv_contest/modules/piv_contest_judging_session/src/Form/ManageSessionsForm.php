<?php

namespace Drupal\piv_contest_judging_session\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\piv_contest_competition\Entity\Competition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Form controller for the judging session entity edit forms.
 */
class ManageSessionsForm extends FormBase {

  /**
   * The competition entry storage.
   *
   * @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage
   */
  protected $competitionEntryStorage;


  /**
   * The Judging Session Storage.
   *
   * @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage
   */
  protected $judgingSessionsStorage;

  /**
   * The database service.
   *
   * @var Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The request stack.
   *
   * @var Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, Connection $db, RequestStack $request_stack) {
    $this->competitionEntryStorage = $entityTypeManager->getStorage('competition_entry');
    $this->judgingSessionsStorage = $entityTypeManager->getStorage('judging_session');
    $this->database = $db;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'piv_contest_judging_session_manage_sessions';
  }

  /**
   * Build the form.
   */
  public function buildForm(array $form, FormStateInterface $form_state, Competition $competition = NULL) {
    $form_state->set('competition', $competition);
    $current_level = $form_state->getValue('competition_level') ??
      $this->requestStack->getCurrentRequest()->get('level') ??
      $competition->field_competition_current_level->value ?? 1;

    $streams_options = [];
    foreach ($competition->field_competition_streams as $stream) {
      $entity = $stream->entity;
      $streams_options[$entity->id()] = $entity->field_label->value;
    }

    $stream_id = $form_state->getValue('session_stream');
    if (!$stream_id) {
      $stream_id = $competition->field_competition_streams[0]->target_id;
    }

    $add_session = Url::fromRoute('piv_contest_judging_session.add_session', [
      'competition' => $competition->id(),
      'destination' => Url::fromRoute('piv_contest_judging_session.manage_sessions', [
        'competition' => $competition->id(),
      ])->toString(),
    ], [
      'query' => [
        'stream' => $stream_id,
        'level' => $current_level,
      ],
    ]);

    $form['create_session'] = Link::fromTextAndUrl(
      $this->t('Create new session for selected stream and level'),
      $add_session,
    )->toRenderable();

    $form['#prefix'] = '<div id="edit-output">';
    $form['#suffix'] = '</div>';

    $form['session_stream'] = [
      '#type' => 'select',
      '#options' => $streams_options,
      '#title' => $this->t('Select the session stream'),
      '#ajax' => [
        'callback' => [$this, 'onSessionStreamChange'],
        'wrapper' => 'edit-output',
        'event' => 'change',
      ],
    ];

    $competition_levels = array_column($competition->field_competition_levels->getValue(), 'value');
    $competition_levels_options = [];
    foreach ($competition_levels as $delta => $value) {
      $competition_levels_options[$delta + 1] = $value;
    }
    $form['competition_level'] = [
      '#type' => 'select',
      '#options' => $competition_levels_options,
      '#title' => $this->t('Competition levels'),
      '#default_value' => $current_level,
      '#ajax' => [
        'callback' => [$this, 'onLevelChange'],
        'wrapper' => 'edit-output',
        'event' => 'change',
      ],
      '#attributes' => ['autocomplete' => 'off'],
    ];

    $form['fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Session management'),
    ];

    $this->loadFormForSessionStream($form, $form_state);

    return $form;
  }

  /**
   * Ajax callback.
   */
  public function onSessionStreamChange(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Ajax callback.
   */
  public function onLevelChange(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Load the form and populate on $form.
   */
  public function loadFormForSessionStream(&$form, FormStateInterface $form_state) {
    $competition = $form_state->get('competition');

    $competition_level = $form_state->getValue('competition_level') ??
      $this->requestStack->getCurrentRequest()->get('level') ??
      $competition->field_competition_current_level->value ?? 1;

    $stream_id = $form_state->getValue('session_stream');
    if (!$stream_id) {
      $stream_id = $competition->field_competition_streams[0]->target_id;
    }

    $sessions = $this->judgingSessionsStorage->loadByProperties([
      'field_stream' => $stream_id,
      'field_competition_current_level' => $competition_level,
    ]);
    $sessions_options = [];
    foreach ($sessions as $session) {
      $sessions_options[$session->id()] = $session->label();
    }

    $current_session_id = $form_state->getValue('session');

    // Make sure we reset the session id when session stream is changed.
    if (!isset($sessions_options[$current_session_id])) {
      $current_session_id = NULL;
    }

    $session_id = $this->requestStack->getCurrentRequest()->query->get('session');
    if (!$current_session_id && $session_id && isset($sessions_options[$session_id])) {
      $current_session_id = $session_id;
    }

    $form['fieldset']['session'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Session'),
      '#options' => $sessions_options,
      '#default_value' => $current_session_id,
      '#ajax' => [
        'callback' => [$this, 'onSessionStreamChange'],
        'wrapper' => 'edit-output',
        'event' => 'change',
      ],
      '#required' => TRUE,
      '#attributes' => ['autocomplete' => 'off'],
    ];

    if ($current_session_id) {
      /** @var \Drupal\piv_contest_judging_session\Entity\JudgingSession $current_session */
      $current_session = $this->judgingSessionsStorage->load($current_session_id);

      $form['fieldset']['session']['#description'] = $current_session->toLink(
        $this->t('Edit this Session'),
        'edit-form',
        [
          'query' => [
            'destination' => Url::fromRoute(
              'piv_contest_judging_session.manage_sessions',
              [
                'competition' => $competition->id(),
              ],
              [
                'query' => [
                  'session' => $current_session_id,
                  'level' => $competition_level,
                ],
              ]
            )->toString(),
          ],
        ],
      );

      $items_to_add = $this->getEntriesFromList($this->getAvailableEntriesForSession($current_session, $competition_level));
      $form['fieldset']['add_entries'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Add entries'),
      ];

      $form['fieldset']['add_entries']['items_to_add'] = [
        '#type' => 'tableselect',
        '#header' => ['Entry name', 'Province', 'City', 'Student'],
        '#title' => $this->t('Add entries'),
        '#options' => $items_to_add,
      ];

      $entries_to_delete = $this->getEntriesFromList($current_session->field_competition_entries);

      $form['fieldset']['remove_entries'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Remove Entries'),
      ];

      $form['fieldset']['remove_entries']['items_to_remove'] = [
        '#type' => 'tableselect',
        '#header' => ['Entry name', 'Province', 'City', 'Student'],
        '#title' => $this->t('Remove entries'),
        '#options' => $entries_to_delete,
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form['fieldset'];
  }

  /**
   * Get all available entries for a session.
   */
  protected function getAvailableEntriesForSession($judging_session, $level): array {
    $competition_id = $judging_session->field_competition->target_id;
    if (!$competition_id) {
      return [];
    }

    $stream_id = $judging_session->field_stream->target_id;
    $available_entries = $this->getAvailableEntriesForStreamAndLevel($stream_id, $level);
    if (empty($available_entries)) {
      return [];
    }

    $already_added = array_column($judging_session->field_competition_entries->getValue(), 'target_id');
    $query = $this->competitionEntryStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_competition', $competition_id)
      ->condition('field_competition_current_level', $judging_session->field_competition_current_level->value)
      ->condition('field_stream', $stream_id)
      ->condition('id', array_keys($available_entries), 'IN');
    if ($already_added) {
      $query->condition('id', $already_added, 'NOT IN');
    }

    $competition_entry_ids = $query->execute();
    return $this->competitionEntryStorage->loadMultiple($competition_entry_ids);
  }

  /**
   * Get all available entries for a stream.
   */
  protected function getAvailableEntriesForStreamAndLevel($stream_id, $level): array {
    $query = $this->database->query('
        SELECT id from {competition_entry} as ce
            LEFT JOIN {judging_session__field_competition_entries} as js
                ON ce.id = js.field_competition_entries_target_id
            LEFT JOIN {competition_entry__field_stream} as fst
                ON ce.id = fst.entity_id
            WHERE 1=1
              AND fst.field_stream_target_id = :stream_id
              AND (
                (
                    js.field_competition_entries_target_id is NULL
                ) OR (
                    js.field_competition_entries_target_id NOT IN (
                        SELECT id from {competition_entry} as ce
                            LEFT JOIN {judging_session__field_competition_entries} as js
                                ON ce.id = js.field_competition_entries_target_id
                            LEFT JOIN {competition_entry__field_stream} as fst
                                ON ce.id = fst.entity_id
                            LEFT JOIN {judging_session__field_competition_current_level} as lvl
                                on js.entity_id = lvl.entity_id
                            WHERE 1=1
                                AND fst.field_stream_target_id = :stream_id
                                AND lvl.field_competition_current_level_value = :level
                    )
                )
              )
    ',
      [
        ':stream_id' => $stream_id,
        ':level' => $level,
      ],
    );

    if (!$query) {
      return [];
    }

    return $this->competitionEntryStorage->loadMultiple(
      $query->fetchAll(\PDO::FETCH_COLUMN)
    );

  }

  /**
   * Get entries from list.
   */
  protected function getEntriesFromList($items): array {
    $entries = [];

    foreach ($items as $item) {
      if ($item instanceof EntityInterface) {
        $entity = $item;
      }
      else {
        $entity = $item->entity;
      }

      if (!$entity) {
        continue;
      }

      $is_completed = (bool) $entity->field_complete->value;
      // We just want to show completed entries.
      if (!$is_completed) {
        continue;
      }

      $entries[$entity->id()][0] = $entity->label() ?: "";
      $entries[$entity->id()][3] = piv_contest_get_student_name($entity);

      if (!$entity->field_school) {
        continue;
      }

      $school = $entity->field_school->entity;
      if (!$school->field_address) {
        continue;
      }

      $address = $school->field_address;
      $entries[$entity->id()][1] = $address->administrative_area ?? '';
      $entries[$entity->id()][2] = $address->locality ?? '';
    }

    return $entries;
  }

  /**
   * Form submit.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();

    $this->messenger()->addMessage($this->t('You changes have been saved'));

    $session_id = $form_state->getValue('session', NULL);
    $items_to_remove = $form_state->getValue('items_to_remove', []);
    $items_to_add = $form_state->getValue('items_to_add', []);

    $session = $this->judgingSessionsStorage->load($session_id);

    $new_items = [];
    foreach ($session->field_competition_entries as $field_entry) {
      // Exclude items to remove.
      if (!in_array($field_entry->target_id, $items_to_remove)) {
        $new_items[] = $field_entry->target_id;
      }
    }

    foreach ($items_to_add as $id) {
      if ($id === 0) {
        continue;
      }

      $new_items[] = $id;
    }

    $session->field_competition_entries = [];
    foreach ($new_items as $new_item) {
      $session->field_competition_entries[] = [
        'target_id' => $new_item,
      ];
    }

    $session->save();

    return $form;
  }

  /**
   * Form validation.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    return $form;
  }

}
