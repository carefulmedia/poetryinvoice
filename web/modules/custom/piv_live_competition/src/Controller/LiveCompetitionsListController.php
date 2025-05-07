<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Access\AccessResult;
use Drupal\User\UserInterface;
use Drupal\Core\Url;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Returns responses for PIV Live Competition routes.
 */
final class LiveCompetitionsListController extends ControllerBase {

  /**
   * Get the competitions for user id.
   */
  public function getCompetitionIds($user_id) {
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

    return $query->condition($judge_group)
      ->accessCheck(TRUE)
      ->execute();
  }

  /**
   * Check if there is a competition where this user is assigned to.
   */
  public function access(AccountInterface $account, UserInterface $user) {
    $total = count($this->getCompetitionIds($user->id()));
    return AccessResult::allowedIf($total > 0 && $account->id() === $user->id())
      ->cachePerUser()
      ->addCacheableDependency($user)
      ->addCacheTags(['node_list:competition']);
  }

  /**
   * Builds a list of live competitions this user is referenced.
   */
  public function __invoke(UserInterface $user): array {
    $competition_ids = $this->getCompetitionIds($user->id());
    $competitions = $competition_ids
      ? $this->entityTypeManager()->getStorage('node')->loadMultiple($competition_ids)
      : [];

    $links = [];
    foreach ($competitions as $competition) {
      $url = Url::fromRoute('piv_live_competition.score', [
        'node' => $competition->id(),
        'user' => $user->id(),
      ]);
      $links[] = [
        '#title' => $competition->label(),
        '#type' => 'link',
        '#url' => $url,
      ];
    }

    $build = [];
    $build['#cache']['tags'][] = 'node_list:competition';
    $build['content'] = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#title' => $this->t('Live competitions'),
      '#items' => $links,
    ];
    return $build;
  }

}
