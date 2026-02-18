<?php

namespace Drupal\piv_futureverse_vote\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Database\Query\TableSortExtender;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\piv_futureverse_vote\PivFutureverseVoteManagerInterface;
use Drupal\piv_futureverse_vote\PivFutureverseVoteManager;

/**
 * Provides a table form for managing futureverse votes.
 */
class FutureverseVotesAdminTableForm extends FormBase {
  use AutowireTrait;

  public function __construct(
    protected Connection $connection,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DateFormatterInterface $dateFormatter,
    protected PivFutureverseVoteManagerInterface $voteManager,
    protected PrivateTempStoreFactory $tempStoreFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'futureverse_votes_admin_table_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, FormStateInterface $filter_form_state = NULL): array {
    if (!$filter_form_state) {
      return $form;
    }

    // Get filter values from the filter form.
    $year_filter = $filter_form_state->getValue('year') ?? 0;
    $langcode_filter = $filter_form_state->getValue('langcode') ?? 0;
    $poem_id_filter = $filter_form_state->getValue('journal_poem_id') ?? '';
    $voter_email_filter = $filter_form_state->getValue('voter_email') ?? '';

    $header = [
      'poem' => [
        'data' => $this->t('Poem'),
        'field' => 'journal_poem_id',
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
      'year' => [
        'data' => $this->t('Year'),
        'field' => 'year',
      ],
      'created' => [
        'data' => $this->t('Created'),
        'field' => 'created',
      ],
    ];

    $query = $this->connection->select(PivFutureverseVoteManager::TABLE_NAME, 't')
      ->fields('t');

    // Apply filters.
    if ($year_filter) {
      $query->condition('year', $year_filter);
    }
    if ($langcode_filter) {
      $query->condition('langcode', $langcode_filter);
    }
    if ($poem_id_filter) {
      $query->condition('journal_poem_id', $poem_id_filter);
    }
    if ($voter_email_filter) {
      $query->condition('voter_email', $voter_email_filter, 'LIKE');
    }

    $query = $query->extend(TableSortExtender::class)
      ->orderByHeader($header);
    $query = $query->extend(PagerSelectExtender::class)
      ->limit(50);

    $results = $query->execute();
    $rows = [];

    foreach ($results as $row) {
      $node = $this->entityTypeManager->getStorage('node')->load($row->journal_poem_id);
      $poem_title = $node ? $node->label() : $this->t('Unknown');
      $link = $node ? Link::createFromRoute($poem_title, 'entity.node.canonical', [
        'node' => $row->journal_poem_id,
      ])->toString() : $poem_title;

      $language_name = $row->langcode === 'en' ? $this->t('English') : $this->t('French');

      $rows[$row->id] = [
        'poem' => Markup::create($link),
        'voter_email' => $row->voter_email,
        'voter_name' => $row->voter_name,
        'langcode' => $language_name,
        'year' => $row->year,
        'created' => $this->dateFormatter->format($row->created),
      ];
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
      '#button_type' => 'danger',
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
    $selected_votes = array_filter($form_state->getValue('votes_table'));
    if (empty($selected_votes)) {
      $form_state->setErrorByName('votes_table', $this->t('Please select at least one vote to delete.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $selected_votes = array_filter($form_state->getValue('votes_table'));
    if ($selected_votes) {
      // Store the vote IDs in tempstore for the confirmation form.
      $tempstore = $this->tempStoreFactory->get('piv_futureverse_vote');
      $tempstore->set('delete_vote_ids', array_keys($selected_votes));

      // Redirect to confirmation form.
      $form_state->setRedirect('piv_futureverse_vote.admin_votes_delete_confirm');
    }
  }

}
