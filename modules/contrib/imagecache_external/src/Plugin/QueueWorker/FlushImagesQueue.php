<?php

namespace Drupal\imagecache_external\Plugin\QueueWorker;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the imagecache_external_flush_images queue worker.
 *
 * @QueueWorker (
 *   id = "imagecache_external_flush_images",
 *   title = @Translation("Imagechache external flush images."),
 *   cron = {"time" = 60}
 * )
 */
class FlushImagesQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The FileSystem Interface.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  private $fileSystem;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * FlushImagesQueue constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FileSystemInterface $file_system, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fileSystem = $file_system;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('file_system'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('logger.channel.imagecache_external'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $count = 0;

    try {
      $default_scheme = $this->configFactory->get('system.file')->get('default_scheme');
      $imagecache_directory = $this->configFactory->get('imagecache_external.settings')->get('imagecache_directory');
      $flush_originals = $this->configFactory->get('imagecache_external.settings')->get('imagecache_external_cron_flush_originals');
      $base_path = $default_scheme . '://' . $imagecache_directory;

      foreach ($data as $file) {
        if ($file == IMAGECACHE_EXTERNAL_FLUSH_DIRECTORIES) {
          // Full directory flush, a much simpler approach.
          $count += $this->flushAllDirectories($default_scheme, $imagecache_directory, $base_path, $flush_originals);
        }
        else {
          // Individual file flush.
          $count += $this->flushSingleFile($file, $base_path, $flush_originals);
        }
      }

      if ($count > 0) {
        $this->logger->notice('@count item(s) have been flushed', ['@count' => $count]);
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Error flushing images: @message', ['@message' => $e->getMessage()]);
    }
  }

  /**
   * Flush all external image directories.
   *
   * @param string $default_scheme
   * @param string $imagecache_directory
   * @param string $base_path
   * @param bool $flush_originals
   *
   * @return int
   */
  private function flushAllDirectories(string $default_scheme, string $imagecache_directory, string $base_path, bool $flush_originals): int {
    $count = 0;

    try {
      // Flush originals if enabled.
      if ($flush_originals && file_exists($base_path)) {
        $this->fileSystem->deleteRecursive($base_path);
        $count++;
      }

      // Flush ALL style derivatives in one go - much more efficient!
      $styles_externals_path = $default_scheme . '://styles';
      if (file_exists($styles_externals_path)) {
        // Get all style directories and delete their external subdirectories.
        $style_dirs = scandir($this->fileSystem->realpath($styles_externals_path));
        foreach ($style_dirs as $style_dir) {
          if ($style_dir === '.' || $style_dir === '..') {
            continue;
          }

          $external_style_path = $styles_externals_path . '/' . $style_dir . '/' . $default_scheme . '/' . $imagecache_directory;
          if (file_exists($external_style_path)) {
            $this->fileSystem->deleteRecursive($external_style_path);
            $count++;
          }
        }
      }
    }
    catch (FileException $e) {
      throw new \Exception('Failed to flush directories: ' . $e->getMessage());
    }

    return $count;
  }

  /**
   * Flush a single file and its derivatives.
   *
   * @param string $file
   * @param string $base_path
   * @param bool $flush_originals
   *
   * @return int
   */
  private function flushSingleFile(string $file, string $base_path, bool $flush_originals): int {
    $file_path = $base_path . '/' . $file;

    try {
      // Flush image derivatives (this handles style derivatives)
      image_path_flush($file_path);

      // Delete original if configured.
      if ($flush_originals && file_exists($file_path)) {
        $this->fileSystem->deleteRecursive($file_path);
      }

      return 1;
    }
    catch (FileException $e) {
      throw new \Exception('Failed to flush file: ' . $e->getMessage());
    }
  }

}
