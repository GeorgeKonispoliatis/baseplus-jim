<?php

namespace Drupal\paragraphs_table\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'Paragraphs table json' formatter.
 */
#[FieldFormatter(
  id: 'paragraphs_table_json_formatter',
  label: new TranslatableMarkup('Paragraphs table json'),
  description: new TranslatableMarkup('Useful for displaying the rest of the export'),
  field_types: [
    'entity_reference_revisions',
  ],
)]
class ParagraphsTableJsonFormatter extends EntityReferenceFormatterBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs an CountryDefaultFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, protected EntityRepositoryInterface $entityRepository,) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity.repository'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $setting = ['recursion_level' => 2];
    return $setting + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements['recursion_level'] = [
      '#type' => 'number',
      '#title' => $this->t('Recursion max level'),
      '#default_value' => $this->getSetting('recursion_level'),
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    return [
      $this->t('Recursion max level: @level', ['@level' => $this->getSetting('recursion_level')]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    if ($items->isEmpty()) {
      return $elements;
    }
    $setting = $this->getSettings();
    foreach ($items as $delta => $item) {
      // Ignore items where no entity could be loaded in prepareView().
      if (!empty($item->_loaded)) {
        $entity = $item->entity;
        // Set the entity in the correct language for display.
        if ($entity instanceof TranslatableInterface) {
          $entity = $this->entityRepository->getTranslationFromContext($entity, $langcode);
        }

        $access = $this->checkAccess($entity);
        // Add the access result's cacheability, ::view() needs it.
        $item->_accessCacheability = CacheableMetadata::createFromObject($access);
        if ($access->isAllowed()) {
          // Add the referring item, in case the formatter needs it.
          if (isset($entity->_referringItem) && ($entity->_referringItem !== $item)) {
            // If the entity is already being referenced by another field item,
            // clone the entity so that _referringItem is set to the correct
            // item in each instance.
            $entity = clone $entity;
            $item->entity = $entity;
          }
          $entity->_referringItem = $item;
        }
        $fields = $this->getFields($entity);
        foreach ($fields as $fieldName => $field) {
          if (!$field->isEmpty()) {
            $value = $field->getValue();
            if (method_exists($field, 'getString')) {
              $value = !empty($field->getString()) ? $field->getString() : $value;
            }
            if (method_exists($field, 'referencedEntities')) {
              $entities = $field->referencedEntities();
              $value = [];
              foreach ($entities as $entity) {
                $value[] = $this->serializeEntity($entity, $setting["recursion_level"]);
              }
            }
            $elements[$delta][$fieldName] = $value;
          }
        }
      }
    }
    return [['#markup' => json_encode($elements, JSON_PRETTY_PRINT)]];
  }

  /**
   * {@inheritdoc}
   */
  public function getFields($entity) {
    $fields = [];
    foreach ($entity->getFieldDefinitions() as $name => $definition) {
      if ($definition instanceof FieldConfig) {
        $fields[$name] = $entity->get($name);
      }
    }
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function serializeEntity($entity, $depthMax = 1, $depth = 0) {
    $data = [];
    if ($depth > $depthMax) {
      return $data;
    }
    if (method_exists($entity, 'getFieldDefinitions')) {
      foreach ($entity->getFieldDefinitions() as $field_name => $field_definition) {
        if (!$entity->hasField($field_name)) {
          continue;
        }
        $field = $entity->get($field_name);
        $fieldDefType = $field->getFieldDefinition()->getType();
        if ($fieldDefType == 'password') {
          continue;
        }
        if ($field instanceof EntityReferenceFieldItemListInterface) {
          $referenced_entities = $field->referencedEntities();
          $data[$field_name] = [];
          foreach ($referenced_entities as $referenced_entity) {
            $subEntity = $this->serializeEntity($referenced_entity, $depthMax, $depth + 1);
            if (!empty($subEntity)) {
              $data[$field_name][] = $subEntity;
            }
          }
        }
        elseif (method_exists($field, 'getString')) {
          $data[$field_name] = $field->getString();
        }
        if ($data[$field_name] !== 0 && empty($data[$field_name])) {
          unset($data[$field_name]);
        }
      }
      $data['link'] = $this->getEntityUrl($entity);
    }
    return $data;
  }

  /**
   * {@inheritDoc}
   */
  public function getEntityUrl(ContentEntityInterface $entity) {
    if ($entity instanceof FileInterface) {
      return $entity->createFileUrl();
    }
    if ($entity->hasLinkTemplate('canonical')) {
      return $entity->toUrl()->toString();
    }
    return NULL;
  }

}
