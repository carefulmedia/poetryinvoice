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
use Drupal\piv_contest_competition\CompetitionInterface;
use Drupal\piv_contest_judging_session\Entity\JudgingSession;
use Drupal\piv_contest_judging_session\Service\JudgeSession;
use Drupal\piv_contest_recitation\Entity\Recitation;
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
    $stream = $this->entityTypeManager->getStorage('paragraph')->load($stream_id);

    $title = "{$competition->label()} {$stream->field_label->value} judging session";
    $judging_session = $this->entityTypeManager->getStorage('judging_session')->create([
      'field_competition' => $competition->id(),
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
    foreach ($judging_sessions as $judging_session) {
      $url = Url::fromRoute('piv_contest_judging_session.read_poems', [
        'judging_session' => $judging_session->id(),
        'user' => $user->id(),
      ]);
      // If user have read all the poems we send them directly to
      // the correct page.
      if ($this->judgeSessionService->numberOfRecitationReadByJudge($judging_session, $user) === $this->judgeSessionService->totalNumberOfRecitations($judging_session, $user)) {
        $url = Url::fromRoute('piv_contest_judging_session.judge_recitations', [
          'judging_session' => $judging_session->id(),
          'user' => $user->id(),
        ]);
      }
      $link = Link::fromTextAndUrl($this->t('Judge now'), $url)->toRenderable();

      if ($this->judgeSessionService->isSessionEvaluatedByJudge($judging_session, $user)) {
        $link['#attributes']['class'][] = 'disabled';
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

    $response['link'] = Link::fromTextAndUrl($this->t('How to judge the Online Contest'), $uri)->toRenderable();

    return $response;
  }

  /**
   * Check if user can access the judging page.
   */
  public function accessStartJudging(User $user): AccessResult {
    $entities = $this->getReadyJudgingSessions($user);
    $tags = ['judging_session_list'];
    return $entities
      ? AccessResult::allowed()->addCacheTags($tags)
      : AccessResult::neutral()->addCacheTags($tags);
  }

  /**
   * Page for a judge to read the poems.
   */
  public function readPoems(User $user, JudgingSession $judging_session): array {
    $recitation = $this->judgeSessionService->nextRecitationToRead($judging_session, $user);
    if (!$recitation) {
      return [
        '#markup' => $this->t('No recitations to read'),
      ];
    }

    $session_id = $judging_session->id();
    $recitation_id = $recitation->id();

    $poem = $recitation->field_poem->entity;
    $build = [
      '#theme' => 'recitation_read_recitations',
    ];
    $build['back'] = [
      '#markup' => Link::fromTextAndUrl('Back to all sessions', Url::fromRoute('piv_contest_judging_session.start_judging', [
        'user' => $user->id(),
      ]))->toString(),
    ];
    $build['title'] = ['#markup' => $poem->title->value];
    $build['description'] = ['#markup' => $poem->body->value];
    $build['video'] = $recitation->field_recitation_video->view([
      'type' => 'entity_reference_entity_view',
      'label' => 'hidden',
    ]);
    $build['recitation_reads'] = [
      '#markup' => $this->t('Poems read: @read/@total', [
        '@read' => $this->judgeSessionService->numberOfRecitationReadByJudge($judging_session, $user),
        '@total' => $this->judgeSessionService->totalNumberOfRecitations($judging_session, $user),
      ]),
    ];

    if ($this->judgeSessionService->numberOfRecitationReadByJudge($judging_session, $user) === $this->judgeSessionService->totalNumberOfRecitations($judging_session, $user)) {
      $build['read_next'] = [
        '#markup' => Link::fromTextAndUrl($this->t('Start judging'), Url::fromRoute('piv_contest_judging_session.judge_recitations', [
          'user' => $user->id(),
          'judging_session' => $judging_session->id(),
        ]))->toString(),
      ];
    }
    else {
      $url = Url::fromRoute('piv_contest_judging_session.mark_recitation_as_read', [
        'judging_session' => $session_id,
        'user' => $user->id(),
        'recitation' => $recitation_id,
      ]);
      $token = $this->tokenGenerator->get($url->getInternalPath());
      $url->setOptions(['query' => ['token' => $token]]);

      $build['read_next'] = [
        '#markup' => Link::fromTextAndUrl('Read next Poem', $url)->toString(),
      ];
    }

    $build['read_later'] = [
      '#markup' => Link::fromTextAndUrl('Read poems later', Url::fromRoute('piv_contest_judging_session.start_judging', [
        'user' => $user->id(),
      ]))->toString(),
    ];

    return $build;
  }

  /**
   * Endpoint to mark recitation as read by a judge.
   */
  public function markRecitationAsRead(User $user, JudgingSession $judging_session, Recitation $recitation): RedirectResponse {
    $this->judgeSessionService->markRecitationAsRead($judging_session, $user, $recitation);
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
    // Not allowed to judge if user have not read the recitation yet.
    if ($this->judgeSessionService->numberOfRecitationReadByJudge($judging_session, $user) !== $this->judgeSessionService->totalNumberOfRecitations($judging_session, $user)) {
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
      $message = $this->t('You finished judging a round of recitations.');
    }
    $build['form'] = $this->formBuilder
      ->getForm('Drupal\piv_contest\Form\ScoreForm', $recitation, $judging_session, $destination, $message);
    $build['judge_later'] = Link::fromTextAndUrl($this->t('Judge later'), $start_judging_url)->toRenderable();
    // Same link as above.
    $build['back'] = Link::fromTextAndUrl($this->t('Back to all sessions'), $start_judging_url)->toRenderable();
    $build['recitation_evaluated'] = [
      '#markup' => $this->t('Recitation evaluated: @evaluated / @count', [
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
    $ready_for_scoring = (BOOL) $judging_session->field_ready_for_scoring->value;
    $judges = array_merge($judging_session->field_english_judge->getValue(), $judging_session->field_french_judge->getValue());
    $user_is_judge = in_array($user->id(), array_column($judges, 'target_id'));
    return AccessResult::allowedIf($ready_for_scoring && $user_is_judge)
      ->addCacheTags($judging_session->getCacheTags());
  }

}
