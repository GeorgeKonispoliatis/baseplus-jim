<?php

namespace Drupal\media_external\Plugin\media\ExternalMediaProvider;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Site\Settings;
use Drupal\media_external\ExternalMedia;
use Drupal\media_external\Plugin\ExternalMediaProviderInterface;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a external media provider for Pexels.
 *
 * @ExternalMediaProvider(
 *   id = "pexels",
 *   label = "Pexels",
 * )
 */
class Pexels extends PluginBase implements ExternalMediaProviderInterface, ContainerFactoryPluginInterface {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Constructs a new OEmbed instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \GuzzleHttp\Client $http_client
   *   The HTTP client.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Client $http_client) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function search(string $keyword, int $page = 1): array {
    $response = $this->httpClient->get('https://api.pexels.com/v1/search', [
      'query' => [
        'query' => $keyword,
        // The pager for the pexels API starts at 1.
        'page' => $page + 1,
        'per_page' => 30,
      ],
      'headers' => [
        'Authorization' => Settings::get('media.external_provider.pexels.api_key'),
      ],
    ]);

    $data = Json::decode($response->getBody()->getContents());

    $photos = [];
    foreach ($data['photos'] as $photo) {
      $photos[] = new ExternalMedia(
        $photo['id'],
        $photo['src']['original'],
        $photo['src']['original'] . '?w=800&h=600&fit=crop',
        $photo['alt'] ?? '',
        $photo['alt'] ?? '',
        $photo['photographer'] ?? '',
        $photo['photographer_url'] ?? '',
      );
    }

    return [
      'total' => $data['total_results'],
      'per_page' => $data['per_page'],
      'current_page' => $data['page'],
      'results' => $photos,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $id): ExternalMedia {
    $response = $this->httpClient->get('https://api.pexels.com/v1/photos/' . $id, [
      'headers' => [
        'Authorization' => Settings::get('media.external_provider.pexels.api_key'),
      ],
    ]);

    $data = Json::decode($response->getBody()->getContents());

    return new ExternalMedia(
      $data['id'],
      $data['src']['original'],
      $data['src']['original'] . '?w=800&h=600&fit=crop',
      $data['alt'] ?? '',
      $data['alt'] ?? '',
      $data['photographer'] ?? '',
      $data['photographer_url'] ?? '',
    );
  }

}
