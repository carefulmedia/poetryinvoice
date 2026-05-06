<?php

namespace Drupal\piv_base\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/**
 * Returns responses for PIV Base routes.
 */
class SurveyController extends ControllerBase {

  /**
   * The entity form builder service.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected $entityFormBuilder;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The controller constructor.
   */
  final public function __construct(EntityFormBuilderInterface $entity_form_builder, EntityTypeManagerInterface $entity_type_manager) {
    $this->entityFormBuilder = $entity_form_builder;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.form_builder'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Get the paragraph form.
   */
  private function getForm(NodeInterface $node, $paragraph_type) {
    $paragraph_field_map = [
      'poet_questionnaire' => 'field_poet_questionnaire',
      'teacher_questionnaire' => 'field_teacher_questionnaire',
    ];
    $field_name = $paragraph_field_map[$paragraph_type] ?? NULL;
    if (!$field_name) {
      return [];
    }

    // Check if there is already a paragraph in the node, otherwise, create new.
    $is_new = FALSE;
    $paragraph = $node->{$field_name}->entity;
    if (!$paragraph) {
      $is_new = TRUE;
      $paragraph = $this->entityTypeManager->getStorage('paragraph')->create([
        'type' => $paragraph_type,
      ]);
    }

    $form_state_additions = [];
    if ($is_new) {
      $form_state_additions = [
        'survey_paragraph_field' => $field_name,
        'survey_paragraph_node' => $node,
      ];
    }
    // A form alter adds a custom submit to this form. It is too early to alter
    // the form here, other functions alter it and end up removing the custom
    // submits.
    // @see piv_base_form_survey_custom_submit()
    // @see piv_base_form_alter()
    return $this->entityFormBuilder->getForm($paragraph, 'default', $form_state_additions);
  }

  /**
   * Builds the response for the poet survey.
   */
  public function poetForm(NodeInterface $node) {
    return $this->getForm($node, 'poet_questionnaire');
  }

  /**
   * Builds the response for the teacher survey.
   */
  public function teacherForm(NodeInterface $node) {
    return $this->getForm($node, 'teacher_questionnaire');
  }

  /**
   * Poet access.
   */
  public function poetFormAccess(AccountInterface $account, NodeInterface $node) {
    $is_visit = $node->bundle() == 'pal_pir_school_visit';
    $has_permission = $account->hasPermission('edit any poet questionnaire');
    $is_visit_poet = $account->id() == $node->field_pal_pir->target_id;
    return AccessResult::allowedIf($is_visit && ($has_permission || $is_visit_poet));
  }

  /**
   * Teacher access.
   */
  public function teacherFormAccess(AccountInterface $account, NodeInterface $node) {
    $is_visit = $node->bundle() == 'pal_pir_school_visit';
    $has_permission = $account->hasPermission('edit any teacher questionnaire');
    $is_visit_teacher = $account->id() == $node->getOwner()->id();
    return AccessResult::allowedIf($is_visit && ($has_permission || $is_visit_teacher));
  }

  /**
   * Render the paragraph.
   */
  private function getView(NodeInterface $node, $paragraph_type) {
    $paragraph_field_map = [
      'poet_questionnaire' => 'field_poet_questionnaire',
      'teacher_questionnaire' => 'field_teacher_questionnaire',
    ];
    $field_name = $paragraph_field_map[$paragraph_type] ?? NULL;
    $entity = $node->{$field_name}->entity;
    if (!$entity) {
      return [];
    }

    return $this->entityTypeManager
      ->getViewBuilder('paragraph')
      ->view($entity);
  }

  /**
   * Poet view paragraph.
   */
  public function poetView(NodeInterface $node) {
    return $this->getView($node, 'poet_questionnaire');
  }

  /**
   * Teacher view paragraph.
   */
  public function teacherView(NodeInterface $node) {
    return $this->getView($node, 'teacher_questionnaire');
  }

  /**
   * Poet view access.
   */
  public function poetViewAccess(AccountInterface $account, NodeInterface $node) {
    return AccessResult::allowedIf(!empty($node->field_poet_questionnaire->entity))
      ->andIf($this->poetFormAccess($account, $node));
  }

  /**
   * Teacher view access.
   */
  public function teacherViewAccess(AccountInterface $account, NodeInterface $node) {
    return AccessResult::allowedIf(!empty($node->field_teacher_questionnaire))
      ->andIf($this->teacherFormAccess($account, $node));
  }

}
