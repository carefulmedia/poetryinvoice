<?php

namespace Drupal\piv_popular_vote\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\piv_contest_competition\CompetitionInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Link;
use Drupal\Core\Database\Connection;
use Drupal\piv_popular_vote\PivPopularVoteManager;
use Drupal\Core\Render\Markup;

/**
 * Return results for the popular vote.
 */
class PopularVoteResultsController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * PivPopularVoteController constructor.
   */
  final public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $connection) {
    $this->entityTypeManager = $entity_type_manager;
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account, CompetitionInterface $competition) {
    $popular_voting_enabled = !empty($competition->field_enable_popular_voting->value);
    $status = !empty($competition->status->value);
    $can_update = $competition->access('update', $competition);
    return AccessResult::allowedIf($popular_voting_enabled && $status && $can_update)
      ->addCacheableDependency($competition);
  }

  /**
   * Builds the response.
   */
  public function build(CompetitionInterface $competition) {
    $level = $competition->field_popular_vote_level->value ?? 1;
    $competition_entry_storage = $this->entityTypeManager->getStorage('competition_entry');
    $competition_entry_ids = $competition_entry_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_competition', $competition->id())
      ->condition('field_competition_current_level', $level, '>=')
      ->execute();
    $competition_entries = $competition_entry_ids
      ? $competition_entry_storage->loadMultiple($competition_entry_ids)
      : [];
    $query = $this->connection->select(PivPopularVoteManager::TABLE_NAME, 't')
      ->condition('competition_id', $competition->id())
      ->fields('t', ['competition_entry_id']);
    $query->addExpression('COUNT(competition_entry_id)', 'total');
    $query->groupBy('competition_entry_id');
    $query->orderBy('total', 'DESC');
    $results = $query->execute()->fetchAllKeyed();

    $tables = [];
    $languages = [];
    $votes_total = 0;
    foreach ($competition_entries as $competition_entry) {
      // Use the second recitation.
      $recitation = $competition_entry->field_recitations[1]->entity;
      if (!$recitation) {
        continue;
      }
      $language = $recitation->field_stream_language->entity ?? $recitation->language();
      $langcode = $language->getId();
      if (empty($languages[$langcode])) {
        $languages[$langcode] = $language->getName();
      }
      $total = $results[$competition_entry->id()] ?? 0;
      $tables[$langcode][$competition_entry->id()] = (int) $total;
      $votes_total += $total;
    }

    $build = [];
    $build['tables'] = [
      '#type' => 'container',
    ];
    $build['votes_total'] = [
      '#type' => 'item',
      '#markup' => $this->t('Total votes: @total', ['@total' => $votes_total]),
    ];
    foreach ($tables as $langcode => $rows) {
      arsort($rows);
      $rows2 = [];
      foreach ($rows as $competition_entry_id => $total) {
        $contestant_name = piv_popular_vote_get_student_name($competition_entry_id);
        $link = Link::createFromRoute($competition_entry_id, 'entity.competition_entry.edit_form', [
          'competition_entry' => $competition_entry_id,
        ])->toString();
        $contestant = Markup::create("{$contestant_name} ({$link})");
        $rows2[] = [
          'contestant' => $contestant,
          'total' => $total,
        ];
      }

      $table_title = $this->t('@language stream', [
        '@language' => $languages[$langcode],
      ]);
      $build['tables'][$langcode] = [
        '#prefix' => "<h2>{$table_title}</h2>",
        '#type' => 'table',
        '#header' => [
          'contestant' => $this->t('Contestant'),
          'total' => $this->t('Total Votes'),
        ],
        '#rows' => $rows2,
      ];
    }

    return $build;
  }

}
