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
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\DependencyInjection\ClassResolverInterface;

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
    protected readonly ClassResolverInterface $classResolver,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cache.default'),
      $container->get('piv_live_competition.helper'),
      $container->get('class_resolver'),
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
  public function access(AccountInterface $account, ?NodeInterface $node = NULL, ?UserInterface $user = NULL) {
    if (!$node || !$user) {
      $list_controller = $this->classResolver
        ->getInstanceFromDefinition(LiveCompetitionsListController::class);
      $competition_ids = $list_controller->getCompetitionIds($account->id());
      return AccessResult::allowedIf(count($competition_ids) > 0)
        ->cachePerUser()
        ->addCacheContexts(['url']);
    }
    // Merge all judge and prompter ids in an array.
    $judges_fr = array_column($node->field_accuracy_judge_fr->getValue(), 'target_id');
    $judges_en = array_column($node->field_accuracy_judge_en->getValue(), 'target_id');
    $prompters_fr = array_column($node->field_prompters_fr->getValue(), 'target_id');
    $prompters_en = array_column($node->field_prompters_en->getValue(), 'target_id');
    $performance_judges = [];
    foreach ($node->field_judges->referencedEntities() as $judge_paragraph) {
      if ($judge_paragraph->hasField('field_judge')) {
        $performance_judges[] = $judge_paragraph->field_judge->target_id;
      }
    }
    $all_judges = array_merge($performance_judges, $judges_en, $judges_fr, $prompters_en, $prompters_fr);
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
   * Get the next recitation this user can score or view (for prompters).
   */
  private function getNextRecitation(NodeInterface $node, UserInterface $user, string $type, array $languages) : ?ParagraphInterface {
    $recitations = $this->helper->getRecitationsInOrder($node);

    // Prompters don't score, so they just see the active round recitation.
    if ($type == 'prompter') {
      $active_round = $node->field_active_round->value ?? 0;
      foreach ($recitations as $delta => $recitation) {
        $round = $delta + 1;
        if ($active_round !== NULL && $active_round == $round) {
          // Return the active round recitation regardless of language.
          // The controller will handle showing a waiting message if it's not their language.
          return $recitation;
        }
      }
      return NULL;
    }

    $field = $type == 'performance'
      ? 'field_performance_scores'
      : 'field_accuracy_scores';

    $active_round = $node->field_active_round->value ?? 0;
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
   * Redirect from /live-competition-scoring.
   */
  public function alias() {
    $user_id = $this->currentUser()->getId();

    // Calculate yesterday's date in "Y-m-d" format.
    $yesterday = new DrupalDateTime('yesterday');
    $yesterday_formatted = $yesterday->format('Y-m-d\T00:00:00');

    $query = $this->entityTypeManager()->getStorage('node')->getQuery();
    $query->condition('type', 'competition')
      ->condition('field_winners_announced', $yesterday_formatted, '>')
      ->condition('field_active_round', 0, '>=');
    $query->sort('field_winners_announced', 'ASC');
    $judge_group = $query->orConditionGroup()
      ->condition('field_accuracy_judge_fr', $user_id, 'IN')
      ->condition('field_accuracy_judge_en', $user_id, 'IN')
      ->condition('field_judges.entity:paragraph.field_judge', $user_id);

    $ids = $query->condition($judge_group)
      ->accessCheck(TRUE)
      ->execute();
    return ['#markup' => print_r($ids)];
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
    // Check if user is a prompter first, then accuracy judge, otherwise it is a performance judge.
    $prompters_fr = array_column($node->field_prompters_fr->getValue(), 'target_id');
    $prompters_en = array_column($node->field_prompters_en->getValue(), 'target_id');
    $judges_fr = array_column($node->field_accuracy_judge_fr->getValue(), 'target_id');
    $judges_en = array_column($node->field_accuracy_judge_en->getValue(), 'target_id');

    $is_prompter = in_array($user->id(), array_merge($prompters_fr, $prompters_en));
    $is_accuracy = in_array($user->id(), array_merge($judges_fr, $judges_en));

    if ($is_prompter) {
      $judge_type = 'prompter';
    }
    elseif ($is_accuracy) {
      $judge_type = 'accuracy';
    }
    else {
      $judge_type = 'performance';
    }

    $judge_languages = [];
    if ($judge_type == 'prompter') {
      if (in_array($user->id(), $prompters_fr)) {
        $judge_languages[] = 'fr';
      }
      if (in_array($user->id(), $prompters_en)) {
        $judge_languages[] = 'en';
      }
    }
    elseif ($judge_type == 'accuracy') {
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
  public function __invoke(?NodeInterface $node = NULL, ?UserInterface $user = NULL) : mixed {
    if (!$node || !$user) {
      $user_id = $this->currentUser()->id();
      if (!$user_id) {
        throw new NotFoundHttpException();
      }

      $list_controller = $this->classResolver
        ->getInstanceFromDefinition(LiveCompetitionsListController::class);
      $competition_ids = $list_controller->getCompetitionIds($user_id);
      if (!$competition_ids) {
        throw new NotFoundHttpException();
      }

      $competition_id = reset($competition_ids);
      return $this->redirect('piv_live_competition.score', [
        'node' => $competition_id,
        'user' => $user_id,
      ]);
    }
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
      $build['#attributes']['class'][] = 'is-locked';
      $message_text = $judge_type == 'prompter'
        ? $this->t('The contest will begin shortly.')
        : $this->t('You will be able to start judging once the contest has begun.');
      $build['message'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $message_text,
        '#attributes' => [
          'class' => ['h1', 'text-danger'],
        ],
        '#attached' => [
          'library' => ['piv_live_competition/score-form'],
        ],
      ];
      return $build;
    }

    // Load or create new score entity (not for prompters).
    $score_entity = NULL;
    $is_locked = FALSE;
    if ($judge_type !== 'prompter') {
      $score_entity = $this->getScoreEntity($node, $user, $recitation, $judge_type);
      $is_locked = $score_entity->field_locked->value == 1;
    }

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
    // Show poem content for accuracy judges and prompters.
    if (($judge_type == 'accuracy' || $judge_type == 'prompter') && !$is_locked) {
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

    // Round is the number of scores already created (not applicable for prompters).
    if ($judge_type !== 'prompter') {
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
    }
    else {
      // For prompters, show the current round.
      $build['progress'] = [
        '#type' => 'inline_template',
        '#template' => '<div>{{ "Currently reciting"|t }}</div>',
      ];
      $is_last_recitation = FALSE;
    }

    $build['messages_wrapper'] = [
      '#markup' => '<div data-drupal-messages></div>',
    ];

    // Prompters don't score - show poem content if it's their language, otherwise show waiting page.
    if ($judge_type == 'prompter') {
      $build['#attached']['library'] = ['piv_live_competition/score-form'];
      if (!$can_judge_language) {
        // Not their language - show waiting page with black background.
        $build['#attributes']['class'][] = 'is-locked';
        unset($build['epigraph']);
        unset($build['poem_content']);
        $build['form'] = $this->formBuilder()
          ->getForm('Drupal\piv_live_competition\Form\WaitingPageForm', FALSE, []);
      }
      // If it is their language, no form needed - they just read the poem content.
    }
    elseif ($is_locked || !$score_entity->isNew() || !$can_judge_language) {
      $build['#attributes']['class'][] = 'is-locked';
      $build['#attached']['library'] = ['piv_live_competition/score-form'];
      // Do not print the poem.
      unset($build['epigraph']);
      unset($build['poem_content']);
      $message = $is_last_recitation
        ? [
          '#markup' => $this->t('Thank you for judging the @label! Results will be announced soon.', [
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
    if ($judge_type !== 'prompter' && $is_last_recitation && (!$score_entity->isNew() || !$can_judge_language)) {
      unset($build['form']['navigation']);
    }
    return $build;
  }

  /**
   * Return a generated title.
   */
  public function title(NodeInterface $node, UserInterface $user) {
    $active_round = $node->field_active_round->value ?? 0;
    if ($active_round <= 0) {
      return $node->label();
    }
    [$judge_type, $judge_languages] = $this->getJudgeTypeAndLanguages($node, $user);
    $recitation = $this->getNextRecitation($node, $user, $judge_type, $judge_languages);
    return $recitation
      ? $this->helper->getStudentName($recitation)
      : $node->label();
  }

}
