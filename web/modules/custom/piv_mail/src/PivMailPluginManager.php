<?php

namespace Drupal\piv_mail;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Config\ConfigFactory;

/**
 * PivMail plugin manager.
 */
class PivMailPluginManager extends DefaultPluginManager {

  /**
   * Instances cache.
   *
   * @var PivMailInterface[]
   */
  private $instances = [];

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Constructs PivMailPluginManager object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, ConfigFactory $config_factory) {
    parent::__construct(
      'Plugin/PivMail',
      $namespaces,
      $module_handler,
      'Drupal\piv_mail\PivMailInterface',
      'Drupal\piv_mail\Annotation\PivMail'
    );
    $this->configFactory = $config_factory;
    $this->alterInfo('piv_mail_info');
    $this->setCacheBackend($cache_backend, 'piv_mail_plugins');
  }

  /**
   * Generate a config name for a plugin.
   */
  public function getConfigName(string $plugin_id) {
    return "piv_mail.piv_mail.{$plugin_id}";
  }

  /**
   * {@inheritdoc}
   *
   * Create a instance and load the configurations for the plugin.
   */
  public function createInstance($plugin_id, array $configuration = []) {
    if (empty($this->instances[$plugin_id])) {
      $settings = $this->configFactory->get($this->getConfigName($plugin_id))->get() ?? [];
      $configuration = $settings + $configuration;
      $this->instances[$plugin_id] = parent::createInstance($plugin_id, $configuration);
      $this->instances[$plugin_id]->setPluginManager($this);
    }
    return $this->instances[$plugin_id];
  }

  /**
   * Save the configurations for plugin.
   */
  public function saveConfigurations(PivMailInterface $plugin): void {
    $plugin_id = $plugin->getPluginId();
    $this->configFactory
      ->getEditable($this->getConfigName($plugin_id))
      ->setData($plugin->getConfiguration())
      ->save();
  }

}
