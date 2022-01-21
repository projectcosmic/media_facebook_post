<?php

namespace Drupal\media_facebook_post;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;

/**
 * Facebook authentication management service.
 */
class FacebookAuthentication implements FacebookAuthenticationInterface {

  /**
   * The tempstore object.
   *
   * @var \Drupal\Core\TempStore\SharedTempStore
   */
  protected $tempStore;

  /**
   * The UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidService;

  /**
   * Constructs a FacebookAuthentication instance.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid_service
   *   The UUID service.
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, UuidInterface $uuid_service) {
    $this->tempStore = $temp_store_factory->get('media_facebook_post');
    $this->uuidService = $uuid_service;
  }

  /**
   * {@inheritdoc}
   */
  public function createLoginLink($app_id) {
    $state = $this->uuidService->generate();
    $this->tempStore->set('login_state', $state);

    return Url::fromUri('https://www.facebook.com/v12.0/dialog/oauth', [
      'query' => [
        'client_id' => $app_id,
        'redirect_uri' => Url::fromRoute('media_facebook_post.after_login', [], ['absolute' => TRUE])->toString(),
        'state' => $state,
        'scope' => 'pages_show_list',
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function isValidState($state) {
    if ($this->tempStore->get('login_state') == $state) {
      $this->tempStore->delete('login_state');
      return TRUE;
    }

    return FALSE;
  }

}
