<?php

namespace Drupal\piv_futureverse_vote\Plugin\views\field;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Renders a modal vote link for a journal poem.
 */
#[ViewsField("futureverse_vote_link")]
class FutureverseVoteLink extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // No query alteration needed — entity is available via _entity.
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['link_text'] = ['default' => 'Vote'];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['link_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link text'),
      '#default_value' => $this->options['link_text'],
      '#required' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $node = $values->_entity;
    if (!$node) {
      return [];
    }

    $url = Url::fromRoute('piv_futureverse_vote.vote', ['node' => $node->id()])
      ->toString();

    $link_text = $this->options['link_text'] ?: $this->t('Vote');

    return [
      '#type' => 'html_tag',
      '#tag' => 'a',
      '#value' => $link_text,
      '#attributes' => [
        'href' => $url,
        'class' => ['futureverse-poem-teaser', 'use-ajax'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'width' => 800,
          'drupalAutoButtons' => FALSE,
        ]),
      ],
      '#attached' => [
        'library' => ['core/drupal.dialog.ajax'],
      ],
    ];
  }

}
