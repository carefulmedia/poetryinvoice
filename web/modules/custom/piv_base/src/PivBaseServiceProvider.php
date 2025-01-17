<?php

namespace Drupal\piv_base;

use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\DependencyInjection\ContainerBuilder;

/**
 * Replace the geocoder preprocessor plugin manager with a custom one.
 */
class PivBaseServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    // Replace class to override the sourceFieldIsSameOfOriginal method
    // with a custom method that compares postal code only.
    if ($definition = $container->getDefinition('plugin.manager.geocoder.preprocessor')) {
      $definition->setClass(PivBasePreprocessorPluginManager::class);
    }
  }

}
