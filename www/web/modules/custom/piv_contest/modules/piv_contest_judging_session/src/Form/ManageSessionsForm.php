<?php

namespace Drupal\piv_contest_judging_session\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\piv_contest_competition\Entity\Competition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the judging session entity edit forms.
 */
class ManageSessionsForm extends FormBase {

  protected $competitionEntryStorage;

  protected $judgingSessionsStorage;

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->competitionEntryStorage = $entityTypeManager->getStorage('competition_entry');
    $this->judgingSessionsStorage = $entityTypeManager->getStorage('judging_session');
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  public function getFormId() {
    return 'piv_contest_judging_session_manage_sessions';
  }

  public function buildForm(array $form, FormStateInterface $form_state,  Competition $competition = NULL) {
    $current_user = \Drupal::currentUser();
    $form_state->set('competition', $competition);

    $streams_options = [];
    foreach ($competition->field_competition_streams as $stream) {
      $entity = $stream->entity;
      $streams_options[$entity->id()] = $entity->field_label->value;
    }

    $add_session = Url::fromRoute('piv_contest_judging_session.add_session', [
      'competition' => $competition->id(),
      'user' => $current_user->id(),
      'destination' => Url::fromRoute('piv_contest_judging_session.manage_sessions', [
        'competition' => $competition->id(),
        'user' => $current_user->id(),
      ])->toString(),
    ]);

    $form['create_session'] = Link::fromTextAndUrl(
      t('Create new session'),
      $add_session,
    )->toRenderable();

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
      '#prefix' => '<div id="edit-output">',
      '#suffix' => '</div>',
    ];

    $this->loadFormForSessionStream($form, $form_state);

    return $form;
  }

  public function onSessionStreamChange(array &$form, FormStateInterface $form_state) {
    return $form['fieldset'];
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

    $entry_ids = $this->competitionEntryStorage->getQuery()
      ->condition('field_stream', $stream_id)
      ->execute();

    $entries = $this->competitionEntryStorage->loadMultiple($entry_ids);

    $current_session_id = $form_state->getValue('session');

    // Make sure we reset the session id when session stream is changed.
    if (!in_array($current_session_id, $session_ids)) {
      $current_session_id = NULL;
    }

    $checked_entries = [];
    if ($current_session_id) {
      $current_session = $this->judgingSessionsStorage->load($current_session_id);

      foreach ($current_session->field_competition_entries as $entry_field) {
        $checked_entries[] = (int) $entry_field->target_id;
      }

      $entries_options = [];
      foreach ($entries as $entry) {
        $entries_options[$entry->id()] = $entry->label();
      }

      $form['fieldset']['entries'] = [
        '#type' => 'checkboxes',
        '#title' => 'Select Entries',
        '#options' => $entries_options,
        '#default_value' => $checked_entries,
        '#required' => TRUE,
      ];
    } else {
      unset($form['fieldset']['entries']);
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Save'),
    ];

    return $form['fieldset'];
  }

  private function getStreamFromCompetitionByID(Competition $competition, $id): ?Paragraph {
    foreach ($competition->field_competition_streams as $stream) {
      if ($stream->target_id === $id) {
        return $stream->entity;
      }
    }

    return NULL;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();

    $this->messenger()->addMessage(t('You changes have been saved'));
    $entries = $form_state->getValue('entries');
    $session_id = $form_state->getValue('session');

    $session = $this->judgingSessionsStorage->load($session_id);
    $session->field_competition_entries = $entries;
    $session->save();

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    return $form;
  }

}
