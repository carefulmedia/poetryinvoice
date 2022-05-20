<?php

namespace Drupal\piv_popular_vote\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\piv_contest_competition\CompetitionInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Returns responses for PIV Popular Vote routes.
 */
class PivPopularVoteController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * PivPopularVoteController constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
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
  public function access(AccountInterface $account, CompetitionInterface $competition) {
    $popular_voting_enabled = !empty($competition->field_enable_popular_voting->value);
    $status = !empty($competition->status->value);
    return AccessResult::allowedIf($popular_voting_enabled && $status)
      ->addCacheableDependency($competition);
  }

  /**
   * Builds the response.
   */
  public function build(CompetitionInterface $competition) {
    $competition_entries = $this->entityTypeManager
      ->getStorage('competition_entry')
      ->loadByProperties([
        'field_competition' => $competition->id(),
        'field_competition_current_level' => $competition->field_popular_vote_level->value ?? 1,
      ]);

    $build['recitations'] = [
      '#type' => 'container',
    ];

    $current_language = $this->languageManager->getCurrentLanguage()->getId();
    // Get the first recitation for each competition entry.
    foreach ($competition_entries as $competition_entry) {
      $recitation = $competition_entry->field_recitations->entity;
      if (!$recitation || $recitation->language()->getId() != $current_language) {
        continue;
      }
      $build['recitations'][$recitation->id()] = [
        '#type' => 'container',
        'video' => $recitation->field_recitation_video->view([
          'type' => 'entity_reference_entity_view',
          'label' => 'hidden',
        ]),
        'link' => [
          '#type' => 'link',
          '#url' => Url::fromRoute('piv_popular_vote.popular_vote', [
            'competition' => $competition->id(),
            'competition_entry' => $competition_entry->id(),
          ]),
          '#attributes' => [
            'class' => ['use-ajax', 'button'],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => '{"width":800}',
          ],
          '#title' => $this->t('This is my choice'),
        ],
      ];
    }
    return $build;
  }

}
