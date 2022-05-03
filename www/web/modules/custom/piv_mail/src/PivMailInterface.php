<?php

namespace Drupal\piv_mail;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Interface for piv_mail plugins.
 */
interface PivMailInterface extends ConfigurableInterface, DependentPluginInterface, PluginFormInterface {

  /**
   * Returns the translated plugin label.
   *
   * @return string
   *   The translated title.
   */
  public function label();

}
