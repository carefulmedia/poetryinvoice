<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\piv_live_competition\Helper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the TeamRegionalsEntry constraint.
 */
class TeamRegionalsEntryConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs a TeamRegionalsEntryConstraintValidator object.
   */
  public function __construct(
    private readonly Helper $helper,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('piv_live_competition.helper'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    if (!$entity instanceof NodeInterface || $entity->bundle() != 'team_regionals_entry') {
      return;
    }

    // Entities are created with a contest associated to it, if they
    // don't have one because the contest was deleted then there is no
    // reason to validate it.
    $competition = $entity->field_contest_association?->entity;
    if (!$competition) {
      return;
    }

    // Skip validation if user has permissions or user is a competition
    // admin.
    $account = $this->currentUser;
    $live_competition_admin_id = $competition->field_live_competition_admin?->target_id ?? NULL;
    if ($account->hasPermission('bypass live competition entries validation')
    || $account->id() == $live_competition_admin_id) {
      return;
    }

    // Validate competition closing date hasn't passed.
    if ($this->helper->hasCompetitionClosingDatePassed($competition)) {
      $this->context->addViolation($constraint->closingDatePassedMessage);
    }

    // Validate competition hasn't started.
    if ($this->helper->hasCompetitionStarted($competition)) {
      $this->context->addViolation($constraint->alreadyStartedMessage);
    }

    // Check if user has a school assigned.
    $teacher_school = $this->helper->getTeacherSchool($account);
    if (!$teacher_school) {
      $this->context->addViolation($constraint->noSchoolMessage);
    }

    // Validate invited schools.
    if (!$this->helper->isTeacherSchoolInvited($competition, $account)) {
      $this->context->addViolation($constraint->notInvitedMessage);
    }

    // Validate max entries per school.
    $exclude_entry_id = $entity->isNew() ? NULL : $entity->id();
    if ($this->helper->isCompetitionMaxedOutForTeacherSchool($competition, $account, $exclude_entry_id)) {
      $this->context->addViolation($constraint->maxEntriesMessage);
    }

    // Validate students in a competition entry. Default is 3 as per
    // field default value and description.
    // While the field name says "maximum", it is in fact the exact
    // number that is required.
    $max_students = (int) $competition->field_maximum_students_per_entry->value ?? 3;
    if ($max_students > 0) {
      if (count($entity->field_tr_student) !== $max_students) {
        $this->context->buildViolation($constraint->maxStudentsMessage)
          ->setParameter('%count', $max_students)
          ->setPlural($max_students)
          ->addViolation();
      }
    }
  }

}
