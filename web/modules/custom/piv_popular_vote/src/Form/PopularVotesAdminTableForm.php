<?php

namespace Drupal\piv_popular_vote\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\piv_contest_competition\CompetitionInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\piv_popular_vote\PivPopularVoteManager;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Datetime\DateFormatter;

/**
 * Provides a PIV Popular Vote form.
 */
class PopularVotesAdminTableForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'popular_votes_admin_table_form';
  }

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The Piv Popular Vote Manager.
   *
   * @var \Drupal\piv_popular_vote\PivPopularVoteManager
   */
  protected $pivPopularVoteManager;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * Constructs a PivPopularVoteManager object.
   */
  public function __construct(Connection $connection, PivPopularVoteManager $piv_popular_vote_manager, DateFormatter $date_formatter) {
    $this->connection = $connection;
    $this->pivPopularVoteManager = $piv_popular_vote_manager;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('piv_popular_vote.manager'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, FormStateInterface $filter_form_state = NULL, CompetitionInterface $competition = NULL) {
    if (!$competition) {
      return $form;
    }

    $form['filter_form_state'] = [
      '#type' => 'value',
      '#value' => $filter_form_state,
    ];
    // Contestants names were calculated in the filter form state.
    $contestants = $filter_form_state->get('contestants');
    $languages = $filter_form_state->get('languages');
    $competition_entry_id_filter = $filter_form_state->getValue('competition_entry_id') ?? 0;
    $langcode_filter = $filter_form_state->getValue('langcode') ?? 0;
    $header = [
      'contestant' => [
        'data' => $this->t('Contestant'),
        'field' => 'competition_entry_id',
      ],
      'voter_email' => [
        'data' => $this->t('Voter Email'),
        'field' => 'voter_email',
      ],
      'voter_name' => [
        'data' => $this->t('Voter Name'),
        'field' => 'voter_name',
      ],
      'langcode' => [
        'data' => $this->t('Language'),
        'field' => 'langcode',
      ],
      'created' => [
        'data' => $this->t('Created'),
        'field' => 'created',
      ],
    ];
    $query = $this->connection->select(PivPopularVoteManager::TABLE_NAME, 't')
      ->condition('competition_id', $competition->id())
      ->fields('t');
    $query = $query->extend('Drupal\Core\Database\Query\TableSortExtender')
      ->orderByHeader($header);
    $query = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->limit(50);
    if ($competition_entry_id_filter) {
      $query->condition('competition_entry_id', $competition_entry_id_filter);
    }
    if ($langcode_filter) {
      $query->condition('langcode', $langcode_filter);
    }
    $results = $query->execute();
    $rows = [];
    foreach ($results as $row) {
      $rows[$row->id] = (array) $row;
      $link = Link::createFromRoute($row->competition_entry_id, 'entity.competition_entry.edit_form', [
        'competition_entry' => $row->competition_entry_id,
      ])->toString();
      $competition_entry_id = $row->competition_entry_id;
      $contestant = Markup::create("{$contestants[$competition_entry_id]} ({$link})");
      $rows[$row->id]['contestant'] = $contestant;
      $rows[$row->id]['created'] = $this->dateFormatter->format($row->created);
      $rows[$row->id]['langcode'] = $languages[$row->langcode];
    }
    $form['votes_table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#empty' => $this->t('No votes found.'),
    ];
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete selected'),
    ];
    $form['pager'] = [
      '#type' => 'pager',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setRebuild();
    $selected_votes = array_filter($form_state->getValue('votes_table'));
    if ($selected_votes) {
      $this->pivPopularVoteManager->delete($selected_votes);
    }
    $this->messenger()->addMessage($this->t('@count vote(s) deleted.', [
      '@count' => count($selected_votes),
    ]));
  }

}
