<?php

namespace Drupal\piv_contest_recitation\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\piv_contest_competition\Entity\Competition;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
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

    $stream = $competition_entry->field_stream->entity;
    $number_languages = count($stream->field_stream_languages) ?? 1;
    $number_languages = $number_languages === 0 ? 1 : $number_languages;
    $recitations_required = $number_languages * (int) $stream->field_min_recitations->value;
    $recitations = $competition_entry->field_recitations->referencedEntities();
    while (count($recitations) < $recitations_required) {
      $recitations[] = $this->entityTypeManager
        ->getStorage('recitation')
        ->create([
          'bundle' => 'default',
        ]);
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
        $this->t('Poem'),
        $this->t('Language'),
        $this->t('Operations'),
        //$this->t('Weight'),
      ],
      // Table draw is not enabled since we don't know if it is required, if it
      // is, then uncomment the weight table column too below in code.
      /*'#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ],
      ],*/
    ];

    foreach (array_values($recitations) as $i => $recitation) {
      $recitation_form = [
        '#type' => 'html_tag',
        '#tag' => 'dialog',
        '#attributes' => [
          'class' => ['recitation-form'],
        ],
      ];
      $poem = $recitation->field_poem->entity;
      $recitation_form['field_poem'][0]['target_id'] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'node',
        '#selection_settings' => [
          'target_bundles' => ['poem'],
        ],
        '#default_value' => $poem,
        '#title' => $this->t('Poem'),
      ];

      $media_entity = $recitation->field_recitation_video->entity;
      $recitation_form['field_recitation_video'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Video'),
        '#access' => $is_online,
        'video_title' => [
          '#type' => 'item',
          '#title' => 'Title',
          '#markup' => $media_entity ? '<div>' . $media_entity->label() . '</div>' : '',
        ],
        'value' => [
          '#type' => 'textfield',
          '#title' => $this->t('Remote video URL'),
          '#default_value' => $media_entity ? $media_entity->field_media_oembed_video->value : NULL,
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
      $form['recitations'][] = [
        '#attributes' => [
          'class' => ['draggable'],
        ],
        'poem' => [
          '#markup' => $poem ? $poem->label() : NULL,
        ],
        'language' => [
          '#markup' => $poem ? $poem->language()->getName() : NULL,
        ],
        'operations' => [
          'edit' => [
            '#markup' => "<a href='#' class='button btn recitation-open-modal'>{$edit_label}</a>",
          ],
          'delete' => [
            '#type' => 'submit',
            '#submit' => ['::submitDelete'],
            '#value' => $this->t('Remove'),
            '#recitation_delta' => $i,
            '#name' => "delete[$i]",
            '#access' => !$is_new,
            '#ajax' => [
              'callback' => '::ajaxRefresh',
              'wrapper' => 'recitations-form-wrapper',
              'event' => 'click',
            ],
          ],
          'form' => $recitation_form,
        ],
        /*'weight' => [
          '#type' => 'weight',
          '#title' => $this->t('Weight'),
          '#title_display' => 'invisible',
          '#default_value' => $i,
          '#attributes' => [
            'class' => [
              'table-sort-weight',
            ],
          ],
        ],*/
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

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
    $recitation_entity_ids = [];
    $recitations = $values['recitations'];
    if (is_numeric($delta)) {
      $recitations = [$recitations[$delta]];
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
          // Medias are added to new recitations in the
          // piv_contest_recitation_recitation_create() hook.
          if ($media = $entity->field_recitation_video->entity) {
            $media->field_media_oembed_video = $value;
            $media->save();
            // This is for new medias to be attached to the recitation.
            $entity->field_recitation_video = [['target_id' => $media->id()]];
          }
        }
        else {
          if ($entity->hasField($field_name)) {
            $entity->$field_name = $value;
          }
        }
      }
      $entity->save();
      $recitation_entity_ids[] = $entity->id();
    }
    $competition_entry = $form_state->get('competition_entry');
    $existing_recitations = array_column($competition_entry->field_recitations->getValue(), 'target_id');
    $recitation_entity_ids = array_unique(array_merge($existing_recitations, $recitation_entity_ids));
    $competition_entry->field_recitations = $recitation_entity_ids;
    $competition_entry->save();
  }

  /**
   * {@inheritdoc}
   */
  public function submitDelete(&$form, FormStateInterface $form_state) {
    $form_state->setRebuild();
    $triggering_element = $form_state->getTriggeringElement();
    $delta = $triggering_element['#recitation_delta'];
    $values = $form_state->getValues();
    $recitations = $values['recitations'];
    $entity = $recitations[$delta]['operations']['form']['entity'] ?? NULL;
    if ($entity) {
      $entity->delete();
    }
  }

}
