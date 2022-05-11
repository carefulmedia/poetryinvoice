<?php

namespace Drupal\piv_contest_score_template;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of score template type entities.
 *
 * @see \Drupal\piv_contest_score_template\Entity\ScoreTemplateType
 */
class ScoreTemplateTypeListBuilder extends ConfigEntityListBuilder {

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
      'No score template types available. <a href=":link">Add score template type</a>.',
      [':link' => Url::fromRoute('entity.score_template_type.add_form')->toString()]
    );

    return $build;
  }

}
