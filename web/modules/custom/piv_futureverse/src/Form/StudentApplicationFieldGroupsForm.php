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
 * Edit field group labels and descriptions on the student application form.
 */
final class StudentApplicationFieldGroupsForm extends ConfigFormBase {

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
    return 'piv_futureverse_student_application_field_groups';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['piv_futureverse.futureverse_application_student_field_groups'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $settings = $this->config('piv_futureverse.futureverse_application_student_field_groups');
    $defaults = piv_futureverse_student_field_group_defaults();
    $groups = piv_futureverse_student_field_groups_from_config($settings);

    $form['intro'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('These labels and descriptions appear on the public FutureVerse student application form (student form mode). The active site language determines which translation is shown. You can edit English and French here, or use the Translate tab to add other languages.'),
    ];

    $form['english'] = [
      '#type' => 'details',
      '#title' => $this->t('English'),
      '#open' => TRUE,
    ];
    foreach (piv_futureverse_student_field_group_definitions() as $group_id => $title) {
      $form['english'] += $this->buildGroupFields(
        (string) $title,
        $group_id,
        $groups[$group_id]['label'] ?: ($defaults[$group_id]['label'] ?? ''),
        $groups[$group_id]['description'] ?? ($defaults[$group_id]['description'] ?? ''),
      );
    }

    if ($this->languageManager->getLanguage('fr')) {
      $override = $this->languageManager->getLanguageConfigOverride('fr', 'piv_futureverse.futureverse_application_student_field_groups');
      $french_groups = piv_futureverse_student_field_groups_from_config($override);
      $form['french'] = [
        '#type' => 'details',
        '#title' => $this->t('French'),
        '#open' => TRUE,
      ];
      foreach (piv_futureverse_student_field_group_definitions() as $group_id => $title) {
        $form['french'] += $this->buildGroupFields(
          (string) $title,
          $group_id . '_fr',
          $french_groups[$group_id]['label'] ?: ($groups[$group_id]['label'] ?? ''),
          $french_groups[$group_id]['description'] ?? ($groups[$group_id]['description'] ?? ''),
        );
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Builds label and description elements for one field group.
   *
   * @return array<string, array<string, mixed>>
   *   Form API elements keyed by field name prefix.
   */
  private function buildGroupFields(string $title, string $field_prefix, string $label, string $description): array {
    return [
      $field_prefix => [
        '#type' => 'details',
        '#title' => $title,
        '#open' => FALSE,
      ],
      $field_prefix . '_label' => [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#default_value' => $label,
        '#required' => TRUE,
        '#maxlength' => 255,
        '#group' => $field_prefix,
      ],
      $field_prefix . '_description' => [
        '#type' => 'textarea',
        '#title' => $this->t('Description'),
        '#default_value' => $description,
        '#rows' => 8,
        '#group' => $field_prefix,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('piv_futureverse.futureverse_application_student_field_groups');
    foreach (array_keys(piv_futureverse_student_field_group_definitions()) as $group_id) {
      $keys = piv_futureverse_student_field_group_storage_keys($group_id);
      $config
        ->set($keys['label'], trim((string) $form_state->getValue($group_id . '_label')))
        ->set($keys['description'], trim((string) $form_state->getValue($group_id . '_description')));
    }
    $config->save();

    if ($this->languageManager->getLanguage('fr')) {
      $override = $this->languageManager->getLanguageConfigOverride('fr', 'piv_futureverse.futureverse_application_student_field_groups');
      foreach (array_keys(piv_futureverse_student_field_group_definitions()) as $group_id) {
        $keys = piv_futureverse_student_field_group_storage_keys($group_id);
        $override
          ->set($keys['label'], trim((string) $form_state->getValue($group_id . '_fr_label')))
          ->set($keys['description'], trim((string) $form_state->getValue($group_id . '_fr_description')));
      }
      $override->save();
    }

    parent::submitForm($form, $form_state);
  }

}
