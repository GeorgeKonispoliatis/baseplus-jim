<?php

namespace Drupal\paragraphs_table\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\FieldConfigInterface;
use Drupal\paragraphs\Plugin\Field\FieldWidget\ParagraphsWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'paragraphs_table_widget' widget.
 */
#[FieldWidget(
  id: 'paragraphs_table_widget',
  label: new TranslatableMarkup('Paragraphs table'),
  description: new TranslatableMarkup('Paragraphs table form widget'),
  field_types: ['entity_reference_revisions'],
)]
class ParagraphsTableWidget extends ParagraphsWidget {

  /**
   * Constructs a ParagraphsWidget object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Entity field manager Service.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entityDisplayRepository
   *   Entity display service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityFieldManagerInterface $entity_field_manager, protected EntityDisplayRepositoryInterface $entityDisplayRepository, protected EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings, $entity_field_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new self(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_field.manager'),
      $container->get('entity_display.repository'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'vertical' => FALSE,
      'paste_clipboard' => FALSE,
      'field_reference' => '',
      'show_all' => FALSE,
      'duplicate' => FALSE,
      'features' => ['duplicate' => 'duplicate'],
      'form_mode' => 'default',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = [];
    $field_name = $this->fieldDefinition->getName();
    $elements['vertical'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Table vertical'),
      '#description' => $this->t('If checked, table data will show in vertical mode.'),
      '#default_value' => !empty($this->getSetting('vertical')) ? $this->getSetting('vertical') : FALSE,
    ];
    $elements['paste_clipboard'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Paste from clipboard'),
      '#description' => $this->t('Add multiple rows, you can paste data from Excel'),
      '#default_value' => !empty($this->getSetting('paste_clipboard')) ? $this->getSetting('paste_clipboard') : FALSE,
    ];
    $options = $this->getFieldsParagraphs();
    if (!empty($options)) {
      $elements['field_reference'] = [
        '#type' => 'select',
        '#options' => $options,
        '#empty_option' => $this->t('- Select -'),
        '#title' => $this->t('Reference field'),
        '#description' => $this->t('Add button multi search on reference'),
        '#default_value' => $this->getSetting('field_reference'),
        '#states' => [
          'invisible' => [":input[name='fields[$field_name][settings_edit_form][settings][paste_clipboard]']" => ['checked' => FALSE]],
        ],
      ];
    }
    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()?->getCardinality();
    if ($cardinality > 1) {
      $elements['show_all'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Show all %cardinality items in form', ['%cardinality' => $cardinality]),
        '#description' => $this->t('If checked, remove button add more.'),
        '#default_value' => !empty($this->getSetting('show_all')) ? $this->getSetting('show_all') : FALSE,
      ];
    }
    if (!in_array($cardinality, range(0, 3))) {
      $elements['features'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Enable widget features'),
        '#options' => [
          'duplicate' => $this->t('Duplicate'),
          // Reserve for future features.
        ],
        '#default_value' => $this->getSetting('features'),
        '#multiple' => TRUE,
      ];
    }
    $settings = $this->getFieldSettings();
    $bundle = NULL;
    if (!empty($settings["handler_settings"]["target_bundles"])) {
      $bundle = array_key_first($this->getAllowedTypes($this->fieldDefinition));
    }
    if (!empty($bundle)) {
      $modes = $this->entityDisplayRepository->getFormModeOptionsByBundle("paragraph", $bundle);
    }
    else {
      $modes = ['default' => $this->t("Default")];
    }

    $elements['form_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Form mode'),
      '#description' => $this->t('Select which form mode is displayed'),
      '#options' => $modes,
      '#default_value' => !empty($this->getSetting('form_mode')) ? $this->getSetting('form_mode') : 'default',
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    if (!empty($this->getSetting('vertical'))) {
      $summary[] = $this->t('Table mode vertical');
    }
    if (!empty($this->getSetting('paste_clipboard'))) {
      $summary[] = $this->t('Paste from Excel');
    }
    if (!empty($this->getSetting('show_all'))) {
      $cardinality = $this->fieldDefinition?->getFieldStorageDefinition()?->getCardinality();
      $summary[] = $this->t('Show all %cardinality elements in form', ['%cardinality' => $cardinality]);
    }
    $features = array_filter($this->getSetting('features'));
    if (!empty($features)) {
      $summary[] = $this->t('Features: @features', ['@features' => implode(', ', $features)]);
    }
    if (!empty($this->getSetting('form_mode'))) {
      $summary[] = $this->t('Mode: @mode', ['@mode' => $this->getSetting('form_mode')]);
    }

    return $summary;
  }

  /**
   * For multiple elements.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $form_mode = $this->getSetting('form_mode');
    $this->setSetting("form_display_mode", $form_mode);
    $elements = parent::formMultipleElements($items, $form, $form_state);
    $settings = $this->fieldDefinition->getSettings();
    $handler = $settings['handler_settings'];
    $bundles = array_keys($this->getAllowedTypes($this->fieldDefinition));
    if (!empty($handler["target_bundles"]) && count($bundles) > 1) {
      return $elements;
    }
    $target_type = $this->getFieldSetting('target_type');
    $default_type = $this->getDefaultParagraphTypeMachineName();
    $field_name = $this->fieldDefinition->getName();
    $widgetState = static::getWidgetState($this->fieldParents, $field_name, $form_state);
    $widgetState["paragraphs"][0]["mode"] = 'edit';
    $elements["#paragraphsTable"]["#widget_state"] = $widgetState;
    $elements["#paragraphsTable"]["#table_vertical"] = $this->getSetting('vertical');
    $elements["#paragraphsTable"]["#paste_clipboard"] = $this->getSetting('paste_clipboard');
    $elements["#paragraphsTable"]["#field_reference"] = $this->getSetting('field_reference');
    $elements["#paragraphsTable"]["#show_all"] = $this->getSetting('show_all');
    $elements["#paragraphsTable"]["#feature"] = $this->getSetting('features');
    if (empty($default_type)) {
      $default_type = array_key_first($this->getAllowedTypes());
    }
    if (empty($default_type)) {
      return $elements;
    }
    $formDisplay = $this->entityDisplayRepository
      ->getFormDisplay($target_type, $default_type, $form_mode);
    $components = $formDisplay->getComponents();
    uasort($components, 'Drupal\Component\Utility\SortArray::sortByWeightElement');

    /** @var \Drupal\paragraphs\ParagraphInterface $paragraphs_entity */
    $paragraphs_entity = $this->entityTypeManager->getStorage($target_type)
      ->create(['type' => $default_type]);
    $field_definitions = $paragraphs_entity->getFieldDefinitions();

