<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Node\NodeInterface;
use Drupal\User\UserInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\piv_live_competition\Helper;
use Drupal\piv_contest_score\ScoreInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Url;

/**
 * Returns responses for PIV Live Competition routes.
 */
final class ScoreController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected readonly CacheBackendInterface $cache,
    protected readonly Helper $helper,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cache.default'),
      $container->get('piv_live_competition.helper')
    );
  }

  /**
   * Custom access checks.
   *
   * Node doesn't need to be checked since the route controller already
   * check the node type, we need to check if the user from the route
   * is assigned as a judge to the node in the route. Recitation also
   * is already checked in the routing file.
   */
  public function access(AccountInterface $account, NodeInterface $node, UserInterface $user) {
    // Merge all judge ids in an array.
    $judges_fr = array_column($node->field_accuracy_judge_fr->getValue(), 'target_id');
    $judges_en = array_column($node->field_accuracy_judge_en->getValue(), 'target_id');
    $performance_judges = [];
    foreach ($node->field_judges->referencedEntities() as $judge_paragraph) {
      if ($judge_paragraph->hasField('field_judge')) {
        $performance_judges[] = $judge_paragraph->field_judge->target_id;
      }
    }
    $all_judges = array_merge($performance_judges, $judges_en, $judges_fr);
    $user_is_judge = in_array($user->id(), $all_judges);

    // The current logged in user accessing that url is the same from
    // the "user" parameter in the url. The user is not trying to access
    // the live-competition url for another user.
    $user_is_accessing_own_page = $account->id() === $user->id();
    return AccessResult::allowedIf($user_is_accessing_own_page && $user_is_judge)
      ->cachePerUser()
      ->addCacheableDependency($node)
      ->addCacheableDependency($user);
  }

  /**
   * Get the next recitation this user can score.
   */
  private function getNextRecitation(NodeInterface $node, UserInterface $user, string $type, array $languages) : ?ParagraphInterface {
    $recitations = $this->helper->getRecitationsInOrder($node);
    $field = $type == 'performance'
      ? 'field_performance_scores'
      : 'field_accuracy_scores';

    $active_round = $node->field_active_round->value ?? NULL;
    // Iterate in all recitations in order, check for a recitation with
    // no score where this user is the judge.
    foreach ($recitations as $delta => $recitation) {
      $recitation_language = $recitation->field_poem?->entity->langcode->value ?? 'en';

      $round = $delta + 1;
      // Return active round recitation at most, even if complete or not
      // correct language.
      if ($active_round !== NULL && $active_round == $round) {
        return $recitation;
      }
      // Skip recitation not in the correct language.
      if (!in_array($recitation_language, $languages)) {
        continue;
      }
      foreach ($recitation->{$field}->referencedEntities() as $score) {
        if ($score->judge->target_id == $user->id()) {
          // Check next recitation.
          continue 2;
        }
      }
      // Checked all scores on this recitation and nothing was found, so
      // this is a recitation with no score.
      return $recitation;
    }
    return NULL;
  }

  /**
   * Get all scores for this competition created by this user.
   */
  private function getScores(NodeInterface $node, UserInterface $user) {
    return $this->entityTypeManager()->getStorage('score')->loadByProperties([
      'judge' => $user->id(),
      'field_competition' => $node->id(),
    ]);
  }

  /**
   * Return a score entity.
   *
   * Load from the recitation or create new, $type is 'accuracy' or
   * 'performance'.
   */
  private function getScoreEntity(NodeInterface $node, UserInterface $user, ParagraphInterface $recitation, string $type) : ScoreInterface {
    // Load a score entity or create a new one.
    $field = $type == 'performance'
      ? 'field_performance_scores'
      : 'field_accuracy_scores';
    foreach ($recitation->{$field}->referencedEntities() as $score) {
      if ($score->judge->target_id == $user->id()) {
        return $score;
      }
    }

    $student_name = $this->helper->getStudentName($recitation);
    $judge_name = $user->getDisplayName();
    $competition = $node->label();
    $suffix = $type == 'performance' ? '' : ' (accuracy)';
    return $this->entityTypeManager()->getStorage('score')->create([
      'judge' => $user->id(),
      'bundle' => $type == 'performance'
        ? 'live_competition'
        : 'accuracy_live_competition',
      'score_template' => $node->field_score_template->target_id,
      'field_competition' => $node->id(),
      'title' => "{$competition}: {$judge_name} judging {$student_name}{$suffix}",
    ]);
  }

  /**
   * Get key by id from the sorted recitation list.
   */
  private function getKeyById($recitations, $id) {
    foreach ($recitations as $delta => $recitation) {
      if ($recitation->id() == $id) {
        return $delta;
      }
    }
    return 0;
  }

  /**
   * Return the active round as a json response.
   */
  public function activeRound(NodeInterface $node) {
    return new JsonResponse([
      'active_round' => $node->field_active_round->value ?? 0,
    ]);
  }

  /**
   * Return the Judge Type and Languages.
   */
  private function getJudgeTypeAndLanguages(NodeInterface $node, UserInterface $user) {
    // Check if user is an accuracy judge, otherwise it is a performance
    // judge.
    $judges_fr = array_column($node->field_accuracy_judge_fr->getValue(), 'target_id');
    $judges_en = array_column($node->field_accuracy_judge_en->getValue(), 'target_id');
    $judge_type = in_array($user->id(), array_merge($judges_fr, $judges_en))
      ? 'accuracy'
      : 'performance';

    $judge_languages = [];
    if ($judge_type == 'accuracy') {
      if (in_array($user->id(), $judges_fr)) {
        $judge_languages[] = 'fr';
      }
      if (in_array($user->id(), $judges_en)) {
        $judge_languages[] = 'en';
      }
    }
    else {
      $judge_languages[] = $user->preferred_langcode->value ?? 'en';
    }

    return [$judge_type, $judge_languages];
  }

  /**
   * Builds the response.
   */
  public function __invoke(NodeInterface $node, UserInterface $user) : mixed {
    $score_template = $node->field_score_template->entity;
    if (!$score_template) {
      // This field is required.
      throw new NotFoundHttpException();
    }

    [$judge_type, $judge_languages] = $this->getJudgeTypeAndLanguages($node, $user);
    $recitation = $this->getNextRecitation($node, $user, $judge_type, $judge_languages);
    if (!$recitation) {
      $this->messenger()->addMessage('There are no more recitations to judge.');
      return $this->redirect('piv_live_competition.live_competition_list', [
        'user' => $user->id(),
      ]);
    }

    // Key starts at 0.
    $recitations = $this->helper->getRecitationsInOrder($node);
    $key = $this->getKeyById($recitations, $recitation->id());
    // This recitation's round.
    $round = $key + 1;

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['score-wrapper'],
        'data-endpoint' => Url::fromRoute('piv_live_competition.api_round', [
          'node' => $node->id(),
        ])->toString(),
        'data-round' => $round,
      ],
    ];

    $active_round = $node->field_active_round->value ?? 0;
    if ($active_round <= 0) {
      $build['#attributes']['data-round'] = $active_round;
      $build['message'] = [
        '#markup' => 'You will be able to start judging once the contest has begun.',
        '#attached' => [
          'library' => ['piv_live_competition/score-form'],
        ],
      ];
      return $build;
    }

    // Load or create new score entity.
    $score_entity = $this->getScoreEntity($node, $user, $recitation, $judge_type);
    $is_locked = $score_entity->field_locked->value == 1;
    $poet = $recitation->field_poem?->entity->getOwner()?->getDisplayName();
    $poem_name = $recitation->field_poem?->entity->label();
    $build['school'] = [
      '#type' => 'inline_template',
      '#template' => '<p>{{ school }}</p>',
      '#context' => [
        'school' => $recitation->getOwner()?->field_school->entity?->label(),
      ],
    ];
    $build['poem'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $poem_name,
    ];
    $build['poet'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => $poet,
    ];
    $build['epigraph'] = $recitation->field_poem?->entity->field_epigraph?->view(['label' => 'hidden']);
    if ($judge_type == 'accuracy' && !$is_locked) {
      $build['poem_content'] = $recitation->field_poem?->entity->body?->view(['label' => 'hidden']);
    }

    // Count only the recitations that this judge can judge based on the
    // language.
    $total = 0;
    // We can score the next recitation if the recitation in the foreach
    // iteration is beyond the active round and we can score it.
    $can_score_next_recitation = FALSE;
    foreach ($recitations as $i => $r) {
      $recitation_language = $r->field_poem?->entity->langcode->value ?? 'en';
      if (in_array($recitation_language, $judge_languages)) {
        $total++;
        if ($i >= $round && $i < $active_round) {
          $can_score_next_recitation = TRUE;
        }
      }
    }

    $recitation_language = $recitation->field_poem?->entity->langcode->value ?? 'en';
    $can_judge_language = in_array($recitation_language, $judge_languages);

    // Round is the number of scores already created.
    $round = count($this->getScores($node, $user)) + ($is_locked ? 0 : 1);
    $is_last_recitation = $can_judge_language ? $round == $total : $round > $total;
    if ($can_judge_language) {
      $build['progress'] = [
        '#type' => 'inline_template',
        '#template' => '<div>{{ round }}/{{ total }}</div>',
        '#context' => [
          'round' => $round,
          'total' => $total,
        ],
      ];
    }
    else {
      $build['progress'] = [
        '#type' => 'inline_template',
        '#template' => '<div>{{ "Currently reciting"|t }}</div>',
      ];
    }
    $build['messages_wrapper'] = [
      '#markup' => '<div data-drupal-messages></div>',
    ];

    if ($is_locked || !$score_entity->isNew() || !$can_judge_language) {
      $build['#attributes']['class'][] = 'is-locked';
      $build['#attached']['library'] = ['piv_live_competition/score-form'];
      // Do not print the poem.
      unset($build['epigraph']);
      unset($build['poem_content']);
      $message = [];
      $message = $is_last_recitation
        ? [
          '#markup' => $this->t('Thank you for judging the @label contest! Results will be announced soon.', [
            '@label' => $node->label(),
          ]),
        ]
        : [];
      $build['form'] = $this->formBuilder()
        ->getForm('Drupal\piv_live_competition\Form\WaitingPageForm', $can_score_next_recitation, $message);
    }
    else {
      if ($judge_type == 'accuracy') {
        // Accuracy judge.
        $build['form'] = $this->formBuilder()
          ->getForm('Drupal\piv_live_competition\Form\AccuracyScoreForm', $recitation, $score_entity, $can_score_next_recitation, $is_last_recitation);
      }
      else {
        $build['form'] = $this->formBuilder()
          ->getForm('Drupal\piv_live_competition\Form\PerformanceScoreForm', $recitation, $score_template, $score_entity, $can_score_next_recitation, $is_last_recitation);
      }
    }
    // Last recitation and already submitted (complete).
    if ($is_last_recitation && (!$score_entity->isNew() || !$can_judge_language)) {
      unset($build['form']['navigation']);
    }
    return $build;
  }

  /**
   * Return a generated title.
   */
  public function title(NodeInterface $node, UserInterface $user) {
    [$judge_type, $judge_languages] = $this->getJudgeTypeAndLanguages($node, $user);
    $recitation = $this->getNextRecitation($node, $user, $judge_type, $judge_languages);
    return $recitation
      ? $this->helper->getStudentName($recitation)
      : $this->t('Live Competition Scoring');
  }

}
