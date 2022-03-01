<?php

namespace Drupal\piv_contest\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
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

    $build['competition'] = $this->entityTypeManager
      ->getViewBuilder('competition')
      ->view($competition, 'teacher_ui');

    $build['enrollement'] = [
      '#theme' => 'competition_enrollement_progress',
      '#school' => $user->field_school->entity,
      '#competition' => $competition,
      '#teacher' => $user,
    ];

    return $build;

  }
}
