<?php

namespace Drupal\piv_user\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for PIV User routes.
 */
class LoginRequiredController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build(Request $request) {
    // Usually we would use the redirect.destination service, but it returns a
    // full url and the login form removes it from the url.
    $login = [
      '#type' => 'link',
      '#title' => $this->t('log in'),
      '#url' => Url::fromRoute('user.login', [], [
        'query' => ['destination' => $request->getRequestUri()],
      ]),
    ];
    $build['content'] = [
      '#type' => 'inline_template',
      '#template' => "<p>{{ 'To view this page'|t }}, <strong>{{ login }}</strong> {{ 'to your teacher or'|t }} <a href='/about/poet-network'>{{ 'Poet Network'|t }}</a> {{ 'account'|t }}.</p><p>{{ 'Don\'t have an account yet'|t }}? <strong><a href='/create-account'>{{ 'Apply for your account today'|t }}</a></strong>. {{ 'It\'s free to join us and requires no commitment'|t }} .</p>",
      '#context' => ['login' => $login],
    ];
    return $build;
  }

}
