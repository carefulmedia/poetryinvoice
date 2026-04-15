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
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

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
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The controller constructor.
   */
  final public function __construct(Connection $connection, AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager) {
    $this->connection = $connection;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access(UserInterface $user) {
    $permissions = ['view all competitions'];
    if ($user) {
      $user_storage = $this->entityTypeManager->getStorage('user');
      $logger_user = $user_storage->load($this->currentUser->id());

      $logger_user_school = $logger_user->field_school->target_id;
      $user_school = $user->field_school->target_id;

      if ($user_school === $logger_user_school) {
        $permissions[] = 'view own school competitions';
      }
    }

    return AccessResult::allowedIfHasPermissions($this->currentUser, $permissions, 'OR');
  }

  /**
   * Builds the response.
   */
  public function build(UserInterface $user) {
    $school = $user->field_school->target_id;
    if (!$school || piv_contest_school_is_excluded($school)) {
      return ['#markup' => $this->t('No school associated with teacher account.')];
    }

    $now = (new DrupalDatetime('now'));
    $now_formatted = $now->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    // Maybe this could move to the factory method as constructor
    // arguments.
    $competition_storage = $this->entityTypeManager
      ->getStorage('competition');
    $competition_entry_storage = $this->entityTypeManager
      ->getStorage('competition_entry');
    $competition_view_builder = $this->entityTypeManager
      ->getViewBuilder('competition');
    $query = $competition_storage->getQuery()->accessCheck(FALSE);
    if (!piv_contest_user_can_bypass_permissions()) {
      // Filter out past submissions if logged in user can't see it.
      $query->condition('field_submission_deadline', $now_formatted, '>');
    }
    $query->condition('field_active', TRUE);
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
    $current_langcode = $this->languageManager->getCurrentLanguage()->getId();
    foreach ($competitions as $id => $competition) {
      if ($competition->hasTranslation($current_langcode)) {
        $competitions[$id] = $competition->getTranslation($current_langcode);
      }
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
        '#title' => $this->t('Current Contests'),
        '#access' => FALSE,
      ];
      $build['competition']['closed_registration'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Closed registration'),
        'intro' => [
          '#markup' => $this->t('<small>Competitions where the submission deadline expired already, intended for admins to be able to edit it.</small>'),
        ],
        '#access' => FALSE,
      ];

      foreach ($competitions_with_entries as $id => $competition) {
        $section = $competition->field_submission_deadline->date > $now
          ? 'with_entries'
          : 'closed_registration';
        $build['competition'][$section]['#access'] = TRUE;
        $build['competition'][$section][] = [
          '#type' => 'details',
          '#title' => $competition->label(),
          '#attributes' => [
            'class' => ['full-width piv-competition'],
          ],
          'competition' => $competition_view_builder->view($competition, 'teaser'),
          'link' => [
            '#type' => 'link',
            '#title' => $this->t('Manage your competition entries'),
            '#attributes' => [
              'class' => ['button'],
            ],
            '#url' => Url::fromRoute('piv_contest.competition', [
              'user' => $user->id(),
              'competition' => $id,
            ]),
          ],
        ];
      }
      if (!piv_contest_user_can_bypass_permissions()) {
        $build['competition']['closed_registration']['#access'] = FALSE;
      }
    }

    $build['competition']['without_entries'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Competitions in which your school can enroll'),
      '#access' => FALSE,
    ];
    $build['competition']['future_competitions'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Future competitions'),
      '#access' => FALSE,
    ];

    $alert_expired_deadline = [
      '#theme' => 'status_messages',
      '#message_list' => [
        'warning' => [
          $this->t('Competition deadline already expired. Only enroll if you know what you are doing.'),
        ],
      ],
      '#status_headings' => [
        'status' => $this->t('Status message'),
        'error' => $this->t('Error message'),
        'warning' => $this->t('Warning message'),
      ],
    ];

    foreach ($competitions_without_entries as $id => $competition) {
      $in_future = $competition->field_open_date->date && $competition->field_open_date->date > $now;
      $is_closed = $competition->field_submission_deadline->date && $competition->field_submission_deadline->date <= $now;
      $fieldset = $in_future ? 'future_competitions' : 'without_entries';
      $build['competition'][$fieldset]['#access'] = TRUE;
      $message = $is_closed ? $alert_expired_deadline : [];
      $build['competition'][$fieldset][] = [
        '#type' => 'details',
        '#title' => $competition->label(),
        '#attributes' => [
          'class' => ['full-width piv-competition'],
        ],
        'message' => $message,
        'competition' => $competition_view_builder->view($competition, 'teaser'),
        'link' => [
          '#type' => 'link',
          '#title' => $this->t('Enroll your school'),
          '#attributes' => [
            'class' => ['button'],
          ],
          '#url' => Url::fromRoute('piv_contest.competition', [
            'user' => $user->id(),
            'competition' => $id,
          ]),
          '#access' => !$in_future,
        ],
      ];
    }
    return $build;
  }

  /**
   * Redirect to the competitions page or to login page.
   */
  public function onlineSemifinalsRedirect() {
    if ($this->currentUser->isAnonymous()) {
      // Redirect to the login page with the competition list as a destination.
      $destination = Url::fromRoute('piv_contest.online_semifinals_redirect')->toString();
      $login_url = Url::fromRoute('user.login', [], [
        'query' => [
          'destination' => $destination,
        ],
      ])->toString();
      return new RedirectResponse($login_url);
    }

    // Else redirect to the competitions list.
    $url = Url::fromRoute('piv_content.competitions_list', [
      'user' => $this->currentUser->id(),
    ])->toString();
    return new RedirectResponse($url);
  }

}
