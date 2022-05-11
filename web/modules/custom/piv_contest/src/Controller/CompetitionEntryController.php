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
   * Verify access to add entry.
   */
  public function accessAdd(UserInterface $user, CompetitionInterface $competition, ParagraphInterface $stream) {
    $school = $user->field_school->entity;
    if (!$school) {
      return AccessResult::forbidden();
    }

    return $this->competitionService->currentCanAddEntriesForSchoolOnCompetition($school, $competition);
  }

  /**
   * Builds the competition entry form.
   */
  public function add(UserInterface $user, CompetitionInterface $competition, ParagraphInterface $stream) {
    $school = $user->field_school->target_id;
    $competition_entry = $this->entityTypeManager->getStorage('competition_entry')->create([
      'field_competition' => $competition->id(),
      'field_school' => $school,
      'bundle' => 'default',
      'field_stream' => $stream,
      'field_competition_current_level' => $competition->field_competition_current_level->value,
    ]);
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

  /**
   * Verify access to edit entry.
   */
  public function accessEdit(UserInterface $user, CompetitionInterface $competition, CompetitionEntryInterface $competition_entry) {
    return $competition_entry->access('update', $user, TRUE);
  }

  /**
   * Edit form.
   */
  public function edit(UserInterface $user, CompetitionInterface $competition, CompetitionEntryInterface $competition_entry) {
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
