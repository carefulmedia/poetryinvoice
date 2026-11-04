<?php

declare(strict_types=1);

namespace Drupal\piv_futureverse\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Edit the thank-you messages shown after a public submission.
 */
final class SubmissionMessagesForm extends ConfigFormBase {

  /**
   * Constructs the form.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    protected LanguageManagerInterface $languageManager,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'piv_futureverse_submission_messages';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['piv_futureverse.submission_messages'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $settings = $this->config('piv_futureverse.submission_messages');
    $defaults = piv_futureverse_submission_message_defaults();

    $form['intro'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('These messages appear after a public submission. The journal poem thank-you and fifth-poem messages use the thank-you modal. The monthly limit message is shown if someone tries to submit a sixth poem. Staff editing the same records in the admin keep the usual save message and can exceed the limit.'),
    ];

    $form['english'] = [
      '#type' => 'details',
      '#title' => $this->t('English'),
      '#open' => TRUE,
    ];
    $form['english']['journal_poem'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Journal poem'),
      '#description' => $this->t('Shown after a student or teacher submits a journal poem.'),
      '#default_value' => $settings->get('journal_poem') ?: $defaults['journal_poem'],
      '#required' => TRUE,
      '#maxlength' => 255,
    ];
    $form['english']['journal_poem_fifth'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Journal poem — fifth this month'),
      '#description' => $this->t('Shown after the fifth journal poem from the same email in the current journal month.'),
      '#default_value' => $settings->get('journal_poem_fifth') ?: $defaults['journal_poem_fifth'],
      '#required' => TRUE,
      '#rows' => 2,
    ];
    $form['english']['journal_poem_limit'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Journal poem — monthly limit'),
      '#description' => $this->t('Shown when the same email tries to submit a sixth poem in the current journal month.'),
      '#default_value' => $settings->get('journal_poem_limit') ?: $defaults['journal_poem_limit'],
      '#required' => TRUE,
      '#rows' => 2,
    ];
    $form['english']['futureverse_application'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Futureverse application'),
      '#description' => $this->t('Shown after a futureverse application is submitted from its invitation link.'),
      '#default_value' => $settings->get('futureverse_application') ?: $defaults['futureverse_application'],
      '#required' => TRUE,
      '#maxlength' => 255,
    ];
    $form['english']['poet_bio'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Poet bio'),
      '#description' => $this->t('Shown after a poet bio is submitted from its invitation link.'),
      '#default_value' => $settings->get('poet_bio') ?: $defaults['poet_bio'],
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    if ($french = $this->languageManager->getLanguage('fr')) {
      $override = $this->languageManager->getLanguageConfigOverride('fr', 'piv_futureverse.submission_messages');
      $form['french'] = [
        '#type' => 'details',
        '#title' => $this->t('French'),
        '#open' => TRUE,
      ];
      $form['french']['journal_poem_fr'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Journal poem'),
        '#default_value' => $override->get('journal_poem') ?: $defaults['journal_poem'],
        '#required' => TRUE,
        '#maxlength' => 255,
      ];
      $form['french']['journal_poem_fifth_fr'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Journal poem — fifth this month'),
        '#default_value' => $override->get('journal_poem_fifth') ?: $defaults['journal_poem_fifth'],
        '#required' => TRUE,
        '#rows' => 2,
      ];
      $form['french']['journal_poem_limit_fr'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Journal poem — monthly limit'),
        '#default_value' => $override->get('journal_poem_limit') ?: $defaults['journal_poem_limit'],
        '#required' => TRUE,
        '#rows' => 2,
      ];
      $form['french']['futureverse_application_fr'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Futureverse application'),
        '#default_value' => $override->get('futureverse_application') ?: $defaults['futureverse_application'],
        '#required' => TRUE,
        '#maxlength' => 255,
      ];
      $form['french']['poet_bio_fr'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Poet bio'),
        '#default_value' => $override->get('poet_bio') ?: $defaults['poet_bio'],
        '#required' => TRUE,
        '#maxlength' => 255,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('piv_futureverse.submission_messages')
      ->set('journal_poem', trim($form_state->getValue('journal_poem')))
      ->set('journal_poem_fifth', trim($form_state->getValue('journal_poem_fifth')))
      ->set('journal_poem_limit', trim($form_state->getValue('journal_poem_limit')))
      ->set('futureverse_application', trim($form_state->getValue('futureverse_application')))
      ->set('poet_bio', trim($form_state->getValue('poet_bio')))
      ->save();

    if ($this->languageManager->getLanguage('fr')) {
      $override = $this->languageManager->getLanguageConfigOverride('fr', 'piv_futureverse.submission_messages');
      $override
        ->set('journal_poem', trim($form_state->getValue('journal_poem_fr')))
        ->set('journal_poem_fifth', trim($form_state->getValue('journal_poem_fifth_fr')))
        ->set('journal_poem_limit', trim($form_state->getValue('journal_poem_limit_fr')))
        ->set('futureverse_application', trim($form_state->getValue('futureverse_application_fr')))
        ->set('poet_bio', trim($form_state->getValue('poet_bio_fr')))
        ->save();
    }

    parent::submitForm($form, $form_state);
  }

}
