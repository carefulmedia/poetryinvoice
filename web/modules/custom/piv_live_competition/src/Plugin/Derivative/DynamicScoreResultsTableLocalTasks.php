<?php

namespace Drupal\piv_live_competition\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;

/**
 * Defines dynamic local tasks.
 */
class DynamicScoreResultsTableLocalTasks extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $streams = \Drupal::service('piv_live_competition.helper')->getStreams();

    foreach ($streams as $value => $label) {
      $id = "piv_live_competition.live_competition_score_results_table_stream.{$value}";
      $this->derivatives[$id] = $base_plugin_definition;
      $this->derivatives[$id]['title'] = $label;
      $this->derivatives[$id]['route_name'] = 'piv_live_competition.live_competition_score_results_table';
      $this->derivatives[$id]['route_parameters']['stream'] = $value;
    }

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
