<?php

namespace Drupal\piv_contest\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\piv_contest\Form\SendNotificationsForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field formatter for sending competition notifications.
 *
 * @FieldFormatter(
 *   id = "send_notifications",
 *   label = @Translation("Send Notifications"),
 *   field_types = {
 *     "string_long"
 *   }
 * )
 */
class SendNotificationsFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected FormBuilderInterface $formBuilder;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, FormBuilderInterface $form_builder) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->formBuilder = $form_builder;
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
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $entity = $items->getEntity();
    $field_name = $items->getName();
    $elements = [];
    $elements[0] = $this->formBuilder->getForm(
      SendNotificationsForm::class,
      $entity,
      $field_name
    );
    return $elements;
  }

}
