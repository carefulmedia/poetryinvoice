<?php

namespace Drupal\piv_base\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\piv_base\PoemFavouritesStorage;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * API for logged-in user poem favourites.
 */
class PoemFavouritesController extends ControllerBase {

  public const CSRF_CONTEXT = 'piv_base/poem_favourites';

  public function __construct(
    protected CsrfTokenGenerator $csrfToken,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('csrf_token'),
    );
  }

  /**
   * Returns bootstrap data for favourites JS (Drupal pages and roulette).
   */
  public function bootstrap(): JsonResponse {
    $account = $this->currentUser();
    if ($account->isAnonymous()) {
      return new JsonResponse([
        'uid' => 0,
        'favourites' => [],
      ]);
    }

    $user = User::load($account->id());
    return new JsonResponse([
      'uid' => (int) $account->id(),
      'favourites' => $user ? PoemFavouritesStorage::load($user) : [],
      'apiUrl' => '/api/poem-favourites',
      'csrfToken' => $this->csrfToken->get(self::CSRF_CONTEXT),
    ]);
  }

  /**
   * Returns favourites for the current user.
   */
  public function get(): JsonResponse {
    $user = User::load($this->currentUser()->id());
    if (!$user) {
      return new JsonResponse([], Response::HTTP_FORBIDDEN);
    }

    return new JsonResponse(PoemFavouritesStorage::load($user));
  }

  /**
   * Saves favourites for the current user.
   */
  public function save(Request $request): JsonResponse {
    $token = $request->headers->get('X-CSRF-Token');
    if (!$token || !$this->csrfToken->validate($token, self::CSRF_CONTEXT)) {
      throw new AccessDeniedHttpException('Invalid CSRF token.');
    }

    $payload = json_decode($request->getContent(), TRUE);
    if (!is_array($payload)) {
      return new JsonResponse(['message' => 'Expected a JSON array.'], Response::HTTP_BAD_REQUEST);
    }

    $user = User::load($this->currentUser()->id());
    if (!$user) {
      return new JsonResponse([], Response::HTTP_FORBIDDEN);
    }

    return new JsonResponse(PoemFavouritesStorage::save($user, $payload));
  }

  /**
   * Adds a single favourite for the current user.
   */
  public function add(Request $request): JsonResponse {
    $token = $request->headers->get('X-CSRF-Token');
    if (!$token || !$this->csrfToken->validate($token, self::CSRF_CONTEXT)) {
      throw new AccessDeniedHttpException('Invalid CSRF token.');
    }

    $payload = json_decode($request->getContent(), TRUE);
    if (!is_array($payload)) {
      return new JsonResponse(['message' => 'Expected a JSON object.'], Response::HTTP_BAD_REQUEST);
    }

    $user = User::load($this->currentUser()->id());
    if (!$user) {
      return new JsonResponse([], Response::HTTP_FORBIDDEN);
    }

    return new JsonResponse(PoemFavouritesStorage::add($user, $payload));
  }

  /**
   * Removes a single favourite for the current user.
   */
  public function remove(Request $request): JsonResponse {
    $token = $request->headers->get('X-CSRF-Token');
    if (!$token || !$this->csrfToken->validate($token, self::CSRF_CONTEXT)) {
      throw new AccessDeniedHttpException('Invalid CSRF token.');
    }

    $payload = json_decode($request->getContent(), TRUE);
    if (!is_array($payload)) {
      return new JsonResponse(['message' => 'Expected a JSON object.'], Response::HTTP_BAD_REQUEST);
    }

    $poem_path = (string) ($payload['poemPath'] ?? $payload['poemId'] ?? '');
    if ($poem_path === '') {
      return new JsonResponse(['message' => 'Expected poemPath.'], Response::HTTP_BAD_REQUEST);
    }

    $user = User::load($this->currentUser()->id());
    if (!$user) {
      return new JsonResponse([], Response::HTTP_FORBIDDEN);
    }

    return new JsonResponse(PoemFavouritesStorage::remove($user, $poem_path));
  }

}
