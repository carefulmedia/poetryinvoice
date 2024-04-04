<?php

namespace Drupal\piv_contest_score_template\Form;

use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for score template type forms.
 */
class ScoreTemplateTypeForm extends BundleEntityFormBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $entity_type = $this->entity;
    if ($this->operation == 'add') {
      $form['#title'] = $this->t('Add score template type');
    }
    else {
      $form['#title'] = $this->t(
        'Edit %label score template type',
        ['%label' => $entity_type->label()]
      );
    }

    $form['label'] = [
      '#title' => $this->t('Label'),
      '#type' => 'textfield',
      '#default_value' => $entity_type->label(),
      '#description' => $this->t('The human-readable name of this score template type.'),
      '#required' => TRUE,
      '#size' => 30,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity_type->id(),
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#machine_name' => [
        'exists' => [
          'Drupal\piv_contest_score_template\Entity\ScoreTemplateType',
          'load',
        ],
        'source' => ['label'],
      ],
      '#description' => $this->t('A unique machine-readable name for this score template type. It must only contain lowercase letters, numbers, and underscores.'),
    ];

    $entity_type_bundle_info = \Drupal::service('entity_type.bundle.info');
    $bundles = [];
    foreach ($entity_type_bundle_info->getBundleInfo('score') as $machine_name => $data) {
      $bundles[$machine_name] = $data['label'];
    }
    if (!$bundles) {
      $this->messenger()->addWarning('You need to create a Score type before you create a Score Template type.');
    }
    $form['score_type'] = [
      '#type' => 'select',
      '#required' => TRUE,
      '#title' => $this->t('Score type'),
      '#description' => $this->t('Select the score type that will be created when creating a score for a template of this type.'),
      '#default_value' => $entity_type->getScoreType(),
      '#options' => $bundles,
    ];

    return $this->protectBundleIdElement($form);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Save score template type');
    $actions['delete']['#value'] = $this->t('Delete score template type');
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): void {
    $entity_type = $this->entity;

    $entity_type->set('id', trim($entity_type->id()));
    $entity_type->set('label', trim($entity_type->label()));
    $entity_type->set('scoreType', $form_state->getValue('score_type'));
    $status = $entity_type->save();

    $t_args = ['%name' => $entity_type->label()];
    if ($status == SAVED_UPDATED) {
      $message = $this->t('The score template type %name has been updated.', $t_args);
    }
    elseif ($status == SAVED_NEW) {
      $message = $this->t('The score template type %name has been added.', $t_args);
    }
    $this->messenger()->addStatus($message);

    $form_state->setRedirectUrl($entity_type->toUrl('collection'));
  }

}
