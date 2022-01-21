<?php

namespace Drupal\Tests\media_facebook_post\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
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

    $fetcher = new FacebookFetcher($state, $client, $logger, $this->getConfigFactoryStub());
    $this->assertNull($fetcher->getPost($this->randomMachineName()), 'Request exception results in NULL return value.');
    $this->assertIsArray($fetcher->getPost($this->randomMachineName()), 'Returns array of data.');
  }

  /**
   * @covers ::getPageToken()
   * @dataProvider getPageTokenProvider
   */
  public function testGetPageToken($mock_handler, $expect_error = TRUE, $expected = NULL) {
    $state = $this->createMock(StateInterface::class);
    $client = new Client(['handler' => HandlerStack::create($mock_handler)]);
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($expect_error ? $this->once() : $this->never())->method('error');
    $config_factory = $this->getConfigFactoryStub(['media_facebook_post.settings' => []]);

    $urlGenerator = $this->createMock('Drupal\Core\Routing\UrlGeneratorInterface');
    $container = new ContainerBuilder();
    $container->set('url_generator', $urlGenerator);
    \Drupal::setContainer($container);

    $fetcher = new FacebookFetcher($state, $client, $logger, $config_factory);
    $this->assertEquals($expected, $fetcher->getPageToken($this->randomMachineName()));
  }

  /**
   * Provides test cases for ::testGetPageToken().
   */
  public function getPageTokenProvider() {
    $token_request = new Request('GET', 'https://graph.facebook.com/v12.0/oauth/access_token');
    $token_body = json_encode([
      'access_token' => '__ACCESS_TOKEN__',
      'token_type' => 'bearer',
      'expires_in' => 3600,
    ]);

    $token = $this->randomMachineName();

    return [
      'Short-lived token error' => [
        new MockHandler([
          new RequestException('Error getting short-lived token', clone $token_request),
        ]),
      ],
      'Long-lived token error' => [
        new MockHandler([
          new Response(200, [], $token_body),
          new RequestException('Error getting long-lived token', clone $token_request),
        ]),
      ],
      'Error requesting /me' => [
        new MockHandler([
          new Response(200, [], $token_body),
          new Response(200, [], $token_body),
          new RequestException('Error getting /me', new Request('GET', 'https://graph.facebook.com/v12.0/me')),
        ]),
      ],
      'No managed pages' => [
        new MockHandler([
          new Response(200, [], $token_body),
          new Response(200, [], $token_body),
          new Response(200, [], json_encode(['id' => '0123456789'])),
        ]),
        FALSE,
      ],
      'Insufficient access to pages' => [
        new MockHandler([
          new Response(200, [], $token_body),
          new Response(200, [], $token_body),
          new Response(200, [], json_encode([
            'accounts' => ['data' => [[], []]],
            'id' => '0123456789',
          ])),
        ]),
        FALSE,
      ],
      'Access to a page' => [
        new MockHandler([
          new Response(200, [], $token_body),
          new Response(200, [], $token_body),
          new Response(200, [], json_encode([
            'accounts' => [
              'data' => [
                [],
                ['access_token' => $token],
              ],
            ],
            'id' => '0123456789',
          ])),
        ]),
        FALSE,
        $token,
      ],
    ];
  }

}
