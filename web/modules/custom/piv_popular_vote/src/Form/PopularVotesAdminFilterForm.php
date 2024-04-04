<?php

namespace Drupal\piv_popular_vote\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\piv_contest_competition\CompetitionInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\piv_popular_vote\PivPopularVoteManager;
use Drupal\Core\Language\LanguageManager;

/**
 * Provides a PIV Popular Vote form.
 */
class PopularVotesAdminFilterForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'popular_votes_admin_filter_form';
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
   * The Language Manager.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * Constructs a PivPopularVoteManager object.
   */
  public function __construct(Connection $connection, PivPopularVoteManager $piv_popular_vote_manager, LanguageManager $language_manager) {
    $this->connection = $connection;
    $this->pivPopularVoteManager = $piv_popular_vote_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('piv_popular_vote.manager'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, CompetitionInterface $competition = NULL) {
    if (!$competition) {
      return $form;
    }

    $form = [];
    $form['filters'] = [
      '#type' => 'fieldset',
    ];
    // Get all competition entries and load the contestant names from there.
    $competition_entries = $this->connection->select(PivPopularVoteManager::TABLE_NAME, 't')
      ->fields('t', ['competition_entry_id'])
      ->condition('competition_id', $competition->id())
      ->distinct()
      ->execute()
      ->fetchCol();
    $contestants = [$this->t('- all -')];
    foreach ($competition_entries as $competition_entry_id) {
      $contestants[$competition_entry_id] = piv_popular_vote_get_student_name($competition_entry_id);
    }
    $contestants = array_filter($contestants);
    $languages = [$this->t('- all -')];
    foreach ($this->languageManager->getLanguages() as $langcode => $language) {
      $languages[$langcode] = $language->getName();
    }
    // Add to the form state to access these values in the table form.
    $form_state->set('contestants', $contestants);
    $form_state->set('languages', $languages);

    $form['filters']['competition_entry_id'] = [
      '#type' => 'select',
      '#options' => $contestants,
      '#title' => 'Contestant',
    ];
    $form['filters']['langcode'] = [
      '#type' => 'select',
      '#options' => $languages,
      '#title' => 'Stream language',
    ];
    $form['filters']['actions'] = [
      '#type' => 'actions',
    ];
    $form['filters']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
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
  }

}
