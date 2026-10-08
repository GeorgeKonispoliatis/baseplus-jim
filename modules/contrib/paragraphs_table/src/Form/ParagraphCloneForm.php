<?php

namespace Drupal\paragraphs_table\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Paragraph Clone Form class.
 */
class ParagraphCloneForm extends ContentEntityForm {

  /**
   * Constructs a paragraphs clone form object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, protected EntityFieldManagerInterface $entityFieldManager) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
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
    );
  }

  /**
   * The entity being cloned by this form.
   *
   * @var \Drupal\paragraphs\ParagraphInterface
   */
  protected $originalEntity;

  /**
   * {@inheritdoc}
   */
  protected function prepareEntity() {
    parent::prepareEntity();

    $account = $this->currentUser();

    // Keep track of the original entity.
    $this->originalEntity = $this->entity;

    // Create a duplicate.
    $paragraph = $this->entity = $this->entity->createDuplicate();
    $paragraph->set('created', $this->time->getRequestTime());
    $paragraph->setOwnerId($account->id());
    $paragraph->setRevisionAuthorId($account->id());
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = parent::form($form, $form_state);
    $entity = $this->entity;
    $host = $entity->getParentEntity();
    $typeName = '';
    $entity_type = '';
    $bundle = '';
    $parent = '';
    $field = $entity->get('parent_field_name')->value;
    $form['field'] = [
      '#type' => 'hidden',
      '#value' => $field,
    ];
    if (!empty($host)) {
      $entity_type = $host->getEntityTypeId();
      $bundle = $host->bundle();
      $parent = $host->id();
      $entityFieldManager = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
      $typeName = $entityFieldManager[$field]?->getLabel() ?? '';
    }

    $form['#title'] = $this->t('Clone %type item %id', [
      '%type' => $typeName,
      '%id' => $entity->id(),
    ]);

    $form['entity_type'] = [
      '#type' => 'hidden',
      '#value' => $entity_type,
    ];

    $form['bundle'] = [
      '#type' => 'hidden',
      '#value' => $bundle,
    ];
    $form['parent'] = [
      '#type' => 'hidden',
      '#value' => $parent,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $destination_entity_type = $form_state->getValue(['entity_type']);
    $destination_entity_id = $form_state->getValue(['parent']);
    $destination_field = $form_state->getValue(['field']);
    if ($destination_entity_id && $destination_field) {
      /** @var \Drupal\Core\Entity\FieldableEntityInterface $destination_entity */
      $destination_entity = $this->entityTypeManager->getStorage($destination_entity_type)->load($destination_entity_id);
      if ($destination_entity) {
        if (!$destination_entity->access('update')) {
          $form_state->setError($form['parent'], 'You are not allowed to update this content.');
        }
        if (!$destination_entity->get($destination_field)->access('edit')) {
          $form_state->setError($form['field'], 'You are not allowed to edit this field.');
        }
      }
    }
    return parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $destination_entity_type = $form_state->getValue(['entity_type']);
    $destination_entity_id = $form_state->getValue(['parent']);
    $destination_field = $form_state->getValue(['field']);
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $destination_entity */
    $destination_entity = $this->entityTypeManager->getStorage($destination_entity_type)->load($destination_entity_id);
    $destination_entity->get($destination_field)->appendItem($this->entity);

    $destination_entity->save();

    $this->entity = $this->entityTypeManager
      ->getStorage($this->entity->getEntityTypeId())
      ->loadUnchanged($this->entity->id());

    $request = $this->getRequest();
    if ($request->query->has('destination')) {
      $destination = $request->query->get('destination');
      if (strpos($destination, '/') !== 0) {
        $destination = '/' . $destination;
      }
      $url = Url::fromUserInput($destination);
      $request->query->remove('destination');
      $form_state->setRedirectUrl($url);
    }
    else {
      $form_state->setRedirectUrl($destination_entity->toUrl());
    }
    return $destination_entity;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Call parent to get the actual form.
    $form = parent::buildForm($form, $form_state);

    // Ensure the form has a page title.
    $form['#title'] = $this->t('Duplicate paragraph: @label', [
      '@label' => $this->entity->label() ?: $this->t('(No label)'),
    ]);

    return $form;
  }

}
