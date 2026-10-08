<?php

namespace Drupal\media_external;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Site\Settings;
use Drupal\media_external\Plugin\ExternalMediaProviderInterface;

/**
 * Cache wrapper for external media API calls.
 */
class ExternalMediaCacheWrapper implements ExternalMediaCacheWrapperInterface {

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs Cache provider Service.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(CacheBackendInterface $cache, TimeInterface $time) {
    $this->cache = $cache;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public function search(ExternalMediaProviderInterface $provider, string $keyword, int $page = 1): array {
    $cid = 'search:' . $provider->getPluginId() . ':' . Crypt::hashBase64($keyword) . ':' . $page;

    // Try to fetch the data from the cache.
    if ($cache = $this->cache->get($cid)) {
      return $cache->data;
    }

    // If cached data is not available, fetch the data from the provider.
    $result = $provider->search($keyword, $page);
    $this->cache->set($cid, $result, $this->generateExpireTimestamp($provider, 'search'));

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function load(ExternalMediaProviderInterface $provider, string $id): ExternalMedia {
    $cid = 'load:' . $provider->getPluginId() . ':' . $id;

    // Try to fetch the data from the cache.
    if ($cache = $this->cache->get($cid)) {
      return $cache->data;
    }

    // If cached data is not available, fetch the data from the provider.
    $result = $provider->load($id);
    $this->cache->set($cid, $result, $this->generateExpireTimestamp($provider, 'load'));

    return $result;
  }

  /**
   * Generate an expire timestamp.
   *
   * The expire timestamp can be set in the settings.php file. The default for
   * all API calls is 24 hours. The expire timestamp can be set per API call
   * type (search or load) and provider. For example:
   * $settings['media_external.pexels.cache.search'] = 60 * 60;
   * $settings['media_external.pexels.cache.load'] = 60 * 60 * 24;
   *
   * @param \Drupal\media_external\Plugin\ExternalMediaProviderInterface $provider
   *   The ExternalMediaProvider class.
   * @param string $type
   *   The type of API call.
   *
   * @return int
   *   Unix timestamp when to expire this item.
   */
  protected function generateExpireTimestamp(ExternalMediaProviderInterface $provider, string $type): int {
    $settings_key = 'media_external.' . $provider->getPluginId() . '.cache.' . $type;
    return $this->time->getRequestTime() + Settings::get($settings_key, 60 * 60 * 24);
  }

}
