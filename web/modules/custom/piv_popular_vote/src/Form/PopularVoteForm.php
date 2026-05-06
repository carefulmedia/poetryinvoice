<?php

namespace Drupal\piv_popular_vote\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\piv_contest_competition\CompetitionInterface;
use Drupal\piv_contest_competition_entry\CompetitionEntryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\piv_popular_vote\PivPopularVoteManager;

/**
 * Provides a PIV Popular Vote form.
 */
class PopularVoteForm extends FormBase {

  /**
   * The Piv Popular Vote Manager.
   *
   * @var \Drupal\piv_popular_vote\PivPopularVoteManager
   */
  protected $pivPopularVoteManager;

  /**
   * {@inheritdoc}
   */
  final public function __construct(PivPopularVoteManager $piv_popular_vote_manager) {
    $this->pivPopularVoteManager = $piv_popular_vote_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('piv_popular_vote.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'piv_popular_vote_popular_vote';
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account, CompetitionInterface $competition, CompetitionEntryInterface $competition_entry) {
    $popular_voting_enabled = !empty($competition->field_enable_popular_voting->value);
    $enable_the_voting_ui = !empty($competition->field_enable_the_voting_ui->value);
    $status = !empty($competition->status->value);
    $competition_entry_is_for_competition = $competition_entry->field_competition->target_id == $competition->id();
    return AccessResult::allowedIf($competition_entry_is_for_competition && $popular_voting_enabled && $enable_the_voting_ui && $status)
      ->addCacheableDependency($competition);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?CompetitionInterface $competition = NULL, ?CompetitionEntryInterface $competition_entry = NULL) {
    $form['#prefix'] = '<div id="popular-vote-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $form['text'] = [
      '#markup' => $this->t('Please provide the following information'),
    ];

    $recitation = $competition_entry->field_recitations->entity;
    if (!$recitation) {
      return $form;
    }
    $language = $recitation->field_stream_language->entity ?? $recitation->language();

    $form['langcode'] = [
      '#type' => 'value',
      '#value' => $language->getId(),
    ];
    $form['competition'] = [
      '#type' => 'value',
      '#value' => $competition->id(),
    ];
    $form['competition_entry'] = [
      '#type' => 'value',
      '#value' => $competition_entry->id(),
    ];
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('My name'),
      '#required' => TRUE,
    ];
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
    ];
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Mark as my choice'),
      '#ajax' => [
        'callback' => [$this, 'ajaxSubmit'],
        'wrapper' => 'popular-vote-form',
        'event' => 'click',
      ],
    ];
    $form['actions']['cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
      '#ajax' => [
        'callback' => [$this, 'ajaxCloseDialog'],
        'event' => 'click',
      ],
    ];

    // If user already voted, display a success message instead.
    $voted = $form_state->get('voted') ?? FALSE;
    if ($voted) {
      $form['name']['#access'] = FALSE;
      $form['email']['#access'] = FALSE;
      $form['actions']['submit']['#access'] = FALSE;
      $form['text'] = [
        '#markup' => $this->t('Thank you for voting!'),
      ];
      $form['actions']['cancel']['#value'] = $this->t('Close');
      return $form;
    }

    // If user already voted with that email, ask for confirmation.
    $ask_to_confirm_vote = $form_state->get('ask_to_confirm_vote') ?? FALSE;
    if ($ask_to_confirm_vote) {
      $form['has_voted_information'] = [
        '#weight' => -1,
        '#type' => 'fieldset',
        'text' => [
          '#markup' => $this->t('Do you want to cancel your last choice, and select this recitation?'),
        ],
      ];
      $form['actions']['submit']['#value'] = $this->t('Mark as my new choice');
      return $form;
    }

    // Return normal form.
    return $form;
  }

  /**
   * Ajax callback.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Ajax callback.
   */
  public function ajaxCloseDialog(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Nothing to validate.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Create vote.
    $competition_entry = $form_state->getValue('competition_entry');
    $competition = $form_state->getValue('competition');
    $name = $form_state->getValue('name');
    $email = $form_state->getValue('email');
    $langcode = $form_state->getValue('langcode');
    $form_state->setRebuild(TRUE);

    // Email already voted and user did not confirm yet.
    if (!isset($form['has_voted_information']) && $this->pivPopularVoteManager->hasVoted($email, $competition, $langcode)) {
      $form_state->set('ask_to_confirm_vote', TRUE);
      return;
    }

    // Success.
    $this->pivPopularVoteManager
      ->vote($email, $name, $competition_entry, $competition, $langcode);
    $form_state->set('voted', TRUE);
  }

}
