<?php

declare(strict_types=1);

namespace Drupal\piv_base\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Provides Header Images field handler.
 *
 * @ViewsField("piv_base_header_images")
 */
final class HeaderImages extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);
    $form['information'] = [
      '#type' => 'markup',
      '#markup' => 'Print images for headers from the field_mixtape_image or field_images_insert fields. Images are printed twice inside a div with a class "blur" or "noblur"',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): string|MarkupInterface {
    $node = $values?->_entity;
    if (!$node) {
      return "";
    }

    $field = $node?->field_mixtape_image?->entity
      ? 'field_mixtape_image'
      : 'field_images_insert';

    if ($node->hasField($field)) {
      $filemime = $node->{$field}?->entity?->filemime?->value;
      $value = $node->field_images_insert[0]->getValue();

      // Base image build with 2 images in div containers and no image
      // styles.
      $build = [];
      $build['blur'] = [
        '#theme' => 'image',
        '#uri' => $node->field_images_insert[0]->entity->uri->value,
        '#alt' => $value['alt'],
        '#title' => $value['title'],
        '#width' => $value['width'],
        '#height' => $value['height'],
        '#prefix' => "<div class='blur'>",
        '#suffix' => "</div>",
        '#attributes' => ['loading' => 'eager'],
      ];
      $build['noblur'] = $build['blur'];
      $build['noblur']['#prefix'] = "<div class='noblur'>";

      // Display original images, do not use responsive images or image
      // styles since resizing animates gifs will stop their animations.
      if ($filemime == 'image/gif') {
        return($this->getRenderer()->render($build));
      }

      foreach ($build as &$image_build) {
        $image_build['#theme'] = 'responsive_image';
        $image_build['#responsive_image_style_id'] = '2000x800_responsive';
      }

      return($this->getRenderer()->render($build));
    }

    return "";
  }

}
