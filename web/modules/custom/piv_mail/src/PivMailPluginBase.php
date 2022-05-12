<?php

namespace Drupal\piv_mail;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Base class for piv_mail plugins.
 */
abstract class PivMailPluginBase extends PluginBase implements PivMailInterface {

  use StringTranslationTrait;

  /**
   * The configurations for this plugin.
   *
   * @var array
   */
  protected $configuration;

  /**
   * The manager for this plugin type.
   *
   * @var Drupal\piv_mail\PivMailPluginManager|null
   */
  protected $pluginManager;

  /**
   * Set a reference to the plugin manager.
   */
  public function setPluginManager(PivMailPluginManager $plugin_manager) {
    $this->pluginManager = $plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, ?string $langcode = NULL) {
    $configurations = $this->getConfiguration()[$langcode] ?? [];
    $plugin_sources = $this->pluginDefinition['sources'] ?? [];
    $form['active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Active'),
      '#default_value' => $configurations['active'] ?? FALSE,
      '#description' => $this->pluginDefinition['description'] ?? '',
    ];
    $recipients = ReplacementsService::$recipients;
    $recipients_filtered = array_filter($recipients, function ($option) use ($plugin_sources) {
      return empty($option['source']) || in_array($option['source'], $plugin_sources);
    });
    $options = [];
    foreach ($recipients_filtered as $key => $recipient) {
      $options[$key] = $recipient['title'];
    }
    $form['to'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('TO:'),
      '#options' => $options,
      '#default_value' => $configurations['to'] ?? [],
    ];
    $form['to_more'] = [
      '#type' => 'textfield',
      '#description' => $this->t('You can add more emails here, separate them by space.'),
      '#default_value' => $configurations['to_more'] ?? '',
    ];
    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#default_value' => $configurations['subject'] ?? '',
    ];
    $form['body'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Body'),
      '#default_value' => $configurations['body']['value'] ?? '',
      '#format' => $configurations['body']['format'] ?? NULL,
    ];
    $tokens = ReplacementsService::$tokens;
    $items = [];
    foreach ($plugin_sources as $plugin_source) {
      $tokens_to_add = $tokens[$plugin_source] ?? [];
      $items = array_merge($items, $tokens_to_add);
    }

    $form['tokens'] = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#title' => $this->t('Replacement tokens'),
      '#items' => array_map(fn($item) => "[$item]", $items),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Configurations should be already set.
    if ($this->pluginManager) {
      $this->pluginManager->saveConfigurations($this);
    }
  }

}
