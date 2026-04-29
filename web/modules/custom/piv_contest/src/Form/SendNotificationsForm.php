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
use Drupal\piv_contest\NotificationLogs;

/**
 * Form for sending competition notifications.
 */
class SendNotificationsForm extends FormBase {

  /**
   * Constructs a SendNotificationsForm.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CompetitionRankHelper $rankHelper,
    protected ReplacementsService $replacementsService,
    protected PivMailPluginManager $pivMailManager,
    protected NotificationLogs $notificationLogs,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('piv_contest.competition_rank_helper'),
      $container->get('piv_mail.replacements_service'),
      $container->get('plugin.manager.piv_mail'),
      $container->get('piv_contest.notification_logs'),
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
  public function buildForm(array $form, FormStateInterface $form_state, ?CompetitionInterface $competition = NULL) {
    if (!$competition) {
      return $form;
    }

    $form_state->set('competition_id', $competition->id());
    $levels = $competition->field_competition_levels->getValue();
    $notification_levels = $competition->field_notification_levels->referencedEntities();
    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');

    $logs = $this->notificationLogs->getLogsForCompetition($competition->id());

    $form['#prefix'] = '<div id="send-notifications-wrapper">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'piv_contest/send-notifications-formatter';

    $form['levels'] = [
      '#type' => 'horizontal_tabs',
      '#group_name' => 'levels',
    ];

    foreach ($levels as $delta => $level_value) {
      $level_number = $delta + 1;
      $level_name = $level_value['value'] ?? "Level $level_number";
      $notification_paragraph = $notification_levels[$delta] ?? NULL;

      $rank_paragraphs_by_rank = $this->buildRankParagraphMap($notification_paragraph);

      $form['levels']["level_$level_number"] = [
        '#type' => 'details',
        '#title' => $this->t('Level @num: @name', [
          '@num' => $level_number,
          '@name' => $level_name,
        ]),
        '#group' => 'levels',
        '#open' => $delta === 0,
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

      $delta = 0;
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

        $form['levels']["level_$level_number"][$type] = [
          '#type' => 'fieldset',
          '#title' => $config['label'],
          '#attributes' => [
            'class' => [
              'notifications-wrapper',
              $delta++ % 2 == 0 ? 'odd' : 'even',
            ],
          ],
        ];

        $button_text = $config['label'];
        if ($plugin_label) {
          $button_text .= ' (' . $plugin_label . ')';
        }

        $form['levels']["level_$level_number"][$type]['send'] = [
          '#type' => 'submit',
          '#value' => $button_text,
          '#name' => "send_{$level_number}_{$type}",
          '#ajax' => [
            'callback' => [$this, 'ajaxSend'],
            'wrapper' => 'send-notifications-wrapper',
          ],
          // Disable sending to losers too if there are no winners.
          '#disabled' => empty($plugin_id) || !$has_entries || !$winners,
          '#attributes' => str_contains($type, 'congrats')
            ? ['class' => ['button--success']]
            : [],
        ];

        if ($has_entries) {
          $form['levels']["level_$level_number"][$type]['recipients'] = $this->buildRecipientsPerStream(
            $config['entries'],
            $config['recipient_type'],
            $rank_paragraphs_by_rank,
            $paragraph_storage,
            $logs,
            $level_number,
            $type,
            $this->getLogType($type),
            !empty($plugin_id) && $winners,
          );
        }
        else {
          $form['levels']["level_$level_number"][$type]['no_recipients'] = [
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

    if (preg_match('/^send_entry_(\d+)_(\w+)_(\d+)_(\d+)$/', $name, $matches)) {
      $this->submitSingleEntry($form_state, (int) $matches[1], $matches[2], (int) $matches[3], (int) $matches[4]);
    }
    elseif (preg_match('/^send_(\d+)_(.+)$/', $name, $matches)) {
      $this->submitAllEntries($form_state, (int) $matches[1], $matches[2]);
    }

    $form_state->setRebuild();
  }

  /**
   * Send email to single entry.
   */
  protected function submitSingleEntry(FormStateInterface $form_state, int $level, string $type, int $entry_id, int $stream_id) {
    [$competition, $notification_paragraph, $plugin_id, $log_type] = $this->resolveNotificationConfig($form_state, $level, $type);
    if (!$plugin_id) {
      return;
    }

    $entry = $this->entityTypeManager->getStorage('competition_entry')->load($entry_id);
    if (!$entry) {
      $this->messenger()->addError($this->t('Entry not found.'));
      return;
    }

    $rank_paragraphs_by_rank = $this->buildRankParagraphMap($notification_paragraph);
    $entries_per_stream = str_contains($type, 'congrats')
      ? $this->rankHelper->getRankedEntriesPerStream($competition, $level)
      : $this->rankHelper->getLoserEntriesPerStream($competition, $level);

    $rank_paragraph = $this->findRankParagraphForEntry($entry_id, $entries_per_stream, $rank_paragraphs_by_rank);

    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
    $stream = $paragraph_storage->load($stream_id);
    if (!$stream) {
      return;
    }

    $stream_languages = array_column($stream->field_stream_languages->getValue(), 'target_id');
    $success = FALSE;

    foreach ($stream_languages as $langcode) {
      $replacements_service = clone $this->replacementsService;
      $replacements_service->addSource('competition_entry', $entry);
      if ($rank_paragraph) {
        $replacements_service->addSource('paragraph_rank', $rank_paragraph);
      }
      $result = piv_mail_send_mail($plugin_id, $langcode, $replacements_service);
      if ($result) {
        $success = TRUE;
      }
      $log = $this->notificationLogs->getLogsForCompetitionEntry($entry_id, $level, $log_type, $langcode);
      $log->field_email_status = $result ? 'sent' : 'failed_to_send';
      $log->save();
    }

    if ($success) {
      $this->messenger()->addStatus($this->t('Notification sent.'));
    }
    else {
      $this->messenger()->addWarning($this->t('Notification may have failed to send.'));
    }
  }

