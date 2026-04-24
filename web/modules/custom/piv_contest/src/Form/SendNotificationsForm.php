<?php

namespace Drupal\piv_contest\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\piv_contest\CompetitionRankHelper;
use Drupal\piv_mail\PivMailPluginManager;
use Drupal\piv_mail\ReplacementsService;
use Drupal\piv_contest_competition\CompetitionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for sending competition notifications.
 */
class SendNotificationsForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The competition rank helper.
   *
   * @var \Drupal\piv_contest\CompetitionRankHelper
   */
  protected CompetitionRankHelper $rankHelper;

  /**
   * The replacements service.
   *
   * @var \Drupal\piv_mail\ReplacementsService
   */
  protected ReplacementsService $replacementsService;

  /**
   * The PivMail plugin manager.
   *
   * @var \Drupal\piv_mail\PivMailPluginManager
   */
  protected PivMailPluginManager $pivMailManager;

  /**
   * Constructs a SendNotificationsForm.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    CompetitionRankHelper $rank_helper,
    ReplacementsService $replacements_service,
    PivMailPluginManager $piv_mail_manager,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->rankHelper = $rank_helper;
    $this->replacementsService = $replacements_service;
    $this->pivMailManager = $piv_mail_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('piv_contest.competition_rank_helper'),
      $container->get('piv_mail.replacements_service'),
      $container->get('plugin.manager.piv_mail')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'send_notifications_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?CompetitionInterface $competition = NULL, ?string $field_name = NULL) {
    if (!$competition || !$field_name) {
      return $form;
    }

    $form_state->set('competition_id', $competition->id());
    $form_state->set('field_name', $field_name);

    $sent_data = json_decode($competition->get($field_name)->value ?? '[]', TRUE) ?: [];
    $levels = $competition->field_competition_levels->getValue();
    $notification_levels = $competition->field_notification_levels->referencedEntities();
    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');

    $form['#prefix'] = '<div id="send-notifications-wrapper">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'piv_contest/send-notifications-formatter';

    foreach ($levels as $delta => $level_value) {
      $level_number = $delta + 1;
      $level_name = $level_value['value'] ?? "Level $level_number";
      $notification_paragraph = $notification_levels[$delta] ?? NULL;

      $rank_paragraphs_by_rank = $this->buildRankParagraphMap($notification_paragraph);

      $form["level_$level_number"] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Level @num: @name', [
          '@num' => $level_number,
          '@name' => $level_name,
        ]),
      ];

      $winners = $this->rankHelper->getRankedEntriesPerStream($competition, $level_number);
      $losers = $this->rankHelper->getLoserEntriesPerStream($competition, $level_number);

      $button_configs = [
        'student_congrats' => [
          'label' => $this->t('Send student congrats'),
          'entries' => $winners,
          'plugin_field' => 'field_student_winner_notificatio',
          'recipient_type' => 'student',
        ],
        'student_sorry' => [
          'label' => $this->t('Send student sorry'),
          'entries' => $losers,
          'plugin_field' => 'field_student_loser_notification',
          'recipient_type' => 'student',
        ],
        'teacher_congrats' => [
          'label' => $this->t('Send teacher congrats'),
          'entries' => $winners,
          'plugin_field' => 'field_teacher_winner_notificatio',
          'recipient_type' => 'teacher',
        ],
        'teacher_sorry' => [
          'label' => $this->t('Send teacher sorry'),
          'entries' => $losers,
          'plugin_field' => 'field_teacher_loser_notification',
          'recipient_type' => 'teacher',
        ],
      ];

      foreach ($button_configs as $type => $config) {
        $plugin_id = '';
        $plugin_label = '';
        if ($notification_paragraph && $notification_paragraph->hasField($config['plugin_field'])) {
          $plugin_id = $notification_paragraph->get($config['plugin_field'])->value ?? '';
        }
        if ($plugin_id && $this->pivMailManager->hasDefinition($plugin_id)) {
          $plugin_label = $this->pivMailManager->createInstance($plugin_id)->label();
        }

        $has_entries = !empty($config['entries']);
        $was_sent = $this->wasSent($sent_data, $level_number, $type);

        $form["level_$level_number"][$type] = [
          '#type' => 'container',
          '#attributes' => ['style' => ['margin-bottom: 1.5rem;']],
        ];

        $button_text = $config['label'];
        if ($plugin_label) {
          $button_text .= ' (' . $plugin_label . ')';
        }

        $form["level_$level_number"][$type]['send'] = [
          '#type' => 'submit',
          '#value' => $button_text,
          '#name' => "send_{$level_number}_{$type}",
          '#ajax' => [
            'callback' => [$this, 'ajaxSend'],
            'wrapper' => 'send-notifications-wrapper',
          ],
          '#disabled' => empty($plugin_id) || !$has_entries || $was_sent,
          '#attributes' => $was_sent
            ? ['class' => ['button--was-sent']]
            : (str_contains($type, 'congrats') ? ['class' => ['button--success']] : []),
        ];

        if ($was_sent) {
          $form["level_$level_number"][$type]['sent_info'] = [
            '#markup' => '<em>' . $this->t('Sent on @date', [
              '@date' => date('Y-m-d H:i', $was_sent['timestamp']),
            ]) . '</em>',
          ];
        }

        if ($has_entries) {
          $form["level_$level_number"][$type]['recipients'] = $this->buildRecipientsPerStream(
            $config['entries'],
            $config['recipient_type'],
            $rank_paragraphs_by_rank,
            $paragraph_storage
          );
        }
        else {
          $form["level_$level_number"][$type]['no_recipients'] = [
            '#markup' => '<em>' . $this->t('No recipients') . '</em>',
          ];
        }
      }
    }

    return $form;
  }

  /**
   * AJAX callback for send buttons.
   */
  public function ajaxSend(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $triggering = $form_state->getTriggeringElement();
    $name = $triggering['#name'] ?? '';
    if (!preg_match('/^send_(\d+)_(.+)$/', $name, $matches)) {
      return;
    }

    $level = (int) $matches[1];
    $type = $matches[2];

    $competition_id = $form_state->get('competition_id');
    $field_name = $form_state->get('field_name');
    $competition = $this->entityTypeManager
      ->getStorage('competition')->load($competition_id);

    $notification_levels = $competition->field_notification_levels->referencedEntities();
    $notification_paragraph = $notification_levels[$level - 1] ?? NULL;

    $type_config = [
      'student_congrats' => [
        'plugin_field' => 'field_student_winner_notificatio',
        'recipient_type' => 'student',
        'is_winner' => TRUE,
      ],
      'student_sorry' => [
        'plugin_field' => 'field_student_loser_notification',
        'recipient_type' => 'student',
        'is_winner' => FALSE,
      ],
      'teacher_congrats' => [
        'plugin_field' => 'field_teacher_winner_notificatio',
        'recipient_type' => 'teacher',
        'is_winner' => TRUE,
      ],
      'teacher_sorry' => [
        'plugin_field' => 'field_teacher_loser_notification',
        'recipient_type' => 'teacher',
        'is_winner' => FALSE,
      ],
    ];

    $config = $type_config[$type] ?? NULL;
    if (!$config || !$notification_paragraph) {
      $this->messenger()->addError($this->t('Invalid notification configuration.'));
      return;
    }

    $plugin_id = $notification_paragraph->get($config['plugin_field'])->value ?? '';
    if (empty($plugin_id)) {
      $this->messenger()->addError($this->t('No mail plugin configured for this notification.'));
      return;
    }

    $entries_per_stream = $config['is_winner']
      ? $this->rankHelper->getRankedEntriesPerStream($competition, $level)
      : $this->rankHelper->getLoserEntriesPerStream($competition, $level);

    $rank_paragraphs_by_rank = $this->buildRankParagraphMap($notification_paragraph);

    $recipients = [];
    $success = TRUE;

    foreach ($entries_per_stream as $sessions_ranked) {
      foreach ($sessions_ranked as $session_ranked) {
        foreach ($session_ranked as $rank => $rank_entries) {
          $rank_paragraph = $rank_paragraphs_by_rank[$rank] ?? NULL;
          foreach ($rank_entries as $entry) {
          $replacements_service = clone $this->replacementsService;
          $replacements_service->addSource('competition_entry', $entry);
          if ($rank_paragraph) {
            $replacements_service->addSource('paragraph_rank', $rank_paragraph);
          }

          $langcode = $entry->langcode->value ?? 'en';
          $result = piv_mail_send_mail($plugin_id, $langcode, $replacements_service);

          $email = $this->getRecipientEmail($entry, $config['recipient_type']);
          if ($email) {
            $recipients[] = $email;
          }

          if (!$result) {
            $success = FALSE;
          }
          }
        }
      }
    }

    $sent_data = json_decode($competition->get($field_name)->value ?? '[]', TRUE) ?: [];
    $sent_data[] = [
      'timestamp' => time(),
      'level' => $level,
      'type' => $type,
      'plugin_id' => $plugin_id,
      'recipients' => $recipients,
      'success' => $success,
    ];

    $competition->set($field_name, json_encode($sent_data));
    $competition->save();

    if ($success) {
      $this->messenger()->addStatus($this->t('Notifications sent successfully.'));
    }
    else {
      $this->messenger()->addWarning($this->t('Some notifications may have failed to send.'));
    }

    $form_state->setRebuild();
  }

  /**
   * Builds rank paragraph map keyed by rank value.
   */
  protected function buildRankParagraphMap($notification_paragraph): array {
    $map = [];
    if (!$notification_paragraph || !$notification_paragraph->hasField('field_ranks')) {
      return $map;
    }
    foreach ($notification_paragraph->field_ranks->referencedEntities() as $rank_paragraph) {
      $rank_value = (int) $rank_paragraph->field_rank->value;
      $map[$rank_value] = $rank_paragraph;
    }
    return $map;
  }

  /**
   * Builds recipients display grouped by stream with ranks.
   */
  protected function buildRecipientsPerStream(array $entries_per_stream, string $recipient_type, array $rank_paragraphs_by_rank, $paragraph_storage): array {
    $container = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['send-notifications-recipients'],
      ],
      '#attached' => [
        'library' => ['piv_contest/send-notifications-formatter'],
      ],
    ];

    $session_storage = \Drupal::entityTypeManager()->getStorage('judging_session');

    foreach ($entries_per_stream as $stream_id => $sessions_ranked) {
      $stream = $paragraph_storage->load($stream_id);
      $stream_label = $stream ? $stream->field_label->value : '';

      $session_containers = [];
      foreach ($sessions_ranked as $session_id => $session_ranked) {
        $session = $session_storage->load($session_id);
        $session_label = $session ? $session->label() : $this->t('Session @id', ['@id' => $session_id]);
        $items = [];
        foreach ($session_ranked as $rank => $entries) {
          $has_tokens = isset($rank_paragraphs_by_rank[$rank])
            && !$rank_paragraphs_by_rank[$rank]->field_tokens->isEmpty();
          $tokens_text = $has_tokens ? ' <em>(tokens configured)</em>' : '';

          foreach ($entries as $entry) {
            $name = $entry->getStudentsDisplayName();
            $email = $this->getRecipientEmail($entry, $recipient_type);
            $label = $email ? "$name ($email)" : $name;
            $items[] = [
              '#markup' => $this->t('Rank @rank: @label', [
                '@rank' => $rank,
                '@label' => $label,
              ]) . $tokens_text,
            ];
          }
        }
        if (!empty($items)) {
          $session_containers["session_$session_id"] = [
            '#type' => 'container',
            'title' => [
              '#markup' => '<h5>' . $session_label . '</h5>',
            ],
            'list' => [
              '#theme' => 'item_list',
              '#items' => $items,
            ],
          ];
        }
      }

      if (!empty($session_containers)) {
        $container["stream_$stream_id"] = [
          '#type' => 'container',
          'title' => [
            '#markup' => '<h4>' . $stream_label . '</h4>',
          ],
        ] + $session_containers;
      }
    }

    return $container;
  }

  /**
   * Gets the recipient email for an entry.
   */
  protected function getRecipientEmail($entry, string $recipient_type): string {
    if ($recipient_type === 'student') {
      return $entry->field_student_email->value ?? '';
    }
    $owner = $entry->getOwner();
    return $owner ? $owner->getEmail() : '';
  }

  /**
   * Checks if a notification was already sent.
   */
  protected function wasSent(array $sent_data, int $level, string $type): ?array {
    foreach ($sent_data as $record) {
      if (($record['level'] ?? 0) === $level && ($record['type'] ?? '') === $type) {
        return $record;
      }
    }
    return NULL;
  }

}
