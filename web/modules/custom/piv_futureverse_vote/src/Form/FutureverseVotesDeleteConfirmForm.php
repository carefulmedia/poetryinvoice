<?php

namespace Drupal\piv_futureverse_vote\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\piv_futureverse_vote\PivFutureverseVoteManagerInterface;
use Drupal\piv_futureverse_vote\PivFutureverseVoteManager;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides a confirmation form for deleting futureverse votes.
 */
class FutureverseVotesDeleteConfirmForm extends ConfirmFormBase {
  use AutowireTrait;

  /**
   * The vote IDs to delete.
   *
   * @var array
   */
  protected $voteIds = [];

  public function __construct(
    protected PivFutureverseVoteManagerInterface $voteManager,
    protected PrivateTempStoreFactory $tempStoreFactory,
    protected Connection $connection,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'futureverse_votes_delete_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup|string {
    $count = count($this->voteIds);
    return $this->formatPlural(
      $count,
      'Are you sure you want to delete this vote?',
      'Are you sure you want to delete @count votes?',
      ['@count' => $count]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('piv_futureverse_vote.admin_votes');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup|string {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): RedirectResponse|array{
    // Retrieve vote IDs from tempstore.
    $tempstore = $this->tempStoreFactory->get('piv_futureverse_vote');
    $this->voteIds = $tempstore->get('delete_vote_ids') ?? [];

    if (empty($this->voteIds)) {
      $this->messenger()->addError($this->t('No votes selected for deletion.'));
      return $this->redirect('piv_futureverse_vote.admin_votes');
    }

    $form = parent::buildForm($form, $form_state);

    // Add a list of the votes being deleted.
    $votes = $this->connection->select(PivFutureverseVoteManager::TABLE_NAME, 't')
      ->fields('t')
      ->condition('id', $this->voteIds, 'IN')
      ->execute()
      ->fetchAll();

    $items = [];
    foreach ($votes as $vote) {
      $node = $this->entityTypeManager->getStorage('node')->load($vote->journal_poem_id);
      $poem_title = $node ? $node->label() : $this->t('Unknown poem');
      $items[] = $this->t('@poem by @name (@email)', [
        '@poem' => $poem_title,
        '@name' => $vote->voter_name,
        '@email' => $vote->voter_email,
      ]);
    }

    $form['votes_list'] = [
      '#theme' => 'item_list',
      '#items' => $items,
      '#title' => $this->t('The following votes will be deleted:'),
      '#weight' => -10,
    ];

    // Add danger class to the submit button.
    $form['actions']['submit']['#attributes']['class'][] = 'button--danger';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!empty($this->voteIds)) {
      $this->voteManager->delete($this->voteIds);
      $count = count($this->voteIds);
      $this->messenger()->addStatus($this->formatPlural(
        $count,
        'Deleted 1 vote.',
        'Deleted @count votes.',
        ['@count' => $count]
      ));

      // Clear the tempstore.
      $tempstore = $this->tempStoreFactory->get('piv_futureverse_vote');
      $tempstore->delete('delete_vote_ids');
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
