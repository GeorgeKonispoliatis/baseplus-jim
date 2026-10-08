<?php

namespace Drupal\media_external;

use Drupal\media_external\Plugin\ExternalMediaProviderInterface;

/**
 * Defines the interface for the Cache Provider.
 */
interface ExternalMediaCacheWrapperInterface {

  /**
   * Search for external media items by keyword.
   *
   * @param \Drupal\media_external\Plugin\ExternalMediaProviderInterface $provider
   *   The ExternalMediaProvider plugin.
   * @param string $keyword
   *   The keyword to search for.
   * @param int $page
   *   An optional page number for the search results.
   *
   * @return array
   *   An array of external media value objects and pager data.
   */
  public function search(ExternalMediaProviderInterface $provider, string $keyword, int $page = 1): array;

  /**
   * Loads external media by ID.
   *
   * @param \Drupal\media_external\Plugin\ExternalMediaProviderInterface $provider
   *   The ExternalMediaProvider plugin class.
   * @param string $id
   *   The ID of the photo to load.
   *
   * @return \Drupal\media_external\ExternalMedia
   *   The external media value object.
   */
  public function load(ExternalMediaProviderInterface $provider, string $id): ExternalMedia;

}
