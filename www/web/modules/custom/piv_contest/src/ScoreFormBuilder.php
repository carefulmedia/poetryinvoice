<?php

namespace Drupal\piv_contest;

use Drupal\piv_contest_score_template\ScoreTemplateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
 
/**
 * ScoreFormBuilder service.
 */
class ScoreFormBuilder {

  /**
   * The plugin.manager.score_form service.
   *
   * @var \Drupal\piv_contest\ScoreFormPluginManager
   */
  protected $pluginManagerScoreForm;

  /**
   * The entity_type.manager service.
   *
   * @var \Drupal\example\ExampleInterface
   */
  protected $entityTypeManager;

  /**
   * The current logged in user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a ScoreFormBuilder object.
   *
   * @param \Drupal\piv_contest\ScoreFormPluginManager $plugin_manager_score_form
   *   The plugin.manager.score_form service.
   * @param \Drupal\piv_contest\EntityTypeManager $entity_type_manager
   *   The entity_type.manager service.
   */
  public function __construct(ScoreFormPluginManager $plugin_manager_score_form, EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $current_user) {
    $this->pluginManagerScoreForm = $plugin_manager_score_form;
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  /**
   * Get the plugin instance given a score_template.
   */
  private function getInstance(ScoreTemplateInterface $score_template) {
    $bundle = $score_template->bundle();
    $definitions = $this->pluginManagerScoreForm->getDefinitions();
    if (!$definitions[$bundle]) {
      throw new \Exception("No ScoreForm plugin defined for bundle {$bundle}.");
    }

    return $this->pluginManagerScoreForm->createInstance($bundle); 
  }

  /**
   * Build a form given a score template.
   */
  public function getForm(ScoreTemplateInterface $score_template) {
    $instance = $this->getInstance($score_template);
    return $instance->form($score_template);
  }

  /**
   * Create a score when submitting the score template.
   */
  public function createScore(NodeInterface $node, array $values) {
    $score_template = $node->field_score_template->entity;
    if (!$score_template) {
      // This field is required.
      return FALSE;
    }
    
    $bundle = $score_template->bundle();
    $score_template_type = $this->entityTypeManager
      ->getStorage('score_template_type')
      ->load($bundle);
    if (!$score_template_type) {
      throw new \Exception("Missing score_template bundle $bundle");
    }

    $score_type = $score_template_type->getScoreType();
    $user_name = $this->currentUser->getAccountName();
    $competition_label = $node->label();
    $score = $this->entityTypeManager
      ->getStorage('score')
      ->create([
        'bundle' => $score_type,
        'score_template' => $score_template,
        'title' => "$user_name judging of $competition_label",
        'judge' => $this->currentUser->id(),
      ]);

    // Let the plugin populate the score.
    $instance = $this->getInstance($score_template);
    $instance->save($score, $values);
    $score->save();
  }

}
