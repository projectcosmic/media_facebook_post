<?php

namespace Drupal\media_facebook_post;

/**
 * Describes a Facebook authentication management service.
 */
interface FacebookAuthenticationInterface {

  /**
   * Creates a Facebook Login link.
   *
   * @param string $app_id
   *   The Facebook App ID.
   *
   * @return \Drupal\Core\Url
   *   The Login link URL.
   */
  public function createLoginLink($app_id);

  /**
   * Checks that a state parameter from a redirect is valid.
   *
   * @param string $state
   *   The state string from the request URL.
   *
   * @return bool
   *   TRUE if the state was valid, FALSE otherwise.
   */
  public function isValidState($state);

}
