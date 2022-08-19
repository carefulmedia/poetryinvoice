<?php

namespace Drupal\piv_base\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Component\Serialization\Json;

/**
 * Provides a PIV Base form.
 */
class PivWelcomeModal extends FormBase {

  /**
   * The relevant configurations.
   *
   * @var Drupal\Core\Config\ImmutableConfig
   */
  protected $configs;

  /**
   * The entity type manager.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current logged in user.
   *
   * @var Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ImmutableConfig $configs, AccountProxyInterface $current_user) {
    $this->configs = $configs;
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory')->get('piv_base.welcome_modal_settings'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'piv_welcome_modal';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#theme'] = 'piv_welcome_modal';
    $form['#attach']['library'][] = 'core/drupal.states';
    $form['#prefix'] = '<div id="piv-welcome-modal">';
    $form['#suffix'] = '</div>';

    // 0 is the empty value.
    $option_1 = 0;
    $option_2 = 0;
    $option_3 = 0;
    if ($this->currentUser->isAuthenticated()) {
      $user = $this->entityTypeManager
        ->getStorage('user')
        ->load($this->currentUser->id());

      $data = Json::decode($user->field_welcome_modal_settings->value);
      if ($data) {
        $option_1 = $data['option_1'] ?? 0;
        $option_2 = $data['option_2'] ?? 0;
        $option_3 = $data['option_3'] ?? 0;
      }
    }

    $form['option_1'] = [
      '#type' => 'select',
      '#title' => $this->t('I am a'),
      '#empty_key' => 0,
      '#empty_option' => '',
      '#default_value' => $option_1,
      '#required' => TRUE,
      '#options' => [
        'student' => $this->t('student'),
        'teacher' => $this->t('teacher'),
        'poet' => $this->t('poet'),
        'parent_interested_person' => $this->t('parent/interested person'),
      ],
    ];

    $form['option_2_label'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'option-2-label',
      ],
      'student' => [
        '#type' => 'item',
        '#markup' => $this->t('in grades'),
        '#states' => [
          'visible' => [
            'select[name="option_1"]' => ['value' => 'student'],
          ],
        ],
      ],
      'teacher' => [
        '#type' => 'item',
        '#markup' => $this->t('teaching grades'),
        '#states' => [
          'visible' => [
            'select[name="option_1"]' => ['value' => 'teacher'],
          ],
        ],
      ],
    ];
    $form['option_2'] = [
      '#type' => 'select',
      '#empty_key' => 0,
      '#empty_option' => '',
      '#default_value' => $option_2,
      '#attributes' => [
        'aria-labelledby' => 'option-2-label',
      ],
      '#options' => [
        'k_5' => $this->t('K-5'),
        '6_8' => $this->t('6-8'),
        '9_12' => $this->t('9-12'),
      ],
      '#states' => [
        'visible' => [
          ['select[name="option_1"]' => ['value' => 'student']],
          'or',
          ['select[name="option_1"]' => ['value' => 'teacher']],
        ],
        'required' => [
          ['select[name="option_1"]' => ['value' => 'student']],
          'or',
          ['select[name="option_1"]' => ['value' => 'teacher']],
        ],
      ],
    ];
    $form['option_3'] = [
      '#type' => 'select',
      '#title' => $this->t('Canada'),
      '#title_display' => 'after',
      '#empty_key' => 0,
      '#empty_option' => '',
      '#default_value' => $option_3,
      '#required' => TRUE,
      '#options' => [
        'in' => $this->t('in'),
        'outside' => $this->t('outside'),
      ],
      '#states' => [
        'visible' => [
          ['select[name="option_1"]' => ['value' => 'poet']],
          'or',
          ['select[name="option_1"]' => ['value' => 'parent_interested_person']],
          'or',
          ['select[name="option_2"]' => ['value' => 'k_5']],
          'or',
          ['select[name="option_2"]' => ['value' => '6_8']],
          'or',
          ['select[name="option_2"]' => ['value' => '9_12']],
        ],
      ],
    ];
    $form['go'] = [
      '#type' => 'submit',
      '#value' => $this->t('Go!'),
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'disable-refocus' => FALSE,
        'event' => 'click',
        'wrapper' => 'piv-welcome-modal',
      ],
      '#states' => [
        'visible' => [
          ['select[name="option_3"]' => ['value' => 'in']],
          'or',
          ['select[name="option_3"]' => ['value' => 'outside']],
        ],
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state) {
    $option_1 = $form_state->getValue('option_1');
    $option_2 = $form_state->getValue('option_2');
    $option_3 = $form_state->getValue('option_3');
    $config_key = [];
    $config_key[] = $option_1;
    if (in_array($option_1, ['student', 'teacher'])) {
      $config_key[] = $option_2;
    }
    $config_key[] = $option_3;
    $config_key = implode('_', $config_key);
    $entity_id = $this->configs->get($config_key);
    if ($entity_id) {
      $entity = $this->entityTypeManager
        ->getStorage('block_content')
        ->load($entity_id);
      if ($entity) {
        $view_builder = $this->entityTypeManager
          ->getViewBuilder('block_content');
        return $view_builder->view($entity);
      }
    }
    return ['#markup' => '<i>' . $this->t('Block not configured.') . '</i>'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $data = [
      'option_1' => $form_state->getValue('option_1'),
      'option_2' => $form_state->getValue('option_2'),
      'option_3' => $form_state->getValue('option_3'),
    ];
    if ($this->currentUser->isAuthenticated()) {
      $user = $this->entityTypeManager
        ->getStorage('user')
        ->load($this->currentUser->id());

      $user->field_welcome_modal_settings->value = Json::encode($data);
      $user->save();
    }
  }

}
