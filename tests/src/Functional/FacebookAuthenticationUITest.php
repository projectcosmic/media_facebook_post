<?php

namespace Drupal\Tests\media_facebook_post\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests Facebook OAuth flow.
 *
 * @group media_facebook_post
 */
class FacebookAuthenticationUITest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'media_facebook_post',
    'media_facebook_post_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->drupalCreateUser(['media_facebook_post link facebook']));
  }

  /**
   * Tests authentication flow.
   */
  public function testAuthenticationFlow() {
    $this->drupalGet('/admin/media-facebook-post/link');

    // Show message when app configuration has not been set.
    $this->assertSession()->pageTextContains('Facebook account linking has not been set up');

    $this->config('media_facebook_post.settings')
      ->set('authentication.app_id', (string) random_int(0, 1000))
      ->set('authentication.app_secret', $this->randomMachineName())
      ->save();

    $this->drupalGet('/admin/media-facebook-post/link');
    $this->assertSession()->linkExists('Link with Facebook');

    $this->drupalGet('/admin/media-facebook-post/after-login', [
      'query' => [
        'error_reason' => 'user_denied',
        'error' => 'access_denied',
        'error_description' => 'Permissions error.',
      ],
    ]);
    $this->assertSession()->pageTextContains('Login cancelled.');

    $this->drupalGet('/admin/media-facebook-post/link');
    $this->drupalGet('/admin/media-facebook-post/after-login', [
      'query' => [
        'error_reason' => $this->randomMachineName(),
        'error' => $this->randomMachineName(),
        'error_description' => $this->randomMachineName(),
      ],
    ]);
    $this->assertSession()->pageTextContains('An error occurred');

    \Drupal::state()->set('media_facebook_post_test.success', TRUE);
    $this->drupalGet('/admin/media-facebook-post/link');
    $this->drupalGet('/admin/media-facebook-post/after-login', [
      'query' => [
        'state' => \Drupal::service('tempstore.private')
          ->get('media_facebook_post')
          ->get('login_state'),
        'code' => $this->randomMachineName(),
      ],
    ]);
    $this->assertSession()->pageTextContains('Linking successful.');
    $this->assertNotNull(\Drupal::state()->get('media_facebook_post.token'), 'Token saved.');

    \Drupal::state()->set('media_facebook_post_test.success', FALSE);
    $this->drupalGet('/admin/media-facebook-post/link');
    $this->drupalGet('/admin/media-facebook-post/after-login', [
      'query' => [
        'state' => \Drupal::service('tempstore.private')
          ->get('media_facebook_post')
          ->get('login_state'),
        'code' => $this->randomMachineName(),
      ],
    ]);
    $this->assertSession()->pageTextContains('Could not establish a link to a Facebook page.');
  }

}
