<?php

namespace Drupal\piv_live_competition;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Service for managing competition banner display logic.
 *
 * This service will handle displaying a call to action banner for Judges/Administrators of a competition.
 * The 'hook_preprocess_page' hook in this module uses this service to print the banner under the header.
 */
class CompetitionBannerService {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager
  ) {}

  /**
   * Get banner data for the current user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @return array|NULL
   *   Render array for the banner, or NULL if no banner should be shown.
   */
  public function getBannerData(AccountInterface $account): ?array {
    if ($account->isAnonymous()) {
      return NULL;
    }

    $user_id = $account->id();
    $competitions = $this->getActiveCompetitionsForToday();

    // If no active competitions today, don't show banner.
    if (empty($competitions)) {
      return NULL;
    }

    // Here we loop through competitions until we find one with a role, if any.
    $role = NULL;
    $relevant_competition = NULL;
    foreach ($competitions as $competition) {
      // Check user's role in this competition.
      // Not to be confused with Drupal roles! This is the role in the context of this service.
      $role = $this->getUserRoleInCompetition($competition, $user_id);
      if ($role) {
        $relevant_competition = $competition;
        break;
      }
    }

    if (!$role || !$relevant_competition) {
      return NULL;
    }

    if ($role === 'admin') {
      return $this->buildAdminBanner($relevant_competition);
    }

    if ($role === 'judge') {
      return $this->buildJudgeBanner($relevant_competition, $user_id);
    }

    return NULL;
  }

  /**
   * Get competitions active for today.
   *
   * We use the contest date to determine which competitions to obtain.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Array of competition node entities.
   */
  protected function getActiveCompetitionsForToday(): array {
    $today = new DrupalDateTime('today');
    $today_start = $today->format('Y-m-d\T00:00:00');
    $today_end = $today->format('Y-m-d\T23:59:59');

    try {
      $query = $this->entityTypeManager->getStorage('node')->getQuery()
        ->condition('type', 'competition')
        ->condition('status', 1)
        ->condition('field_winners_announced', $today_start, '>=')
        ->condition('field_winners_announced', $today_end, '<=')
        ->accessCheck(FALSE)
        ->sort('created', 'DESC');
    } catch (InvalidPluginDefinitionException|PluginNotFoundException $e) {
      return [];
    }

    $nids = $query->execute();

    if (empty($nids)) {
      return [];
    }

    try {
      return $this->entityTypeManager->getStorage('node')->loadMultiple($nids);
    }
    catch (InvalidPluginDefinitionException|PluginNotFoundException $e) {
      return [];
    }
  }

  /**
   * Determine user's role in a competition.
   *
   * Not to be confused with Drupal roles!!! We obtain the "role" for the user in the context of the
   * given competition. e.g. Admin if the user is the Live Competition Admin set in the 'field_live_competition_admin'
   * field, or 'judge' if the user is referenced in any of the judge fields.
   *
   * @param \Drupal\node\NodeInterface $competition
   *   The competition node.
   * @param int|string $user_id
   *   The user ID to check.
   *
   * @return string|NULL
   *   'admin', 'judge', or NULL if no role.
   */
  protected function getUserRoleInCompetition(NodeInterface $competition, int|string $user_id): ?string {
    // Check if user is the competition admin.
    if ($competition->hasField('field_live_competition_admin')) {
      $admin_field = $competition->get('field_live_competition_admin');
      if (!$admin_field->isEmpty()) {
        foreach ($admin_field as $item) {
          if ((int) $item->target_id === (int) $user_id) {
            return 'admin';
          }
        }
      }
    }

    // Check if user is the English accuracy judge.
    if ($competition->hasField('field_accuracy_judge_en')) {
      $judge_en_field = $competition->get('field_accuracy_judge_en');
      if (!$judge_en_field->isEmpty()) {
        foreach ($judge_en_field as $item) {
          if ((int) $item->target_id === (int) $user_id) {
            return 'judge';
          }
        }
      }
    }

    // Check if user is the French accuracy judge.
    if ($competition->hasField('field_accuracy_judge_fr')) {
      $judge_fr_field = $competition->get('field_accuracy_judge_fr');
      if (!$judge_fr_field->isEmpty()) {
        foreach ($judge_fr_field as $item) {
          if ((int) $item->target_id === (int) $user_id) {
            return 'judge';
          }
        }
      }
    }

    // Check if user is in judges paragraph field.
    if ($competition->hasField('field_judges')) {
      $judges_field = $competition->get('field_judges');
      if (!$judges_field->isEmpty()) {
        foreach ($judges_field as $item) {
          $paragraph = $item->entity;
          if ($paragraph && $paragraph->hasField('field_judge')) {
            $judge_field = $paragraph->get('field_judge');
            if (!$judge_field->isEmpty()) {
              foreach ($judge_field as $judge_item) {
                if ((int) $judge_item->target_id === (int) $user_id) {
                  return 'judge';
                }
              }
            }
          }
        }
      }
    }

    return NULL;
  }

  /**
   * Build banner render array for judges.
   *
   * @param \Drupal\node\NodeInterface $competition
   *   The competition node.
   * @param int|string $user_id
   *   The user ID.
   *
   * @return array
   *   Render array for the banner.
   */
  protected function buildJudgeBanner(NodeInterface $competition, int|string $user_id): array {
    $url = Url::fromRoute('piv_live_competition.score', [
      'node' => $competition->id(),
      'user' => $user_id,
    ])->toString();

    return $this->buildBannerRenderArray($competition, 'judge', $url);
  }

  /**
   * Build banner render array for admins.
   *
   * @param \Drupal\node\NodeInterface $competition
   *   The competition node.
   *
   * @return array
   *   Render array for the banner.
   */
  protected function buildAdminBanner(NodeInterface $competition): array {
    $url = Url::fromRoute('piv_live_competition.monitor_dashboard', [
      'node' => $competition->id(),
    ])->toString();

    return $this->buildBannerRenderArray($competition, 'admin', $url);
  }

  /**
   * Build the banner render array.
   *
   * @param \Drupal\node\NodeInterface $competition
   *   The competition node.
   * @param string $banner_type
   *   'judge' or 'admin'.
   * @param string $url
   *   The URL to link to.
   *
   * @return array
   *   Render array for the banner.
   */
  protected function buildBannerRenderArray(
    NodeInterface $competition,
    string $banner_type,
    string $url,
  ): array {
    $competition_name = $competition->getTitle();

    // Calculate seconds until midnight for cache max-age.
    $now = new DrupalDateTime('now');
    $midnight = new DrupalDateTime('tomorrow');
    $seconds_until_midnight = $midnight->getTimestamp() - $now->getTimestamp();

    return [
      '#theme' => 'competition_banner',
      '#competition_name' => $competition_name,
      '#banner_type' => $banner_type,
      '#url' => $url,
      '#cache' => [
        'keys' => ['competition_banner', \Drupal::currentUser()->id(), date('Y-m-d')],
        'contexts' => ['user', 'url.path'],
        'tags' => [
          'node:' . $competition->id(),
          'user:' . \Drupal::currentUser()->id(),
        ],
        'max-age' => $seconds_until_midnight,
      ],
    ];
  }

}
