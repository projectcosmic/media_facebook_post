<?php

namespace Drupal\media_facebook_post;

use Drupal\Core\State\StateInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\TransferException;
use Psr\Log\LoggerInterface;

/**
 * Facebook fetcher service.
 */
class FacebookFetcher implements FacebookFetcherInterface {

  /**
   * The state key value store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs the Facebook fetcher service.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state key-value store service.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(StateInterface $state, ClientInterface $http_client, LoggerInterface $logger) {
    $this->state = $state;
    $this->httpClient = $http_client;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function getPost($id) {
    try {
      $response = $this->httpClient->get("https://graph.facebook.com/v12.0/$id", [
        'query' => [
          'fields' => 'created_time,full_picture,message',
          'access_token' => $this->state->get('media_facebook_post.api_token'),
        ],
      ]);

      return json_decode((string) $response->getBody(), TRUE);
    }
    catch (TransferException $e) {
      $this->logger->error($e->__toString());
    }

    return NULL;
  }

}
