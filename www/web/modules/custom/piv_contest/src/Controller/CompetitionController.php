<?php

namespace Drupal\piv_contest\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\UserInterface;
use Drupal\piv_contest_competition\CompetitionInterface;
use Drupal\Core\Url;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Access\AccessResult;

/**
 * Returns responses for PIV Contest routes.
 */
class CompetitionController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current language code.
   *
   * @var \Drupal\Core\Language\Language
   */
  protected $currentLanguage;

  /**
   * The controller constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LanguageManager $language_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentLanguage = $language_manager->getCurrentLanguage();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('language_manager')
    );
  }

  public function access(UserInterface $user, CompetitionInterface $competition) {
    return AccessResult::allowed();
  }

  public function title(CompetitionInterface $competition) {
    return $competition->label();
  }

  /**
   * Builds the competition entry form.
   */
  public function build(UserInterface $user, CompetitionInterface $competition) {
    $school = $user->field_school->target_id;
    if (!$school) {
      return [];
    }

    $competition_entries = $this->entityTypeManager->getStorage('competition_entry')->loadByProperties([
      'field_school' => $school,
      'field_competition' => $competition->id(),
    ]);

    $build['enrollement'] = [
      '#theme' => 'competition_enrollement_progress',
      '#school' => $user->field_school->entity,
      '#competition' => $competition,
    ];

    $build['new'] = [
      '#type' => 'link',
      '#title' => 'Add new entry',
      '#url' => Url::fromRoute('piv_contest.competition_entry_add', [
        'user' => $user->id(),
        'competition' => $competition->id(),
        'stream' => 3
      ]),
    ];
    foreach ($competition_entries as $entry) {
      \Drupal::service('piv_contest.competition_service')->verifyEntryIsComplete($entry); //@todo

      $build[] = [
        'title' => [
          '#markup' => $entry->label(),
          '#type' => 'item',
        ],
        'link' => [
          '#type' => 'link',
          '#title' => 'edit',
          '#url' => Url::fromRoute('piv_contest.competition_entry_edit', [
            'user' => $user->id(),
            'competition' => $competition->id(),
            'competition_entry' => $entry->id(),
          ]),
        ],
      ];
    }

    return $build;

  }
}
