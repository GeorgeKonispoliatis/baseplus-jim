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
 *   id = "unsplash",
 *   label = "Unsplash",
 * )
 */
class Unsplash extends PluginBase implements ExternalMediaProviderInterface, ContainerFactoryPluginInterface {

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
    $response = $this->httpClient->get('https://api.unsplash.com/search/photos', [
      'query' => [
        'query' => $keyword,
        // The pager for the unsplash API starts at 1.
        'page' => $page + 1,
        'per_page' => 30,
      ],
      'headers' => [
        'Authorization' => 'Client-ID ' . Settings::get('media.external_provider.unsplash.access_key'),
      ],
    ]);

    $data = Json::decode($response->getBody()->getContents());

    $photos = [];
    foreach ($data['results'] as $photo) {
      $photos[] = new ExternalMedia(
        $photo['id'],
        $photo['urls']['full'],
        $photo['urls']['full'] . '?w=800&h=600&crop=faces,edges',
        $photo['description'] ?? '',
        $photo['alt_description'] ?? '',
        $photo['user']['name'] ?? '',
        $photo['user']['portfolio_url'] ?? '',
      );
    }

    return [
      'total' => $data['total'],
      'per_page' => 30,
      'current_page' => $page,
      'results' => $photos,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $id): ExternalMedia {
    $response = $this->httpClient->get('https://api.unsplash.com/photos/' . $id, [
      'headers' => [
        'Authorization' => 'Client-ID ' . Settings::get('media.external_provider.unsplash.access_key'),
      ],
    ]);

    $data = Json::decode($response->getBody()->getContents());

    return new ExternalMedia(
      $data['id'],
      $data['urls']['full'],
      $data['urls']['full'] . '?w=800&h=600&crop=faces,edges',
      $data['description'] ?? '',
      $data['alt_description'] ?? '',
      $data['user']['name'] ?? '',
      $data['user']['portfolio_url'] ?? '',
    );
  }

}
