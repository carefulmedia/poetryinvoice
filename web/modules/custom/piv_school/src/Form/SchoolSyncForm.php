<?php

namespace Drupal\piv_school\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use Drupal\piv_school\SyncService;

/**
 * Class SchoolSyncForm.
 *
 * @package Drupal\piv_school\Form
 */
class SchoolSyncForm extends FormBase {

  /**
   * @var \Drupal\piv_school\SyncService
   */
  protected $syncService;

  /**
   * Construct EventsSyncForm.
   */
  public function __construct(SyncService $sync) {
    $this->syncService = $sync;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('piv_school.sync')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'school_sync_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $file = $this->syncService->getCSVFile();
    $file_name = $file->get('filename')->value;
    $uri = $file->get('uri')->value;
    $url = file_create_url($uri);
    $prefix = [
      '#theme' => 'piv_school__sync_form',
      '#name' => $file_name,
      '#url' => $url,
    ];

    $form['#prefix'] = \Drupal::service('renderer')->render($prefix);

    $form['start'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Start syncronization'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->syncService->start();
  }

}
