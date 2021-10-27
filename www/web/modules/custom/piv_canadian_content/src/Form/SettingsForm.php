<?php

namespace Drupal\piv_canadian_content\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Configure PIV Canadian Content settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'piv_canadian_content_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['piv_canadian_content.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Load a node from config if there is one.
   */
  private function defaultNode($config_name) {
    $nid = $this->config('piv_canadian_content.settings')->get($config_name);
    return $nid ? $this->entityTypeManager->getStorage('node')->load($nid) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['piv_canadian_content_redirect_en'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Node for english redirection'),
      '#target_type' => 'node',
      '#default_value' => $this->defaultNode('piv_canadian_content_redirect_en'),
    ];
    $form['piv_canadian_content_redirect_fr'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Node for french redirection'),
      '#target_type' => 'node',
      '#default_value' => $this->defaultNode('piv_canadian_content_redirect_fr'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('piv_canadian_content.settings')
      ->set('piv_canadian_content_redirect_en', $form_state->getValue('piv_canadian_content_redirect_en'))
      ->set('piv_canadian_content_redirect_fr', $form_state->getValue('piv_canadian_content_redirect_fr'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
