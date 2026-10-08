<?php

namespace Drupal\media_external\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\media_external\ExternalMedia;

/**
 * Defines the interface for external media provider plugins.
 */
interface ExternalMediaProviderInterface extends PluginInspectionInterface {

  /**
   * Search for external media items by keyword.
   *
   * @param string $keyword
   *   The keyword to search for.
   * @param int $page
   *   An optional page number for the search results.
   *
   * @return array
   *   An array of external media value objects and pager data.
   */
  public function search(string $keyword, int $page = 1): array;

  /**
   * Loads external media by ID.
   *
   * @param string $id
   *   The ID of the photo to load.
   *
   * @return \Drupal\media_external\ExternalMedia
   *   The external media value object.
   */
  public function load(string $id): ExternalMedia;

}
