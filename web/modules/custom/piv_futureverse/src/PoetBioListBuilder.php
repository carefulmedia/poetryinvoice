<?php

declare(strict_types=1);

namespace Drupal\piv_futureverse;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list controller for the poet bio entity type.
 */
final class PoetBioListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['label'] = $this->t('Label');
    $header['uid'] = $this->t('Author');
    $header['language'] = $this->t('Language');
    $header['created'] = $this->t('Created');
    $header['changed'] = $this->t('Updated');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\piv_futureverse\PoetBioInterface $entity */
    $row['id'] = $entity->id();
    $row['label'] = $entity->label();
    $username_options = [
      'label' => 'hidden',
      'settings' => ['link' => $entity->get('uid')->entity->isAuthenticated()],
    ];
    $row['uid']['data'] = $entity->get('uid')->view($username_options);
    $row['language']['data'] = $entity->get('field_language')->entity?->label() ?? '';
    $row['created']['data'] = $entity->get('created')->view(['label' => 'hidden']);
    $row['changed']['data'] = $entity->get('changed')->view(['label' => 'hidden']);
    return $row + parent::buildRow($entity);
  }

}
