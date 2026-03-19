<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Confirmation modal before starting the first recitation of a contest.
 */
final class StartContestConfirmForm extends FormBase {
  use AutowireTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function access(AccountInterface $account, NodeInterface $node): AccessResultInterface {
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
    return 'piv_live_competition_start_contest_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('Are you sure the first reciter and all judges are ready?'),
    ];

    $form['node_id'] = ['#type' => 'value', '#value' => $node?->id()];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start Recitation 1'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel Start'),
      '#submit' => ['::cancelForm'],
      '#limit_validation_errors' => [],
      '#attributes' => ['class' => ['dialog-cancel']],
    ];

    return $form;
  }

  /**
   * Cancel our action and redirect back to the monitor dashboard.
   */
  public function cancelForm(array &$form, FormStateInterface $form_state): void {
    $node_id = $form_state->getValue('node_id');
    $form_state->setRedirect('piv_live_competition.monitor_dashboard', ['node' => $node_id]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $node_id = $form_state->getValue('node_id');
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->entityTypeManager->getStorage('node')->load($node_id);

    if (!$node || $node->field_active_round->value != 0) {
      $this->messenger()->addError($this->t('The form has become outdated. Please reload the page.'));
      $form_state->setRedirect('piv_live_competition.monitor_dashboard', ['node' => $node_id]);
      return;
    }

    $node->field_active_round = 1;
    $node->save();

    $form_state->setRedirect('piv_live_competition.monitor_dashboard', ['node' => $node_id]);
  }

}
