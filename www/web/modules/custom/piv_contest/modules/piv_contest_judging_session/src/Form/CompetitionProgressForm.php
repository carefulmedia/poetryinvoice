<?php

namespace Drupal\piv_contest_judging_session\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\piv_contest_judging_session\Service\JudgeSession;
use Drupal\piv_contest_competition\Entity\Competition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Form controller to manager the competition entries progress.
 */
class CompetitionProgressForm extends FormBase {

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
   * The judge service.
   *
   * @var \Drupal\piv_contest_judging_session\Service\JudgeSession
   */
  protected $judgeSessionService;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, Connection $db, RequestStack $request_stack, JudgeSession $judge_session) {
    $this->competitionEntryStorage = $entityTypeManager->getStorage('competition_entry');
    $this->judgingSessionsStorage = $entityTypeManager->getStorage('judging_session');
    $this->database = $db;
    $this->requestStack = $request_stack;
    $this->judgeSessionService = $judge_session;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('request_stack'),
      $container->get('piv_contest_judging_session.service.judge_session')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'piv_contest_judging_session_competition_progress';
  }

  /**
   * Build the form.
   */
  public function buildForm(array $form, FormStateInterface $form_state, Competition $competition = NULL) {
    $form_state->set('competition', $competition);

    $form['#prefix'] = '<div id="competition-progress">';
    $form['#suffix'] = '</div>';

    $judging_sessions = $this->judgingSessionsStorage->loadByProperties([
      'field_competition' => $competition->id(),
    ]);
    $form['judging_session'] = [
      '#type' => 'select',
      '#options' => array_map(fn($s) => $s->label(), $judging_sessions),
      '#title' => $this->t('Judging Session'),
      '#ajax' => [
        'callback' => [$this, 'ajaxRefresh'],
        'wrapper' => 'competition-progress',
        'event' => 'change',
      ],
      '#required' => TRUE,
    ];

    $competition_level = $competition->field_competition_current_level->value;
    $competition_level_name = $competition->field_competition_levels[$competition_level - 1]->value ?? NULL;
    $form['intro'] = [
      '#type' => 'fieldset',
      '#title' => 'Current competition level',
      'text' => [
        '#markup' => $this->t('@level (@level_name)', [
          '@level' => $competition_level,
          '@level_name' => $competition_level_name,
        ]),
      ],
    ];

    $can_promote = FALSE;
    if (is_numeric($competition_level)) {
      $next_level = $competition_level + 1;
      $total_levels = count($competition->field_competition_levels);
      if ($next_level <= $total_levels) {
        $can_promote = TRUE;
      }
    }

    $form['promote'] = [
      '#type' => 'submit',
      '#value' => $this->t('Promote the selected entries'),
      '#ajax' => [
        'callback' => [$this, 'ajaxRefresh'],
        'wrapper' => 'competition-progress',
        'event' => 'click',
      ],
      '#submit' => [[$this, 'promote']],
      '#access' => $can_promote,
    ];

    if (!$can_promote) {
      $form['cant_promote'] = [
        '#markup' => $this->t('Competition entries are already at the last level'),
        '#prefix' => '<div>',
        '#suffix' => '</div>',
      ];
    }

    $form['table'] = [
      '#markup' => 'no results',
    ];
    $judging_session_id = $form_state->getValue('judging_session');
    if ($judging_session_id) {
      $judging_session = $this->judgingSessionsStorage->load($judging_session_id);
      $rows = [];
      $is_team_competition = (BOOL) $competition->field_team_competition->value;
      foreach ($judging_session->field_competition_entries->referencedEntities() as $competition_entry) {
        $regular_score = 0;
        $accuracy_score = 0;
        $student_name = [];
        if (!$is_team_competition) {
          $student_name = [$competition_entry->field_student_name->value];
        }

        foreach ($competition_entry->field_recitations->referencedEntities() as $recitation) {
          if ($is_team_competition) {
            $student_name[] = $recitation->field_student_name->value;
          }
          $regular_score += $this->judgeSessionService
            ->getRecitationScore($recitation, $judging_session);
          $accuracy_score += $recitation->field_score->value ?? 0;
        }

        $school = $competition_entry->field_school->entity;
        $competition_entry_level = $competition_entry->field_competition_current_level->value;
        // The rank is pushed later.
        $rows[$competition_entry->id()] = [
          'rank' => 0,
          'entry_level' => $competition_entry_level,
          'actions' => ['data' => $competition_entry->toLink('Edit', 'edit-form')],
          'student' => implode(', ', $student_name),
          'school' => $school->title->value,
          'regular_score' => $regular_score,
          'accuracy_score' => $accuracy_score,
          'total_score' => $regular_score + $accuracy_score,
          '#attributes' => [
            'class' => $competition_entry_level > $competition_level ? ['promoted'] : [],
          ],
        ];
      }
      uasort($rows, function ($a, $b) {
        if ($a['total_score'] == $b['total_score']) {
          return 0;
        }
        return $a['total_score'] > $b['total_score'] ? -1 : 1;
      });
      // Add the rank.
      $i = 1;
      foreach ($rows as &$row) {
        $row['rank'] = $i++;
      }
      $header = [
        'rank' => $this->t('Rank'),
        'entry_level' => $this->t('Entry level'),
        'actions' => $this->t('Actions'),
        'student' => $this->t('Student'),
        'school' => $this->t('School'),
        'regular_score' => $this->t('Performance Score'),
        'accuracy_score' => $this->t('Accuracy Score'),
        'total_score' => $this->t('Total Score'),
      ];
      $form['table'] = [
        '#type' => 'tableselect',
        '#header' => $header,
        '#options' => $rows,
      ];
    }
    $form['#attached']['library'][] = 'piv/competition-progress';
    return $form;
  }

  /**
   * Ajax callback.
   */
  public function ajaxRefresh(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Form submit.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Promote the selected entries.
   */
  public function promote(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
    $to_promote = array_filter($form_state->getValue('table'));
    if ($to_promote) {
      $competition = $form_state->get('competition');
      $current_level = $competition->field_competition_current_level->value;
      if (is_numeric($current_level)) {
        $next_level = $current_level + 1;
        $total_levels = count($competition->field_competition_levels);
        if ($next_level <= $total_levels) {
          foreach ($this->competitionEntryStorage->loadMultiple($to_promote) as $competition_entry) {
            $competition_entry->field_competition_current_level = $next_level;
            $competition_entry->save();
          }
        }
      }
    }
  }

  /**
   * Form validation.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

}
