<?php

namespace Drupal\paragraphs_table\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\filter\FilterPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Hook implementations for Paragraphs Table.
 */
class ParagraphsTableHook {
  use StringTranslationTrait;

  /**
   * Constructs a new DataFieldHook object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The message service.
   * @param \Drupal\filter\FilterPluginManager|null $filterPluginManager
   *   Filter plugin manager.
   */
  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected ConfigFactoryInterface $configFactory,
    protected MessengerInterface $messenger,
    protected ?FilterPluginManager $filterPluginManager = NULL,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('config.factory'),
      $container->get('messenger'),
      $container->get('plugin.manager.filter'),
    );
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'help.page.paragraphs_table':
        $text = file_get_contents(__DIR__ . '/../../README.md');
        if (!$this->moduleHandler->moduleExists('markdown')) {
          return '<pre>' . Html::escape($text) . '</pre>';
        }
        else {
          // Use the Markdown filter to render the README.
          $settings = $this->configFactory->get('markdown.settings')->getRawData();
          $config = ['settings' => $settings];
          $filter = $this->filterPluginManager->createInstance('markdown', $config);
          return $filter->process($text, 'en');
        }

      default:
        return FALSE;
    }
    return FALSE;
  }

}
