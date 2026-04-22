<?php

namespace Drupal\piv_mail\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\piv_mail\PivMailPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field widget for selecting a PivMail plugin of type 'competition'.
 *
 * @FieldWidget(
 *   id = "piv_mail_plugin_select",
 *   label = @Translation("PivMail plugin select"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class PivMailPluginSelectWidget extends WidgetBase {

  /**
   * The PivMail plugin manager.
   *
   * @var \Drupal\piv_mail\PivMailPluginManager
   */
  protected $pivMailPluginManager;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, PivMailPluginManager $piv_mail_plugin_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->pivMailPluginManager = $piv_mail_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('plugin.manager.piv_mail')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $options = ['' => $this->t('- None -')];
    foreach ($this->pivMailPluginManager->getDefinitions() as $id => $definition) {
      if (($definition['type'] ?? NULL) === 'competition') {
        $options[$id] = $definition['label'];
      }
    }

    asort($options);
    $element['value'] = $element + [
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => $items[$delta]->value ?? '',
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $allowed_fields = [
      'field_student_winner_notificatio',
      'field_student_loser_notification',
      'field_teacher_winner_notificatio',
      'field_teacher_loser_notification',
    ];
    return in_array($field_definition->getName(), $allowed_fields);
  }

}
