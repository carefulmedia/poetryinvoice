<?php

namespace Drupal\piv_futureverse_vote\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Drupal\piv_futureverse_vote\PivFutureverseVoteManager;

/**
 * Provides a filter form for futureverse votes admin page.
 */
class FutureverseVotesAdminFilterForm extends FormBase {
  use AutowireTrait;

  public function __construct(
    protected Connection $connection,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'futureverse_votes_admin_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Get unique years from votes.
    $years = $this->connection->select(PivFutureverseVoteManager::TABLE_NAME, 't')
      ->fields('t', ['year'])
      ->distinct()
      ->orderBy('year', 'DESC')
      ->execute()
      ->fetchCol();

    $year_options = [0 => $this->t('- All years -')];
    foreach ($years as $year) {
      $year_options[$year] = $year;
    }

    // Get unique languages from votes.
    $langcodes = $this->connection->select(PivFutureverseVoteManager::TABLE_NAME, 't')
      ->fields('t', ['langcode'])
      ->distinct()
      ->execute()
      ->fetchCol();

    $language_options = [0 => $this->t('- All languages -')];
    foreach ($langcodes as $langcode) {
      $language_options[$langcode] = $langcode === 'en' ? $this->t('English') : $this->t('French');
    }

    $form['filters'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Filter votes'),
      '#open' => TRUE,
    ];

    $form['filters']['year'] = [
      '#type' => 'select',
      '#title' => $this->t('Year'),
      '#options' => $year_options,
      '#default_value' => $form_state->getValue('year') ?? 0,
    ];

    $form['filters']['langcode'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $language_options,
      '#default_value' => $form_state->getValue('langcode') ?? 0,
    ];

    $form['filters']['journal_poem_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Poem ID'),
      '#size' => 20,
      '#default_value' => $form_state->getValue('journal_poem_id') ?? '',
    ];

    $form['filters']['voter_email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Voter Email'),
      '#size' => 30,
      '#default_value' => $form_state->getValue('voter_email') ?? '',
    ];

    $form['filters']['actions'] = [
      '#type' => 'actions',
    ];

    $form['filters']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
    ];

    $form['filters']['actions']['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset'),
      '#submit' => ['::resetForm'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Validate poem ID is numeric if provided.
    $poem_id = $form_state->getValue('journal_poem_id');
    if (!empty($poem_id) && !is_numeric($poem_id)) {
      $form_state->setErrorByName('journal_poem_id', $this->t('Poem ID must be a number.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Form state values are used by the table form.
    $form_state->setRebuild(TRUE);
  }

  /**
   * Reset form handler.
   */
  public function resetForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setValues([
      'year' => 0,
      'langcode' => 0,
      'journal_poem_id' => '',
      'voter_email' => '',
    ]);
    $form_state->setRebuild(TRUE);
  }

}