  /**
   * Send emails to all.
   */
  protected function submitAllEntries(FormStateInterface $form_state, int $level, string $type) {
    [$competition, $notification_paragraph, $plugin_id, $log_type] = $this->resolveNotificationConfig($form_state, $level, $type);
    if (!$plugin_id) {
      return;
    }

    $type_config = $this->getTypeConfig();
    $config = $type_config[$type];
    $entries_per_stream = $config['is_winner']
      ? $this->rankHelper->getRankedEntriesPerStream($competition, $level)
      : $this->rankHelper->getLoserEntriesPerStream($competition, $level);

    $rank_paragraphs_by_rank = $this->buildRankParagraphMap($notification_paragraph);
    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
    $success = FALSE;

    foreach ($entries_per_stream as $stream_id => $sessions_ranked) {
      $stream = $paragraph_storage->load($stream_id);
      if (!$stream) {
        continue;
      }

      $stream_languages = array_column($stream->field_stream_languages->getValue(), 'target_id');
      foreach ($sessions_ranked as $session_ranked) {
        foreach ($session_ranked as $rank => $rank_entries) {
          $rank_paragraph = $rank_paragraphs_by_rank[$rank] ?? NULL;
          foreach ($rank_entries as $entry) {
            $replacements_service = clone $this->replacementsService;
            $replacements_service->addSource('competition_entry', $entry);
            if ($rank_paragraph) {
              $replacements_service->addSource('paragraph_rank', $rank_paragraph);
            }

            foreach ($stream_languages as $langcode) {
              $result = piv_mail_send_mail($plugin_id, $langcode, $replacements_service);
              if ($result) {
                $success = TRUE;
              }
              $log = $this->notificationLogs->getLogsForCompetitionEntry($entry->id(), $level, $log_type, $langcode);
              $log->field_email_status = $result ? 'sent' : 'failed_to_send';
              $log->save();
            }
          }
        }
      }
    }

    if ($success) {
      $this->messenger()->addStatus($this->t('Notifications sent successfully.'));
    }
    else {
      $this->messenger()->addWarning($this->t('Some notifications may have failed to send.'));
    }
  }

  /**
   * Get configs.
   */
  protected function resolveNotificationConfig(FormStateInterface $form_state, int $level, string $type): array {
    $competition_id = $form_state->get('competition_id');
    $competition = $this->entityTypeManager
      ->getStorage('competition')->load($competition_id);

    $notification_levels = $competition->field_notification_levels->referencedEntities();
    $notification_paragraph = $notification_levels[$level - 1] ?? NULL;

    $type_config = $this->getTypeConfig();
    $config = $type_config[$type] ?? NULL;
    if (!$config || !$notification_paragraph) {
      $this->messenger()->addError($this->t('Invalid notification configuration.'));
      return [$competition, NULL, NULL, NULL];
    }

    $plugin_id = $notification_paragraph->get($config['plugin_field'])->value ?? '';
    if (empty($plugin_id)) {
      $this->messenger()->addError($this->t('No mail plugin configured for this notification.'));
      return [$competition, $notification_paragraph, NULL, NULL];
    }

    return [$competition, $notification_paragraph, $plugin_id, $this->getLogType($type)];
  }

