<?php

namespace Drupal\imagecache_external\Commands;

use Drush\Commands\DrushCommands;

class ImagecacheExternalCommands extends DrushCommands {

  /**
   * Generate the path and (optionally) download an image.
   *
   * @param $url
   *
   * @command imagecache-external:generate
   */
  public function generate($url) {
    $this->logger()->notice('Generating path for ' . $url);
    $uri = imagecache_external_generate_path($url);
    if ($uri) {
      $this->logger()->notice('File available at ' . \Drupal::service('file_system')->realPath($uri));
    }
    else {
      $this->logger()->notice('Failed generating path for ' . $url);
    }
  }

  /**
   * Set a default image in config.
   *
   * @param int $fid
   *
   * @command imagecache-external:set-default-image
   */
  public function setDefaultImageInConfig(int $fid = 0) {
    $config_factory = \Drupal::configFactory();
    $config = $config_factory->getEditable('imagecache_external.settings');
    $config->set('imagecache_fallback_image', $fid);
    $config->save();
  }

  /**
   * Validate a host.
   *
   * @param string $host
   *
   * @command imagecache-external:validate-host
   */
  public function testValidate(string $host) {
    $config = imagecache_external_config();
    $list = preg_split('/\s+/', $config->get('imagecache_external_hosts'), -1, PREG_SPLIT_NO_EMPTY);
    foreach ($list as $key => $value) {
      $list[$key] = preg_quote($value);
    }
    $this->logger()->notice('Use whitelist: ' . ($config->get('imagecache_external_use_whitelist') ? 'yes' : 'no'));
    $this->logger->notice('Hosts: ' . print_r($list, TRUE));
    $this->logger()->notice('Validating host: ' . $host);
    if (imagecache_external_validate_host($host)) {
      $this->logger()->notice('Host is valid: ' . $host);
    }
    else {
      $this->logger()->error('Host is not valid: ' . $host);
    }
  }

}
