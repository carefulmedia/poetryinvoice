<?php

namespace Drupal\piv_contest_judging_session\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\piv_contest_competition\CompetitionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

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
    CompetitionInterface $competition,
    Request $request
  ): array {
    $current_user = \Drupal::currentUser();
    $stream_id = $request->query->get('stream');
    $stream = \Drupal::entityTypeManager()->getStorage('paragraph')->load($stream_id);

    $title = "{$competition->label()} {$stream->field_label->value} judging session";

    //  session_management_ui
    $session = $this->entityTypeManager->getStorage('judging_session')->create([
      'field_competition' => $competition->id(),
      'user' => $current_user->id(),
      'field_stream' => $stream,
      'title' => $title,
      'bundle' => 'default',
    ]);

    $form = $this->entityFormBuilder
      ->getForm($session, 'session_management_ui');
    $form['revision_information']['#access'] = FALSE;
    return $form;
  }
}
