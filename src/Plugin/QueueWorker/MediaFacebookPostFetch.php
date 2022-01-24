<?php

namespace Drupal\media_facebook_post\Plugin\QueueWorker;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\State\StateInterface;
use Drupal\media\MediaTypeInterface;
use Drupal\media_facebook_post\FacebookFetcherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Fetches new posts for a Facebook post media entity bundle.
 *
 * @QueueWorker(
 *   id = "media_facebook_post_fetch",
 *   title = @Translation("Facebook Post Fetch"),
 *   cron = {"time" = 60}
 * )
 */
class MediaFacebookPostFetch extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The state key/value store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The Facebook fetcher service.
   *
   * @var \Drupal\media_facebook_post\FacebookFetcherInterface
   */
  protected $facebookFetcher;

  /**
   * The media entity storage.
   *
   * @var \Drupal\Core\Entity\Sql\SqlEntityStorageInterface
   */
  protected $mediaStorage;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Tests the test access block.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state key/value store.
   * @param \Drupal\media_facebook_post\FacebookFetcherInterface $facebook_fetcher
   *   The Facebook fetcher service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, StateInterface $state, FacebookFetcherInterface $facebook_fetcher, EntityTypeManagerInterface $entity_type_manager, TimeInterface $time) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->state = $state;
    $this->facebookFetcher = $facebook_fetcher;
    $this->mediaStorage = $entity_type_manager->getStorage('media');
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('state'),
      $container->get('media_facebook_post.facebook_fetcher'),
      $container->get('entity_type.manager'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if ($data instanceof MediaTypeInterface) {
      $source = $data->getSource();
      $source_config = $source->getConfiguration();
      $token = $this->state->get('media_facebook_post.token');

      if ($source->getPluginId() == 'facebook_post' && $source_config['fetch_count'] > 0 && $token) {
        $bundle_key = $this->mediaStorage->getEntityType()->getKey('bundle');
        $source_field = $source->getSourceFieldDefinition($data)->getName();

        $state_key = "media_facebook_post.{$data->id()}.since";
        $since = $this->state->get($state_key, 0);
        $this->state->set($state_key, $this->time->getRequestTime());

        $posts = $this->facebookFetcher->getPagePosts($token, $source_config['fetch_count']);

        // If an error occurred, reset state key and return early.
        if ($posts === NULL) {
          return $this->state->set($state_key, $since);
        }

        foreach ($posts as $post) {
          $timestamp = strtotime($post['created_time']);

          // Rely on the post list being in descending created_time order so we
          // can exit out of the loop early if we encounter a post we should
          // have already seen.
          // @todo check that this post is not a duplicate due to the
          //   possibility of posts being added manually.
          if ($timestamp < $since) {
            break;
          }

          $this->mediaStorage
            ->create([
              $bundle_key => $data->id(),
              $source_field => $post['id'],
            ])
            ->save();
        }
      }
    }
  }

}
