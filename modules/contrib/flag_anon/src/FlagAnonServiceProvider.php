<?php

namespace Drupal\flag_anon;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Modifies a lazy builder for flag links.
 */
class FlagAnonServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $definition = $container->getDefinition('flag.link_builder');
    $definition->setClass(FlagAnonLinkBuilder::class)
      ->setArguments([
      new Reference('entity_type.manager'),
      new Reference('flag'),
      new Reference('current_user'),
      new Reference('module_handler'),
      ]);
  }

}
