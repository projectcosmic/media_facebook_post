<?php

namespace Drupal\Tests\media_facebook_post\Unit;

use Drupal\Core\State\StateInterface;
use Drupal\media_facebook_post\FacebookFetcher;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \Drupal\media_facebook_post\FacebookFetcher
 * @group media_facebook_post
 */
class FacebookFetcherTest extends UnitTestCase {

  /**
   * @covers ::getPost()
   */
  public function testGetPost() {
    $state = $this->createMock(StateInterface::class);
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())->method('error');

    $mock = new MockHandler([
      new RequestException('Error', new Request('GET', 'https://graph.facebook.com/12.0/__POST_ID__')),
      new Response(200, [], '{"message":"Success Response"}'),
    ]);
    $client = new Client(['handler' => HandlerStack::create($mock)]);

    $fetcher = new FacebookFetcher($state, $client, $logger);
    $this->assertNull($fetcher->getPost($this->randomMachineName()), 'Request exception results in NULL return value.');
    $this->assertIsArray($fetcher->getPost($this->randomMachineName()), 'Returns array of data.');
  }

}
