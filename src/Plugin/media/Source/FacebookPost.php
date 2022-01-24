<?php

namespace Drupal\media_facebook_post\Plugin\media\Source;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Drupal\media\MediaTypeInterface;
use Drupal\media_facebook_post\FacebookFetcherInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\TransferException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Mime\MimeTypes;

/**
 * Facebook post media source.
 *
 * @MediaSource(
 *   id = "facebook_post",
 *   label = @Translation("Facebook post"),
 *   allowed_field_types = {"string"},
 *   description = @Translation("Provides media representation for a Facebook post."),
 * )
 */
class FacebookPost extends MediaSourceBase {

  /**
   * The logger channel for media facebook posts.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * The Facebook fetcher service.
   *
   * @var \Drupal\media_facebook_post\FacebookFetcherInterface
   */
  protected $facebookFetcher;

  /**
   * Constructs a new FacebookPost instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Entity field manager service.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager
   *   The field type plugin manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel for media.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory.
   * @param \Drupal\media_facebook_post\FacebookFetcherInterface $facebook_fetcher
   *   The Facebook fetcher service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, FieldTypePluginManagerInterface $field_type_manager, ConfigFactoryInterface $config_factory, LoggerInterface $logger, ClientInterface $http_client, FileSystemInterface $file_system, ImageFactory $image_factory, FacebookFetcherInterface $facebook_fetcher) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_field_manager, $field_type_manager, $config_factory);
    $this->logger = $logger;
    $this->httpClient = $http_client;
    $this->fileSystem = $file_system;
    $this->imageFactory = $image_factory;
    $this->facebookFetcher = $facebook_fetcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('config.factory'),
      $container->get('logger.factory')->get('media_facebook_posts'),
      $container->get('http_client'),
      $container->get('file_system'),
      $container->get('image.factory'),
      $container->get('media_facebook_post.facebook_fetcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {
    return [
      'id' => $this->t('ID'),
      'message' => $this->t('Post text content'),
      'thumbnail_uri' => $this->t('Thumbnail URI'),
      'thumbnail_width' => $this->t('Thumbnail width'),
      'thumbnail_height' => $this->t('Thumbnail height'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $attribute_name) {
    $id = $this->getSourceFieldValue($media);

    if (in_array($attribute_name, ['id', 'default_name'])) {
      return $id;
    }

    if ($post = $this->facebookFetcher->getPost($id)) {
      switch ($post) {
        case 'message':
          return $post['message'] ?? NULL;

        case 'thumbnail_uri':
          return !empty($post['full_picture'])
            ? $this->getLocalPictureUrl($post['full_picture'])
            : NULL;

        case 'thumbnail_width':
        case 'thumbnail_height':
          if ($local_picture = $this->getMetadata($media, $attribute_name)) {
            $image = $this->imageFactory->get($local_picture);
            return $attribute_name == 'thumbnail_width'
              ? $image->getWidth()
              : $image->getHeight();
          }
      }
    }

    return parent::getMetadata($media, $attribute_name);
  }

  /**
   * Returns the local URI for a post's picture.
   *
   * If the picture is not already locally stored, this method will attempt
   * to download it. Adapted from
   * \Drupal\media\Plugin\media\Source\OEmbed::getLocalThumbnailUri().
   *
   * @param string $remote_url
   *   The URL to the image.
   *
   * @return string|null
   *   The local image URI, or NULL if it could not be downloaded.
   */
  protected function getLocalPictureUrl($remote_url) {
    $directory = 'public://media_facebook_post';

    // The local image doesn't exist yet, so try to download it. First,
    // ensure that the destination directory is writable, and if it's not,
    // log an error and bail out.
    if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      $this->logger->warning('Could not prepare images destination directory @dir for Facebook post media.', [
        '@dir' => $directory,
      ]);
      return NULL;
    }

    // The local filename of the image is always a hash of its remote URL.
    // If a file with that name already exists in the local directory,
    // regardless of its extension, return its URI.
    $hash = Crypt::hashBase64($remote_url);
    $files = $this->fileSystem->scanDirectory($directory, "/^$hash\..*/");
    if (count($files) > 0) {
      return reset($files)->uri;
    }

    // The local thumbnail doesn't exist yet, so we need to download it.
    try {
      $response = $this->httpClient->request('GET', $remote_url);
      if ($response->getStatusCode() === 200) {
        $local_thumbnail_uri = $directory . DIRECTORY_SEPARATOR . $hash . '.' . $this->getImageFileExtensionFromUrl($remote_url, $response);
        $this->fileSystem->saveData((string) $response->getBody(), $local_thumbnail_uri, FileSystemInterface::EXISTS_REPLACE);
        return $local_thumbnail_uri;
      }
    }
    catch (TransferException $e) {
      $this->logger->warning($e->getMessage());
    }
    catch (FileException $e) {
      $this->logger->warning('Could not download remote thumbnail from {url}.', [
        'url' => $remote_url,
      ]);
    }

    return NULL;
  }

  /**
   * Tries to determine the file extension of an image.
   *
   * Copied from \Drupal\media\Plugin\media\Source\OEmbed::getThumbnailFileExtensionFromUrl().
   *
   * @param string $image_url
   *   The remote URL of the image.
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The response for the downloaded image.
   *
   * @return string|null
   *   The file extension, or NULL if it could not be determined.
   */
  protected function getImageFileExtensionFromUrl(string $image_url, ResponseInterface $response): ?string {
    // First, try to glean the extension from the URL path.
    $path = parse_url($image_url, PHP_URL_PATH);
    if ($path) {
      $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
      if ($extension) {
        return $extension;
      }
    }

    // If the URL didn't give us any clues about the file extension, see if the
    // response headers will give us a MIME type.
    $content_type = $response->getHeader('Content-Type');
    // If there was no Content-Type header, there's nothing else we can do.
    if (empty($content_type)) {
      return NULL;
    }
    $extensions = MimeTypes::getDefault()->getExtensions(reset($content_type));
    if ($extensions) {
      return reset($extensions);
    }
    // If no file extension could be determined from the Content-Type header,
    // we're stumped.
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function createSourceField(MediaTypeInterface $type) {
    return parent::createSourceField($type)->set(
      'description',
      $this->t('Must be in the form <code>[PAGE_ID]_[POST_ID]</code>, for example: <code>0123456789_0123456789</code>.'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function prepareViewDisplay(MediaTypeInterface $type, EntityViewDisplayInterface $display) {
    $display->removeComponent($this->getSourceFieldDefinition($type)->getName());
  }

}
