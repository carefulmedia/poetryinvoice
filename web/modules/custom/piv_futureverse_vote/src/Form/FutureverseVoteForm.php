<?php

namespace Drupal\piv_futureverse_vote\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\piv_futureverse_vote\PivFutureverseVoteManagerInterface;

/**
 * Provides a PIV Futureverse Vote form.
 *
 * This form will be used in modal dialogs.
 */
class FutureverseVoteForm extends FormBase {
  use AutowireTrait;

  public function __construct(
    protected PivFutureverseVoteManagerInterface $voteManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'piv_futureverse_vote_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL): array {
    if (!$node || $node->bundle() !== 'journal_poem') {
      return $form;
    }

    $form['#prefix'] = '<div id="futureverse-vote-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    $langcode = $node->language()->getId();
    $year = 2026;

    $form['langcode'] = [
      '#type' => 'value',
      '#value' => $langcode,
    ];
    $form['year'] = [
      '#type' => 'value',
      '#value' => $year,
    ];
    $form['journal_poem_id'] = [
      '#type' => 'value',
      '#value' => $node->id(),
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
        'wrapper' => 'futureverse-vote-form',
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
      $form['confirmation_text'] = [
        '#markup' => '<div class="alert alert-success">' . $this->t('Thank you for voting!') . '</div>',
        '#weight' => -100,
      ];
      $form['actions']['cancel']['#value'] = $this->t('Close');
      return $form;
    }

    // Display the poem title and author (only if not voted yet).
    $student_name = '';
    if ($node->field_legal_name_boolean->value && $node->field_legal_name->value) {
      $student_name = $node->field_legal_name->value;
    }
    elseif ($node->piv_teacher_first_name->value || $node->piv_teacher_last_name->value) {
      $student_name = trim($node->piv_teacher_first_name->value . ' ' . $node->piv_teacher_last_name->value);
    }

    $form['poem_header'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['futureverse-vote-poem-header']],
      '#weight' => -100,
      'title' => [
        '#markup' => '<h3>' . $node->label() . '</h3>',
      ],
      'author' => [
        '#markup' => $student_name ? '<p class="author">' . $this->t('by') . ' ' . $student_name . '</p>' : '',
      ],
    ];

    // Display the poem text (only if not voted yet).
    if ($node->field_journal_poem_body->value) {
      $format = $node->field_journal_poem_body->format;

      // For plain text, preserve line breaks manually.
      if ($format === 'plain_text') {
        $poem_text = nl2br(htmlspecialchars($node->field_journal_poem_body->value, ENT_QUOTES, 'UTF-8'));
      }
      else {
        // For other formats (HTML, etc.), use Drupal's text format processing.
        $poem_text = check_markup($node->field_journal_poem_body->value, $format);
      }

      $form['poem_body'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['futureverse-vote-poem-body']],
        '#weight' => -99,
        'text' => [
          '#markup' => '<div class="poem-text">' . $poem_text . '</div>',
        ],
      ];
    }

    $form['separator'] = [
      '#markup' => '<hr>',
      '#weight' => -98,
    ];

    $form['text'] = [
      '#markup' => $this->t('Please provide the following information to vote for this poem') . ':',
      '#weight' => -97,
    ];

    // If user already voted with that email, ask for confirmation.
    $ask_to_confirm_vote = $form_state->get('ask_to_confirm_vote') ?? FALSE;
    if ($ask_to_confirm_vote) {
      $form['has_voted_information'] = [
        '#type' => 'fieldset',
        'text' => [
          '#markup' => '<strong>' . $this->t('Do you want to cancel your last choice and select this poem?') . '</strong>',
        ],
      ];
      $form['actions']['submit']['#value'] = $this->t('Mark as my new choice');
    }

    // Return normal form.
    return $form;
  }

  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    return $form;
  }

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
    $journal_poem_id = $form_state->getValue('journal_poem_id');
    $name = $form_state->getValue('name');
    $email = $form_state->getValue('email');
    $langcode = $form_state->getValue('langcode');
    $year = $form_state->getValue('year');
    $form_state->setRebuild(TRUE);

    // Email already voted and user did not confirm yet.
    if (!isset($form['has_voted_information']) && $this->voteManager->hasVoted($email, $year, $langcode)) {
      $form_state->set('ask_to_confirm_vote', TRUE);
      return;
    }

    // Success.
    $this->voteManager
      ->vote($email, $name, $journal_poem_id, $year, $langcode);
    $form_state->set('voted', TRUE);
  }

}
