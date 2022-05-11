<?php

namespace Drupal\piv_contest_judging_session;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of judging session type entities.
 *
 * @see \Drupal\piv_contest_judging_session\Entity\JudgingSessionType
 */
class JudgingSessionTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['title'] = $this->t('Label');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['title'] = [
      'data' => $entity->label(),
      'class' => ['menu-label'],
    ];

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();

    $build['table']['#empty'] = $this->t(
      'No judging session types available. <a href=":link">Add judging session type</a>.',
      [':link' => Url::fromRoute('entity.judging_session_type.add_form')->toString()]
    );

    return $build;
  }

}
