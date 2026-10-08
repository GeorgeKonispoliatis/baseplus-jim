<?php

namespace Drupal\media_external\Plugin\media\Source;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\Utility\Token;
use Drupal\field\FieldConfigInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Drupal\media\MediaTypeInterface;
use Drupal\media_external\ExternalMediaCacheWrapperInterface;
use Drupal\media_external\Plugin\ExternalMediaProviderManager;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\TransferException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Mime\MimeTypes;

/**
 * Provides a media source plugin for external media resources.
 *
 * @MediaSource(
 *   id = "external_media",
 *   label = @Translation("External media"),
 *   description = @Translation("Use an external API to import local media items."),
 *   allowed_field_types = {"string"},
 *   default_thumbnail_filename = "no-thumbnail.png",
 *   thumbnail_alt_metadata_attribute = "thumbnail_alt_value",
 *   forms = {
 *     "media_library_add" = "Drupal\media_external\Form\ExternalMediaAddForm"
 *   }
 * )
 */
class ExternalMedia extends MediaSourceBase {

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The token replacement service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The external media provider manager.
   *
   * @var \Drupal\media_external\Plugin\ExternalMediaProviderManager
   */
  protected $externalMediaProviderManager;

  /**
   * The stream wrapper manager service.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The external media cache wrapper.
   *
   * @var \Drupal\media_external\ExternalMediaCacheWrapperInterface
   */
  protected $cacheWrapper;

  /**
   * Constructs a new ExternalMedia instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager
   *   The field type plugin manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param \Drupal\Core\Utility\Token $token
   *   The token replacement service.
   * @param \Drupal\media_external\Plugin\ExternalMediaProviderManager $external_provider_manager
   *   The external media provider manager.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager service.
   * @param \Drupal\media_external\ExternalMediaCacheWrapperInterface $cache_wrapper
   *   The external media cache wrapper.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, ConfigFactoryInterface $config_factory, FieldTypePluginManagerInterface $field_type_manager, LoggerChannelFactoryInterface $logger_factory, MessengerInterface $messenger, ClientInterface $http_client, FileSystemInterface $file_system, Token $token, ExternalMediaProviderManager $external_provider_manager, StreamWrapperManagerInterface $stream_wrapper_manager, ExternalMediaCacheWrapperInterface $cache_wrapper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_field_manager, $field_type_manager, $config_factory);
    $this->loggerFactory = $logger_factory;
    $this->messenger = $messenger;
    $this->httpClient = $http_client;
    $this->fileSystem = $file_system;
    $this->token = $token;
    $this->externalMediaProviderManager = $external_provider_manager;
    $this->streamWrapperManager = $stream_wrapper_manager;
    $this->cacheWrapper = $cache_wrapper;
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
      $container->get('config.factory'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('logger.factory'),
      $container->get('messenger'),
      $container->get('http_client'),
      $container->get('file_system'),
      $container->get('token'),
      $container->get('plugin.manager.media_external_provider'),
      $container->get('stream_wrapper_manager'),
      $container->get('media_external.cache.wrapper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes(): array {
    return [
      'provider' => $this->t('Provider'),
      'title' => $this->t('Filename'),
      'url' => $this->t('File URL'),
      'description' => $this->t('Description'),
      'alt' => $this->t('Alt text'),
      'photographer' => $this->t('Photographer'),
      'photographer_url' => $this->t('Photographer URL'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $name): ?string {
    $external_id = $this->getSourceFieldValue($media);
    // When the source field is empty, just return NULL.
    if (empty($external_id)) {
      return NULL;
    }

    $provider_name = $this->getConfiguration()['provider'];
    /** @var \Drupal\media_external\Plugin\ExternalMediaProviderInterface $provider */
    $provider = $this->externalMediaProviderManager->createInstance($provider_name);
    $photo = $this->cacheWrapper->load($provider, $external_id);

