<?php

namespace Drupal\piv_contest_judging_session\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\piv_contest_competition\Entity\Competition;
use Drupal\piv_contest_judging_session\Entity\JudgingSession;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the judging session entity edit forms.
 */
class ManageSessionsForm extends FormBase {

  protected $competitionEntryStorage;

  protected $judgingSessionsStorage;

  protected $database;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, Connection $db) {
    $this->competitionEntryStorage = $entityTypeManager->getStorage('competition_entry');
    $this->judgingSessionsStorage = $entityTypeManager->getStorage('judging_session');
    $this->database = $db;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database')
    );
  }

  public function getFormId() {
    return 'piv_contest_judging_session_manage_sessions';
  }

  public function buildForm(array $form, FormStateInterface $form_state,  Competition $competition = NULL) {
    $form_state->set('competition', $competition);

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
      ],
    ]);

    $form['create_session'] = Link::fromTextAndUrl(
      t('Create new session for selected stream'),
      $add_session,
    )->toRenderable();

    $form['#prefix'] = '<div id="edit-output">';
    $form['#suffix'] = '</div>';

    $form['session_stream'] = [
      '#type' => 'select',
      '#options' => $streams_options,
      '#title' => t('Select the session stream'),
      '#ajax' => [
        'callback' => [$this, 'onSessionStreamChange'],
        'wrapper' => 'edit-output',
        'event' => 'change',
      ],
    ];

    $form['fieldset'] = [
      '#type' => 'fieldset',
      '#title' => t('Session management'),
    ];

    $this->loadFormForSessionStream($form, $form_state);

    return $form;
  }

  public function onSessionStreamChange(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  public function loadFormForSessionStream(&$form, FormStateInterface $form_state) {
    $competition = $form_state->get('competition');

    $stream_id = $form_state->getValue('session_stream');
    if (!$stream_id) {
      $stream_id = $competition->field_competition_streams[0]->target_id;
    }

    $session_ids = $this->judgingSessionsStorage->getQuery()
      ->condition('field_stream', $stream_id)
      ->execute();

    $sessions = $this->judgingSessionsStorage->loadMultiple($session_ids);

    $sessions_options = [];
    foreach ($sessions as $session) {
      $sessions_options[$session->id()] = $session->label();
    }

    $form['fieldset']['session'] = [
      '#type' => 'select',
      '#title' => t('Select Session'),
      '#options' => $sessions_options,
      '#default_value' => NULL,
      '#ajax' => [
        'callback' => [$this, 'onSessionStreamChange'],
        'wrapper' => 'edit-output',
        'event' => 'change',
      ],
      '#required' => TRUE,
    ];

    $current_session_id = $form_state->getValue('session');

    // Make sure we reset the session id when session stream is changed.
    if (!in_array($current_session_id, $session_ids)) {
      $current_session_id = NULL;
    }

    if ($current_session_id) {
      /** @var JudgingSession $current_session */
      $current_session = $this->judgingSessionsStorage->load($current_session_id);

      $form['fieldset']['session']['#description'] = $current_session->toLink(
        t('Edit this Session'),
        'edit-form',
        [
          'query' => [
            'destination' => Url::fromRoute(
              'piv_contest_judging_session.manage_sessions',
              [
                'competition' => $competition->id(),
              ]
            )->toString(),
          ],
        ],
      );

      $items_to_add = $this->getEntriesFromList(
        $this->getAvailableEntriesForStream($stream_id),
      );

      $form['fieldset']['add_entries'] = [
        '#type' => 'fieldset',
        '#title' => t('Add entries'),
      ];

      $form['fieldset']['add_entries']['items_to_add'] = [
        '#type' => 'tableselect',
        '#header' => ['Entry name', 'Province', 'City', 'Student'],
        '#title' => t('Add entries'),
        '#options' => $items_to_add,
      ];

      $entries_to_delete = $this->getEntriesFromList($current_session->field_competition_entries);

      $form['fieldset']['remove_entries'] = [
        '#type' => 'fieldset',
        '#title' => t('Remove Entries'),
      ];

      $form['fieldset']['remove_entries']['items_to_remove'] = [
        '#type' => 'tableselect',
        '#header' => ['Entry name', 'Province', 'City', 'Student'],
        '#title' => t('Remove entries'),
        '#options' => $entries_to_delete,
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Save'),
    ];

    return $form['fieldset'];
  }

  protected function getAvailableEntriesForStream($stream_id): array {
    $query = $this->database->query('
        SELECT id from {competition_entry} as ce
            LEFT JOIN {judging_session__field_competition_entries} as js
                ON ce.id = js.field_competition_entries_target_id
            LEFT JOIN {competition_entry__field_stream} as fst
                ON ce.id = fst.entity_id
            WHERE js.field_competition_entries_target_id is NULL
              AND fst.field_stream_target_id = :stream_id
    ',
      [
        ':stream_id' => $stream_id,
      ],
    );

    if (!$query) {
      return [];
    }

    return $this->competitionEntryStorage->loadMultiple(
      $query->fetchAll(\PDO::FETCH_COLUMN),
    );
  }

  protected function getEntriesFromList($items): array {
    $entries = [];

    foreach ($items as $item) {
      if ($item instanceof EntityInterface) {
        $entity = $item;
      } else {
        $entity = $item->entity;
      }

      if (!$entity) {
        continue;
      }

      $entries[$entity->id()][0] = $entity->label() ?: "";
      $entries[$entity->id()][3] = $entity->field_student_name->value ?: "";

      if (!$entity->field_school) {
        continue;
      }

      $school = $entity->field_school->entity;
      if (!$school->field_address) {
        continue;
      }

      $address = $school->field_address;
      $entries[$entity->id()][1] = $address->administrative_area;
      $entries[$entity->id()][2] = $address->locality;
    }

    return $entries;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();

    $this->messenger()->addMessage(t('You changes have been saved'));

    $session_id = $form_state->getValue('session');
    $items_to_remove = $form_state->getValue('items_to_remove');
    $items_to_add = $form_state->getValue('items_to_add');

    $session = $this->judgingSessionsStorage->load($session_id);

    $new_items = [];
    foreach ($session->field_competition_entries as $key => $field_entry) {
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

  public function validateForm(array &$form, FormStateInterface $form_state) {
    return $form;
  }

}
