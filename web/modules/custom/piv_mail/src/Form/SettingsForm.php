<?php

namespace Drupal\piv_mail\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\piv_mail\PivMailPluginManager;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Configure PIV Mail settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The 'PivMail' plugin manager.
   *
   * @var \Drupal\piv_mail\PivMailPluginManager
   */
  protected $pivMailPluginManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   */
  final public function __construct(PivMailPluginManager $piv_mail_plugin_manager, LanguageManagerInterface $language_manager) {
    $this->pivMailPluginManager = $piv_mail_plugin_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.piv_mail'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'piv_mail_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['piv_mail.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $type = NULL) {
    $definitions = $this->pivMailPluginManager->getDefinitions();
    if ($type) {
      $definitions = array_filter($definitions, fn($d) => $d['type'] === $type);
    }
    ksort($definitions);
    $languages = $this->languageManager->getLanguages();
    // Horizontal tabs require the field_group module.
    $form['emails'] = [
      '#type' => 'horizontal_tabs',
    ];
    foreach ($languages as $langcode => $language) {
      $form["emails_{$langcode}"] = [
        '#type' => 'details',
        '#group' => 'emails',
        '#title' => $language->getName(),
        "emails_{$langcode}_tabs" => [
          '#type' => 'vertical_tabs',
        ],
      ];
      foreach ($definitions as $plugin_id => $plugin_definition) {
        $instance = $this->pivMailPluginManager->createInstance($plugin_id);
        $form["emails_{$langcode}_{$plugin_id}"] = [
          '#type' => 'details',
          '#group' => "emails_{$langcode}_tabs",
          '#title' => $plugin_definition['label'],
          '#tree' => TRUE,
          '#parents' => ['plugins', $plugin_id, $langcode],
        ] + $instance->buildConfigurationForm([], $form_state, $langcode);
      }
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $plugins_values = $form_state->getValue('plugins');
    foreach ($plugins_values as $plugin_id => $plugin_configurations) {
      $instance = $this->pivMailPluginManager->createInstance($plugin_id);
      $instance->validateConfigurationForm($form, $form_state);
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $plugins_values = $form_state->getValue('plugins');
    foreach ($plugins_values as $plugin_id => $plugin_configurations) {
      $instance = $this->pivMailPluginManager->createInstance($plugin_id);
      $instance->setConfiguration($plugin_configurations);
      $instance->submitConfigurationForm($form, $form_state);
    }
    parent::submitForm($form, $form_state);
  }

}
