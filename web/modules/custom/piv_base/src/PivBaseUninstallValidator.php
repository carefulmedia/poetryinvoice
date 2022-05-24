<?php

namespace Drupal\piv_base;

use Drupal\Core\Extension\ModuleUninstallValidatorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Prevents uninstalling of modules providing used block plugins.
 */
class PivBaseUninstallValidator implements ModuleUninstallValidatorInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function validate($module) {
    $reasons = [];
    if ($module == 'reroute_email') {
      $reasons[] = $this->t("Module can't be disabled in order to prevent dev environments from sending email.");
    }
    return $reasons;
  }

}
