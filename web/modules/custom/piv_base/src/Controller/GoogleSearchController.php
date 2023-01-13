<?php

namespace Drupal\piv_base\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for PIV Base routes.
 */
class GoogleSearchController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build($route_language) {
    $cx = $route_language == 'en' ? '94f5aa845dde74f98' : '53a9e1319434b96c4';
    $build['script'] = [
      '#markup' => "<script async src='https://cse.google.com/cse.js?cx={$cx}'></script>",
      '#allowed_tags' => ['script'],
    ];
    $build['search'] = [
      '#markup' => '<div class="gcse-search"></div>',
    ];
    return $build;
  }

}