  /**
   * Get config "type".
   */
  protected function getTypeConfig(): array {
    return [
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
  }

  /**
   *
   */
  protected function findRankParagraphForEntry(int $entry_id, array $entries_per_stream, array $rank_paragraphs_by_rank) {
    foreach ($entries_per_stream as $sessions_ranked) {
      foreach ($sessions_ranked as $session_ranked) {
        foreach ($session_ranked as $rank => $entries) {
          foreach ($entries as $entry) {
            if ((int) $entry->id() === $entry_id) {
              return $rank_paragraphs_by_rank[$rank] ?? NULL;
            }
          }
        }
      }
    }
    return NULL;
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
  protected function buildRecipientsPerStream(array $entries_per_stream, string $recipient_type, array $rank_paragraphs_by_rank, $paragraph_storage, array $logs = [], int $level = 0, string $type = '', string $log_type = '', bool $has_plugin = FALSE): array {
    $container = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['send-notifications-recipients'],
      ],
      '#attached' => [
        'library' => ['piv_contest/send-notifications-formatter'],
      ],
    ];

    $session_storage = $this->entityTypeManager->getStorage('judging_session');

    foreach ($entries_per_stream as $stream_id => $sessions_ranked) {
      $stream = $paragraph_storage->load($stream_id);
      $stream_label = $stream ? $stream->field_label->value : '';

      $stream_languages = $stream ? array_column($stream->field_stream_languages->getValue(), 'target_id') : [];

      $session_containers = [];
      foreach ($sessions_ranked as $session_id => $session_ranked) {
        $session = $session_storage->load($session_id);
        $session_label = $session ? $session->label() : $this->t('Session @id', ['@id' => $session_id]);
        $items = [];
        foreach ($session_ranked as $rank => $entries) {
          $has_tokens = isset($rank_paragraphs_by_rank[$rank])
            && !$rank_paragraphs_by_rank[$rank]->field_tokens->isEmpty();

          foreach ($entries as $entry) {
            $entry_logs = [];
            foreach ($stream_languages as $langcode) {
              $key = implode(':', [$entry->id(), $level, $log_type, $langcode]);
              if (isset($logs[$key])) {
                $entry_logs[$langcode] = $logs[$key];
              }
            }

            $entry_key = "entry_{$entry->id()}";
            $items[$entry_key] = [
              '#type' => 'container',
              '#attributes' => ['class' => ['notification-recipient-entry']],
              // No need for a </li>.
              '#prefix' => '<li class="list-group-item">',
              'info' => [
                '#theme' => 'notification_recipient_item',
                '#entry' => $entry,
                '#rank' => $rank,
                '#has_tokens' => $has_tokens,
                '#recipient_type' => $recipient_type,
                '#logs' => $entry_logs,
              ],
              'send' => [
                '#type' => 'submit',
                '#value' => $this->t('Send'),
                '#name' => "send_entry_{$level}_{$type}_{$entry->id()}_{$stream_id}",
                '#ajax' => [
                  'callback' => [$this, 'ajaxSend'],
                  'wrapper' => 'send-notifications-wrapper',
                ],
                '#disabled' => !$has_plugin,
                '#attributes' => [
                  'class' => ['button--small', 'send-notification-entry'],
                ],
              ],
            ];
          }
        }
        if (!empty($items)) {
          $session_containers["session_$session_id"] = [
            '#type' => 'container',
            '#attributes' => [
              'class' => ['item-list'],
            ],
            'title' => [
              '#markup' => '<h5>' . $session_label . '</h5>',
            ],

            'list' => [
              '#type' => 'html_tag',
              '#tag' => 'ul',
              '#attributes' => [
                'class' => ['list-group'],
              ],
            ] + $items,
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
   * Get log type.
   */
  private function getLogType(string $type): string {
    return match($type) {
      'student_congrats' => 'student_winner_notification',
      'student_sorry' => 'student_loser_notification',
      'teacher_congrats' => 'teacher_winner_notification',
      'teacher_sorry' => 'teacher_loser_notification',
    };
  }

}
