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
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\piv_live_competition\Helper;

/**
 * Form to edit a recitation's student name and poem.
 */
final class EditRecitationForm extends FormBase {
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
    return 'piv_live_competition_edit_recitation_form';
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
    $form['node_id'] = ['#type' => 'value', '#value' => $node->id()];
    $form['paragraph_id'] = ['#type' => 'value', '#value' => $paragraph->id()];

    $form['field_legal_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Full legal name'),
      '#default_value' => $paragraph->field_legal_name->value,
      '#required' => TRUE,
    ];

    $form['field_poem'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Poem to be recited'),
      '#target_type' => 'node',
      '#selection_handler' => 'views',
      '#selection_settings' => [
        'view' => [
          'view_name' => 'poems_not_retired',
          'display_name' => 'entity_reference_1',
          'arguments' => [],
        ],
      ],
      '#default_value' => $paragraph->field_poem->entity,
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('piv_live_competition.monitor_dashboard', ['node' => $node->id()]),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $paragraph_id = $form_state->getValue('paragraph_id');
    $node_id = $form_state->getValue('node_id');

    $recitation = $this->entityTypeManager->getStorage('paragraph')->load($paragraph_id);
    if (!$recitation) {
      $this->messenger()->addError($this->t('Recitation not found.'));
      $form_state->setRedirect('piv_live_competition.monitor_dashboard', ['node' => $node_id]);
      return;
    }

    $recitation->set('field_legal_name', $form_state->getValue('field_legal_name'));
    $recitation->set('field_poem', $form_state->getValue('field_poem'));
    $recitation->save();

    $this->messenger()->addStatus($this->t('The recitation has been updated.'));
    $form_state->setRedirect('piv_live_competition.monitor_dashboard', ['node' => $node_id]);
  }

}
