<?php

namespace Drupal\piv_contest\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\UserInterface;
use Drupal\piv_contest_competition\CompetitionInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;

/**
 * Returns responses for PIV Contest routes.
 */
class CompetitionController extends ControllerBase {

  use StringTranslationTrait;

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
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LanguageManager $language_manager) {
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

  /**
   * {@inheritdoc}
   */
  public function access(UserInterface $user, CompetitionInterface $competition) {
    return $competition->access('view', \Drupal::currentUser(), TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function title(CompetitionInterface $competition) {
    return $competition->label();
  }

  /**
   * Builds the competition entry form.
   */
  public function build(UserInterface $user, CompetitionInterface $competition) {
    $school = $user->field_school->entity;
    if (!$school) {
      return [];
    }
    $build['back_link'] = [
      '#theme' => 'piv_back_link',
      '#link' => Link::createFromRoute($this->t('Back to all competitions'), 'piv_content.competitions_list', [
        'user' => $user->id(),
      ]),
    ];

    $build['title'] = [
      '#type' => 'item',
      'title' => [
        '#theme' => 'page_title',
        '#title' => $competition->label(),
      ],
    ];

    $build['school'] = [
      '#type' => 'item',
      'school_title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $school->label(),
      ],
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'small',
        '#value' => $this->t('Not your school? Contact us as @email', [
          '@email' => Markup::create('<a href="mailto:info@poetryinvoice.ca">info@poetryinvoice.ca</a>'),
        ]),
      ],
    ];

    $build['competition'] = $this->entityTypeManager
      ->getViewBuilder('competition')
      ->view($competition, 'teacher_ui');

    $criterias = [];
    foreach ($competition->field_criteria as $item) {
      $criterias[] = $item->entity->label();
    }

    $message = [];

    if (count($criterias) > 0) {
      $message = [
        '#theme' => 'item_list',
        '#prefix' => '<b>' . $this->t('This competition requires that all entries contain at least one poem with each of the following criteria:') . '</b>',
        '#list_type' => 'ul',
        '#items' => $criterias,
      ];
    }

    $build['enrollement'] = [
      '#theme' => 'competition_enrollement_progress',
      '#school' => $school,
      '#competition' => $competition,
      '#teacher' => $user,
      '#message' => $message,
    ];

    return $build;
  }

}
