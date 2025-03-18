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

/**
 * Returns responses for PIV Live Competition routes.
 */
final class ScoreController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected readonly CacheBackendInterface $cache,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cache.default')
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
   * Get a list of recitations in the correct sort order.
   */
  private function getRecitationsInOrder($node) {
    $cid = "piv_live_competition:recitations_in_order:{$node->id()}";
    if ($cache = $this->cache->get($cid)) {
      return $cache->data;
    }

    $team_regionals = $this->entityTypeManager()->getStorage('node')
      ->loadByProperties([
        'type' => 'team_regionals_entry',
        'field_contest_association' => $node->id(),
      ]);
    $entries = [];
    foreach ($team_regionals as $team_regional) {
      $entries = array_merge($entries, $team_regional->field_tr_student->referencedEntities());
    }

    // Sort by order, if its the same value then use the id, if no value
    // is set then its infinite (push to last).
    usort($entries, function ($a, $b) {
      $a_order = $a->field_recitation_order->value ?? INF;
      $b_order = $b->field_recitation_order->value ?? INF;
      if ($a_order == $b_order) {
        return $a->id() <=> $b->id();
      }
      return $a_order <=> $b_order;
    });

    $tags = ['paragraph_list:tr_student'];
    $this->cache->set($cid, $entries, CacheBackendInterface::CACHE_PERMANENT, $tags);
    return $entries;
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
    $recitations = $this->getRecitationsInOrder($node);
    if (!$recitation) {
      $recitation = count($recitations) ? reset($recitations) : NULL;
    }
    $build = [];

    if (!$recitation) {
      $build['empty'] = [
        '#markup' => $this->t('No recitations for this competition yet.'),
      ];
      return $build;
    }

    $student_name = $recitation->field_stage_name->value == 1 && !empty($recitation->field_student_name_1)
      ? $recitation->field_student_name_1->value
      : $recitation->field_legal_name->value;

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

    $build['form'] = $this->formBuilder()
      ->getForm('Drupal\piv_live_competition\Form\ScoreForm');

    $build['navigation'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['score-controller__navigation'],
      ],
    ];
    $previous_recitation = $recitations[$key - 1] ?? NULL;
    if ($previous_recitation) {
      // Previous.
      $build['navigation']['previous'] = [
        '#type' => 'link',
        '#url' => Url::fromRoute('piv_live_competition.score', [
          'node' => $node->id(),
          'user' => $user->id(),
          'recitation' => $previous_recitation->id(),
        ]),
        '#title' => $this->t('Previous'),
        '#attributes' => [
          'class' => [
            'button',
            'score-controller__navigation__previous',
          ],
        ],
      ];
    }

    $next_recitation = $recitations[$key + 1] ?? NULL;
    if ($key < $total - 1) {
      // Next.
      $build['navigation']['next'] = [
        '#type' => 'link',
        '#url' => Url::fromRoute('piv_live_competition.score', [
          'node' => $node->id(),
          'user' => $user->id(),
          'recitation' => $next_recitation->id(),
        ]),
        '#title' => $this->t('Next'),
        '#attributes' => [
          'class' => [
            'button',
            'score-controller__navigation__next',
          ],
        ],
      ];
    }
    return $build;
  }

}
