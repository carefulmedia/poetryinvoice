<?php

namespace Drupal\piv_base\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;

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
    return 'piv_base_welcome_modal';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'core/drupal.ajax';

    // 0 is the empty value.
    $option_1 = 0;
    $option_2 = 0;
    $option_3 = 0;
    if ($this->currentUser->isAuthenticated()) {
      $user = $this->entityTypeManager
        ->getStorage('user')
        ->load($this->currentUser->id());

      if ($value = $user->field_welcome_modal_settings->value) {
        $data = Json::decode($user->field_welcome_modal_settings->value);
        if ($data) {
          $option_1 = $data['option_1'] ?? 0;
          $option_2 = $data['option_2'] ?? 0;
          $option_3 = $data['option_3'] ?? 0;
        }
      }
    }

    $form['start'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['start-here'],
      ],
      '#value' => $this->t('Start here:'),
    ];

    $form['options_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['options-wrapper'],
      ],
    ];
    $form['options_wrapper']['option_1'] = [
      '#type' => 'piv_select',
      '#title' => $this->t('I am a'),
      '#empty_value' => '_null',
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
    $form['options_wrapper']['option_2'] = [
      '#type' => 'piv_select',
      '#title' => $this->t('in grades'),
      '#empty_value' => '_null',
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
    ];
    $form['options_wrapper']['option_3'] = [
      '#type' => 'piv_select',
      '#title' => $this->t('Canada'),
      '#title_display' => 'after',
      '#empty_value' => '_null',
      '#empty_option' => '',
      '#default_value' => $option_3,
      '#required' => TRUE,
      '#options' => [
        'inside' => $this->t('inside'),
        'outside' => $this->t('outside'),
      ],
    ];
    $form['go'] = [
      '#type' => 'submit',
      '#value' => $this->t('Go!'),
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'event' => 'click',
      ],
    ];
    $form['#suffix'] = '<div id="welcome-modal-content"></div>';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state) {
    $form = [];
    $form['back'] = [
      '#type' => 'html_tag',
      '#tag' => 'a',
      '#value' => $this->t('Back'),
      '#attributes' => [
        'class' => [
          'welcome-modal-back',
        ],
        'href' => '#',
      ],
    ];

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
        $form['block_content'] = $view_builder->view($entity);
      }
    }
    else {
      $form['not_configured'] = [
        '#markup' => '<div><i>' . $this->t('Block not configured.') . '</i></div>',
      ];
    }
    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('#welcome-modal-content', $form));
    $response->addCommand(new InvokeCommand('.piv-base-welcome-modal', 'hide'));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();
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
