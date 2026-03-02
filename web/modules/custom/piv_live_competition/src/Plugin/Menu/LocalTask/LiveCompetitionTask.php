<?php

namespace Drupal\piv_live_competition\Plugin\Menu\LocalTask;

use Drupal\Core\Menu\LocalTaskDefault;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\Request;
use Drupal\piv_live_competition\Controller\LiveCompetitionsListController;

/**
 * Defines a local task plugin with a dynamic title.
 */
class LiveCompetitionTask extends LocalTaskDefault {
  use StringTranslationTrait;

  /**
   * Get the competition banner service.
   */
  protected function getCompetitionBannerService() {
    return \Drupal::service('piv_live_competition.banner_service');
  }

  /**
   * The the node storage.
   */
  protected function getNodeStorage() {
    return \Drupal::entityTypeManager()->getStorage('node');
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(?Request $request = NULL) {
    $prompt_title = $this->t('Team Regionals Prompting');
    $judge_title = $this->t('Team Regionals Judging');

    if ($request && $user = $request->attributes->get('user')) {
      $competition_banner_service = $this->getCompetitionBannerService();

      $controller = \Drupal::service('class_resolver')
        ->getInstanceFromDefinition(LiveCompetitionsListController::class);
      $competition_ids = $controller->getCompetitionIds($user->id());
      $node_storage = $this->getNodeStorage();
      foreach ($competition_ids as $competition_id) {
        if ($competition = $node_storage->load($competition_id)) {
          $role = $competition_banner_service->getUserRoleInCompetition($competition, $user->id());
          if ($role == 'judge') {
            return $judge_title;
          }
          if ($role == 'prompter') {
            return $prompt_title;
          }
        }
      }
    }
    return $judge_title;
  }

}
