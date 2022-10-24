<?php

namespace Drupal\piv_user\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\piv_mail\ReplacementsService;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use CommerceGuys\Addressing\AddressFormat\AddressField;
use CommerceGuys\Addressing\AddressFormat\FieldOverride;

/**
 * Provides a PIV User form.
 */
class CreateAccountForm extends FormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The piv mail replacements service.
   *
   * @var \Drupal\piv_mail\ReplacementsService
   */
  protected $pivMailReplacementsService;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ReplacementsService $piv_mail_replacements_service, LanguageManagerInterface $language_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->pivMailReplacementsService = $piv_mail_replacements_service;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('piv_mail.replacements_service'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'piv_user_create_account';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = FALSE;
    $form['#attributes']['autocomplete'] = 'off';
    $form['#attached']['library'][] = 'piv_user/create_account';
    $form['#attached']['library'][] = 'core/drupal.autocomplete';
    $form['#attached']['library'][] = 'file/drupal.file';

    $form['mail'] = [
      '#type' => 'email',
      '#title' => $this->t('Email address'),
      '#required' => TRUE,
      '#weight' => 1,
    ];
    $form['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First name'),
      '#required' => TRUE,
      '#weight' => 2,
    ];
    $form['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last name'),
      '#required' => TRUE,
      '#weight' => 3,
    ];
    $form['pass'] = [
      '#type' => 'password_confirm',
      '#size' => 25,
      '#weight' => 4,
      '#required' => TRUE,
    ];
    $form['account_type'] = [
      '#type' => 'radios',
      '#required' => TRUE,
      '#title' => $this->t('Account type'),
      '#options' => [
        'poet' => $this->t('I am a Canadian poet and would like to join the Poet Network'),
        'teacher' => $this->t('I work at a Canadian school'),
        'teacher_not_affiliated' => $this->t('I am in Canada but am not affiliated with a school'),
        'non_canadian' => $this->t('I work outside of Canada'),
      ],
      '#ajax' => [
        'callback' => '::accountTypeAjaxCallback',
        'wrapper' => 'account-type-wrapper',
        'method' => 'replace',
        'effect' => 'fade',
      ],
      '#weight' => 5,
    ];

    $account_type = $form_state->getValue('account_type') ?? NULL;
    $form['account_type_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'account-type-wrapper',
        'class' => [$account_type],
      ],
      '#weight' => 6,
    ];

    // If there is a value, this needs to be rendered, it will be hidden with
    // css if not poet.
    // The Address and Checkboxes needs to be rendered too since the cv reloads
    // the form with ajax and these fields will not be rendered since they
    // depend on the $account_type which will have no value since there is a
    // limit_validation_errors in the file field.
    if ($account_type == 'poet' || $form_state->getValue('cv')) {
      $form['account_type_wrapper']['poet'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['poet-container']],
      ];
      $form['account_type_wrapper']['poet']['cv'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('CV'),
        '#upload_location' => 'private://',
        '#multiple' => FALSE,
        '#description' => $this->t('Upload your CV in pdf, doc or docx format.'),
        '#upload_validators' => [
          'file_validate_extensions' => ['pdf doc docx'],
        ],
        '#weight' => 8,
        '#required' => TRUE,
      ];
      $form['account_type_wrapper']['poet']['address'] = [
        '#type' => 'address',
        '#required' => TRUE,
        '#default_value' => [
          'country_code' => 'CA',
        ],
        '#field_overrides' => [
          AddressField::ADMINISTRATIVE_AREA => FieldOverride::REQUIRED,
          AddressField::LOCALITY => FieldOverride::REQUIRED,
          AddressField::DEPENDENT_LOCALITY => FieldOverride::HIDDEN,
          AddressField::POSTAL_CODE => FieldOverride::HIDDEN,
          AddressField::SORTING_CODE => FieldOverride::HIDDEN,
          AddressField::ADDRESS_LINE1 => FieldOverride::HIDDEN,
          AddressField::ADDRESS_LINE2 => FieldOverride::HIDDEN,
          AddressField::ORGANIZATION => FieldOverride::HIDDEN,
          AddressField::GIVEN_NAME => FieldOverride::HIDDEN,
          AddressField::ADDITIONAL_NAME => FieldOverride::HIDDEN,
          AddressField::FAMILY_NAME => FieldOverride::HIDDEN,
        ],
        '#available_countries' => ['CA'],
        '#weight' => 9,
      ];
      $form['account_type_wrapper']['poet']['checkboxes'] = [
        '#type' => 'checkboxes',
        '#options' => [
          'poet_in_class' => $this->t('I am interested in participating in the Poet In Class program.'),
          'judging' => $this->t('I am interested in judging recitation contests.'),
          'writing' => $this->t('I am interested in writing content.'),
        ],
        '#weight' => 10,
      ];
    }

    // Display different fields according to the account type selected.
    $isPostalCode = 0;
    if ($form_state->getValue('postal_code')) {
      $isPostalCode = 1;
    }
    switch ($account_type) {
      case 'poet':
        // Poet is handled in the if above.
        break;

      case 'teacher':
        $form['account_type_wrapper']['school'] = [
          '#title' => $this->t('School'),
          '#description' => $this->t('Type a few letters of your school name, wait, and then select it from the list. All Canadian schools should be in our system. Don’t see your school? <a href="mailto:webmaster@poetryinvoice.com">Contact us</a>.'),
          '#type' => 'entity_autocomplete',
          '#target_type' => 'node',
          "#validate_reference" => false,
          '#selection_handler' => 'default:piv_school',
          '#selection_settings' => [
            'target_bundles' => [
              "school" => "school",
            ],
            'sort' => [
              "field" => "_none",
              "direction" => "ASC",
            ],
            "postal_code" => $isPostalCode,
            "auto_create" => 0,
            "auto_create_bundle" => "",
            "match_operator" => "CONTAINS",
            "match_limit" => 10,
          ],
          '#required' => TRUE,
          '#weight' => 7,
          "#prefix" => "<div id='school-reference-wrapper'>",
          "#suffix" => "</div>",
        ];
        $form['account_type_wrapper']['postal_code'] = [
          '#title' => $this->t('Filter by Postal Code?'),
          '#type' => 'checkbox',
          '#description' => $this->t('Default search will use the School title, check this option to search by the Postal Code instead.'),
          '#default_value' => 0,
          '#ajax' => [
            'callback' => [$this, 'postalCodeCallback'],
            'event' => 'change',
            'wrapper' => 'school-reference-wrapper',
          ],
          '#weight' => 8,
        ];
        $form['account_type_wrapper']['how_did_you_hear_about_us'] = [
          '#type' => 'select',
          '#title' => $this->t('How did you hear about us?'),
          '#required' => TRUE,
          '#options' => [
            'returning' => $this->t('Returning school'),
            'conference' => $this->t('Conference/workshop'),
            'direct' => $this->t('Directly from a Poetry In Voice contact'),
            'email-flyer' => $this->t('Email / e-flyer'),
            'ad' => $this->t('Advertisement'),
            'wom' => $this->t('Word-of-mouth'),
          ],
          '#weight' => 8,
        ];
        break;

      case 'teacher_not_affiliated':
      case 'non_canadian':
        $form['account_type_wrapper']['non_school_organization'] = [
          '#type' => 'textfield',
          '#title' => $this->t('I&rsquo;m affiliated with the following organization'),
          '#maxlength' => 60,
          '#required' => TRUE,
          '#weight' => 7,
        ];
        $form['account_type_wrapper']['role'] = [
          '#type' => 'textfield',
          '#title' => $this->t('My role is'),
          '#maxlength' => 60,
          '#required' => TRUE,
          '#weight' => 8,
        ];
        break;

    }
    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => 10,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Account'),
      '#weight' => 11,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function accountTypeAjaxCallback(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();
    return $form['account_type_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $current_langcode = $this->languageManager->getCurrentLanguage()->getId();
    $values = $form_state->getValues();
    $checkboxes = $values['checkboxes'] ?? [];
    $mail = trim($values['mail']);
    // If user with that email exists already, mock the user creation and
    // display the success messages but don't create a new user.
    $user = user_load_by_mail($mail);
    $email_not_exists = empty($user);
    $new_user = $this->entityTypeManager->getStorage('user')->create([
      'status' => 0,
      'name' => $mail,
      'mail' => $mail,
      'piv_teacher_first_name' => trim($values['first_name']),
      'piv_teacher_last_name' => trim($values['last_name']),
      'pass' => $values['pass'],
      'field_i_am_interested_in_partici' => empty($checkboxes['poet_in_class']) ? 0 : 1,
      'field_i_am_interested_in_judging' => empty($checkboxes['judging']) ? 0 : 1,
      'field_i_am_interested_in_writing' => empty($checkboxes['writing']) ? 0 : 1,
      'piv_teacher_profile_origin' => $values['how_did_you_hear_about_us'] ?? NULL,
      'field_non_school_organization' => $values['non_school_organization'] ?? NULL,
      'field_my_role_at_school_organiza' => $values['role'] ?? NULL,
      'langcode' => $current_langcode,
      'preferred_langcode' => $current_langcode,
    ]);
    switch ($values['account_type']) {
      case 'poet':
        if ($email_not_exists) {
          $new_user->field_address = $values['address'] ?? NULL;
          $new_user->addRole('poet_network');
          // Only save the CV if user is a poet.
          if ($file_id = $values['cv'][0] ?? NULL) {
            $file = $this->entityTypeManager->getStorage('file')->load($file_id);
            if ($file) {
              $file->setPermanent();
              $file->save();
              $new_user->field_cv = ['target_id' => $file_id];
            }
          }
          $new_user->save();
        }
        $this->messenger()->addMessage($this->t('Thank you very much for applying for a Poet Network account. We will review your application and contact you soon. - The Poetry In Voice Team.'));
        $this->pivMailReplacementsService->addSource('user', $new_user);
        piv_mail_send_mail('poet_applied_admin', $current_langcode, $this->pivMailReplacementsService);
        $form_state->setRedirect('piv_user.create_account');
        break;

      case 'teacher':
        $new_user->addRole('teacher');
        $new_user->field_school = ['target_id' => $values['school']];
        break;

      case 'teacher_not_affiliated':
        $new_user->addRole('teacher');
        $new_user->field_school = ['target_id' => 22469];
        break;

      case 'non_canadian':
        $new_user->addRole('non_canadian_educator');
        $new_user->field_school = ['target_id' => 22470];
        break;
    }

    // Common to non poet.
    $non_poet = ['teacher', 'teacher_not_affiliated', 'non_canadian'];
    if (in_array($values['account_type'], $non_poet)) {
      if ($email_not_exists) {
        $new_user->activate();
        $new_user->save();
      }
      $url = Url::fromUri('mailto://webmaster@poetryinvoice.com');
      $link = Link::fromTextAndUrl('webmaster@poetryinvoice.com', $url);
      $this->messenger()->addStatus($this->t('Thank you @display_name, we have sent you an email with a link to login. If you do not receive the email, please check your spam folder. Then, if needed, write to us at @email', [
        '@display_name' => $new_user->getDisplayName(),
        '@email' => $link->toString(),
      ]));
      // Resend the email to the existing user or to a new user.
      $mail_to = $email_not_exists ? $new_user : $user;
      _user_mail_notify('register_no_approval_required', $mail_to, $current_langcode);
      $form_state->setRedirect('user.login');
    }
  }

  /**
   * Set the value of Postal Code.
   */
  public function postalCodeCallback(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();
    return $form['account_type_wrapper']['school'];
  }

}
