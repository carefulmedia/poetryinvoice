<?php

declare(strict_types=1);

namespace Drupal\piv_logs;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of log type entities.
 *
 * @see \Drupal\piv_logs\Entity\LogType
 */
final class LogTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Label');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $row['label'] = $entity->label();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();

    $build['table']['#empty'] = $this->t(
      'No log types available. <a href=":link">Add log type</a>.',
      [':link' => Url::fromRoute('entity.piv_log_type.add_form')->toString()],
    );

    return $build;
  }

}
