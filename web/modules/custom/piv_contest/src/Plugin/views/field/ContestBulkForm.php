<?php

namespace Drupal\piv_contest\Plugin\views\field;

use Drupal\views\Plugin\views\field\BulkForm;

/**
 * Defines a node operations bulk form element.
 *
 * @ViewsField("contest_bulk_form")
 */
class ContestBulkForm extends BulkForm {

  /**
   * {@inheritdoc}
   */
  protected function emptySelectedMessage() {
    return $this->t('No content selected.');
  }

}
