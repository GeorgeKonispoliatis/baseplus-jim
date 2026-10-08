<?php

namespace Drupal\Tests\imagecache_external\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the functionality of the imagecache_external_validate_host() function.
 *
 * @group imagecache_external
 */
class ImagecacheExternalValidateHostTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'image',
    'imagecache_external',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig([
      'system',
      'imagecache_external',
    ]);
  }

  /**
   * Tests the imagecache_external_validate_host() function.
   *
   * @dataProvider providerValidateHosts
   */
  public function testValidateHost($url, $expected_valid, $config, $use_whitelist): void {
    $this->config('imagecache_external.settings')
      ->set('imagecache_external_use_whitelist', $use_whitelist)
      ->set('imagecache_external_hosts', $config)
      ->save();

    $validate = imagecache_external_validate_host($url);
    $this->assertEquals($expected_valid, $validate);
  }

  /**
   * Data provider for testValidateHost.
   *
   * @return array
   *   An array of test cases, each containing a host, expected validity and
   *   config.
   */
  public static function providerValidateHosts(): array {
    return [
      ['https://example.com/my-photo.jpg', TRUE, '', FALSE],
      ['https://whatever.com/my-photo.jpg', TRUE, '', FALSE],
      // At this point, imagecache_external_use_whitelist will be set to TRUE.
      // which defaults to FALSE originally.
      ['https://example.com/my-photo.jpg', TRUE, 'example.com', TRUE],
      ['https://sub.example.com/my-photo.jpg', TRUE, 'example.com', TRUE],
      ['https://example.com:8080/my-photo.jpg', TRUE, 'example.com', TRUE],
      ['https://whatever.com/my-photo.jpg', FALSE, 'example.com', TRUE],
      // Make sure when no hosts are configured, validation will fail.
      ['https://example.com/my-photo-will-fail.jpg', FALSE, '', TRUE]
    ];
  }
}