    switch ($name) {
      case 'default_name':
        if ($title = $this->getMetadata($media, 'title')) {
          return $title;
        }
        if ($url = $this->getMetadata($media, 'url')) {
          return $url;
        }
        return parent::getMetadata($media, 'default_name');

      case 'thumbnail_uri':
        return $this->getLocalThumbnailUri($photo->getThumbnailUrl()) ?: parent::getMetadata($media, 'thumbnail_uri');

      case 'type':
        return $provider_name;

      case 'title':
        return $this->fileSystem->basename($photo->getUrl());

      case 'url':
        return $photo->getUrl();

      case 'description':
        return $photo->getDescription();

      case 'alt':
        return $photo->getAlt();

      case 'photographer':
        return $photo->getPhotographer();

      case 'photographer_url':
        return $photo->getPhotographerUrl();

      default:
        break;
    }
    return NULL;
  }

  /**
   * Returns the local URI for a resource thumbnail.
   *
   * If the thumbnail is not already locally stored, this method will attempt
   * to download it.
   *
   * @param string $url
   *   The thumbnail URL.
   *
   * @return string|null
   *   The local thumbnail URI, or NULL if it could not be downloaded, or if the
   *   resource has no thumbnail at all.
   */
  protected function getLocalThumbnailUri(string $url): ?string {
    // Use the configured directory to store thumbnails. The directory can
    // contain basic (i.e., global) tokens. If any of the replaced tokens
    // contain HTML, the tags will be removed and XML entities will be decoded.
    $configuration = $this->getConfiguration();
    $directory = $configuration['thumbnails_directory'];
    $directory = $this->token->replace($directory);
    $directory = PlainTextOutput::renderFromHtml($directory);

    // The local thumbnail doesn't exist yet, so try to download it. First,
    // ensure that the destination directory is writable, and if it's not,
    // log an error and bail out.
    if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      $this->loggerFactory->get('media')->warning('Could not prepare thumbnail destination directory @dir for oEmbed media.', [
        '@dir' => $directory,
      ]);
      return NULL;
    }

    // The local filename of the thumbnail is always a hash of its remote URL.
    // If a file with that name already exists in the thumbnails directory,
    // regardless of its extension, return its URI.
    $hash = Crypt::hashBase64($url);
    $files = $this->fileSystem->scanDirectory($directory, "/^{$hash}\..*/");
    if (count($files) > 0) {
      return reset($files)->uri;
    }

    // The local thumbnail doesn't exist yet, so we need to download it.
    try {
      $response = $this->httpClient->request('GET', $url);
      if ($response->getStatusCode() === 200) {
        $local_thumbnail_uri = $directory . DIRECTORY_SEPARATOR . $hash . '.' . $this->getThumbnailFileExtensionFromUrl($url, $response);
        $this->fileSystem->saveData((string) $response->getBody(), $local_thumbnail_uri, FileSystemInterface::EXISTS_REPLACE);
        return $local_thumbnail_uri;
      }
    }
    catch (TransferException $e) {
      $this->loggerFactory->get('media')->warning($e->getMessage());
    }
    catch (FileException $e) {
      $this->loggerFactory->get('media')->warning('Could not download remote thumbnail from {url}.', [
        'url' => $url,
      ]);
    }
    return NULL;
  }

  /**
   * Tries to determine the file extension of a thumbnail.
   *
   * @param string $thumbnail_url
   *   The remote URL of the thumbnail.
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The response for the downloaded thumbnail.
   *
   * @return string|null
   *   The file extension, or NULL if it could not be determined.
   */
  protected function getThumbnailFileExtensionFromUrl(string $thumbnail_url, ResponseInterface $response): ?string {
    // First, try to glean the extension from the URL path.
    $path = parse_url($thumbnail_url, PHP_URL_PATH);
    if ($path) {
      $extension = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));
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
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $configuration = $this->getConfiguration();

    $form['thumbnails_directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Thumbnails location'),
      '#default_value' => $this->configuration['thumbnails_directory'],
      '#description' => $this->t('Thumbnails will be fetched from the provider for local usage. This is the URI of the directory where they will be placed.'),
      '#required' => TRUE,
    ];

    $providers = [];
    $definitions = $this->externalMediaProviderManager->getDefinitions();
    foreach ($definitions as $definition) {
      $providers[$definition['id']] = $definition['label'];
    }

    $form['provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Provider'),
      '#default_value' => $configuration['provider'],
      '#options' => $providers,
      '#description' => $this->t('Select an allowed external media provider for this media type.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $thumbnails_directory = $form_state->getValue('thumbnails_directory');
    if (!$this->streamWrapperManager->isValidUri($thumbnails_directory)) {
      $form_state->setErrorByName('thumbnails_directory', $this->t('@path is not a valid path.', [
        '@path' => $thumbnails_directory,
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return parent::defaultConfiguration() + [
      'thumbnails_directory' => 'public://external_thumbnails/[date:custom:Y-m]',
      'provider' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareFormDisplay(MediaTypeInterface $type, EntityFormDisplayInterface $display): void {
    parent::prepareFormDisplay($type, $display);
    $source_field = $this->getSourceFieldDefinition($type)->getName();

    $display->setComponent($source_field, [
      'type' => 'string_textfield',
      'weight' => $display->getComponent($source_field)['weight'],
    ]);
    $display->removeComponent('name');
  }

  /**
   * Returns the external media provider name.
   *
   * @return string
   *   The configured provider name.
   */
  public function getProvider(): string {
    $configuration = $this->getConfiguration();
    return $configuration['provider'] ?: $this->getPluginDefinition()['provider'];
  }

  /**
   * {@inheritdoc}
   */
  public function createSourceField(MediaTypeInterface $type): FieldConfigInterface {
    $plugin_definition = $this->getPluginDefinition();

    $label = (string) $this->t('@type ID', [
      '@type' => $plugin_definition['label'],
    ]);
    return parent::createSourceField($type)->set('label', $label);
  }

}
