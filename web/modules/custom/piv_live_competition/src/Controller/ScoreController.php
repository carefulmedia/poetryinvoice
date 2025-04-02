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
   * Builds the response.
   */
  public function __invoke(NodeInterface $node, UserInterface $user) : mixed {
    $score_template = $node->field_score_template->entity;
    if (!$score_template) {
      // This field is required.
      throw new NotFoundHttpException();
    }

    $active_round = $node->field_active_round->value ?? 0;
    if ($active_round <= 0) {
      return ['#markup' => 'Waiting for the first round to be activated'];
    }

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
      '#attached' => [
        'library' => ['piv_live_competition/score-form'],
      ],
    ];

    // Load or create new score entity.
    $score_entity = $this->getScoreEntity($node, $user, $recitation, $judge_type);
    $is_locked = $score_entity->field_locked->value == 1;
    $student_name = $this->helper->getStudentName($recitation);
    $poem_name = $recitation->field_poem?->entity->label();
    $build['student_name'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $student_name,
    ];
    $build['poem'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => $poem_name,
    ];
    $build['epigraph'] = $recitation->field_poem?->entity->field_epigraph?->view(['label' => 'hidden']);
    if ($judge_type == 'accuracy') {
      $build['poem_content'] = $recitation->field_poem?->entity->body?->view(['label' => 'hidden']);
    }

    $total = count($recitations);
    $build['progress'] = [
      '#type' => 'inline_template',
      '#template' => '<div>{{ round }}/{{ total }}</div>',
      '#context' => [
        'round' => $round,
        'total' => $total,
      ],
    ];

    $build['padlock'] = [
      '#markup' => $is_locked
        ? '<svg width="20px" height="20px" fill="#000000" version="1.1" viewBox="0 0 330 330" xml:space="preserve" xmlns="http://www.w3.org/2000/svg"><path d="m65 330h200c8.284 0 15-6.716 15-15v-170c0-8.284-6.716-15-15-15h-15v-45c0-46.869-38.131-85-85-85s-85 38.131-85 85v45h-15c-8.284 0-15 6.716-15 15v170c0 8.284 6.716 15 15 15zm45-245c0-30.327 24.673-55 55-55s55 24.673 55 55v45h-110z"/></svg>'
        : '<svg width="20px" height="20px" fill="#000000" version="1.1" viewBox="0 0 330 330" xml:space="preserve" xmlns="http://www.w3.org/2000/svg"><path d="m15 160c8.284 0 15-6.716 15-15v-60c0-30.327 24.673-55 55-55s55 24.673 55 55v45h-25c-8.284 0-15 6.716-15 15v170c0 8.284 6.716 15 15 15h200c8.284 0 15-6.716 15-15v-170c0-8.284-6.716-15-15-15h-145v-45c0-46.869-38.131-85-85-85s-85 38.131-85 85v60c0 8.284 6.716 15 15 15z"/></svg>',
      '#allowed_tags' => ['svg', 'path'],
    ];

    $recitation_language = $recitation->field_poem?->entity->langcode->value ?? 'en';
    if (!in_array($recitation_language, $judge_languages)) {
      // We only get there if the judge can't score the next recitation
      // yet and the only current recitation is not for the correct
      // language.
      $language_label = in_array('en', $judge_languages)
        ? $this->t('English')
        : $this->t('French');
      $build['form'] = [
        '#type' => 'item',
        '#markup' => $this->t('Waiting for the next @language recitation...', [
          '@language' => $language_label,
        ]),
      ];
    }
    else {
      $can_score_next_recitation = $round < $active_round;
      if ($judge_type == 'accuracy') {
        // Accuracy judge.
        $build['form'] = $this->formBuilder()
          ->getForm('Drupal\piv_live_competition\Form\AccuracyScoreForm', $recitation, $score_entity, $can_score_next_recitation);
      }
      else {
        $build['form'] = $this->formBuilder()
          ->getForm('Drupal\piv_live_competition\Form\PerformanceScoreForm', $recitation, $score_template, $score_entity, $can_score_next_recitation);
      }
    }

    return $build;
  }

}
