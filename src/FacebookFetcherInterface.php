<?php

namespace Drupal\media_facebook_post;

/**
 * Describes a Facebook fetcher service.
 */
interface FacebookFetcherInterface {

  /**
   * Gets a Facebook post from the Graph API.
   *
   * @param string $id
   *   The ID of the post to get.
   *
   * @return string[]|null
   *   Returns NULL if there was an error or an array of data, which may
   *   include:
   *   - message: The post text.
   *   - full_picture: The main image attached to the post.
   *   - created_time: The created time as ISO 8601 format.
   */
  public function getPost($id);

  /**
   * Gets posts for a page from its access token.
   *
   * @param string $token
   *   A page access token.
   * @param int $limit
   *   (optional). The number of posts to fetch. Default 100.
   *
   * @return array[]|null
   *   NULL if an error occurred, otherwise a list of post arrays, each
   *   containing:
   *   - id: The Facebook ID of the post.
   *   - created_time: The created time as ISO 8601 format.
   */
  public function getPagePosts($token, $limit = 100);

  /**
   * Gets a long-lived page token from a code from OAuth.
   *
   * @param string $code
   *   The code.
   *
   * @return string|null
   *   The access token or NULL if an error occurred.
   */
  public function getPageToken($code);

}
