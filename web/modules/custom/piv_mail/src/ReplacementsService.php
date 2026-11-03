<?php

namespace Drupal\piv_mail;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\piv_futureverse\JournalHelper;

/**
 * Do the replacements for piv_mail.
 */
class ReplacementsService {

  use StringTranslationTrait;

  /**
   * Site paths for journal poem emails, keyed by logical name and langcode.
   *
   * @var array<string, array<string, string>>
   */
  private const JOURNAL_POEM_SITE_PATHS = [
    'monthly_winning_poems' => [
      'en' => '/write/monthly-poem-prize/winning-poems',
      'fr' => '/ecrire/cest-tout-un-poeme/poemes-gagnants',
    ],
    'write' => [
      'en' => '/write',
      'fr' => '/ecrire/exercices-de-creation',
    ],
    'read' => [
      'en' => '/read',
      'fr' => '/lire/poemes',
    ],
    'poet_class' => [
      'en' => '/teach/poet-class',
      'fr' => '/enseigner/poetes-lecole',
    ],
    'futureverse' => [
      'en' => '/write/futureverse',
      'fr' => '/write/futureverse',
    ],
  ];

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
  public static $tokens = [
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
    // Live competitions.
    'team_regional_entry' => [
      'contest_name',
      'team_details',
    ],
    'user_diff' => [
      'user_diff',
    ],
    'journal_poem' => [
      'journal_poem_first_name',
      'journal_poem_title',
      'link_to_bio_enrichment',
      'link_to_futureverse_application',
      'link_to_journal_poem',
      'monthly_prize_winner_link',
      'monthly_prize_winner_author',
      'monthly_prize_winner_title',
      'monthly_prize_winner_banner_subtitle',
      'journal_year_editor',
      'link_to_monthly_winning_poems',
      'link_to_write',
      'link_to_read',
      'link_to_poet_class',
      'link_to_futureverse',
      'monthly_winning_poems_url',
      'write_url',
      'read_url',
      'poet_class_url',
      'futureverse_url',
    ],
    'poet_bio' => [
      'link_to_poet_bio',
    ],
    'competition_entry' => [
      'competition_entry:student_name',
      'competition_entry:teacher_name',
      'competition_entry:school_name',
      'competition_entry:poem_title_1',
      'competition_entry:poem_title_2',
      'competition_entry:poem_author_1',
      'competition_entry:poem_author_2',
      'competition_entry:stream',
      'competition_entry:level',
      // Competition related, get from the entry.
      'competition:title',
    ],
    'paragraph_rank' => [
      'rank:*',
    ],
  ];

