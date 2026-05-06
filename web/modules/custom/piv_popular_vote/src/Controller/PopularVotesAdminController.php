<?php

namespace Drupal\piv_popular_vote\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\piv_contest_competition\CompetitionInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;

/**
 * Return results for the popular vote.
 */
class PopularVotesAdminController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * PivPopularVoteController constructor.
   */
  final public function __construct(FormBuilderInterface $form_builder) {
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account, CompetitionInterface $competition) {
    $popular_voting_enabled = !empty($competition->field_enable_popular_voting->value);
    $status = !empty($competition->status->value);
    $can_update = $competition->access('update', $competition);
    return AccessResult::allowedIf($popular_voting_enabled && $status && $can_update)
      ->addCacheableDependency($competition);
  }

  /**
   * Builds the response.
   */
  public function build(CompetitionInterface $competition) {
    $build = [];
    $filter_form_state = (new FormState())
      ->setMethod('get')
      ->setAlwaysProcess()
      ->disableRedirect()
      ->addBuildInfo('args', [$competition]);
    $build['filter_form'] = $this->formBuilder
      ->buildForm('Drupal\piv_popular_vote\Form\PopularVotesAdminFilterForm', $filter_form_state);

    $table_form_state = (new FormState())
      ->addBuildInfo('args', [$filter_form_state, $competition]);
    $build['table_form'] = $this->formBuilder
      ->buildForm('Drupal\piv_popular_vote\Form\PopularVotesAdminTableForm', $table_form_state);
    return $build;
  }

}
