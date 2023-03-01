<?php

namespace Drupal\piv_contest_judging_session\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\piv_contest_judging_session\Entity\JudgingSession;
use Drupal\piv_contest_judging_session\Service\JudgeSession;
use Drupal\user\Entity\User;
use Drupal\Core\Url;
use Drupal\piv_contest_competition_entry\Entity\CompetitionEntry;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Routing\RedirectDestination;

/**
 * The controller for accuracy related pages.
 */
class AccuracyJudgingSessionController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The judge service.
   *
   * @var \Drupal\piv_contest_judging_session\Service\JudgeSession
   */
  protected $judgeSessionService;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * The redirect destination service.
   *
   * @var Drupal\Core\Routing\RedirectDestination
   */
  protected $redirectDestination;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, JudgeSession $judge_session, FormBuilderInterface $form_builder, RedirectDestination $redirect_destination, AccountProxyInterface $current_user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->judgeSessionService = $judge_session;
    $this->formBuilder = $form_builder;
    $this->redirectDestination = $redirect_destination;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('piv_contest_judging_session.service.judge_session'),
      $container->get('form_builder'),
      $container->get('redirect.destination'),
      $container->get('current_user')
    );
  }

  /**
   * Generate a list of recitations.
   */
  public function recitationsList(User $user, JudgingSession $judging_session) : array {
    $build = [];
    $competition = $judging_session->field_competition->entity;
    $is_team_competition = (BOOL) $competition->field_team_competition->value;
    $rows = [];
    $destination = $this->redirectDestination->getAsArray();

    $build['back_link'] = [
      '#theme' => 'piv_back_link',
      '#link' => Link::createFromRoute($this->t('Back to Judge for accuracy'), 'piv_contest_judging_session.judge_for_accuracy', [
        'user' => $user->id(),
      ]),
    ];

    foreach ($judging_session->field_competition_entries->referencedEntities() as $competition_entry) {
      $regular_score = 0;
      $accuracy_score = 0;

      foreach ($competition_entry->field_recitations->referencedEntities() as $recitation) {
        $regular_score += $this->judgeSessionService
          ->getRecitationScore($recitation, $judging_session);
        $accuracy_score += $recitation->field_score->value ?? 0;
      }

      $school = $competition_entry->field_school->entity;
      $completed = $this->judgeSessionService->entryWasScoredForAccuracy($competition_entry);
      $label = $completed ? $this->t('Edit scores (Accuracy judging complete)') : $this->t('Judge now');
      $link = Link::createFromRoute($label, 'piv_contest_judging_session.judge_for_accuracy.judge_competition_entry', [
        'user' => $user->id(),
          'judging_session' => $judging_session->id(),
          'competition_entry' => $competition_entry->id(),
        ], [
          'query' => $destination,
        ]);

      $rows[] = [
        $competition_entry->getStudentsDisplayName(),
        $school->title->value,
        $school->field_address->administrative_area,
        $regular_score,
        $accuracy_score,
        $regular_score + $accuracy_score,
        $link,
      ];
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $is_team_competition ? $this->t('Students') : $this->t('Student'),
        $this->t('School'),
        $this->t('Province'),
        $this->t('Judge score'),
        $this->t('Accuracy score'),
        $this->t('Total'),
        $this->t('Judge now'),
      ],
      '#rows' => $rows,
    ];

    return $build;
  }

  /**
   * Display a form to judge a competition entry.
   */
  public function judgeCompetitionEntry(User $user, JudgingSession $judging_session, CompetitionEntry $competition_entry) {
    $build = [];
    $recitations = $competition_entry->field_recitations->referencedEntities();
    $build['form'] = $this->formBuilder
      ->getForm('Drupal\piv_contest_judging_session\Form\AccuracyJudgingForm', $recitations);
    return $build;
  }

  /**
   * List the sessions that can be judged.
   */
  public function sessionsList(User $user) : array {
    /** @var \Drupal\Core\Entity\EntityInterface[] $judging_sessions */
    $judging_sessions = $this->getReadyJudgingSessions($user);
    $rows = [];
    foreach ($judging_sessions as $judging_session) {
      $link = Link::createFromRoute($this->t('Judge now'), 'piv_contest_judging_session.judge_for_accuracy.recitations_list', [
        'judging_session' => $judging_session->id(),
        'user' => $user->id(),
      ])->toRenderable();

      if ($this->judgeSessionService->isSessionEvaluatedByAccuracyJudge($judging_session, $user)) {
        $link['#title'] = $this->t('Edit scores (Accuracy judging complete)');
      }

      $rows[] = [
        $judging_session->label(),
        new FormattableMarkup('@evaluated/@total', [
          '@evaluated' => $this->judgeSessionService->numberOfRecitationsEvaluatedByAccuracyJudge($judging_session, $user),
          '@total' => $this->judgeSessionService->totalNumberOfRecitationsAccuracy($judging_session, $user),
        ]),
        [
          'data' => $link,
        ],
      ];
    }

    $response['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Session'),
        $this->t('Recitations evaluated'),
        $this->t('Evaluate'),
      ],
      '#rows' => $rows,
    ];

    return $response;
  }

  /**
   * Get the sessions ready for judging for this user.
   */
  private function getReadyJudgingSessions(User $user): array {
    return $this->entityTypeManager->getStorage('judging_session')
      ->loadByProperties([
        'field_ready_for_scoring' => TRUE,
        'field_accuracy_judge' => $user->id(),
      ]);
  }

  /**
   * Judge a session.
   *
   * One recitation is judged each time, however the recitation to be judged is
   * determined in a custom order, where the first recitation for all entries
   * are judged first, then all second recitations for all entries, and it goes
   * like that. Between the all first, all second, all third, etc recitation,
   * there is a "break" page.
   */
  public function judgeSession(User $user, JudgingSession $judging_session) {
    // This is used in multiple places.
    $start_judging_url = Url::fromRoute('piv_contest_judging_session.start_judging', [
      'user' => $user->id(),
    ]);
    $recitation_data = $this->judgeSessionService->nextRecitation($judging_session, $user);
    if (!$recitation_data) {
      $this->messenger()->addMessage('There are no more recitations to judge in this session.');
      return new RedirectResponse($start_judging_url->toString());
    }

    $recitation = $recitation_data['recitation'];
    $poem = $recitation->field_poem->entity;
    if (!$poem) {
      throw new \Exception('No poem is assigned to this recitation');
    }

    $build = [
      '#theme' => 'recitation_judging',
    ];
    $build['title'] = ['#markup' => $poem->title->value];
    $build['video'] = $recitation->field_recitation_video->view([
      'type' => 'entity_reference_entity_view',
      'label' => 'hidden',
    ]);
    // If thats the last item in a round, redirect back to the recitation list.
    $destination = NULL;
    $message = NULL;
    if ($recitation_data['last_of_round']) {
      $destination = $start_judging_url;
      $message = $this->t('Thank you for completing this round. Please take a few moments before beginning the next round of this session.');
    }
    $build['form'] = $this->formBuilder
      ->getForm('Drupal\piv_contest\Form\ScoreForm', $recitation, $judging_session, $destination, $message);
    $build['judge_later'] = Link::fromTextAndUrl($this->t('Judge later'), $start_judging_url)->toRenderable();
    // Same link as above.
    $build['back'] = Link::fromTextAndUrl($this->t('Back to all sessions'), $start_judging_url)->toRenderable();
    $build['recitation_evaluated'] = [
      '#markup' => $this->t('Recitations evaluated: @evaluated / @count', [
        '@evaluated' => $this->judgeSessionService->numberOfRecitationsEvaluatedByJudge($judging_session, $user),
        '@count' => $this->judgeSessionService->totalNumberOfRecitations($judging_session, $user),
      ]),
    ];

    return $build;
  }

  /**
   * Check if user can access the sessions list.
   */
  public function accessSessionsList(User $user): AccessResult {
    $tags = ['judging_session_list'];
    $permissions = ['access to all judge for accuracy pages'];

    $entities = $this->getReadyJudgingSessions($user);
    if (!$entities) {
      return AccessResult::forbidden()->addCacheTags($tags);
    }

    if ($user->id() === $this->currentUser->id()) {
      $permissions[] = 'access own judge for accuracy page';
    }

    return AccessResult::allowedIfHasPermissions($this->currentUser, $permissions, 'OR')->addCacheTags($tags);
  }

  /**
   * Check if user can access the recitations list for a session.
   */
  public function accessRecitationsList(User $user, JudgingSession $judging_session): AccessResult {
    $ready_for_scoring = (BOOL) $judging_session->field_ready_for_scoring->value;
    if (!$ready_for_scoring) {
      return AccessResult::forbidden()->addCacheTags($judging_session->getCacheTags());
    }

    $user_is_judge = $judging_session->field_accuracy_judge->target_id == $user->id();
    if (!$user_is_judge) {
      return AccessResult::forbidden()->addCacheTags($judging_session->getCacheTags());
    }

    $permissions = ['access to all judge for accuracy pages'];
    if ($user->id() === $this->currentUser->id()) {
      $permissions[] = 'access own judge for accuracy page';
    }

    return AccessResult::allowedIfHasPermissions($this->currentUser, $permissions, 'OR')
      ->addCacheTags($judging_session->getCacheTags());
  }

  /**
   * Check if user can accuracy judge a competition entry and its recitations.
   */
  public function accessJudgeCompetitionEntry(User $user, JudgingSession $judging_session, CompetitionEntry $competition_entry) : AccessResult {
    $tags = array_merge($competition_entry->getCacheTags(), $judging_session->getCacheTags());
    $can_access_recitation_list = $this->accessRecitationsList($user, $judging_session);
    return AccessResult::allowedIf($can_access_recitation_list)
      ->addCacheTags($tags);
  }

}
