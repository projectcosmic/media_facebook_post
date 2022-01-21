<?php

namespace Drupal\media_facebook_post_test;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Alters services for media Facebook post tests.
 */
class MediaFacebookPostTestServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $container
      ->getDefinition('media_facebook_post.facebook_fetcher')
      ->setArgument(1, new Reference('media_facebook_post_test.http_client'));
  }

}
