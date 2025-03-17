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
    $judges_fr = array_column($node->field_accuracy_judge_fr->getValue(), 'target_id');
    $judges_en = array_column($node->field_accuracy_judge_en->getValue(), 'target_id');
    return AccessResult::allowedIf(in_array($user->id(), array_merge($judges_en, $judges_fr)));
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
