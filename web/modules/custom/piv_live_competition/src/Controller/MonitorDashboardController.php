<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;

/**
 * Returns responses for PIV Live Competition routes.
 */
final class MonitorDashboardController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function __invoke(NodeInterface $node): array {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => 'Dashboard',
    ];

    return $build;
  }

}
