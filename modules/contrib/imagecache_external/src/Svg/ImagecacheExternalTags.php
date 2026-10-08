<?php

declare(strict_types = 1);

namespace Drupal\imagecache_external\Svg;

use enshrined\svgSanitize\data\AllowedTags;

/**
 * Prepares and returns a list of allowed tags.
 */
class ImagecacheExternalTags extends AllowedTags {

  /**
   * Additional allowed tags.
   *
   * @var string[]
   */
  protected static $tags = [];

  /**
   * {@inheritdoc}
   */
  public static function getTags(): array {
    return array_unique([...parent::getTags(), ...self::$tags]);
  }

  /**
   * Sets additional allowed tags.
   *
   * @param array $tags
   *   Additional allowed tags.
   */
  public static function setTags(array $tags): void {
    self::$tags = $tags;
  }

}