  /**
   * The recipients.
   *
   * @var array
   */
  public static $recipients = [
    'poet' => [
      'source' => 'visit_node',
      'title' => 'Poet',
    ],
    'visit_admin' => [
      'source' => 'visit_node',
      'title' => 'Visit admin',
    ],
    'site_admin' => [
      'title' => 'Site admin (visits@poetryinvoice.ca or visits@lesvoixdelapoesie.ca)',
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
    'journal_poem_email' => [
      'source' => 'journal_poem',
      'title' => 'Email from the journal poem',
    ],
    'competition_entry:student' => [
      'source' => 'competition_entry',
      'title' => 'Student email on competition entry.',
    ],
    'competition_entry:teacher' => [
      'source' => 'competition_entry',
      'title' => 'Teacher email on competition entry.',
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
   * The original replacements service instance.
   *
   * @var \Drupal\piv_mail\ReplacementsService|null
   */
  protected $original;

  /**
   * A langcode to be used.
   *
   * If one is not set than it tries to get it from other sources.
   *
   * @var string
   */
  private $langcode;

  /**
   * Journal month and year for a source poem, keyed by poem id.
   *
   * @var array<int, array{month: object|null, year: object|null}>
   */
  private $journalContext = [];

  /**
   * Monthly prize winner for a source poem, keyed by poem id.
   *
   * A stored NULL means the lookup already ran and there is no winner.
   *
   * @var array<int, \Drupal\node\NodeInterface|null>
   */
  private $monthlyPrizeWinner = [];

  /**
   * An optional prefix prepended to the email body.
   *
   * When set, this text is prepended (as its own block) to the rendered email
   * body. Used, for example, to note that a message in the other language was
   * sent separately.
   *
   * @var string|null
   */
  private $prefix;

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
   * Force a langcode to be used.
   */
  public function setLangcode(string $langcode) {
    $this->langcode = $langcode;
    return $this;
  }

  /**
   * Set a prefix to be prepended to the email body.
   */
  public function setPrefix(?string $prefix) {
    $this->prefix = $prefix;
    return $this;
  }

  /**
   * Return the prefix to be prepended to the email body, if any.
   */
  public function getPrefix(): ?string {
    return $this->prefix;
  }

  /**
   * Return the langcode used.
   */
  public function getLangcode() {
    if ($this->langcode) {
      return $this->langcode;
    }
    if (!empty($this->sources['journal_poem'])) {
      return $this->sources['journal_poem']->langcode->value;
    }
    if (!empty($this->sources['node'])) {
      return $this->sources['node']->langcode->value;
    }
    if (!empty($this->sources['visit_node'])) {
      return $this->sources['visit_node']->langcode->value;
    }
    if (!empty($this->sources['poet_bio'])) {
      return $this->sources['poet_bio']->field_language->target_id;
    }
    return 'en';
  }

  /**
   * Langcode of the journal poem used as the mail context.
   */
  private function getJournalPoemLangcode(): string {
    if (!empty($this->sources['journal_poem'])) {
      return $this->sources['journal_poem']->langcode->value;
    }
    return $this->getLangcode();
  }

  /**
   * Build an absolute front-end URL for a journal poem email.
   */
  private function journalPoemSiteUrl(string $path_key): ?Url {
    $langcode = $this->getJournalPoemLangcode();
    $path = self::JOURNAL_POEM_SITE_PATHS[$path_key][$langcode]
      ?? self::JOURNAL_POEM_SITE_PATHS[$path_key]['en']
      ?? NULL;
    if (!$path) {
      return NULL;
    }
    $language = \Drupal::languageManager()->getLanguage($langcode);
    return Url::fromUserInput($path, [
      'language' => $language,
      'absolute' => TRUE,
    ]);
  }

  /**
   * Render a translatable text link on the poem language site domain.
   */
  private function journalPoemSiteLink(string $path_key, string $text, array $options = []): string {
    $url = $this->journalPoemSiteUrl($path_key);
    if (!$url) {
      return '';
    }
    $langcode = $this->getJournalPoemLangcode();
    $link = Link::fromTextAndUrl($this->t($text, $options, ['langcode' => $langcode]), $url)
      ->toString()
      ->getGeneratedLink();
    return $link;
  }

  /**
   * Absolute URL string for a journal poem site path key.
   */
  private function journalPoemSiteUrlString(string $path_key): string {
    $url = $this->journalPoemSiteUrl($path_key);
    return $url ? $url->toString() : '';
  }

  /**
   * Journal month and year that contain the current journal poem.
   *
   * @return array{month: object|null, year: object|null}
   *   Month and year, or NULL for either when the poem is not attached.
   */
  private function getJournalContext(): array {
    $empty = ['month' => NULL, 'year' => NULL];
    $poem = $this->sources['journal_poem'] ?? NULL;
    if (!$poem || !class_exists(JournalHelper::class)) {
      return $empty;
    }
    $id = (int) $poem->id();
    if (isset($this->journalContext[$id])) {
      return $this->journalContext[$id];
    }
    $month = JournalHelper::getJournalMonthFromPoem($poem);
    $year = $month ? JournalHelper::getJournalYearFromJournalMonth($month) : NULL;
    $this->journalContext[$id] = ['month' => $month, 'year' => $year];
    return $this->journalContext[$id];
  }

  /**
   * Poem flagged as the monthly prize winner for the source poem's month.
   *
   * Loser mailings pass the recipient's poem, so the winner is looked up
   * from that poem's journal month. When several poems are flagged, the
   * first one on the month is used.
   */
  private function getMonthlyPrizeWinnerPoem(): ?NodeInterface {
    $poem = $this->sources['journal_poem'] ?? NULL;
    if (!$poem || !class_exists(JournalHelper::class)) {
      return NULL;
    }
    $id = (int) $poem->id();
    if (array_key_exists($id, $this->monthlyPrizeWinner)) {
      return $this->monthlyPrizeWinner[$id];
    }
    $month = $this->getJournalContext()['month'];
    if (!$month) {
      $this->monthlyPrizeWinner[$id] = NULL;
      return NULL;
    }
    $winners = JournalHelper::getMonthlyPrizeWinner($month);
    $winner = $winners ? reset($winners) : NULL;
    $this->monthlyPrizeWinner[$id] = $winner instanceof NodeInterface ? $winner : NULL;
    return $this->monthlyPrizeWinner[$id];
  }

  /**
   * Poem translation matching the email language, when one exists.
   */
  private function poemInMailLanguage(NodeInterface $poem): NodeInterface {
    $langcode = $this->getLangcode();
    if ($langcode && $poem->hasTranslation($langcode)) {
      return $poem->getTranslation($langcode);
    }
    return $poem;
  }

  /**
   * Processed banner subtitle for a poem.
   */
  private function bannerSubtitle(NodeInterface $poem): string {
    if (!$poem->hasField('field_banner_subtitle') || $poem->get('field_banner_subtitle')->isEmpty()) {
      return '';
    }
    $subtitle = $poem->get('field_banner_subtitle');
    $processed = $subtitle->processed;
    if ($processed) {
      return (string) $processed;
    }
    return (string) ($subtitle->value ?? '');
  }

  /**
   * Set a original ReplacementsService.
   *
   * A original ReplacementsService is a class with the sources set to the
   * original value of a node in a hook_node_update. If there is a original
   * set, then the replace() will flag the tokens that changed with a prefix.
   */
  public function setOriginal(?ReplacementsService $original = NULL): void {
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
   * Helper function to get competition from sources.
   */
  private function getCompetition() {
    return $this->sources['competition'] ?? $this->sources['competition_entry']->field_competition->entity ?? NULL;
  }

  /**
   * Replace a token, assumes the source exists.
   */
  private function replaceToken($token) {
    $langcode = $this->getLangcode();
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
          $langcode = $sources['visit_node']->langcode->value;
          return $date
            ? $this->dateFormatter->format($date->getTimestamp(), 'custom', 'd F, Y H:i', NULL, $langcode)
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
          $language = $sources['visit_node']->language();
          $text = $this->t('Link to survey', [], [
            'langcode' => $language->getId(),
          ]);
          return Link::createFromRoute($text, 'piv_base.poet_survey', [
            'node' => $sources['visit_node']->id(),
          ],
          [
            'language' => $language,
            'absolute' => TRUE,
          ])->toString()->getGeneratedLink();

        case 'link_to_teacher_survey':
          $language = $sources['visit_node']->language();
          $text = $this->t('Link to survey', [], [
            'langcode' => $language->getId(),
          ]);
          return Link::createFromRoute($text, 'piv_base.teacher_survey', [
            'node' => $sources['visit_node']->id(),
          ],
          [
            'language' => $language,
            'absolute' => TRUE,
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
            $html .= $this->t('Reciter:') . " " . ($delta + 1) . "<br>";
            $html .= $this->t('Legal Name:') . " {$student->field_legal_name->value}<br>";
            if ($stage_name) {
              $html .= $this->t('Stage Name:') . " $stage_name<br>";
            }
            $html .= $this->t('Email:') . " {$student->piv_student_mail->value}<br>";
            $poem = $student->field_poem?->entity;
            $poem_title = $poem?->title?->value ?? '';
            $poet = $poem?->uid?->entity;
            $poem_author = ($poet?->piv_teacher_first_name?->value ?? '') . ' ' . ($poet?->piv_teacher_last_name?->value ?? '');
            $html .= $this->t('Poem to be recited:') . " $poem_title, $poem_author<br>";
            $html .= '</p></div>';
          }
          $html .= '</div>';
          return $html;

        // User diff. Special case.
        case 'user_diff':
          return $sources['user_diff'];

        // Journal poem.
        case 'journal_poem_first_name':
          return $sources['journal_poem']->piv_teacher_first_name->value;

        case 'journal_poem_title':
          return $sources['journal_poem']->getTitle();

        case 'link_to_journal_poem':
          $poem = $sources['journal_poem'];
          $language = $poem->language();
          return Link::createFromRoute($poem->getTitle(), 'entity.node.canonical', [
            'node' => $poem->id(),
          ], [
            'language' => $language,
            'absolute' => TRUE,
          ])->toString()->getGeneratedLink();

        case 'monthly_prize_winner_title':
          $winner = $this->getMonthlyPrizeWinnerPoem();
          return $winner ? $this->poemInMailLanguage($winner)->getTitle() : '';

        case 'monthly_prize_winner_author':
          $winner = $this->getMonthlyPrizeWinnerPoem();
          if (!$winner) {
            return '';
          }
          $winner = $this->poemInMailLanguage($winner);
          $first = $winner->piv_teacher_first_name->value ?? '';
          $last = $winner->piv_teacher_last_name->value ?? '';
          return trim("$first $last");

        case 'monthly_prize_winner_link':
          $winner = $this->getMonthlyPrizeWinnerPoem();
          if (!$winner) {
            return '';
          }
          $winner = $this->poemInMailLanguage($winner);
          return Link::createFromRoute($winner->getTitle(), 'entity.node.canonical', [
            'node' => $winner->id(),
          ], [
            'language' => $winner->language(),
            'absolute' => TRUE,
          ])->toString()->getGeneratedLink();

        case 'monthly_prize_winner_banner_subtitle':
          $winner = $this->getMonthlyPrizeWinnerPoem();
          return $winner ? $this->bannerSubtitle($this->poemInMailLanguage($winner)) : '';

        case 'journal_year_editor':
          $year = $this->getJournalContext()['year'];
          if (!$year) {
            return '';
          }
          $field_name = str_starts_with((string) $this->getLangcode(), 'fr')
            ? 'field_french_journal_editor'
            : 'field_english_journal_editor';
          if (!$year->hasField($field_name) || $year->get($field_name)->isEmpty()) {
            return '';
          }
          $editor = $year->get($field_name)->entity;
          return $editor ? $editor->getDisplayName() : '';

        case 'link_to_monthly_winning_poems':
          return $this->journalPoemSiteLink('monthly_winning_poems', 'on our website');

        case 'link_to_write':
          return $this->journalPoemSiteLink('write', 'Write');

        case 'link_to_read':
          return $this->journalPoemSiteLink('read', 'Read');

        case 'link_to_poet_class':
          return $this->journalPoemSiteLink('poet_class', 'Request a poet visit');

        case 'link_to_futureverse':
          return $this->journalPoemSiteLink('futureverse', 'FutureVerse');

        case 'monthly_winning_poems_url':
          return $this->journalPoemSiteUrlString('monthly_winning_poems');

        case 'write_url':
          return $this->journalPoemSiteUrlString('write');

        case 'read_url':
          return $this->journalPoemSiteUrlString('read');

        case 'poet_class_url':
          return $this->journalPoemSiteUrlString('poet_class');

        case 'futureverse_url':
          return $this->journalPoemSiteUrlString('futureverse');

        case 'link_to_bio_enrichment':
          $bio_service = \Drupal::service('piv_futureverse.bio_enrichment'); // @phpcs:ignore
          $journal_poem_id = $sources['journal_poem']->id();
          if ($bio_service->exists($journal_poem_id)) {
            return '';
          }

          $url = $bio_service->generateUrl($journal_poem_id);
          if (!$url) {
            return '';
          }
          $language = $sources['journal_poem']->language();
          $url->setOption('language', $language);
          $text = $this->t('Click here to update your bio', [], [
            'langcode' => $language->getId(),
          ]);
          $link = Link::fromTextAndUrl($text, $url)
            ->toString()
            ->getGeneratedLink();
          $html = '<p>';
          $html .= $this->t('You will only be able to use this link once to provide your information.', [], [
            'langcode' => $language->getId(),
          ]);
          $html .= '<br>';
          $html .= $link;
          $html .= '</p>';
          return $html;

        case 'link_to_futureverse_application':
          $bio_service = \Drupal::service('piv_futureverse.futureverse_once_url_generator'); // @phpcs:ignore
          $journal_poem_id = $sources['journal_poem']->id();
          if ($bio_service->exists('student', $journal_poem_id)) {
            return '';
          }

          $url = $bio_service->generateUrl('student', $journal_poem_id);
          if (!$url) {
            return '';
          }
          $language = $sources['journal_poem']->language();
          $url->setOption('language', $language);
          $text = $this->t('Click here to complete your Futureverse application', [], [
            'langcode' => $language->getId(),
          ]);
          $link = Link::fromTextAndUrl($text, $url)
            ->toString()
            ->getGeneratedLink();
          $html = '<p>';
          $html .= $this->t('You will only be able to use this link once to provide your information.', [], [
            'langcode' => $language->getId(),
          ]);
          $html .= '<br>';
          $html .= $link;
          $html .= '</p>';
          return $html;

        case 'link_to_poet_bio':
          $langcode = $sources['poet_bio']->field_language->target_id;
          return $sources['poet_bio']->toLink('Link to poet bio', 'edit-form', [
            'absolute' => TRUE,
            'language' => $sources['poet_bio']->field_language->entity,
          ])->toString();

        // Competition related.
        case 'competition_entry:student_name':
          $entry = $sources['competition_entry'];
          if (!empty(trim($entry->field_student_stage_name->value))) {
            return trim($entry->field_student_stage_name->value);
          }
          return trim("{$entry->field_student_name->value} {$entry->field_student_last_name->value}");

        case 'competition_entry:teacher_name':
          return $sources['competition_entry']->getOwner()->getDisplayName();

        case 'competition_entry:school_name':
          return $sources['competition_entry']->field_school->entity->label();

        case 'competition_entry:poem_title_1':
        case 'competition_entry:poem_title_2':
        case 'competition_entry:poem_author_1':
        case 'competition_entry:poem_author_2':
          $entry = $sources['competition_entry'];
          // The poems entered do not change with the email language, so they
          // must not be filtered by the notification langcode. A notification
          // is sent once per stream language, so a french email would otherwise
          // find no matching recitation (they carry their own stream language)
          // and render blank. Index 0/1 maps to poem *_1 / *_2.
          $recitations = array_values($entry->field_recitations->referencedEntities());
          $index = str_ends_with($token, '_1') ? 0 : 1;
          $poem = $recitations[$index]?->field_poem?->entity;
          if (str_contains($token, 'poem_title_')) {
            $title = $poem?->title?->value;
            return $title ? "<em>{$title}</em>" : '';
          }
          $author = $poem?->uid?->entity;
          return $author ? $author->getDisplayName() : '';

        case 'competition_entry:stream':
          $stream = $sources['competition_entry']->getStream();
          if ($stream && $stream->hasTranslation($langcode)) {
            $stream = $stream->getTranslation($langcode);
          }
          return $stream->field_label->value;

        // Return the competition level name for the competition entry,
        // so if the competition entry is on level 1 for example, it
        // will return the label in the competition for delta 0, for
        // example: 'Qualifiers'.
        case 'competition_entry:level':
          $entry = $sources['competition_entry'];
          $entry_level = $entry->field_competition_current_level->value;
          $competition = $this->getCompetition();
          if ($competition && $competition->hasTranslation($langcode)) {
            $competition = $competition->getTranslation($langcode);
          }
          return $competition->field_competition_levels[$entry_level - 1]->value;

        case 'competition:title':
          $competition = $this->getCompetition();
          if ($competition && $competition->hasTranslation($langcode)) {
            $competition = $competition->getTranslation($langcode);
          }
          return $competition->label();
      }

      // [rank:*] cases are tokens populated by the user.
      // This needs the rank paragraph.
      if (!empty($sources['paragraph_rank']) && str_starts_with($token, 'rank:')) {
        $to_replace = str_replace('rank:', '', $token);
        foreach ($sources['paragraph_rank']->field_tokens->getValue() as $value) {
          if ($to_replace == $value['key']) {
            return $value['value'];
          }
        }
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
    $this->journalContext = [];
    $this->monthlyPrizeWinner = [];
    return $this;
  }

  /**
   * Do the replacements.
   *
   * If there is a replacements service to compare, a "changed" prefix will
   * be added to the tokens in case they are different.
   */
  public function replace($text) {
    $langcode = $this->getLangcode();
    $prefix = $langcode == 'en' ? '***CHANGED***' : '***MODIFIÉ***';
    $ignore_modified_tokens = [
      'link_to_poet_page_href',
      'link_to_teacher_survey',
      'link_to_poet_survey',
    ];

    if (!preg_match_all('/\[([^\[\]]+)\]/', $text, $matches)) {
      return $text;
    }

    foreach ($matches[1] as $token) {
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
        return $node && $node->langcode == 'fr' ? ['visits@lesvoixdelapoesie.ca'] : ['visits@poetryinvoice.ca'];

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

      case 'journal_poem_email':
        $mail = $sources['journal_poem']->field_email1->value ?? NULL;
        return $mail ? [$mail] : [];

      case 'competition_entry:student':
        $mail = $sources['competition_entry']->field_student_email->value ?? NULL;
        return $mail ? [$mail] : [];

      case 'competition_entry:teacher':
        $mail = $sources['competition_entry']->uid->entity->mail->value ?? NULL;
        return $mail ? [$mail] : [];
    }

    // Default case - return empty array if no case matches.
    return [];

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
