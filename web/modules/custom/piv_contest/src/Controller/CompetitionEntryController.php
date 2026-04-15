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
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Session\AccountInterface;

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
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The controller constructor.
   */
  final public function __construct(EntityFormBuilderInterface $entity_form_builder, EntityTypeManagerInterface $entity_type_manager, CompetitionService $competition_service, FormBuilderInterface $form_builder, AccountInterface $current_user) {
    $this->entityFormBuilder = $entity_form_builder;
    $this->entityTypeManager = $entity_type_manager;
    $this->competitionService = $competition_service;
    $this->formBuilder = $form_builder;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.form_builder'),
      $container->get('entity_type.manager'),
      $container->get('piv_contest.competition_service'),
      $container->get('form_builder'),
      $container->get('current_user')
    );
  }

  /**
   * Verify access to add entry.
   */
  public function accessAdd(UserInterface $user, CompetitionInterface $competition, ?ParagraphInterface $stream = NULL) {
    $school = $user->field_school->entity;
    if (!$school) {
      return AccessResult::forbidden();
    }

    return $this->competitionService->currentCanAddEntriesForSchoolOnCompetition($school, $competition);
  }

  /**
   * Returns the competition_entry form with some data alredy populated.
   */
  private function getCompetitionEntryForm(UserInterface $user, CompetitionInterface $competition, CompetitionEntryInterface $competition_entry) {
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
    $form_state_additions = [
      'piv_custom_weight' => TRUE,
      'redirect' => $redirect,
    ];
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
    if (!empty($form['field_student_grade']['widget']['#options'] ?? [])) {
      $allowed_grades = array_column($competition->field_allowed_grades->getValue(), 'value');
      $allowed_grades[] = '_none';
      $options = array_intersect_key($form['field_student_grade']['widget']['#options'], array_flip($allowed_grades));
      $form['field_student_grade']['widget']['#options'] = $options;
    }
    return $form;
  }

  /**
   * Build the recitations form.
   */
  private function getRecitationsForm(UserInterface $user, CompetitionInterface $competition, CompetitionEntryInterface $competition_entry) {
    $form = $this->formBuilder
      ->getForm('Drupal\piv_contest_recitation\Form\MultipleRecitationsForm', $competition, $competition_entry);
    return $form;
  }

  /**
   * Add a competition entry and return the edit form.
   */
  public function add(UserInterface $user, CompetitionInterface $competition, ParagraphInterface $stream) {
    $school = $user->field_school->target_id;
    $competition_entry = $this->entityTypeManager->getStorage('competition_entry')->create([
      'uid' => $user,
      'field_competition' => $competition->id(),
      'field_school' => $school,
      'bundle' => 'default',
      'field_stream' => $stream,
      'field_competition_current_level' => $competition->field_competition_current_level->value,
    ]);
    $competition_entry->save();
    // Redirect to the competition entry edit page.
    $url = Url::fromRoute('piv_contest.competition_entry_edit', [
      'user' => $user->id(),
      'competition' => $competition->id(),
      'competition_entry' => $competition_entry->id(),
    ]);
    return new RedirectResponse($url->toString());
  }

  /**
   * Verify access to edit entry.
   */
  public function accessEdit(UserInterface $user, CompetitionInterface $competition, CompetitionEntryInterface $competition_entry) {
    $can_update = $competition_entry->access('update', $this->currentUser, TRUE)->isAllowed();
    $result = AccessResult::allowedIf($can_update || piv_contest_user_can_bypass_permissions());
    return $result->isAllowed() ? $result : AccessResult::forbidden();
  }

  /**
   * Edit form.
   */
  public function edit(UserInterface $user, CompetitionInterface $competition, CompetitionEntryInterface $competition_entry) {
    // The competition entry form is responsible to generate the recitations
    // and save it.
    $competition_entry_form = $this->getCompetitionEntryForm($user, $competition, $competition_entry);
    $recitations_form = $this->getRecitationsForm($user, $competition, $competition_entry);
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['competition-entry-wrapper'],
      ],
      '#attached' => [
        'library' => ['piv_contest/competition-entry-controller'],
      ],
      'competition_entry_form' => $competition_entry_form,
      'recitations' => $recitations_form,
    ];
  }

}
