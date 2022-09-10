<?php

namespace Drupal\piv_mail;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;

/**
 * Do the replacements for piv_mail.
 */
class ReplacementsService {

  use StringTranslationTrait;

  /**
   * Sources of replacement.
   *
   * @var array
   */
  protected $sources = [];

  /**
   * List with all the tokens keyed by their source.
   *
   * @var array
   */
  static public $tokens = [
    'user' => [
      'first_name',
      'user',
      'link_to_profile',
    ],
    'teacher' => [
      'booking_teacher',
    ],
    'school' => [
      'school_name',
      'school_province',
      'school_full_address',
    ],
    'node' => [
      'nid',
      'title',
      'link_to_visit_node',
      'node_language',
    ],
    'visit_node' => [
      'poet_name',
      'visit_datetime',
      'visit_admin',
      'link_to_poet_survey',
      'link_to_teacher_survey',
      'visit_notes',
      'link_to_poet_page_href',
      'visit_type',
      'visit_language',
    ],
    'team_regional_entry' => [
      'contest_name',
      'team_details',
    ],
  ];

  /**
   * The recipients.
   *
   * @var array
   */
  static public $recipients = [
    'poet' => [
      'source' => 'visit_node',
      'title' => 'Poet',
    ],
    'visit_admin' => [
      'source' => 'visit_node',
      'title' => 'Visit admin',
    ],
    'site_admin' => [
      'title' => 'Site admin (visits@poetryinvoice.com or visits@lesvoixdelapoesie.com)',
    ],
    'teacher_that_created_the_visit' => [
      'source' => 'visit_node',
      'title' => 'Teacher that created the visit',
    ],
    'all_teachers_at_the_school' => [
      'source' => 'visit_node',
      'title' => 'All teachers at the school ',
    ],
    'teachers_involved_in_the_visit' => [
      'source' => 'visit_node',
      'title' => 'Teachers involved on the visit',
    ],
    'include_from' => [
      'title' => 'Include FROM address as recipient (self-to-self)',
    ],
    'user_that_created_team_regional_entry' => [
      'source' => 'team_regional_entry',
      'title' => 'User that created the team regional entry',
    ],
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The country manager.
   *
   * @var \Drupal\Core\Locale\CountryManagerInterface
   */
  protected $countryManager;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a ReplacementService object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, CountryManagerInterface $country_manager, DateFormatterInterface $date_formatter) {
    $this->entityTypeManager = $entity_type_manager;
    $this->countryManager = $country_manager;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * Add items to be used as source for the replacements.
   */
  public function addSource($key, $source) {
    $this->sources[$key] = $source;
    return $this;
  }

  /**
   * Set a original ReplacementsService.
   *
   * A original ReplacementsService is a class with the sources set to the
   * original value of a node in a hook_node_update. If there is a original
   * set, then the replace() will flag the tokens that changed with a prefix.
   */
  public function setOriginal(?ReplacementsService $original = NULL) {
    $this->original = $original;
  }

  /**
   * Create a instance of the class given $data as from the pack() method.
   */
  public function unpack(array $data) {
    $this->sources = [];
    foreach ($data as $key => $entity_data) {
      [$entity_type, $entity_id] = explode('/', $entity_data);
      $this->sources[$key] = $this->entityTypeManager
        ->getStorage($entity_type)
        ->load($entity_id);
    }
    return $this;
  }

  /**
   * Return a representation of this class instance.
   */
  public function pack() {
    $data = [];
    foreach ($this->sources as $key => $entity) {
      $data[$key] = $entity->getEntityTypeId() . '/' . $entity->id();
    }
    ksort($data);
    return $data;
  }

  /**
   * Replace a token, assumes the source exists.
   */
  private function replaceToken($token) {
    $sources = &$this->sources;
    try {
      switch ($token) {
        // User.
        case 'first_name':
          return $sources['user']->piv_teacher_first_name->value;

        case 'user':
          return isset($sources['user']) ? $sources['user']->getDisplayName() : NULL;

        case 'link_to_profile':
          return $sources['user']->toLink('Link to poet profile', 'edit-form', [
            'absolute' => TRUE,
          ])->toString();

        // Teacher.
        case 'booking_teacher':
          return isset($sources['teacher']) ? $sources['teacher']->getDisplayName() : NULL;

        // School.
        case 'school_name':
          return $sources['school']->title->value;

        case 'school_province':
          return $sources['school']->field_address->administrative_area;

        case 'school_full_address':
          $a = $sources['school']->field_address->getValue()[0] ?? NULL;
          $country = '';
          if ($a) {
            $country_list = $this->countryManager->getList();
            $country = $country_list[$a['country_code']];
            $country = $country ? $country->__toString() : '';
          }
          return !$a ? '' : "{$a['address_line1']}<br> {$a['locality']}, {$a['administrative_area']}<br> {$country} {$a['postal_code']}";

        // Node.
        case 'nid':
          return $sources['node']->id();

        case 'title':
          return $sources['node']->title->value;

        case 'link_to_visit_node':
          return $sources['node']->toLink('Link to visit node', 'canonical', [
            'absolute' => TRUE,
          ])->toString();

        case 'node_language':
          return $sources['node']->language()->getName();

        // Visit node.
        case 'poet_name':
          $poet = $sources['visit_node']->field_pal_pir->entity
            ?? $sources['visit_node']->field_preferred_poet_s_->entity;
          return $poet ? $poet->getDisplayName() : '';

        case 'visit_datetime':
          $date = $sources['visit_node']->field_agreed_visit_date->date
            ?? $sources['visit_node']->field_date_and_start_time->date;
          return $date
            ? $this->dateFormatter->format($date->getTimestamp(), 'custom', 'd F, Y H:i')
            : $this->t('a date to be defined');

        case 'visit_admin':
          $visit_admin = $sources['visit_node']->field_visit_admin->entity;
          return $visit_admin ? $visit_admin->getDisplayName() : '';

        case 'visit_notes':
          $notes = $sources['visit_node']->field_notes->value ?? NULL;
          return $notes ? nl2br($notes) : '';

        case 'link_to_poet_page_href':
          $poet = $sources['visit_node']->field_pal_pir->entity
            ?? $sources['visit_node']->field_preferred_poet_s_->entity;
          $node = $sources['visit_node'] ?? $sources['node'] ?? NULL;
          if ($poet) {
            $url = $poet->toUrl();
            if ($node) {
              $url->setOption('language', $node->language());
            }
            return $url->toString();
          }
          return '';

        case 'visit_type':
          $visit_type = $sources['visit_node']->field_in_person_or_teleconferenc->value ?? NULL;
          $langcode = $sources['visit_node']->langcode->value ?? NULL;
          switch ($visit_type) {
            case 1:
              return $langcode == 'fr' ? 'En personne' : 'In person';

            case 2:
              return $langcode == 'fr' ? 'Visite virtuelle' : 'Virtual';

            default:
              return '';

          }
          return '';

        case 'visit_language':
          return $sources['visit_node']->language()->getName();

        case 'link_to_poet_survey':
          return Link::createFromRoute($this->t('Link to survey'), 'piv_base.poet_survey', [
            'node' => $sources['visit_node']->id(),
          ])->toString()->getGeneratedLink();

        case 'link_to_teacher_survey':
          return Link::createFromRoute($this->t('Link to survey'), 'piv_base.teacher_survey', [
            'node' => $sources['visit_node']->id(),
          ])->toString()->getGeneratedLink();

        // Team regional entry.
        case 'contest_name':
          return $sources['team_regional_entry']->field_contest_association->entity->title->value ?? '';

        case 'team_details':
          if (empty($sources['team_regional_entry'])) {
            return '';
          }
          $students = $sources['team_regional_entry']->field_tr_student->referencedEntities();
          $html = '<div class="students">';
          foreach ($students as $delta => $student) {
            $stage_name = $student->field_student_name_1->value;
            $html .= '<div class="student"><p>';
            $html .= $this->t('Reciter:') . " $delta<br>";
            $html .= $this->t('Legal Name:') . " {$student->field_legal_name->value}<br>";
            if ($stage_name) {
              $html .= $this->t('Stage Name:') . " $stage_name<br>";
            }
            $html .= $this->t('Email:') . " {$student->piv_student_mail->value}<br>";
            $html .= $this->t('Poem to be recited:') . " {$student->field_poem->entity->title->value}<br>";
            $html .= '</p></div>';
          }
          $html .= '</div>';
          return $html;

      }
    }
    catch (\Exception $e) {
      return $token;
    }

    return $token;
  }

  /**
   * Reset this class instance.
   */
  public function reset() {
    $this->sources = [];
    return $this;
  }

  /**
   * Do the replacements.
   *
   * If there is a replacements service to compare, a "changed" prefix will
   * be added to the tokens in case they are different.
   */
  public function replace($text) {
    $langcode = $this->sources['node']->langcode->value
      ?? $this->sources['visit_node']->langcode->value
      ?? 'en';
    $prefix = $langcode == 'en' ? '***CHANGED***' : '***MODIFIÉ***';
    // Do not add the prefix to modified links.
    $ignore_modified_tokens = [
      'link_to_poet_page_href',
      'link_to_teacher_survey',
      'link_to_poet_survey',
    ];
    foreach ($this::$tokens as $source => $tokens) {
      if (!empty($this->sources[$source])) {
        foreach ($tokens as $token) {
          $token_value = $this->replaceToken($token);
          if (!empty($this->original)) {
            $original_token_value = $this->original->replaceToken($token);
            if (!in_array($token, $ignore_modified_tokens)) {
              if ($token_value != $original_token_value) {
                $token_value = "$prefix <br><strong>$token_value</strong>";
              }
            }
          }
          $token_value = $token_value ?? "";
          $text = str_replace("[$token]", $token_value, $text);
        }
      }
    }
    return $text;
  }

  /**
   * Replace the recipient with an email.
   */
  public function replaceRecipient($recipient) : array {
    $sources = &$this->sources;
    switch ($recipient) {
      case 'poet':
        $poet = $sources['visit_node']->field_pal_pir->entity;
        return $poet ? [$poet->mail->value] : [];

      case 'visit_admin':
        $visit_admin = $sources['visit_node']->field_visit_admin->entity;
        return $visit_admin ? [$visit_admin->mail->value] : [];

      case 'site_admin':
        $node = $sources['visit_node'] ?? $sources['node'] ?? NULL;
        return $node && $node->langcode == 'fr' ? ['visits@lesvoixdelapoesie.com'] : ['visits@poetryinvoice.com'];

      case 'teacher_that_created_the_visit':
        $email = $sources['visit_node']->uid->entity->mail->value ?? NULL;
        return $email ? [$email] : [];

      case 'all_teachers_at_the_school':
        $school_id = $sources['visit_node']->uid->entity->field_school->target_id ?? NULL;
        if (!$school_id) {
          return [];
        }
        $teachers = $this->entityTypeManager
          ->getStorage('user')
          ->loadByProperties(['field_school' => $school_id]);
        return array_map(fn ($teacher) => $teacher->mail->value, $teachers);

      case 'teachers_involved_in_the_visit':
        $teachers = !empty($sources['visit_node'])
          ? $sources['visit_node']->field_teacher_s_involved->referencedEntities()
          : [];
        return array_map(fn ($teacher) => $teacher->mail->value, $teachers);

      case 'include_from':
        // This needs to be calculated outside, this plugin doesn't knows the
        // value for the "from".
        return [];

      case 'user_that_created_team_regional_entry':
        $mail = $sources['team_regional_entry']->uid->entity->mail->value ?? NULL;
        return $mail ? [$mail] : [];

    }

  }

  /**
   * Given a array of recipients, calculate the emails.
   */
  public function recipients(array $recipients) : array {
    $to = [];
    foreach ($recipients as $recipient => $data) {
      $emails = $this->replaceRecipient($recipient);
      $to = array_merge($to, $emails);
    }
    return array_unique($to);
  }

}
