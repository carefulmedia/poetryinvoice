<?php

namespace Drupal\piv_contest\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines score_form annotation object.
 *
 * @Annotation
 */
class ScoreForm extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The score type (bundle) this score form is for.
   *
   * @var string
   */
  public $score_type;

}
