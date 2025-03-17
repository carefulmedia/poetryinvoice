<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Node\NodeInterface;
use Drupal\User\UserInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/**
 * Returns responses for PIV Live Competition routes.
 */
final class ScoreController extends ControllerBase {

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
      if ($recitation->field_contest_association->target_id != $node->id()) {
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
   * Builds the response.
   */
  public function __invoke(NodeInterface $node, UserInterface $user, ?Paragraph $recitation = NULL): array {
    $form = $this->formBuilder()
      ->getForm('Drupal\piv_live_competition\Form\ScoreForm');

    $build['form'] = $form;
    return $build;
  }

}
