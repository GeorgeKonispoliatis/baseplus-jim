<?php

namespace Drupal\media_external;

/**
 * A value object to store the external media data.
 */
class ExternalMedia {

  /**
   * The external media ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The external media URL.
   *
   * @var string
   */
  protected $url;

  /**
   * The external media thumbnail URL.
   *
   * @var string
   */
  protected $thumbnailUrl;


  /**
   * The external media description.
   *
   * @var string
   */
  protected $description;

  /**
   * The external media alt.
   *
   * @var string
   */
  protected $alt;

  /**
   * The external media photographer.
   *
   * @var string
   */
  protected $photographer;

  /**
   * The external media photographer URL.
   *
   * @var string
   */
  protected $photographerUrl;

  /**
   * Constructs a new ExternalMedia object.
   *
   * @param string $id
   *   The external media ID.
   * @param string $url
   *   The external media URL.
   * @param string $thumbnail_url
   *   The external media thumbnail URL.
   * @param string $description
   *   The external media description.
   * @param string $alt
   *   The external media alt.
   * @param string $photographer
   *   The external media photagrapher name.
   * @param string $photographer_url
   *   The external media photographer URL.
   */
  public function __construct(string $id, string $url, string $thumbnail_url, string $description = '', string $alt = '', string $photographer = '', string $photographer_url = '') {
    $this->id = $id;
    $this->url = $url;
    $this->thumbnailUrl = $thumbnail_url;
    $this->description = $description;
    $this->alt = $alt;
    $this->photographer = $photographer;
    $this->photographerUrl = $photographer_url;
  }

  /**
   * Gets the external media ID.
   *
   * @return string
   *   The external media ID.
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Gets the external media URL.
   *
   * @return string
   *   The external media URL.
   */
  public function getUrl(): string {
    return $this->url;
  }

  /**
   * Gets the external media thumbnail URL.
   *
   * @return string
   *   The external media thumbnail URL.
   */
  public function getThumbnailUrl(): string {
    return $this->thumbnailUrl;
  }

  /**
   * Gets the external media description.
   *
   * @return string
   *   The external media description.
   */
  public function getDescription(): string {
    return $this->description;
  }

  /**
   * Gets the external media ALT text.
   *
   * @return string
   *   The external media ALT text.
   */
  public function getAlt(): string {
    return $this->alt;
  }

  /**
   * Gets the external media photographer name.
   *
   * @return string
   *   The external media photographer name.
   */
  public function getPhotographer(): string {
    return $this->photographer;
  }

  /**
   * Gets the external media photographer URL.
   *
   * @return string
   *   The external media photographer URL.
   */
  public function getPhotographerUrl(): string {
    return $this->photographerUrl;
  }

}
