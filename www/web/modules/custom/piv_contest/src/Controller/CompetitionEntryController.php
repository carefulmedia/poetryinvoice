<?php

namespace Drupal\piv_contest\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\piv_contest_competition\CompetitionInterface;
use Drupal\piv_contest_competition_entry\CompetitionEntryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\piv_contest\CompetitionService;
use Drupal\Paragraphs\ParagraphInterface;

/**
 * Returns responses for PIV Contest routes.
 */
class CompetitionEntryController extends ControllerBase {

  /**
   * The entity form builder service.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected $entityFormBuilder;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The competition service.
   *
   * @var \Drupal\piv_contest\CompetitionService
   */
  protected $competitionService;

  /**
   * The controller constructor.
   *
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entity_form_builder
   *   The entity form builder service.
   */
  public function __construct(EntityFormBuilderInterface $entity_form_builder, EntityTypeManagerInterface $entity_type_manager, CompetitionService $competition_service) {
    $this->entityFormBuilder = $entity_form_builder;
    $this->entityTypeManager = $entity_type_manager;
    $this->competitionService = $competition_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.form_builder'),
      $container->get('entity_type.manager'),
      $container->get('piv_contest.competition_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access_add(
    UserInterface $user,
    CompetitionInterface $competition,
    ParagraphInterface $stream
  ) {
    return AccessResult::allowed();

    $school = $user->field_school->entity;
    if (!$school) {
      return AccessResult::forbidden();
    }

    // When editing, we need to also verify if the competition_entry is for the
    // same school and same competition in the url. This means that different
    // teachers can manage the entries of the same school.
    if ($competition_entry) {
      if ($competition_entry->field_school->target_id != $school->id()
      || $competition_entry->field_competition->target_id != $competition->id()) {
        return AccessResult::forbidden();
      }
    }

    return AccessResult::allowedIf($this->competitionService->schoolCanManageEntriesForCompetition($school, $competition));
  }

  /**
   * Builds the competition entry form.
   */
  public function add(
    UserInterface $user,
    CompetitionInterface $competition,
    ParagraphInterface $stream
  ) {
    if (!$competition_entry) {
      $school = $user->field_school->target_id;
      $competition_entry = $this->entityTypeManager->getStorage('competition_entry')->create([
        'field_competition' => $competition->id(),
        'field_school' => $school,
        'bundle' => 'default',
        'field_stream' => $stream,
      ]);
    }


    // The form is different for team or individual competitions.
    $is_team_competition = !empty($competition->field_team_competition->value);
    $form_mode = $is_team_competition
      ? 'teacher_competition_entry_team_competition'
      : 'teacher_competition_entry';

    $form = $this->entityFormBuilder
      ->getForm($competition_entry, $form_mode);
    $form['revision_information']['#access'] = FALSE;
    return $form;
  }

  public function access_edit(
    UserInterface $user,
    CompetitionInterface $competition,
    CompetitionEntryInterface $competition_entry
  ) {
    return AccessResult::allowed();
  }

  public function edit(
    UserInterface $user,
    CompetitionInterface $competition,
    CompetitionEntryInterface $competition_entry
  ) {
    // The form is different for team or individual competitions.
    $is_team_competition = !empty($competition->field_team_competition->value);
    $form_mode = $is_team_competition
      ? 'teacher_competition_entry_team_competition'
      : 'teacher_competition_entry';

    $form = $this->entityFormBuilder
      ->getForm($competition_entry, $form_mode);
    $form['revision_information']['#access'] = FALSE;
    return $form;
  }

}
