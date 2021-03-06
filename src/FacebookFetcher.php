<?php

namespace Drupal\media_facebook_post;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
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
   * The config object for Facebook post settings.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * Constructs the Facebook fetcher service.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state key-value store service.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   */
  public function __construct(StateInterface $state, ClientInterface $http_client, LoggerInterface $logger, ConfigFactoryInterface $config_factory, CacheBackendInterface $cache_backend) {
    $this->state = $state;
    $this->httpClient = $http_client;
    $this->logger = $logger;
    $this->config = $config_factory->get('media_facebook_post.settings');
    $this->cacheBackend = $cache_backend;
  }

  /**
   * {@inheritdoc}
   */
  public function getPost($id) {
    $cache_id = "media:facebook_post:$id";

    $cached = $this->cacheBackend->get($cache_id);
    if ($cached) {
      return $cached->data;
    }

    try {
      $response = $this->httpClient->get("https://graph.facebook.com/v12.0/$id", [
        'query' => [
          'fields' => 'created_time,full_picture,message,permalink_url',
          'access_token' => $this->state->get('media_facebook_post.token'),
        ],
      ]);

      $data = json_decode((string) $response->getBody(), TRUE);
      $this->cacheBackend->set($cache_id, $data);
      return $data;
    }
    catch (TransferException $e) {
      $this->logger->error($e->__toString());
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPagePosts($token, $limit = 100) {
    try {
      $response = $this->httpClient->get('https://graph.facebook.com/v12.0/me', [
        'query' => [
          'fields' => "posts.limit($limit){id,created_time}",
          'access_token' => $token,
        ],
      ]);

      $data = json_decode((string) $response->getBody(), TRUE);
      return $data['posts']['data'] ?? [];
    }
    catch (TransferException $e) {
      $this->logger->error($e->__toString());
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPageToken($code) {
    try {
      $response = $this->httpClient->get('https://graph.facebook.com/v12.0/oauth/access_token', [
        'query' => [
          'client_id' => $this->config->get('authentication.app_id'),
          'redirect_uri' => Url::fromRoute('media_facebook_post.after_login', [], ['absolute' => TRUE])->toString(),
          'client_secret' => $this->config->get('authentication.app_secret'),
          'code' => $code,
        ],
      ]);
      $response = $this->httpClient->get('https://graph.facebook.com/v12.0/oauth/access_token', [
        'query' => [
          'grant_type' => 'fb_exchange_token',
          'client_id' => $this->config->get('authentication.app_id'),
          'client_secret' => $this->config->get('authentication.app_secret'),
          'fb_exchange_token' => json_decode((string) $response->getBody())->access_token,
        ],
      ]);
      $response = $this->httpClient->get('https://graph.facebook.com/v12.0/me', [
        'query' => [
          'fields' => 'accounts',
          'access_token' => json_decode((string) $response->getBody())->access_token,
        ],
      ]);

      $data = json_decode((string) $response->getBody());
      if (isset($data->accounts)) {
        foreach ($data->accounts->data as $page) {
          if (isset($page->access_token)) {
            return $page->access_token;
          }
        }
      }
    }
    catch (TransferException $e) {
      $this->logger->error($e->__toString());
    }

    return NULL;
  }

}
