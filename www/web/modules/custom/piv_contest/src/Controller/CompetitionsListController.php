<?php

namespace Drupal\piv_contest\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\UserInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Core\Url;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Returns responses for PIV Contest routes.
 */
class CompetitionsListController extends ControllerBase {

  use StringTranslationTrait;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The controller constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(Connection $connection, AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    $this->connection = $connection;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('current_user'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    $is_allowed = TRUE;
    if (!in_array('teacher', $account->getRoles())) {
      $is_allowed = FALSE;
    }
    return AccessResult::allowedIf($is_allowed);
  }

  /**
   * Builds the response.
   */
  public function build(UserInterface $user) {
    $school = $user->field_school->target_id;
    if (!$school) {
      return ['#markup' => $this->t('No school associated with teacher account.')];
    }

    $now = (new DrupalDatetime('now'))
      ->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    // Maybe this could move to the factory method as constructor
    // arguments.
    $competition_storage = $this->entityTypeManager
      ->getStorage('competition');
    $competition_entry_storage = $this->entityTypeManager
      ->getStorage('competition_entry');
    $competition_view_builder = $this->entityTypeManager
      ->getViewBuilder('competition');

    $query = $competition_storage->getQuery();
    $query->condition('field_open_date', $now, '<')
      ->condition('field_submission_deadline', $now, '>')
      ->condition('field_active', TRUE);
    $invited_only_condition = $query->orConditionGroup();
    $invited_only_condition->condition('field_by_invitation_only', FALSE);
    $invited_only_condition->condition('field_invited_schools', $school);
    $query->condition($invited_only_condition);
    $results = $query->execute();
    if (!$results) {
      return ['#markup' => $this->t('No active competitions.')];
    }

    // Get the number of entries per competition.
    $competitions = $competition_storage->loadMultiple($results);
    $competitions_entries = [];
    foreach ($competitions as $id => $competition) {
      $competitions_entries[$id] = $competition_entry_storage->loadByProperties([
        'field_competition' => $id,
        'field_school' => $school,
      ]);
    }

    $competitions_with_entries = array_filter($competitions, function ($competition) use ($competitions_entries) {
      return count($competitions_entries[$competition->id()]);
    });
    $competitions_without_entries = array_diff_key($competitions, $competitions_with_entries);

    // Competition is active.
    $build['competition'] = [
      '#type' => 'container',
    ];

    if ($competitions_with_entries) {
      $build['competition']['with_entries'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Competitions in which your school is participating'),
      ];
      foreach ($competitions_with_entries as $id => $competition) {
        $build['competition']['with_entries'][] = [
          'competition' => $competition_view_builder->view($competition, 'teaser'),
          'link' => [
            '#type' => 'link',
            '#title' => $this->t('Manage your competition entries'),
            '#url' => Url::fromRoute('piv_contest.competition', [
              'user' => $user->id(),
              'competition' => $id,
            ]),
          ],
        ];
      }
    }

    if ($competitions_without_entries) {
      $build['competition']['without_entries'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Competitions in which your school can enroll'),
      ];

      foreach ($competitions_without_entries as $id => $competition) {
        $build['competition']['without_entries'][] = [
          'competition' => $competition_view_builder->view($competition, 'teaser'),
          'link' => [
            '#type' => 'link',
            '#title' => $this->t('Enroll your school'),
            '#url' => Url::fromRoute('piv_contest.competition', [
              'user' => $user->id(),
              'competition' => $id,
            ]),
          ],
        ];
      }
    }
    return $build;
  }

}
