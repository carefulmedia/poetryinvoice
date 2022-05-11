<?php

namespace Drupal\piv_mail\Plugin\PivMail;

use Drupal\piv_mail\PivMailPluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Base class for "node created" plugins.
 */
abstract class NodeCreatedBase extends PivMailPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, ?string $langcode = NULL) {
    $form = parent::buildConfigurationForm($form, $form_state, $langcode);
    // Remove the "to" form element.
    $form['to'] = ['#markup' => '"TO" value is not configurable.'];
    $form['to_more']['#access'] = FALSE;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    $configuration['en']['to_more'] = 'info@poetryinvoice.com';
    $configuration['fr']['to_more'] = 'info@lesvoixdelapoesie.com';
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

}
