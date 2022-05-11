<?php

namespace Drupal\piv_mail\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines piv_mail annotation object.
 *
 * @Annotation
 */
class PivMail extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $title;

  /**
   * The description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * A list of replacement tokens available for this plugin.
   *
   * This is just a representation of the tokens available displayed as a help
   * for the admin.
   *
   * @var string[]
   */
  public $tokens;

}
