<?php

declare(strict_types=1);

namespace Drupal\piv_futureverse\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides HTML routes for entities with administrative pages.
 */
final class JournalMonthHtmlRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  protected function getCanonicalRoute(EntityTypeInterface $entity_type): ?Route {
    return $this->getEditFormRoute($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  protected function getCollectionRoute(EntityTypeInterface $entity_type): ?Route {
    if ($route = parent::getCollectionRoute($entity_type)) {
      $route->setRequirement('_custom_access', '\Drupal\piv_futureverse\Access\JournalMonthListAccess::access');
      return $route;
    }
    return NULL;
  }

}
