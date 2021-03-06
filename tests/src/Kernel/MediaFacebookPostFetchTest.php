<?php

namespace Drupal\Tests\media_facebook_post\Kernel;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\media\Entity\Media;
use Drupal\media_facebook_post\FacebookFetcherInterface;
use Drupal\Tests\media\Kernel\MediaKernelTestBase;

/**
 * Tests periodical fetching of Facebook page posts.
 *
 * @group media_facebook_post
 */
class MediaFacebookPostFetchTest extends MediaKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['media', 'media_facebook_post'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createMediaType('facebook_post', [
      'source_configuration' => [
        'fetch_count' => 2,
      ],
    ]);
  }

  /**
   * Tests cron run.
   */
  public function testCronFetching() {
    $state = $this->container->get('state');
    $state->set('media_facebook_post.token', $this->randomMachineName());

    $page_id = random_int(10000, PHP_INT_MAX);
    $data = [
      [
        'id' => "{$page_id}_10002",
        'created_time' => date('c', strtotime('yesterday 01:00')),
      ],
      [
        'id' => "{$page_id}_10001",
        'created_time' => date('c', strtotime('yesterday 02:00')),
      ],
    ];
    $new = [
      'id' => "{$page_id}_10003",
      'created_time' => date('c', time()),
    ];

    $fetcher = $this->createMock(FacebookFetcherInterface::class);
    $fetcher
      ->method('getPagePosts')
      ->willReturnOnConsecutiveCalls(NULL, $data, [$new, ...$data]);
    $this->container->set('media_facebook_post.facebook_fetcher', $fetcher);

    $this->container->get('cron')->run();
    $this->assertCount(0, Media::loadMultiple(), 'Queue worker fails gracefully.');

    $this->container->get('cron')->run();
    $this->assertCount(2, Media::loadMultiple(), 'Media items are created.');

    $this->container->get('cron')->run();
    $this->assertCount(3, Media::loadMultiple(), 'Duplicate items are not created.');
  }

}
