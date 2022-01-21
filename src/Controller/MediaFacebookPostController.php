<?php

namespace Drupal\media_facebook_post\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\media_facebook_post\FacebookAuthenticationInterface;
use Drupal\media_facebook_post\FacebookFetcherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Media Facebook Post routes.
 */
class MediaFacebookPostController extends ControllerBase {

  /**
   * The Facebook authentication management service.
   *
   * @var \Drupal\media_facebook_post\FacebookAuthenticationInterface
   */
  protected $facebookAuthentication;

  /**
   * Facebook fetcher service.
   *
   * @var \Drupal\media_facebook_post\FacebookFetcherInterface
   */
  protected $facebookFetcher;

  /**
   * Constructs a MediaFacebookPostController instance.
   *
   * @param \Drupal\media_facebook_post\FacebookAuthenticationInterface $facebook_authentication
   *   The Facebook authentication management service.
   * @param \Drupal\media_facebook_post\FacebookFetcherInterface $facebook_fetcher
   *   The Facebook fetcher service.
   */
  public function __construct(FacebookAuthenticationInterface $facebook_authentication, FacebookFetcherInterface $facebook_fetcher) {
    $this->facebookAuthentication = $facebook_authentication;
    $this->facebookFetcher = $facebook_fetcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('media_facebook_post.facebook_authentication'),
      $container->get('media_facebook_post.facebook_fetcher')
    );
  }

  /**
   * Builds the page for linking a Facebook account.
   */
  public function link() {
    $auth = $this->config('media_facebook_post.settings')->get('authentication');

    if (empty($auth['app_id']) || empty($auth['app_secret'])) {
      return [
        '#markup' => '<p>' . $this->t('Facebook account linking has not been set up yet for this website. Please contact your website administrator.') . '</p>',
      ];
    }

    return [
      'content' => [
        '#markup' => '<p>' . $this->t('For this website to pull in posts from a page, a linked Facebook account with at least moderation access to the page is required. You will need to select the page to give access to when you proceed through the Facebook login flow. Please use the link below to get started.') . '</p>',
      ],
      'link' => [
        '#type' => 'link',
        '#title' => $this->t('Link with Facebook'),
        '#url' => $this->facebookAuthentication->createLoginLink($auth['app_id']),
        '#attributes' => [
          'class' => [
            'button',
            'button--primary',
          ],
        ],
      ],
    ];
  }

  /**
   * Redirect page after Facebook login.
   */
  public function afterLogin(Request $request) {
    $query = $request->query;

    if ($query->get('error_reason') == 'user_denied') {
      return ['#markup' => '<p>' . $this->t('Login cancelled.') . '</p>'];
    }

    $link = $this->t('<a href=":href">Please try again.</a>', [
      ':href' => Url::fromRoute('media_facebook_post.link')->toString(),
    ]);

    if ($query->has('error')) {
      $this->getLogger('media_facebook_post')->error('Facebook login error: <pre><code>@parameters</code></pre>', [
        '@parameters' => json_encode($query->all(), JSON_PRETTY_PRINT),
      ]);
      return ['#markup' => '<p>' . $this->t('An error occurred.') . " $link</p>"];
    }

    if (!$this->facebookAuthentication->isValidState($query->get('state'))) {
      return ['#markup' => '<p>' . $this->t('Login expired.') . " $link</p>"];
    }

    if (!$token = $this->facebookFetcher->getPageToken($query->get('code'))) {
      return ['#markup' => '<p>' . $this->t('Could not establish a link to a Facebook page.') . " $link</p>"];
    }

    $this->state()->set('media_facebook_post.token', $token);

    return ['#markup' => '<p>' . $this->t('Linking successful.') . '</p>'];
  }

}
