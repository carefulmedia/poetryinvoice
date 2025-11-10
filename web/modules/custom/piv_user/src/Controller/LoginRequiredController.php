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

    $template_string = "<p>To view this page, <strong>{{ login }}</strong> to your teacher or <a href='/about/poet-network'>Poet Network</a> account.</p><p>Don't have an account yet? <strong><a href='/create-account'>Apply for your account today</a></strong>. It's free to join us and requires no commitment .</p>";

    $build['content'] = [
      '#type' => 'inline_template',
      '#template' => $this->t($template_string),
      '#context' => ['login' => $login],
    ];
    return $build;
  }

}
