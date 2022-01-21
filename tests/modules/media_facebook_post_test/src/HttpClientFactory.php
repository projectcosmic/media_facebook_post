<?php

namespace Drupal\media_facebook_post_test;

use Drupal\Core\State\StateInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Guzzle client factory for tests.
 */
class HttpClientFactory {

  /**
   * Constructs a test Guzzle client.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   *
   * @return \GuzzleHttp\ClientInterface
   *   The mocked client.
   */
  public static function create(StateInterface $state) {
    $token_body = json_encode([
      'access_token' => '__ACCESS_TOKEN__',
      'token_type' => 'bearer',
      'expires_in' => 3600,
    ]);

    $mock = new MockHandler(
      $state->get('media_facebook_post_test.success')
      ? [
        new Response(200, [], $token_body),
        new Response(200, [], $token_body),
        new Response(200, [], json_encode(['accounts' => ['data' => [['access_token' => '__PAGE_ACCESS_TOKEN__']]]])),
      ]
      : [
        new RequestException(
          'Error getting short-lived token',
          new Request('GET', 'https://graph.facebook.com/v12.0/oauth/access_token')
        ),
      ]
    );
    return new Client(['handler' => HandlerStack::create($mock)]);
  }

}
