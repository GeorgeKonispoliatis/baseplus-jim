<?php

namespace Drupal\Tests\commerce_shipping\Functional;

use Drupal\commerce_shipping\Entity\ShippingMethod;
use Drupal\Tests\commerce\Functional\CommerceBrowserTestBase;
use Drupal\Tests\field_ui\Traits\FieldUiTestTrait;

/**
 * Tests the shipping method UI.
 *
 * @group commerce_shipping
 */
class ShippingMethodTest extends CommerceBrowserTestBase {

  use FieldUiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field_ui',
    'commerce_shipping',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer commerce_shipping_method',
      'administer commerce_shipping_method fields',
      'administer commerce_shipping_method form display',
    ], parent::getAdministratorPermissions());
  }

  /**
   * Tests that a field added via the Field UI shows up on the add form.
   */
  public function testConfiguredFieldUsage() {
    // Add new field to the shipping method via UI.
    $this->fieldUIAddNewField('admin/commerce/shipping-methods', 'test_note', 'Test note', 'string');

    // Create a new shipping method.
    $this->drupalGet('admin/commerce/shipping-methods/add');
    $this->assertSession()->fieldExists('field_test_note[0][value]');

    // Confirm the position of configured field.
    $page_html = $this->getSession()->getPage()->getContent();
    $plugin_position = strpos($page_html, 'name="plugin[0][target_plugin_id]"');
    $field_position = strpos($page_html, 'name="field_test_note[0][value]"');
    $conditions_position = strpos($page_html, 'name="conditions');
    $this->assertNotFalse($plugin_position, 'The plugin field is present on the form.');
    $this->assertNotFalse($field_position, 'The configured field is present on the form.');
    $this->assertNotFalse($conditions_position, 'The conditions field is present on the form.');
    $this->assertGreaterThan($plugin_position, $field_position, 'The configured field appears after the plugin field.');
    $this->assertLessThan($conditions_position, $field_position, 'The configured field appears before the conditions field.');

    // Save shipping method with custom fields.
    $name = $this->randomMachineName(8);
    $edit = [
      'name[0][value]' => $name,
      'plugin[0][target_plugin_configuration][flat_rate][rate_label]' => 'Test label',
      'plugin[0][target_plugin_configuration][flat_rate][rate_amount][number]' => '10.00',
      'field_test_note[0][value]' => 'Test note',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->addressEquals('admin/commerce/shipping-methods');
    $this->assertSession()->pageTextContains("Saved the $name shipping method.");

    // Check custom field on the edit form.
    $shipping_method = ShippingMethod::load(1);
    $this->drupalGet($shipping_method->toUrl('edit-form'));
    $this->assertSession()->fieldValueEquals('field_test_note[0][value]', 'Test note');
  }

}
