<?php

declare(strict_types = 1);

namespace Drupal\imagecache_external\Svg;

use enshrined\svgSanitize\data\AllowedAttributes;

/**
 * Prepares and returns a list of allowed attributes.
 */
class ImagecacheExternalAttributes extends AllowedAttributes {

  /**
   * Additional allowed attributes.
   *
   * @var string[]
   */
  protected static $attributes = [];

  /**
   * {@inheritdoc}
   */
  public static function getAttributes(): array {
    return array_unique([...parent::getAttributes(), ...self::$attributes]);
  }

  /**
   * Sets additional allowed attributes.
   *
   * @param array $attributes
   *   Additional allowed attributes.
   */
  public static function setAttributes(array $attributes): void {
    self::$attributes = $attributes;
  }

}
