<?php

namespace Drupal\piv_base\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure PIV Base settings for this site.
 */
class PivWelcomeModalSettingsForm extends ConfigFormBase {

  /**
   * The block_content storage.
   *
   * @var Drupal\Core\Entity\Sql\SqlContentEntityStorage
   */
  protected $blockContentStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->blockContentStorage = $entity_type_manager->getStorage('block_content');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'piv_base_piv_welcome_modal_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['piv_base.welcome_modal_settings'];
  }

  /**
   * Get the blocks possible configurations.
   */
  private function getPossibleConfigurations() {
    return [
      'student' => [
        'student_k_5_in' => $this->t('I am a <u>student</u> in grades <u>K-5</u> <u>in</u> canada'),
        'student_k_5_outside' => $this->t('I am a <u>student</u> in grades <u>K-5</u> <u>outside</u> canada'),
        'student_6_8_in' => $this->t('I am a <u>student</u> in grades <u>6-8</u> <u>in</u> canada'),
        'student_6_8_outside' => $this->t('I am a <u>student</u> in grades <u>6-8</u> <u>outside</u> canada'),
        'student_9_12_in' => $this->t('I am a <u>student</u> in grades <u>9-12</u> <u>in</u> canada'),
        'student_9_12_outside' => $this->t('I am a <u>student</u> in grades <u>9-12</u> <u>outside</u> canada'),
      ],
      'teacher' => [
        'teacher_k_5_in' => $this->t('I am a <u>teacher</u> in grades <u>K-5</u> <u>in</u> canada'),
        'teacher_k_5_outside' => $this->t('I am a <u>teacher</u> in grades <u>K-5</u> <u>outside</u> canada'),
        'teacher_6_8_in' => $this->t('I am a <u>teacher</u> in grades <u>6-8</u> <u>in</u> canada'),
        'teacher_6_8_outside' => $this->t('I am a <u>teacher</u> in grades <u>6-8</u> <u>outside</u> canada'),
        'teacher_9_12_in' => $this->t('I am a <u>teacher</u> in grades <u>9-12</u> <u>in</u> canada'),
        'teacher_9_12_outside' => $this->t('I am a <u>teacher</u> in grades <u>9-12</u> <u>outside</u> canada'),
      ],
      'poet' => [
        'poet_in' => $this->t('I am a <u>poet</u> <u>in</u> canada'),
        'poet_outside' => $this->t('I am a <u>poet</u> <u>outside</u> canada'),
      ],
      'parent_interested_person' => [
        'parent_interested_person_in' => $this->t('I am a <u>parent/interested person</u> <u>in</u> canada'),
        'parent_interested_person_outside' => $this->t('I am a <u>parent/interested person</u> <u>outside</u> canada'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = FALSE;
    $groups = [
      'student' => $this->t('Student'),
      'teacher' => $this->t('Teacher'),
      'poet' => $this->t('Poet'),
      'parent_interested_person' => $this->t('Parent/Interested Person'),
    ];
    $configs = $this->config('piv_base.welcome_modal_settings');
    $possible_configurations = $this->getPossibleConfigurations();
    foreach ($possible_configurations as $group => $configuration) {
      $form[$group] = [
        '#type' => 'fieldset',
        '#title' => $groups[$group],
      ];
      foreach ($configuration as $id => $title) {
        $entity = NULL;
        if ($entity_id = $configs->get($id)) {
          $entity = $this->blockContentStorage->load($entity_id);
        }
        $form[$group][$id] = [
          '#type' => 'entity_autocomplete',
          '#title' => $title,
          '#target_type' => 'block_content',
          '#default_value' => $entity,
        ];
      }
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $configs = $this->config('piv_base.welcome_modal_settings');
    $possible_configurations = $this->getPossibleConfigurations();
    foreach ($possible_configurations as $configuration) {
      foreach ($configuration as $id => $title) {
        $configs->set($id, $form_state->getValue($id));
      }
    }
    $configs->save();
    parent::submitForm($form, $form_state);
  }

}
