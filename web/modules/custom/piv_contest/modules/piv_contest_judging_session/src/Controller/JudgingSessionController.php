<?php

namespace Drupal\piv_contest_judging_session\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\piv_contest_competition\CompetitionInterface;
use Drupal\piv_contest_judging_session\Entity\JudgingSession;
use Drupal\piv_contest_judging_session\Service\JudgeSession;
use Drupal\user\Entity\User;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Judging Session controller.
 */
class JudgingSessionController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The entity form builder service.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilder
   */
  protected $entityFormBuilder;

  /**
   * The judge session service.
   *
   * @var \Drupal\piv_contest_judging_session\Service\JudgeSession
   */
  protected $judgeSessionService;

  /**
   * Current logged in user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * CSRF Token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $tokenGenerator;

  /**
   * Form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * JudgingSessionController constructor.
   */
  public function __construct(EntityFormBuilderInterface $entity_form_builder, EntityTypeManagerInterface $entity_type_manager, JudgeSession $judge_session, AccountProxyInterface $current_user, CsrfTokenGenerator $csrf_token_generator, FormBuilderInterface $form_builder) {
    $this->entityFormBuilder = $entity_form_builder;
    $this->entityTypeManager = $entity_type_manager;
    $this->judgeSessionService = $judge_session;
    $this->currentUser = $current_user;
    $this->tokenGenerator = $csrf_token_generator;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.form_builder'),
      $container->get('entity_type.manager'),
      $container->get('piv_contest_judging_session.service.judge_session'),
      $container->get('current_user'),
      $container->get('csrf_token'),
      $container->get('form_builder')
    );
  }

  /**
   * Add new judging session page.
   */
  public function add(CompetitionInterface $competition, Request $request): array {
    $stream_id = $request->query->get('stream');
    $level = $request->query->get('level') ?? 1;
    $stream = $this->entityTypeManager->getStorage('paragraph')->load($stream_id);
    $level_name = $competition->field_competition_levels[$level - 1]->value;
    $identifier = $this->entityTypeManager
      ->getStorage('judging_session')
      ->getQuery()
      ->condition('field_competition', $competition->id())
      ->count()->execute();
    $identifier += 1;
    $title = "{$competition->label()} {$stream->field_label->value} judging session, $level_name ($identifier)";
    $judging_session = $this->entityTypeManager->getStorage('judging_session')->create([
      'field_competition' => $competition->id(),
      'field_competition_current_level' => $level,
      'user' => $this->currentUser->id(),
      'field_stream' => $stream,
      'title' => $title,
      'bundle' => 'default',
    ]);

    $form = $this->entityFormBuilder
      ->getForm($judging_session, 'session_management_ui');
    $form['revision_information']['#access'] = FALSE;
    return $form;
  }

  /**
   * Page to start judging.
   */
  public function startJudging(User $user): array {
    /** @var \Drupal\Core\Entity\EntityInterface[] $judging_sessions */
    $judging_sessions = $this->getReadyJudgingSessions($user);
    $rows = [];

    // Make sure at least one session is available to judge.
    $has_session_available = FALSE;
    foreach ($judging_sessions as $judging_session) {
      if ($this->judgeSessionService->canJudgeStartJudgingSession($judging_session, $user)) {
        $has_session_available = TRUE;
        break;
      }
    }

    // Make sure we clean this up and first session will be judged by default.
    if (!$has_session_available && count($judging_sessions) > 0) {
      $this->judgeSessionService->removeSessionBeingJudged($user);
    }

    foreach ($judging_sessions as $judging_session) {
      $url = Url::fromRoute('piv_contest_judging_session.read_poems', [
        'judging_session' => $judging_session->id(),
        'user' => $user->id(),
      ]);

      // If user have read all the poems we send them directly to
      // the correct page.
      if ($this->judgeSessionService->numberOfPoemsReadByJudge($judging_session, $user) === $this->judgeSessionService->totalNumberOfPoems($judging_session, $user)) {
        $url = Url::fromRoute('piv_contest_judging_session.judge_recitations', [
          'judging_session' => $judging_session->id(),
          'user' => $user->id(),
        ]);
      }

      $link = Link::fromTextAndUrl($this->t('Judge now'), $url)->toRenderable();

      if ($this->judgeSessionService->isSessionEvaluatedByJudge($judging_session, $user)) {
        $link = ['#markup' => $this->t('Judging complete')];
      }
      else {
        if (!$this->judgeSessionService->canJudgeStartJudgingSession($judging_session, $user)) {
          $link = [
            '#markup' => '-',
          ];
        }
        else {
          $this->judgeSessionService->startJudgingSession($judging_session, $user);
        }
      }

      $rows[] = [
        $judging_session->id(),
        new FormattableMarkup('@evaluated/@total', [
          '@evaluated' => $this->judgeSessionService->numberOfRecitationsEvaluatedByJudge($judging_session, $user),
          '@total' => $this->judgeSessionService->totalNumberOfRecitations($judging_session, $user),
        ]),
        [
          'data' => $link,
        ],
      ];
    }

    $response['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Session Id'),
        $this->t('Recitations evaluated'),
        $this->t('Evaluate'),
      ],
      '#rows' => $rows,
    ];

    $uri = Url::fromUserInput('/judge-pages/how-judge-our-online-contests');

    $response['link'] = Link::fromTextAndUrl($this->t('How to judge an online contest'), $uri)->toRenderable();

    return $response;
  }

  /**
   * Check if user can access the judging page.
   */
  public function accessStartJudging(User $user): AccessResult {
    $tags = ['judging_session_list'];

    $entities = $this->getReadyJudgingSessions($user);
    if (!$entities) {
      return AccessResult::forbidden()->addCacheTags($tags);
    }

    $permissions = ['access to all judge session pages'];

    if ($user->id() === $this->currentUser->id()) {
      $permissions[] = 'access own judge session page';
    }

    return AccessResult::allowedIfHasPermissions($this->currentUser, $permissions, 'OR')->addCacheTags($tags);
  }

  /**
   * Page for a judge to read the poems.
   */
  public function readPoems(User $user, JudgingSession $judging_session) {
    // IF user has read all poems redirect to judging page.
    if ($this->judgeSessionService->numberOfPoemsReadByJudge($judging_session, $user) === $this->judgeSessionService->totalNumberOfPoems($judging_session, $user)) {
      return $this->redirect('piv_contest_judging_session.judge_recitations', [
        'user' => $user->id(),
        'judging_session' => $judging_session->id(),
      ]);
    }

    $this->judgeSessionService->startJudgingSession($judging_session, $user);
    $poem = $this->judgeSessionService->nextPoemToRead($judging_session, $user);
    if (!$poem) {
      return [
        '#markup' => $this->t('No poems to read'),
      ];
    }
    $session_id = $judging_session->id();
    $poem_id = $poem->id();

    $build = [
      '#theme' => 'poem_read_poems',
    ];
    $build['back'] = [
      '#markup' => Link::fromTextAndUrl($this->t('Back to all sessions'), Url::fromRoute('piv_contest_judging_session.start_judging', [
        'user' => $user->id(),
      ]))->toString(),
    ];
    $build['title'] = ['#markup' => $poem->title->value];
    $build['description'] = ['#markup' => $poem->body->value];
    $build['poems_read'] = [
      '#markup' => $this->t('Poems read: @read/@total', [
        '@read' => $this->judgeSessionService->numberOfPoemsReadByJudge($judging_session, $user),
        '@total' => $this->judgeSessionService->totalNumberOfPoems($judging_session, $user),
      ]),
    ];

    $url = Url::fromRoute(
      'piv_contest_judging_session.mark_poem_as_read',
      [
        'judging_session' => $session_id,
        'user' => $user->id(),
        'poem' => $poem_id,
      ],
    );
    $token = $this->tokenGenerator->get($url->getInternalPath());
    $url->setOptions(['query' => ['token' => $token]]);

    $text = $this->t('Read next poem');

    $isLastPage = $this->judgeSessionService->numberOfPoemsReadByJudge($judging_session, $user) + 1 >= $this->judgeSessionService->totalNumberOfPoems($judging_session, $user);
    if ($isLastPage) {
      $text = $this->t('Start judging');
    }

    $build['read_next'] = [
      '#markup' => Link::fromTextAndUrl($text, $url)->toString(),
    ];

    if (!$isLastPage) {
      $build['read_later'] = [
        '#markup' => Link::fromTextAndUrl($this->t('Read poems later'), Url::fromRoute('piv_contest_judging_session.start_judging', [
          'user' => $user->id(),
        ]))->toString(),
      ];
    }

    return $build;
  }

  /**
   * Endpoint to mark recitation as read by a judge.
   */
  public function markPoemAsRead(User $user, JudgingSession $judging_session, NodeInterface $poem): RedirectResponse {
    $this->judgeSessionService->markPoemAsRead($judging_session, $user, $poem);
    // IF user has read all poems redirect to judging page.
    if ($this->judgeSessionService->numberOfPoemsReadByJudge($judging_session, $user) === $this->judgeSessionService->totalNumberOfPoems($judging_session, $user)) {
      return $this->redirect('piv_contest_judging_session.judge_recitations', [
        'user' => $user->id(),
        'judging_session' => $judging_session->id(),
      ]);
    }

    return $this->redirect('piv_contest_judging_session.read_poems', [
      'user' => $user->id(),
      'judging_session' => $judging_session->id(),
    ]);
  }

  /**
   * Get sessions that are ready to be judged.
   */
  private function getReadyJudgingSessions(User $user): array {
    $judging_session_manager = $this->entityTypeManager->getStorage('judging_session');
    $query = $judging_session_manager->getQuery();
    $group = $query->orConditionGroup()
      ->condition('field_english_judge', $user->id())
      ->condition('field_french_judge', $user->id());
    $ids = $query
      ->condition('field_ready_for_scoring', TRUE)
      ->condition($group)
      ->execute();
    return $ids ? $judging_session_manager->loadMultiple($ids) : [];
  }

  /**
   * Judge a session.
   *
   * One recitation is judged each time, however the recitation to be judged is
   * determined in a custom order, where the first recitation for all entries
   * are judged first, then all second recitations for all entries, and it goes
   * like that. Between the all first, all second, all third, etc recitation,
   * there is a "break" page so the judge knows that judged
   * all first recitations (for example).
   */
  public function judgeSession(User $user, JudgingSession $judging_session) {
    $this->judgeSessionService->startJudgingSession($judging_session, $user);

    // Not allowed to judge if user have not read the recitation yet.
    if ($this->judgeSessionService->numberOfPoemsReadByJudge($judging_session, $user) !== $this->judgeSessionService->totalNumberOfPoems($judging_session, $user)) {
      return $this->redirect('piv_contest_judging_session.read_poems', [
        'judging_session' => $judging_session->id(),
        'user' => $user->id(),
      ]);
    }

    // This is used in multiple places.
    $start_judging_url = Url::fromRoute('piv_contest_judging_session.start_judging', [
      'user' => $user->id(),
    ]);
    $recitation_data = $this->judgeSessionService->nextRecitation($judging_session, $user);
    if (!$recitation_data) {
      $this->judgeSessionService->removeSessionBeingJudged($user);
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
   * Check if user can access session.
   */
  public function accessJudgeSession(User $user, JudgingSession $judging_session): AccessResult {
    if (!$this->judgeSessionService->canJudgeStartJudgingSession($judging_session, $user)) {
      return AccessResult::forbidden()->addCacheTags($judging_session->getCacheTags());
    }

    $ready_for_scoring = (BOOL) $judging_session->field_ready_for_scoring->value;
    if (!$ready_for_scoring) {
      return AccessResult::forbidden()->addCacheTags($judging_session->getCacheTags());
    }

    $judges = array_merge($judging_session->field_english_judge->getValue(), $judging_session->field_french_judge->getValue());
    $user_is_judge = in_array($user->id(), array_column($judges, 'target_id'));
    if (!$user_is_judge) {
      return AccessResult::forbidden()->addCacheTags($judging_session->getCacheTags());
    }

    return $this->accessStartJudging($user, $judging_session);
  }

}
