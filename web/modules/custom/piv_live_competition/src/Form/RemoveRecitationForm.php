<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\piv_live_competition\Helper;

final class RemoveRecitationForm extends ConfirmFormBase {
  use AutowireTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Helper $helper,
  ) {}

  /**
   * Custom access check to make sure we have an admin user or the Live Competition Administrator.
   */
  public function access(AccountInterface $account, NodeInterface $node, ParagraphInterface $paragraph): AccessResultInterface {
    $is_admin = $account->hasRole('administrator');
    $is_competition_admin = !$node->get('field_live_competition_admin')->isEmpty()
      && $node->get('field_live_competition_admin')->entity->id() === $account->id();

    return AccessResult::allowedIf($is_admin || $is_competition_admin)
      ->cachePerPermissions()
      ->cachePerUser()
      ->addCacheableDependency($node);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'piv_live_competition_remove_recitation_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): string|TranslatableMarkup {
    return $this->t('Remove recitation');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    $node = $this->getRequest()->attributes->get('node');
    return Url::fromRoute('piv_live_competition.monitor_dashboard', ['node' => $node->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): string|TranslatableMarkup {
    return $this->t('Confirm removal');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    ?NodeInterface $node = NULL,
    ?ParagraphInterface $paragraph = NULL,
  ): array {
    $form = parent::buildForm($form, $form_state);

    $student_name = $this->helper->getStudentName($paragraph);
    $warning_text = $this->t('You are about to remove the recitation for <strong>@name</strong>.', ['@name' => $student_name]) . '<br>';
    $warning_text .= $this->t('This action will remove the recitation from the competition.') . '<br>';
    $warning_text .= $this->t("The student's scores will not be included in the final results.") . '<br>';

    $form['warning'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['remove-recitation-warning']],
      '#weight' => -10,
      'message' => [
        '#markup' => Markup::create($warning_text),
      ],
    ];

    $form['node_id'] = ['#type' => 'value', '#value' => $node->id()];
    $form['paragraph_id'] = ['#type' => 'value', '#value' => $paragraph->id()];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $node_id = $form_state->getValue('node_id');
    $paragraph_id = $form_state->getValue('paragraph_id');

    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
    $recitation = $paragraph_storage->load($paragraph_id);

    if (!$recitation) {
      $this->messenger()->addError($this->t('Recitation not found.'));
      $form_state->setRedirect('piv_live_competition.monitor_dashboard', ['node' => $node_id]);
      return;
    }

    $student_name = $this->helper->getStudentName($recitation);

    // Remove all scores attached to this recitation.
    foreach ($recitation->field_accuracy_scores->referencedEntities() as $score) {
      $score->delete();
    }
    foreach ($recitation->field_performance_scores->referencedEntities() as $score) {
      $score->delete();
    }

    // Remove the recitation paragraph from its parent entry.
    $parent = $recitation->getParentEntity();
    if ($parent) {
      $items = $parent->get('field_tr_student');
      $new_items = [];
      foreach ($items as $item) {
        if ((int) $item->target_id !== (int) $paragraph_id) {
          $new_items[] = ['target_id' => $item->target_id, 'target_revision_id' => $item->entity->getRevisionId()];
        }
      }
      $parent->set('field_tr_student', $new_items);
      $parent->save();
    }

    // Delete the paragraph itself.
    $recitation->delete();

    $this->messenger()->addStatus($this->t('The recitation for @name has been removed.', ['@name' => $student_name]));
    $form_state->setRedirect('piv_live_competition.monitor_dashboard', ['node' => $node_id]);
  }

}
