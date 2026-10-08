<?php

namespace Drupal\paragraphs_table\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Paragraph Edit Form class.
 */
class ParagraphEditForm extends ContentEntityForm {

  /**
   * The content translation manager.
   *
   * @var \Drupal\content_translation\ContentTranslationManagerInterface
   */
  protected ?ContentTranslationManagerInterface $translationManager;

  /**
   * Constructs a paragraphs edit form object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\content_translation\ContentTranslationManagerInterface|null $content_translation_manager
   *   The translation manager.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, protected EntityFieldManagerInterface $entityFieldManager, protected LanguageManagerInterface $languageManager, ContentTranslationManagerInterface|null $content_translation_manager = NULL) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->translationManager = $content_translation_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('entity_field.manager'),
      $container->get('language_manager'),
      $container->has('content_translation.manager') ? $container->get('content_translation.manager') : NULL,
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function init(FormStateInterface $form_state) {
    $targetLangcode = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
    $form_state->set('langcode', $targetLangcode);

    if ($this->entity->hasTranslation($targetLangcode)) {
      parent::init($form_state);
      return;
    }

    $translationSource = $this->entity;
    $parentEntity = $this->entity->getParentEntity();
    $parentSourceLangcode = $parentEntity->language()->getId();

    if ($parentEntity->hasTranslation($targetLangcode)) {
      $parentEntity = $parentEntity->getTranslation($targetLangcode);
      $parentSourceLangcode = $this->translationManager->getTranslationMetadata($parentEntity)->getSource();
    }

    if ($this->entity->hasTranslation($parentSourceLangcode)) {
      $translationSource = $this->entity->getTranslation($parentSourceLangcode);
    }

    $this->entity = $this->entity->addTranslation($targetLangcode, $translationSource->toArray());
    $this->translationManager->getTranslationMetadata($this->entity)->setSource($translationSource->language()->getId());

    parent::init($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $field_name = $this->entity->get('parent_field_name')->value;
    $host = $this->entity->getParentEntity();
    $bundle_entity_type = $typeName = '';
    if (!empty($host)) {
      $entity_type = $host->getEntityTypeId();
      $bundle = $host->bundle();
      $entityFieldManager = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
      $typeName = $entityFieldManager[$field_name]->getLabel();
      $bundle_entity_type = $host->getEntityType()->getBundleEntityType();
    }
    $form['#title'] = $this->t('Edit %type item %id', [
      '%type' => $typeName,
      '%id' => $this->entity->id(),
    ]);
    $form = parent::form($form, $form_state);
    $form_mode = $this->getRequest()->query->get('form_mode') ?? NULL;
    if (!empty($form_mode)) {
      $display = EntityFormDisplay::collectRenderDisplay($this->entity, $form_mode);
      foreach ($display->get('hidden') as $fieldName => $isHide) {
        if ($isHide && isset($form[$fieldName])) {
          $form[$fieldName]['#access'] = FALSE;
        }
      }
      $components = $display->getComponents();
      foreach ($components as $name => $options) {
        if (isset($form[$name])) {
          // Get widget plugin from display.
          $widget = $display->getRenderer($name);
          if ($widget) {
            // Replace current widget with form mode widget.
            $form[$name]['widget'] = $widget->form($this->entity->get($name), $form, $form_state);
            // Update weight.
            if (isset($options['weight'])) {
              $form[$name]['#weight'] = $options['weight'];
            }
          }
        }
      }
    }
    $form['#entity_parent_type'] = $entity_type ?? '';
    $form['#entity_field'] = $field_name;
    $form['#entity_id'] = $this->entity->id();
    if (empty($bundle_entity_type)) {
      return $form;
    }
    $bundle_entity = $this->entityTypeManager
      ->getStorage($bundle_entity_type)
      ?->load($host->bundle());
    $form['#new_revision'] = method_exists($bundle_entity, 'shouldCreateNewRevision') ? $bundle_entity?->shouldCreateNewRevision() : FALSE;
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function save(array $form, FormStateInterface $form_state) {
    if (!empty($form['#new_revision'])) {
      $this->entity->setNewRevision();
    }
    return $this->entity->save();
  }

}
