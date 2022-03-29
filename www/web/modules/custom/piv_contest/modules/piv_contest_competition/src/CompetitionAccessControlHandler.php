<?php

namespace Drupal\piv_contest_competition;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the access control handler for the competition entity type.
 */
class CompetitionAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  private $userStorage;

  private $routeMatch;

  public function __construct(
    EntityTypeInterface $entity_type,
    EntityTypeManager $entityTypeManager,
    RouteMatchInterface $routeMatch
  ) {
    parent::__construct($entity_type);
    $this->userStorage = $entityTypeManager->getStorage('user');
    $this->routeMatch = $routeMatch;
  }

  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
      $container->get('current_route_match')
    );
  }


  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    switch ($operation) {
      case 'view':
        $permissions = ['view all competitions'];

        $user = $this->routeMatch->getParameter('user');
        if ($user) {
          $logger_user = $this->userStorage->load($account->id());

          $logger_user_school = $logger_user->field_school->target_id;
          $user_school = $user->field_school->target_id;

          if ($user_school === $logger_user_school) {
            $permissions[] = 'view own school competitions';
          }
        }

        return AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');

      case 'update':
        return AccessResult::allowedIfHasPermissions($account, ['edit competition', 'administer competition'], 'OR');

      case 'delete':
        return AccessResult::allowedIfHasPermissions($account, ['delete competition', 'administer competition'], 'OR');

      default:
        // No opinion.
        return AccessResult::neutral();
    }

  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, ['create competition', 'administer competition'], 'OR');
  }

}
