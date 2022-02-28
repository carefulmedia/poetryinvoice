<?php

namespace Drupal\piv_contest_judging_session\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\piv_contest_competition\CompetitionInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class JudgingSessionController extends ControllerBase {

  protected $entityTypeManager;

  protected $entityFormBuilder;

  public function __construct(EntityFormBuilderInterface $entity_form_builder, EntityTypeManagerInterface $entity_type_manager) {
    $this->entityFormBuilder = $entity_form_builder;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.form_builder'),
      $container->get('entity_type.manager')
    );
  }

  public function add(
    UserInterface $user,
    CompetitionInterface $competition
  ): array {
    //  session_management_ui
    $session = $this->entityTypeManager->getStorage('judging_session')->create([
      'field_competition' => $competition->id(),
      'user' => $user->id(),
      'bundle' => 'default',
    ]);

    $form = $this->entityFormBuilder
      ->getForm($session, 'session_management_ui');
    $form['revision_information']['#access'] = FALSE;
    return $form;
  }
}
