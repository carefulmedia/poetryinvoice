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
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $db, RequestStack $request_stack, JudgeSession $judge_session) {
    $this->competitionEntryStorage = $entity_type_manager->getStorage('competition_entry');
    $this->judgingSessionsStorage = $entity_type_manager->getStorage('judging_session');
    $this->entityTypeManager = $entity_type_manager;
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

    $judging_session_id = $form_state->getValue('judging_session');
    if ($judging_session_id) {
      $judging_session = $this->judgingSessionsStorage->load($judging_session_id);
      $judges_count = $judging_session->field_english_judge->count() + $judging_session->field_french_judge->count();
      $form['judging_session_title'] = [
        '#theme' => 'page_title',
        '#title' => $judging_session->label(),
      ];
      // Initiate a score array with 0 values.
      $judges = array_merge(
        array_map(fn ($id) => "$id:en", array_column($judging_session->field_english_judge->getValue(), 'target_id')),
        array_map(fn ($id) => "$id:fr", array_column($judging_session->field_french_judge->getValue(), 'target_id')),
      );
      $judges = array_flip($judges);
      foreach ($judges as $judge => $value) {
        $judges[$judge] = 0;
      }
      // On the progress form, the rank is based on the ranks from all judges.
      // The value that determines the rank is the total of all ranks per judge.
      // So if a competition entry is 1st for a judge, 2nd for another and 3rd
      // for another, it will have a value of 6 to compare with the other
      // competition entries in order to determine the rank. The best value
      // would be 3 for a competition entry that will be the first one for all
      // three judges.
      // Rank is based on the total ranks of each judge (performance+accuracy),
      // so the best rank would be 3 for a 3 judge session. Tiebreaker for same
      // rank is total score.
      // Load all the scores for this judging session and group by competition
      // entry and judge.
      $score_storage = $this->entityTypeManager->getStorage('score');
      $map = [];
      foreach ($judging_session->field_competition_entries->referencedEntities() as $competition_entry) {
        $accuracy_total = 0;
        $score_per_judge = [];
        $competition_entry_id = $competition_entry->id();
        foreach ($competition_entry->field_recitations->referencedEntities() as $recitation) {
          $accuracy_total += $recitation->field_score->value ?? 0;
          $scores = $score_storage->loadByProperties([
            'judging_session' => $judging_session->id(),
            'recitation' => $recitation->id(),
          ]);

          foreach ($scores as $score) {
            $judge_id = $score->judge->target_id;
            $stream_language = $recitation->field_stream_language->target_id;
            $key = implode(':', [$judge_id, $stream_language]);
            if (empty($score_per_judge[$key])) {
              $score_per_judge[$key] = 0;
            }
            $values = array_column($score->field_scores->getValue(), 'value');
            $score_per_judge[$key] += array_sum($values);
          }
        }
        $map[$competition_entry_id] = [
          'accuracy' => $accuracy_total,
          'score' => $score_per_judge + $judges,
        ];
      }

      $map2 = [];
      foreach ($map as $competition_entry_id => $score) {
        $accuracy = $score['accuracy'];
        foreach ($score['score'] as $key => $score_value) {
          if (!isset($map2[$key][$competition_entry_id])) {
            $map2[$key][$competition_entry_id] = [
              'accuracy' => $accuracy,
              'score' => 0,
            ];
          }
          $map2[$key][$competition_entry_id]['score'] += $score_value;
        }
      }

      // Totalles scores for the final table.
      $totalled_scores_data = [];
      // Contains all information for all judges tables.
      $judges_tables_data = [];
      foreach ($map2 as $key => $judge_scores) {
        $map_judge = $map2[$key];
        // Reorder each judge map by total score desc.
        uasort($map_judge, function ($a, $b) {
          $total_a = $a['score'] + $a['accuracy'];
          $total_b = $b['score'] + $b['accuracy'];
          if ($total_a == $total_b) {
            return 0;
          }
          return $total_a > $total_b ? -1 : 1;
        });

        $i = 1;
        $last_score = 0;
        $last_rank = 1;
        // Remap adding rank.
        foreach ($map_judge as $competition_entry_id => $data) {
          $competition_entry = $this->competitionEntryStorage->load($competition_entry_id);
          $competition_entry_level = $competition_entry->field_competition_current_level->value;
          $student_name = $competition_entry->getStudentsDisplayName();
          $score = $data['score'];
          $accuracy = $data['accuracy'];
          $total_score = $score + $accuracy;
          $rank = $total_score == $last_score ? $last_rank : $i;
          $judges_tables_data[$key][$competition_entry_id] = [
            'entry' => $competition_entry_id,
            'student' => $student_name,
            'judging_score' => $score,
            'accuracy' => $accuracy,
            'total_score' => $total_score,
            'rank' => $rank,
          ];
          if (empty($totalled_scores_data[$competition_entry_id])) {
            $totalled_scores_data[$competition_entry_id] = [
              'student' => $student_name,
              'province' => '',
              'judging_score' => 0,
              'accuracy' => $accuracy * $judges_count,
              'total_score' => 0,
              'edit' => ['data' => $competition_entry->toLink('Edit', 'edit-form')],
              'rank' => 0,
              '#attributes' => [
                'class' => $competition_entry_level > $competition_level ? ['promoted'] : [],
              ],
            ];
          }
          $totalled_scores_data[$competition_entry_id]['judging_score'] += $score;
          $totalled_scores_data[$competition_entry_id]['total_score'] = $totalled_scores_data[$competition_entry_id]['accuracy'] + $totalled_scores_data[$competition_entry_id]['judging_score'];
          $totalled_scores_data[$competition_entry_id]['rank'] += $rank;
          $last_score = $total_score;
          $last_rank = $rank;
          $i++;
        }
      }
      // Reorder totalled scores data according to rank and total_score.
      uasort($totalled_scores_data, function ($a, $b) {
        if ($a['rank'] == $b['rank']) {
          if ($a['total_score'] == $b['total_score']) {
            return 0;
          }
          // More score is better.
          return $a['total_score'] > $b['total_score'] ? -1 : 1;
        }
        // Less rank is better.
        return $a['rank'] < $b['rank'] ? -1 : 1;
      });

      $form['judges_tables'] = [
        '#type' => 'container',
      ];
      $can_promote = FALSE;
      if (is_numeric($competition_level)) {
        $next_level = $competition_level + 1;
        $total_levels = count($competition->field_competition_levels);
        if ($next_level <= $total_levels) {
          $can_promote = TRUE;
        }
      }
      $title = $this->t('Totalled Scores');
      $form['promote'] = [
        '#type' => 'submit',
        '#value' => $this->t('Promote the selected entries'),
        '#ajax' => [
          'callback' => [$this, 'ajaxRefresh'],
          'wrapper' => 'competition-progress',
          'event' => 'click',
        ],
        '#prefix' => "<h3>$title</h3>",
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
      // Tables per judge.
      $judges_tables_header = [
        'entry' => $this->t('Entry'),
        'student' => $this->t('Student'),
        'judging_score' => $this->t('Judging Score'),
        'accuracy' => $this->t('Accuracy'),
        'total_score' => $this->t('Total Score'),
        'rank' => $this->t('Rank'),
      ];
      foreach ($judges_tables_data as $key => $judge_table_rows) {
        [$judge_id, $stream_language] = explode(':', $key);
        $judge = $this->entityTypeManager
          ->getStorage('user')->load($judge_id);
        $judge_name = $judge ? $judge->getDisplayName() : '* Missing Judge';
        $form['judges_tables'][$key] = [
          '#type' => 'table',
          '#header' => $judges_tables_header,
          '#rows' => $judge_table_rows,
          '#prefix' => "<h3>$judge_name ($stream_language)</h3>",
          '#attributes' => ['class' => ['tablesorter']],
        ];
      }
      // Totalled scores table.
      $totalled_scores_header = [
        'student' => $this->t('Student'),
        'province' => $this->t('Province'),
        'judging_score' => $this->t('Judging Score'),
        'accuracy' => $this->t('Accuracy'),
        'total_score' => $this->t('Total Score'),
        'edit' => $this->t('Edit'),
        'rank' => $this->t('Rank'),
      ];
      $form['totalled_scores'] = [
        '#type' => 'tableselect',
        '#header' => $totalled_scores_header,
        '#options' => $totalled_scores_data,
        '#attributes' => ['class' => ['tablesorter']],
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
    $to_promote = array_filter($form_state->getValue('totalled_scores'));
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
