<?php

namespace Drupal\piv_poem_password\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;

/**
 * Provides a PIV Poem Password form.
 */
class PasswordForm extends FormBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The tempstore.
   *
   * @var Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStore;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'piv_poem_password_password';
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactory $config_factory, PrivateTempStoreFactory $temp_store) {
    $this->configFactory = $config_factory;
    $this->tempStore = $temp_store;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('tempstore.private')
    );
  }

  /**
   * Get the configurations for the poem password.
   */
  public function getConfigurations() {
    return $this->configFactory->get('piv_poem_password.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $configs = $this->getConfigurations();
    $form['title'] = [
      '#theme' => 'page_title',
      '#title' => $this->t('Enter the poem password'),
    ];
    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#description' => $configs->get('poem_password_description') ?? '',
    ];
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $configs = $this->getConfigurations();
    $given_password = $form_state->getValue('password');
    $config_password = $configs->get('password');
    if ($given_password !== $config_password) {
      $form_state->setErrorByName('password', $configs->get('poem_password_fail'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    user_cookie_save(['piv_poem_password_valid' => 1]);
    $this->tempStore->get('piv_poem_password')->set('piv_poem_password_valid', 1);
  }

}
