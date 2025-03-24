<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Node\NodeInterface;
use Drupal\User\UserInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\piv_live_competition\Helper;

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
  public function access(AccountInterface $account, NodeInterface $node, UserInterface $user, ?Paragraph $recitation = NULL) {
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

    // Check if the recitation references the competition node from the
    // url.
    $recitation_is_valid = TRUE;
    if ($recitation) {
      // Controller already check the paragraph type, just check the
      // reference field.
      if ($recitation->getParentEntity()?->field_contest_association->target_id != $node->id()) {
        $recitation_is_valid = FALSE;
      }
    }

    // The current logged in user accessing that url is the same from
    // the "user" parameter in the url. The user is not trying to access
    // the live-competition url for another user.
    $user_is_accessing_own_page = $account->id() === $user->id();
    return AccessResult::allowedIf($user_is_accessing_own_page && $recitation_is_valid && $user_is_judge)
      ->cachePerUser()
      ->addCacheableDependency($node)
      ->addCacheableDependency($user);
  }

  /**
   * Return a score entity.
   *
   * Load from the recitation or create new, $type is 'accuracy' or
   * 'performance'.
   */
  private function getScoreEntity($node, $user, $recitation, $type) {
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
   * Builds the response.
   */
  public function __invoke(NodeInterface $node, UserInterface $user, ?Paragraph $recitation = NULL): array {
    $score_template = $node->field_score_template->entity;
    if (!$score_template) {
      // This field is required.
      throw new NotFoundHttpException();
    }

    $build = [];
    $recitations = $this->helper->getRecitationsInOrder($node);
    if (!$recitation) {
      $recitation = count($recitations) ? reset($recitations) : NULL;
    }

    if (!$recitation) {
      $build['empty'] = [
        '#markup' => $this->t('No recitations for this competition yet.'),
      ];
      return $build;
    }

    // Check if user is an accuracy judge, otherwise it is a performance
    // judge.
    $judges_fr = array_column($node->field_accuracy_judge_fr->getValue(), 'target_id');
    $judges_en = array_column($node->field_accuracy_judge_en->getValue(), 'target_id');
    $judge_type = in_array($user->id(), array_merge($judges_fr, $judges_en))
      ? 'accuracy'
      : 'performance';

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
    $build['poem_content'] = $recitation->field_poem?->entity->body?->view(['label' => 'hidden']);

    // Key starts at 0.
    $key = $this->getKeyById($recitations, $recitation->id());
    $total = count($recitations);
    $build['progress'] = [
      '#type' => 'inline_template',
      '#template' => '<div>{{ position }}/{{ total }}</div>',
      '#context' => [
        'position' => $key + 1,
        'total' => $total,
      ],
    ];

    $build['padlock'] = [
      '#markup' => $is_locked
        ? '<svg width="20px" height="20px" fill="#000000" version="1.1" viewBox="0 0 330 330" xml:space="preserve" xmlns="http://www.w3.org/2000/svg"><path d="m65 330h200c8.284 0 15-6.716 15-15v-170c0-8.284-6.716-15-15-15h-15v-45c0-46.869-38.131-85-85-85s-85 38.131-85 85v45h-15c-8.284 0-15 6.716-15 15v170c0 8.284 6.716 15 15 15zm45-245c0-30.327 24.673-55 55-55s55 24.673 55 55v45h-110z"/></svg>'
        : '<svg width="20px" height="20px" fill="#000000" version="1.1" viewBox="0 0 330 330" xml:space="preserve" xmlns="http://www.w3.org/2000/svg"><path d="m15 160c8.284 0 15-6.716 15-15v-60c0-30.327 24.673-55 55-55s55 24.673 55 55v45h-25c-8.284 0-15 6.716-15 15v170c0 8.284 6.716 15 15 15h200c8.284 0 15-6.716 15-15v-170c0-8.284-6.716-15-15-15h-145v-45c0-46.869-38.131-85-85-85s-85 38.131-85 85v60c0 8.284 6.716 15 15 15z"/></svg>',
      '#allowed_tags' => ['svg', 'path'],
    ];

    $previous_recitation = $recitations[$key - 1] ?? NULL;
    $previous = $previous_recitation
      ? Url::fromRoute('piv_live_competition.score', [
        'node' => $node->id(),
        'user' => $user->id(),
        'recitation' => $previous_recitation->id(),
      ])
      : NULL;

    $next_recitation = $recitations[$key + 1] ?? NULL;
    $next = $next_recitation
      ? Url::fromRoute('piv_live_competition.score', [
        'node' => $node->id(),
        'user' => $user->id(),
        'recitation' => $next_recitation->id(),
      ])
      : NULL;

    if ($judge_type == 'accuracy') {
      // Accuracy judge.
      $build['form'] = $this->formBuilder()
        ->getForm('Drupal\piv_live_competition\Form\AccuracyScoreForm', $recitation, $score_entity, $previous, $next);
    }
    else {
      $build['form'] = $this->formBuilder()
        ->getForm('Drupal\piv_live_competition\Form\PerformanceScoreForm', $recitation, $score_template, $score_entity, $previous, $next);
    }

    return $build;
  }

}
