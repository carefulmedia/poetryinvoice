<?php

namespace Drupal\piv_contest_judging_session\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\piv_contest_competition\CompetitionInterface;
use Drupal\piv_contest_judging_session\Entity\JudgingSession;
use Drupal\piv_contest_judging_session\Service\JudgeSession;
use Drupal\user\Entity\User;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class JudgingSessionController extends ControllerBase {

  protected $entityTypeManager;

  protected $entityFormBuilder;

  protected $judgeSessionService;

  public function __construct(EntityFormBuilderInterface $entity_form_builder, EntityTypeManagerInterface $entity_type_manager, JudgeSession $judgeSession) {
    $this->entityFormBuilder = $entity_form_builder;
    $this->entityTypeManager = $entity_type_manager;
    $this->judgeSessionService = $judgeSession;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.form_builder'),
      $container->get('entity_type.manager'),
      $container->get('piv_contest_judging_session.service.judge_session')
    );
  }

  public function add(
    CompetitionInterface $competition,
    Request $request
  ): array {
    $current_user = \Drupal::currentUser();
    $stream_id = $request->query->get('stream');
    $stream = \Drupal::entityTypeManager()->getStorage('paragraph')->load($stream_id);

    $title = "{$competition->label()} {$stream->field_label->value} judging session";

    //  session_management_ui
    $session = $this->entityTypeManager->getStorage('judging_session')->create([
      'field_competition' => $competition->id(),
      'user' => $current_user->id(),
      'field_stream' => $stream,
      'title' => $title,
      'bundle' => 'default',
    ]);

    $form = $this->entityFormBuilder
      ->getForm($session, 'session_management_ui');
    $form['revision_information']['#access'] = FALSE;
    return $form;
  }

  public function startJudging(User $user): array {
    /** @var \Drupal\Core\Entity\EntityInterface[] $sessions */
    $sessions = $this->getReadyJudgingSessions($user);
    $rows = [];
    foreach ($sessions as $session) {
      $link =  Link::fromTextAndUrl(t('Judge now'), Url::fromRoute('piv_contest_judging_session.judge_recitations', [
        'session' => $session->id(),
        'user' => $user->id(),
      ]))->toRenderable();

      if ($this->judgeSessionService->isSessionEvaluatedByJudge($session, $user)) {
        $link['#attributes']['class'][] = 'disabled';
      }

      $rows[] = [
        $session->id(),
        new FormattableMarkup('@evaluated/@total', [
          '@evaluated' => $this->judgeSessionService->numberOfRecitationsEvaluatedByJudge($session, $user),
          '@total' => $this->judgeSessionService->totalNumberOfRecitations($session, $user),
        ]),
        [
          'data' => $link,
        ],
      ];
    }

    $response['table'] = [
      '#type' => 'table',
      '#header' => [
        t('Session Id'),
        t('Recitations evaluated'),
        t('Evaluate'),
      ],
      '#rows' => $rows,
    ];

    $uri = Url::fromUserInput('/judge-pages/how-judge-our-online-contests');

    $response['link'] = Link::fromTextAndUrl(t('How to judge the Online Contest'), $uri)->toRenderable();

    return $response;
  }

  public function accessStartJudging(User $user): AccessResult {
    $entities = $this->getReadyJudgingSessions($user);

    if (count($entities) == 0) {
      return AccessResult::neutral();
    }

    $tags = [];
    foreach ($entities as $entity) {
      $tags = array_merge($tags, $entity->getCacheTags());
    }

    return AccessResult::allowedIfHasPermission($user, 'judge a judging session')->addCacheTags($tags);
  }

  private function getReadyJudgingSessions(User $user): array {
    $judging_session_manager = $this->entityTypeManager->getStorage('judging_session');

    $query = $judging_session_manager->getQuery();

    $group = $query->orConditionGroup()
      ->condition('field_english_judge.target_id', $user->id(), '=')
      ->condition('field_french_judge.target_id', $user->id(), '=');

    $ids =  $query
      ->condition('field_ready_for_scoring', TRUE)
      ->condition($group)
      ->execute();

    return $judging_session_manager->loadMultiple($ids);
  }


  public function judgeSession(User $user, JudgingSession $session): array {
    return [
      '#markup' => 'change me',
    ];
  }

  public function accessJudgeSession(User $user, JudgingSession $session): AccessResult {
    // @TODO change this.
    return AccessResult::allowed();
  }
}
