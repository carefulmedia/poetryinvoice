<?php

namespace Drupal\piv_contest_recitation;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the access control handler for the recitation entity type.
 */
class RecitationAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * The current route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  private $currentRouteMatch;

  /**
   * {@inheritdoc}
   */
  final public function __construct(EntityTypeInterface $entity_type, RouteMatchInterface $currentRouteMatch) {
    parent::__construct($entity_type);
    $this->currentRouteMatch = $currentRouteMatch;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view recitation');

      case 'update':
        $permissions = ['edit recitation', 'administer recitation'];

        if ($entity->get('uid')->target_id === $account->id()) {
          $permissions[] = 'edit own recitation';
        }

        /** @var \Drupal\piv_contest_competition_entry\Entity\CompetitionEntry $competition_entry */
        $competition_entry = $this->currentRouteMatch->getParameter('competition_entry');
        if ($competition_entry) {
          $hasAccessCompetitionEntry = $competition_entry->access('update', $account, TRUE);
          if ($hasAccessCompetitionEntry->isAllowed()) {
            return $hasAccessCompetitionEntry;
          }
        }

        return AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');

      case 'delete':
        $permissions = ['delete recitation', 'administer recitation'];

        if ($entity->get('uid')->target_id === $account->id()) {
          $permissions[] = 'delete own recitation';
        }

        /** @var \Drupal\piv_contest_competition_entry\Entity\CompetitionEntry $competition_entry */
        $competition_entry = $this->currentRouteMatch->getParameter('competition_entry');
        if ($competition_entry) {
          $hasAccessCompetitionEntry = $competition_entry->access('delete', $account, TRUE);
          if ($hasAccessCompetitionEntry->isAllowed()) {
            return $hasAccessCompetitionEntry;
          }
        }

        return AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');

      default:
        // No opinion.
        return AccessResult::neutral();
    }

  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, [
      'create recitation',
      'administer recitation',
    ], 'OR');
  }

}
