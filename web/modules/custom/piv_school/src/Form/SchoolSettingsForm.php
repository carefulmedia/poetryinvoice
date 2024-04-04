<?php

namespace Drupal\piv_school\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

/**
 * The PIV School config form.
 */
class SchoolSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'piv_school_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['piv_school.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('piv_school.settings');
    $state = \Drupal::state();

    $validators = array(
      'file_validate_extensions' => array('csv'),
    );

    $form['contacts_csv_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('School Contacts CSV file'),
      '#description' => $this->t('The CSV with the School Contacts to be synced on School content.'),
      '#default_value' => $state->get('contacts_csv_file'),
      '#upload_validators' => $validators,
      '#upload_location' => 'private://school/',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $state = \Drupal::state();

    if ($file_id = $form_state->getValue(['contacts_csv_file', '0'])) {
      $file = File::load($file_id);
      $file->setPermanent();
      $file->save();
      $state->set('contacts_csv_file', $form_state->getValue('contacts_csv_file'));
    }
    else {
      $state->set('contacts_csv_file', []);
    }

    parent::submitForm($form, $form_state);
  }

}
