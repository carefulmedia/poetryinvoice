<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\piv_live_competition\Helper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Contest Administration controller.
 */
final class ContestAdministrationController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected readonly Helper $helper,
  ) {}

  /**
   * Custom access - only admins or the Live Competition Administrator.
   */
  public function access(AccountInterface $account, NodeInterface $node): AccessResultInterface {
    // Allow administrators.
    $is_admin = $account->hasPermission('administer nodes');

    // Allow the Live Competition Administrator.
    $is_competition_admin = !$node->get('field_live_competition_admin')->isEmpty()
      && $node->get('field_live_competition_admin')->entity->id() === $account->id();

    return AccessResult::allowedIf($is_admin || $is_competition_admin)
      ->cachePerPermissions()
      ->cachePerUser()
      ->addCacheableDependency($node);
  }

  /**
   * Builds the contest administration page.
   */
  public function __invoke(NodeInterface $node): array {
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['contest-administration'],
      ],
    ];

    // Link to create a new team regionals entry for this contest.
    $build['action_links'] = [
      '#type' => 'container',
    ];

    // Only show create entry link if user has permission to create team_regionals_entry nodes.
    $can_create_entry = $this->entityTypeManager()
      ->getAccessControlHandler('node')
      ->createAccess('team_regionals_entry');

    if ($can_create_entry) {
      $create_entry_url = Url::fromRoute('node.add', [
        'node_type' => 'team_regionals_entry',
      ], [
        'query' => [
          'edit[field_contest_association][widget]' => $node->id(),
          'destination' => '/node/' . $node->id() . '/contest-administration',
        ],
      ]);

      // PIV-777, remove button.
      /*$build['action_links']['create_entry_link'] = [
        '#type' => 'link',
        '#title' => $this->t('Create New Entry'),
        '#url' => $create_entry_url,
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
      ];*/
    }

    if ($this->currentUser()->hasRole('administrator')) {
      $edit_url = Url::fromRoute('entity.node.edit_form', [
        'node' => $node->id(),
      ], [
        'query' => [
          'destination' => '/node/' . $node->id() . '/contest-administration',
        ],
      ]);

      $build['action_links']['edit_contest_link'] = [
        '#type' => 'link',
        '#title' => $this->t('Edit Contest'),
        '#url' => $edit_url,
        '#attributes' => [
          'class' => ['button'],
        ],
      ];
    }

    $build['divider_1'] = [
      '#markup' => '<hr>',
    ];

    // Judge Links section.
    $build['judges'] = [
      '#type' => 'container',
    ];
    $build['judges']['heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Judges'),
    ];

    // Get all judges using the helper method.
    [$performance_en, $performance_fr, $accuracy_en, $accuracy_fr] = $this->helper->getJudges($node);

    $judge_groups = [
      'performance_en' => ['judges' => $performance_en, 'label' => $this->t('English Performance Judges')],
      'performance_fr' => ['judges' => $performance_fr, 'label' => $this->t('French Performance Judges')],
      'accuracy_en' => ['judges' => $accuracy_en, 'label' => $this->t('English Accuracy Judges')],
      'accuracy_fr' => ['judges' => $accuracy_fr, 'label' => $this->t('French Accuracy Judges')],
    ];

    $has_judges = FALSE;
    foreach ($judge_groups as $key => $group) {
      if (!empty($group['judges'])) {
        $has_judges = TRUE;
        $build['judges'][$key] = [
          '#type' => 'container',
        ];
        $build['judges'][$key]['subheading'] = [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $group['label'],
        ];
        $build['judges'][$key]['list'] = [
          '#theme' => 'item_list',
          '#items' => [],
        ];

        foreach ($group['judges'] as $judge) {
          $score_url = Url::fromRoute('piv_live_competition.score', [
            'node' => $node->id(),
            'user' => $judge->id(),
          ], ['absolute' => TRUE]);

          $build['judges'][$key]['list']['#items'][] = [
            '#type' => 'container',
            '#attributes' => ['class' => ['judge-link-item']],
            'name' => [
              '#markup' => '<strong>' . $judge->getDisplayName() . ':</strong> ',
            ],
            'link' => [
              '#type' => 'link',
              '#title' => $score_url->toString(),
              '#url' => $score_url,
              '#attributes' => [
                'target' => '_blank',
              ],
            ],
          ];
        }

        // Add spacing after each subsection.
        $build['judges'][$key]['spacing'] = [
          '#markup' => '<br>',
        ];
      }
    }

    if (!$has_judges) {
      $build['judges']['empty'] = [
        '#markup' => '<p><em>' . $this->t('No judges assigned to this contest.') . '</em></p>',
      ];
    }

    $build['divider_2'] = [
      '#markup' => '<hr>',
    ];

    // Prompter Links section.
    $build['prompters'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['admin-section']],
    ];
    $build['prompters']['heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Prompters'),
    ];

    // Get all prompters using the helper method.
    [$prompters_en, $prompters_fr] = $this->helper->getPrompters($node);

    $prompter_groups = [
      'prompters_en' => ['prompters' => $prompters_en, 'label' => $this->t('English Prompters')],
      'prompters_fr' => ['prompters' => $prompters_fr, 'label' => $this->t('French Prompters')],
    ];

    $has_prompters = FALSE;
    foreach ($prompter_groups as $key => $group) {
      if (!empty($group['prompters'])) {
        $has_prompters = TRUE;
        $build['prompters'][$key] = [
          '#type' => 'container',
        ];
        $build['prompters'][$key]['subheading'] = [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $group['label'],
        ];
        $build['prompters'][$key]['list'] = [
          '#theme' => 'item_list',
          '#items' => [],
        ];

        foreach ($group['prompters'] as $prompter) {
          $score_url = Url::fromRoute('piv_live_competition.score', [
            'node' => $node->id(),
            'user' => $prompter->id(),
          ], ['absolute' => TRUE]);

          $build['prompters'][$key]['list']['#items'][] = [
            '#type' => 'container',
            '#attributes' => ['class' => ['prompter-link-item']],
            'name' => [
              '#markup' => '<strong>' . $prompter->getDisplayName() . ':</strong> ',
            ],
            'link' => [
              '#type' => 'link',
              '#title' => $score_url->toString(),
              '#url' => $score_url,
              '#attributes' => [
                'target' => '_blank',
              ],
            ],
          ];
        }

        // Add spacing after each subsection.
        $build['prompters'][$key]['spacing'] = [
          '#markup' => '<br>',
        ];
      }
    }

    if (!$has_prompters) {
      $build['prompters']['empty'] = [
        '#markup' => '<p><em>' . $this->t('No prompters assigned to this contest.') . '</em></p>',
      ];
    }

    return $build;
  }

  /**
   * Return a generated title.
   */
  public function title(NodeInterface $node) {
    return $this->t('Contest Administration - @label', [
      '@label' => $node->label(),
    ]);
  }

}
