<?php

namespace Drupal\piv_futureverse_vote\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\piv_futureverse_vote\PivFutureverseVoteManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Return results for futureverse votes.
 */
class FutureverseVoteResultsController extends ControllerBase {

  public function __construct(
    protected Connection $connection,
    protected $entityTypeManager,
  ) {}

  /**
   * Builds the results page.
   */
  public function build(): array {
    $query = $this->connection->select(PivFutureverseVoteManager::TABLE_NAME, 't')
      ->fields('t', ['journal_poem_id', 'langcode', 'year']);
    $query->addExpression('COUNT(journal_poem_id)', 'total');
    $query->groupBy('journal_poem_id');
    $query->groupBy('langcode');
    $query->groupBy('year');
    $query->orderBy('year', 'DESC');
    $query->orderBy('total', 'DESC');
    $results = $query->execute()->fetchAll();

    $tables = [];
    $votes_total = 0;

    foreach ($results as $row) {
      $node = $this->entityTypeManager()->getStorage('node')->load($row->journal_poem_id);
      if (!$node) {
        continue;
      }

      $poem_title = $node->label();
      $author = $node->field_legal_name->value ?? $this->t('Unknown');
      $link = Link::createFromRoute($poem_title, 'entity.node.canonical', [
        'node' => $row->journal_poem_id,
      ])->toString();

      $key = $row->year . '_' . $row->langcode;
      $tables[$key]['year'] = $row->year;
      $tables[$key]['langcode'] = $row->langcode;
      $tables[$key]['rows'][] = [
        'poem' => Markup::create("{$link} by {$author}"),
        'total' => $row->total,
      ];
      $votes_total += $row->total;
    }

    $build = [];
    $build['votes_total'] = [
      '#type' => 'item',
      '#markup' => $this->t('Total votes: @total', ['@total' => $votes_total]),
    ];

    $build['tables'] = [
      '#type' => 'container',
    ];

    foreach ($tables as $key => $table_data) {
      $language_name = $table_data['langcode'] === 'en' ? 'English' : 'French';
      $table_title = $this->t('@year - @language', [
        '@year' => $table_data['year'],
        '@language' => $language_name,
      ]);

      $build['tables'][$key] = [
        '#prefix' => "<h2>{$table_title}</h2>",
        '#type' => 'table',
        '#header' => [
          'poem' => $this->t('Poem'),
          'total' => $this->t('Total Votes'),
        ],
        '#rows' => $table_data['rows'],
      ];
    }

    return $build;
  }

}
