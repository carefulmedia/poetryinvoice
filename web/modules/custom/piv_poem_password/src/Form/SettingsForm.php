<?php

namespace Drupal\piv_poem_password\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure PIV Poem Password settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'piv_poem_password_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['piv_poem_password.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $settings = $this->config('piv_poem_password.settings');
    $form['password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Password'),
      '#default_value' => $settings->get('password'),
      '#description' => $this->t('Enter the password which users must type to see a protected poem.'),
    ];
    $form['poem_password_description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Information regarding password form'),
      '#default_value' => $settings->get('poem_password_description'),
      '#description' => $this->t('The text presented when a user is asked for the password.'),
    ];
    $form['poem_password_fail'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message on password fail'),
      '#default_value' => $settings->get('poem_password_fail'),
      '#description' => $this->t('The text presented when a user does not provide the right password.'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('piv_poem_password.settings')
      ->set('password', $form_state->getValue('password'))
      ->set('poem_password_description', $form_state->getValue('poem_password_description'))
      ->set('poem_password_fail', $form_state->getValue('poem_password_fail'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
