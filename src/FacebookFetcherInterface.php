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

}
