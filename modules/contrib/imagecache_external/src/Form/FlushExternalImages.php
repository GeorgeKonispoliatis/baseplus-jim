<?php

namespace Drupal\imagecache_external\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

class FlushExternalImages extends ConfirmFormBase {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new SettingsForm.
   *
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   */
  public function __construct(FileSystemInterface $fileSystem, ModuleHandlerInterface $moduleHandler) {
    $this->fileSystem = $fileSystem;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
      $container->get('module_handler')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'imagecache_external_flush_external_images_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Flush all external images?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('imagecache_external.admin_settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $file_count = 0;
    if (file_exists(imagecache_external_get_directory_path())) {
      $files = $this->fileSystem->scanDirectory(imagecache_external_get_directory_path(), '/.*/');
      $files_to_delete = imagecache_external_flush_generate_files_to_delete_list($files);
      $this->moduleHandler->alter('imagecache_external_flush_filepath', $files_to_delete);
      if (!empty($files_to_delete)) {
        $file_count = count($files_to_delete);
      }
    }

    // Check if original files should be flushed.
    $config = \Drupal::config('imagecache_external.settings');
    $flush_originals = $config->get('imagecache_external_cron_flush_originals');

    if ($file_count > 0) {
      if ($flush_originals) {
        // Both original files and derivatives will be deleted.
        return $this->formatPlural($file_count,'Are you sure? This cannot be undone. <strong>1 file will be deleted</strong>.<br />Image style derivatives will also be deleted.', 'Are you sure? This cannot be undone. <strong>@count files will be deleted</strong>.<br />Image style derivatives will also be deleted.');
      }
      else {
        // Only derivatives will be deleted, originals will be preserved.
        return $this->formatPlural($file_count,'Are you sure? <strong>Image style derivatives will be deleted</strong>, but the original file will be preserved.<br />This action cannot be undone.', 'Are you sure? <strong>Image style derivatives will be deleted</strong>, but the @count original files will be preserved.<br />This action cannot be undone.');
      }
    }
    else {
      return $this->t('No files found to flush.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Flush');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return $this->t('Cancel');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (imagecache_external_flush_cache()) {
      $this->messenger()->addMessage($this->t('Imagecache external images have been scheduled to be deleted on next cron run(s).'));
    }
    else {
      $this->messenger()->addMessage($this->t('Could not flush external images'), MessengerInterface::TYPE_ERROR);
    }
    $form_state->setRedirect('imagecache_external.admin_settings');
  }

}