    foreach ($components as $name => $setting) {
      if (!empty($field_definitions[$name]) && $field_definitions[$name] instanceof FieldConfigInterface) {
        $elements["#paragraphsTable"]['#fields'][$name] = $field_definitions[$name];
      }
    }
    // Remove fieldgroups we don't need it.
    if (!empty($elements[0]) && $elements[0]['subform']) {
      foreach (range(0, $elements["#max_delta"]) as $delta) {
        $elements[$delta]["subform"]["#fieldgroups"] = [];
      }
    }
    if ($this->getSetting('show_all') && $elements["#max_delta"] < $elements["#cardinality"] - 1) {
      foreach (range($elements["#max_delta"], $elements["#cardinality"] - 1) as $delta) {
        if (empty($elements[$delta])) {
          $elements[$delta] = $elements[0];
          $elements[$delta]["#delta"] = $delta;
          $elements[$delta]["#weight"] = $delta;
          $elements[$delta]["#prefix"] = str_replace('-0-', "-$delta-", $elements[$delta]["#prefix"]);
          $elements[$delta]["_weight"]["#default_value"] = $delta;
          $elements[$delta]["_weight"]["#delta"] = $delta + 1;
          unset($elements[$delta]["top"]["actions"]);
          $this->setParentId($elements[$delta]["subform"], $delta);
        }
      }
      $elements["#max_delta"] = $elements["#cardinality"] - 1;
    }
    if (!empty($elements["#paragraphsTable"]["#fields"]) && count($elements["#paragraphsTable"]["#fields"]) > 2) {
      $elements['#attached']['library'][] = 'paragraphs_table/tableMobile';
    }
    $elements['#attached']['library'][] = 'paragraphs_table/shortcut';
    return $elements;
  }

  /**
   * {@inheritDoc}
   */
  private function setParentId(&$element, $delta = 0) {
    foreach (Element::children($element) as $field) {
      if (!empty($element[$field]["widget"][0]["value"]["#default_value"])) {
        $element[$field]["widget"][0]["value"]["#default_value"] = '';
      }
      if (!empty($element[$field]['#parents'])) {
        foreach ($element[$field]["#parents"] as &$parent) {
          if (!$parent) {
            $parent = $delta;
            break;
          }
        }
        $this->setParentId($element[$field], $delta);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getFieldsParagraphs() {
    $targetType = $this->getFieldSetting('target_type');
    $targetBundle = array_key_first($this->getAllowedTypes($this->fieldDefinition) ?? []);
    if (empty($targetBundle)) {
      return [];
    }
    /** @var \Drupal\paragraphs\ParagraphInterface $paragraphs_entity */
    $paragraphs_entity = $this->entityTypeManager->getStorage($targetType)->create(['type' => $targetBundle]);
    $field_definitions = $paragraphs_entity->getFieldDefinitions();
    $options = [];
    foreach ($field_definitions as $field_name => $field) {
      if ($field instanceof FieldConfig) {
        if ($field->getType() == 'entity_reference') {
          $options[$field_name] = $field->getLabel();
        }
      }
    }
    return $options;
  }

  /**
   * {@inheritDoc}
   */
  public static function addMoreSubmit(array $form, FormStateInterface $form_state) {
    $submit = ParagraphsWidget::getSubmitElementInfo($form, $form_state);
    $element = NestedArray::getValue($form, [$submit['field_name'], 'widget']);
    $field_name = $element['#field_name'] ?? '';
    if (!empty($form[$field_name]["widget"]["#paragraphsTable"]["#paste_clipboard"]) ||
      !empty($form[$field_name]["widget"]["#paragraphsTable"]["#field_reference"])
    ) {
      $widget_state = static::getWidgetState($element['#field_parents'], $field_name, $form_state);
      // Extract the values from $form_state->getValues().
      $path = array_merge($form['#parents'], [$field_name]);
      $key_exists = NULL;
      $valueInputs = NestedArray::getValue($form_state->getUserInput(), $path, $key_exists);
      if (isset($valueInputs['add_more'])) {
        unset($valueInputs['add_more']);
      }
      if (isset($valueInputs['header_actions'])) {
        unset($valueInputs['header_actions']);
      }
      if ($widget_state["items_count"] < count($valueInputs) && count($valueInputs) > 3) {
        for ($i = $widget_state['items_count']; $i <= count($valueInputs) - 1; $i++) {
          parent::addMoreSubmit($form, $form_state);
        }
        $widget_state['items_count'] = count($valueInputs);
      }
    }
    parent::addMoreSubmit($form, $form_state);
  }

}
