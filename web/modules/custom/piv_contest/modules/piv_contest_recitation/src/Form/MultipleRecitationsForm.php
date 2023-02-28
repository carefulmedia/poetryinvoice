<?php

namespace Drupal\piv_contest_recitation\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\piv_contest_competition\Entity\Competition;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\media\OEmbed\UrlResolverInterface;

/**
 * Provides a PIV Contest Recitation form.
 */
class MultipleRecitationsForm extends FormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The oEmbed URL resolver service.
   *
   * @var \Drupal\media\OEmbed\UrlResolverInterface
   */
  protected $urlResolver;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, UrlResolverInterface $url_resolver) {
    $this->entityTypeManager = $entity_type_manager;
    $this->urlResolver = $url_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('media.oembed.url_resolver')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'piv_contest_recitation_multiple_recitations';
  }

  /**
   * Refresh the form with ajax.
   */
  public function ajaxRefresh($form, $form_state) {
    // If there is a error in a video, print that error and open the dialog.
    $triggering_element = $form_state->getTriggeringElement();
    $delta = $triggering_element['#recitation_delta'] ?? NULL;
    if (is_numeric($delta)) {
      // The dialog[open] attribute doesn't look ok. A custom javascript
      // opens the dialog.
      if ($form_state->hasAnyErrors()) {
        $form['recitations'][$delta]['operations']['form']['#attributes']['ajax-open'] = TRUE;
        array_unshift($form['recitations'][$delta]['operations']['form'], [
          '#type' => 'status_messages',
        ]);
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Competition $competition = NULL, $competition_entry = NULL) {
    $form_state->set('competition', $competition);
    $form_state->set('competition_entry', $competition_entry);
    $form['#attributes']['autocomplete'] = 'off';
    $form['#tree'] = TRUE;
    $form['#prefix'] = '<div id="recitations-form-wrapper">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'piv_contest_recitation/multiple-recitations-form';
    // phpcs:ignore
    $is_team_competition = !empty($competition->field_team_competition->value);
    $is_online = !empty($competition->field_online_competition->value);

    $recitations = [];
    $stream = $competition_entry->field_stream->entity;
    foreach ($stream->field_stream_languages->referencedEntities() as $language) {
      $recitations[$language->id()] = [];
    }
    $recitations_required_per_language = (int) $stream->field_min_recitations->value;
    foreach ($competition_entry->field_recitations->referencedEntities() as $recitation) {
      $langcode = $recitation->language()->getId();
      // Ignore recitations that are not for the stream languages for some
      // reason.
      if (isset($recitations[$langcode])) {
        $recitations[$langcode][] = $recitation;
      }
    }
    $need_save = FALSE;
    foreach ($recitations as $langcode => $recitations_for_language) {
      while (count($recitations[$langcode]) < $recitations_required_per_language) {
        $new_recitation = $this->entityTypeManager
          ->getStorage('recitation')
          ->create([
            'bundle' => 'default',
            'langcode' => $langcode,
            'field_stream_language' => $langcode,
          ]);
        if ($new_recitation->save()) {
          $need_save = TRUE;
          $recitations[$langcode][] = $new_recitation;
        }
      }
    }
    // Flatten the array.
    $recitations = array_merge(...array_values($recitations));
    if ($need_save) {
      $competition_entry->field_recitations = $recitations;
      $competition_entry->save();
    }

    $form['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Recitations'),
    ];
    // Recitations form is a custom table.
    $form['recitations'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Order'),
        $this->t('Poem'),
        $this->t('Video'),
        $this->t('Language'),
        $this->t('Operations'),
        $this->t('Weight'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ],
      ],
    ];

    // Map the grades from the Competition to the Poems.
    // It is confusing that 'grade 6' points to 'Grades 7 & 8 / Sec 1 & 2',
    // this is because the 'Grades 7 & 8 / Sec 1 & 2' is actually the option
    // value, but the option label is "6 to 8". This is legacy code.
    $grades_map = [
      'grade 6' => 'Grades 7 & 8 / Sec 1 & 2',
      'grade 7' => 'Grades 7 & 8 / Sec 1 & 2',
      'grade 8' => 'Grades 7 & 8 / Sec 1 & 2',
      'grade 9' => 'Grades 9-12 / Sec 3-5 / CEGEP 1',
      'grade 10' => 'Grades 9-12 / Sec 3-5 / CEGEP 1',
      'grade 11' => 'Grades 9-12 / Sec 3-5 / CEGEP 1',
      'grade 12/CEGEP I' => 'Grades 9-12 / Sec 3-5 / CEGEP 1',
    ];
    $allowed_grades = array_column($competition->field_allowed_grades->getValue(), 'value');

    // See PIV-479: https://monarq.atlassian.net/browse/PIV-479.
    $allowed_grades = [
      'grade 8',
      'grade 9',
    ];

    $poem_grades = [];
    foreach ($allowed_grades as $allowed_grade) {
      $poem_grades[] = $grades_map[$allowed_grade] ?? NULL;
    }
    $poem_grades = array_filter(array_unique($poem_grades));
    $poem_grades_arg = implode('+', $poem_grades);
    foreach (array_values($recitations) as $i => $recitation) {
      $media = $recitation->field_recitation_video->entity;
      if (!$media) {
        $media = $this->entityTypeManager->getStorage('media')->create([
          'bundle' => 'remote_video',
        ]);
        $recitation->field_recitation_video = [$media];
      }
      $recitation_form = [
        '#type' => 'html_tag',
        '#tag' => 'dialog',
        '#attributes' => [
          'class' => [
            'recitation-form',
            "recitation-form--{$i}",
          ],
        ],
      ];
      $poem = $recitation->field_poem->entity;
      $langcode = $recitation->field_stream_language->target_id;
      $recitation_form['field_poem'][0]['target_id'] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'node',
        '#selection_handler' => 'views',
        '#selection_settings' => [
          // @see piv_contest_recitation_views_query_alter()
          'view' => [
            'view_name' => 'language_restricted_poems',
            'display_name' => 'entity_reference_1',
            'arguments' => [$langcode, $poem_grades_arg],
          ],
          'match_operator' => 'CONTAINS',
        ],
        '#default_value' => $poem,
        '#title' => $this->t('Poem'),
        '#description' => $this->t('Begin typing the poem title and then select it from the list.'),
        '#maxlength' => 500,
      ];

      $media_entity = $recitation->field_recitation_video->entity;
      $recitation_form['field_recitation_video'] = [
        '#type' => 'fieldset',
        '#access' => $is_online,
        'value' => [
          '#type' => 'textfield',
          '#title' => $this->t('YouTube URL'),
          '#default_value' => $media_entity ? $media_entity->field_media_oembed_video->value : NULL,
          '#description' => $this->t("Copy and paste the YouTube URL for your student's video"),
        ],
      ];
      $recitation_form['entity'] = [
        '#type' => 'value',
        '#value' => $recitation,
      ];
      $recitation_form['revision_log']['#access'] = FALSE;
      $recitation_form['#tree'] = TRUE;
      $recitation_form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        '#name' => "submit[$i]",
        '#recitation_delta' => $i,
        '#validate' => [
          '::validateRecitation',
        ],
        '#ajax' => [
          'callback' => '::ajaxRefresh',
          'wrapper' => 'recitations-form-wrapper',
          'event' => 'click',
        ],
      ];
      $recitation_form['cancel'] = [
        '#type' => 'inline_template',
        '#template' => '<a href="#" class="button btn close-modal">{{ cancel }}</a>',
        '#context' => [
          'cancel' => $this->t('Cancel'),
        ],
      ];
      // Table row.
      $is_new = $recitation->isNew();
      $edit_label = $is_new ? $this->t('Add') : $this->t('Edit');
      // Video modal.
      $video = [];
      $embedded_video = $media_entity
        ? $media_entity->field_media_oembed_video->view('oembed')
        : NULL;
      if (isset($embedded_video[0])) {
        $label = $this->t('Watch video');
        $video = [
          'open_modal' => [
            '#markup' => "<a href='#' class='recitation-open-modal'>{$label}</a>",
          ],
          'modal' => [
            '#type' => 'html_tag',
            '#tag' => 'dialog',
            '#attributes' => [
              'class' => [
                'video-modal',
                'recitation-form',
              ],
            ],
            'close' => [
              '#markup' => '<div class="btn-close close-modal"></div>',
            ],
            'video' => $embedded_video,
          ],
        ];
      }
      $form['recitations'][$i] = [
        '#attributes' => [
          'class' => ['draggable'],
        ],
        'order' => [
          '#markup' => $i + 1,
        ],
        'poem' => [
          '#markup' => $poem ? $poem->label() : NULL,
        ],
        'video' => $video,
        'language' => [
          '#markup' => $recitation->field_stream_language->entity->getName(),
        ],
        'operations' => [
          'edit' => [
            '#markup' => "<a href='#' class='button btn recitation-open-modal'>{$edit_label}</a>",
          ],
          'form' => $recitation_form,
        ],
        'weight' => [
          '#type' => 'weight',
          '#title' => $this->t('Weight'),
          '#title_display' => 'invisible',
          '#default_value' => $i,
          '#attributes' => [
            'class' => [
              'table-sort-weight',
            ],
          ],
        ],
      ];
    }
    return $form;
  }

  /**
   * Validate a media url.
   */
  public function validateRecitation(array &$form, FormStateInterface $form_state) {
    // Replace the poem error.
    $should_replace_errors = FALSE;
    $errors = $form_state->getErrors();
    foreach ($errors as &$error) {
      $string = $error->getUntranslatedString();
      if ($string == 'There are no @entity_type_plural matching "%value".') {
        $error = $this->t('No poem found with the title: %value', $error->getArguments());
        $should_replace_errors = TRUE;
      }
    }
    if ($should_replace_errors) {
      $form_state->clearErrors();
      foreach ($errors as $key => $error) {
        $form_state->setErrorByName($key, $error);
      }
    }

    $values = $form_state->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $delta = $triggering_element['#recitation_delta'] ?? NULL;
    $recitations = $values['recitations'];
    if (is_numeric($delta)) {
      $recitations = [$delta => $recitations[$delta]];
    }
    foreach ($recitations as $key => $entry) {
      $recitation = $entry['operations']['form'];
      $url = $recitation['field_recitation_video']['value'] ?? NULL;
      $entity = $recitation['entity'];
      // Only validate if url is not empty.
      if ($url) {
        $media = $entity->field_recitation_video->entity;
        $field_name = "recitations][{$key}][operations][form][field_recitation_video][value";
        $error_message = $this->t("The url provided is not a valid YouTube url.");
        try {
          $provider = $this->urlResolver->getProviderByUrl($url);
          $source = $media->getSource();
          if (!in_array($provider->getName(), $source->getProviders(), TRUE)) {
            $form_state->setErrorByName($field_name, $error_message);
          }
        }
        catch (\Exception $e) {
          $form_state->setErrorByName($field_name, $error_message);
        }
      }
    }
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
    $this->messenger()->addStatus($this->t('The recitation has been saved.'));
    $form_state->setRebuild();
    $values = $form_state->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $delta = $triggering_element['#recitation_delta'] ?? NULL;
    $recitations = $values['recitations'];
    if (is_numeric($delta)) {
      $recitations = [$delta => $recitations[$delta]];
    }
    foreach ($recitations as $entry) {
      $recitation = $entry['operations']['form'];
      $entity = $recitation['entity'];
      unset($recitation['entity']);
      foreach ($recitation as $field_name => $value) {
        // Videos are medias, check if there is one in the recitation entity,
        // otherwise create one populate and save it there. This field
        // cardinality is 1.
        if ($field_name == 'field_recitation_video') {
          $media = $entity->field_recitation_video->entity;
          $media->field_media_oembed_video = $value['value'];
          $media->save();
          $entity->field_recitation_video = [['target_id' => $media->id()]];
        }
        else {
          if ($entity->hasField($field_name)) {
            $entity->$field_name = $value;
          }
        }
      }
      $entity->save();
    }
    // The competition entry should not be saved on this submit.
  }

}
