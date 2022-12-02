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
use Drupal\Core\Url;
use Drupal\Core\Link;

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
   * Returns the competition_entry form with some data alredy populated.
   */
  private function getEntityForm(UserInterface $user, CompetitionInterface $competition, CompetitionEntryInterface $competition_entry) {
    // The form is different for team or individual competitions.
    $is_team_competition = !empty($competition->field_team_competition->value);
    $form_mode = $is_team_competition
      ? 'teacher_competition_entry_team_competition'
      : 'teacher_competition_entry';

    // Redirect the user back to the competition. Only works because the entity
    // form save method does not override the redirects, the default behavior is
    // to redirect to the entity view page.
    // @see Drupal\piv_contest_competition_entry\Form\CompetitionEntryForm::save()
    $redirect = Url::fromRoute('piv_contest.competition', [
      'user' => $user->id(),
      'competition' => $competition->id(),
    ]);
    $form_state_additions = ['redirect' => $redirect];
    $form = $this->entityFormBuilder
      ->getForm($competition_entry, $form_mode, $form_state_additions);
    $form['revision_information']['#access'] = FALSE;
    $form['back_link'] = [
      '#theme' => 'piv_back_link',
      '#link' => Link::createFromRoute($this->t('Back to the competition page'), 'piv_contest.competition', [
        'user' => $user->id(),
        'competition' => $competition->id(),
      ]),
      '#weight' => -1,
    ];
    return $form;
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
    return $this->getEntityForm($user, $competition, $competition_entry);
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
    return $this->getEntityForm($user, $competition, $competition_entry);
  }

}
