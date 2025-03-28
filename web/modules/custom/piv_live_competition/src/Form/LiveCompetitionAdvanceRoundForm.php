<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\piv_live_competition\Helper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a PIV Live Competition form.
 *
 * This is a single button that when clicked advance the round.
 */
final class LiveCompetitionAdvanceRoundForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected readonly Helper $helper,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('piv_live_competition.helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'piv_live_competition_live_competition_advance_round';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL, ?bool $incomplete = FALSE): array {
    $form['#cache'] = ['max-age' => 0];
    $current_round = $node->field_active_round->value;
    $is_last_round = FALSE;
    if (!is_numeric($current_round) || $current_round < 0) {
      $current_round = 0;
    }
    else {
      $total_rounds = count($this->helper->getRecitationsInOrder($node));
      if ($current_round >= $total_rounds) {
        $is_last_round = TRUE;
      }
    }
    if (!$is_last_round) {
      $form['node'] = [
        '#type' => 'value',
        '#value' => $node,
      ];
      $form['next_round'] = [
        '#type' => 'value',
        '#value' => $current_round + 1,
      ];
      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Start round @round', [
          '@round' => $current_round + 1,
        ]),
      ];
      if ($incomplete === TRUE) {
        $form['submit']['#attributes']['class'][] = 'incomplete-round';
      }
    }
    else {
      $form['message'] = [
        '#type' => 'item',
        '#markup' => $this->t('<b>Last round.</b>'),
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $node = $form_state->getValue('node');
    $current_round = $node->field_active_round->value;
    if (!is_numeric($current_round) || $current_round < 0) {
      $current_round = 0;
    }
    $next_round = $form_state->getValue('next_round');
    // Prevent admins from updating deprecated values to the next round,
    // in case another admin already advanced the round and this form
    // is now obsolete.
    if (($current_round + 1) != $next_round) {
      $form_state->setError($form, $this->t('The form has become outdated. Please reload the page.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $node = $form_state->getValue('node');
    $node->field_active_round = $form_state->getValue('next_round');
    $node->save();
    $form_state->setRedirect('piv_live_competition.monitor_dashboard', [
      'node' => $node->id(),
    ]);
  }

}
