<?php

namespace Drupal\media_external\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a ExternalMediaProvider annotation object.
 *
 * @Annotation
 */
class ExternalMediaProvider extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The plugin label.
   *
   * @var string
   */
  public $label;

}
