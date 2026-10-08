<?php

namespace Drupal\Tests\commerce_shipping\Kernel\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Entity\CheckoutFlow;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Exception\OrderVersionMismatchException;
use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\Shipment;
use Drupal\commerce_shipping\Entity\ShippingMethod;
use Drupal\Core\Form\FormState;
use Drupal\profile\Entity\Profile;
use Drupal\Tests\commerce_shipping\Kernel\ShippingKernelTestBase;

/**
 * Tests the ShippingInformation checkout pane.
 *
 * @group commerce_shipping
 */
class ShippingInformationTest extends ShippingKernelTestBase {

  /**
   * The checkout pane manager.
   *
   * @var \Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneManagerInterface
   */
  protected $checkoutPaneManager;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_checkout',
    'commerce_shipping',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('commerce_checkout');
    $this->checkoutPaneManager = $this->container->get('plugin.manager.commerce_checkout_pane');
  }

  /**
   * Tests concurrent AJAX saves on the shipping information pane.
   */
  public function testConcurrentAjaxOrderSaveOnBuildingPaneForm() {
    // Create an order and instantiate the Checkout flow:
    $user = $this->createUser();

    $order_item = OrderItem::create([
      'type' => 'default',
      'quantity' => 1,
      'unit_price' => new Price('12.00', 'USD'),
    ]);
    $order_item->save();

    // Set up a shipping profile with a valid address so the pane runs with
    // the default `require_shipping_profile => TRUE` configuration. Without a
    // valid address, ShippingInformation::canCalculateRates() short-circuits
    // and the bug path under test would never run.
    $shipping_profile = Profile::create([
      'type' => 'customer',
      'uid' => $user->id(),
      'address' => [
        'country_code' => 'US',
        'administrative_area' => 'CA',
        'locality' => 'Mountain View',
        'postal_code' => '94043',
        'address_line1' => '1098 Alta Ave',
        'given_name' => 'John',
        'family_name' => 'Smith',
      ],
    ]);
    $shipping_profile->save();

    // Register a flat-rate shipping method so the pane has at least one rate
    // available when it (re)packs shipments.
    $shipping_method = ShippingMethod::create([
      'stores' => $this->store->id(),
      'name' => 'Example',
      'plugin' => [
        'target_plugin_id' => 'flat_rate',
        'target_plugin_configuration' => [
          'rate_label' => 'Flat rate',
          'rate_amount' => new Price('1', 'USD'),
        ],
      ],
      'status' => TRUE,
      'weight' => 1,
    ]);
    $shipping_method->save();

    /** @var \Drupal\commerce_order\Entity\Order $order */
    $order = Order::create([
      'type' => 'default',
      'mail' => $user->getEmail(),
      'uid' => $user->id(),
      'store_id' => $this->store->id(),
      'order_items' => [$order_item],
    ]);
    $order->save();

    // Attach a shipment referencing the saved profile so that
    // ShippingInformation::getShippingProfile() returns this profile (with its
    // valid address) on every pane build, rather than creating a fresh empty
    // one that would fail address validation.
    $shipment = Shipment::create([
      'type' => 'default',
      'order_id' => $order->id(),
      'title' => 'Shipment',
      'shipping_method' => $shipping_method,
      'shipping_profile' => $shipping_profile,
      'amount' => new Price('1', 'USD'),
      'state' => 'draft',
    ]);
    $shipment->save();
    $order->set('shipments', [$shipment]);
    $order->save();
    $initial_version = $order->getVersion();

    // Load the default checkout flow.
    $checkout_flow = CheckoutFlow::load('default');

    // Simulate Request A and Request B loading the order at the beginning of
    // the request. Both have the exact same version (e.g., Version 1).
    $order_request_a = $this->reloadEntity($order);
    $order_request_b = $this->reloadEntity($order);
    $this->assertSame($initial_version, $order_request_a->getVersion());
    $this->assertSame($initial_version, $order_request_b->getVersion());

    // Instantiate the pane for Request A:
    /** @var \Drupal\commerce_shipping\Plugin\Commerce\CheckoutPane\ShippingInformation $pane_a */
    $pane_a = $this->checkoutPaneManager->createInstance('shipping_information', [
      '_entity_id' => 'default',
    ], $checkout_flow->getPlugin());
    $pane_a->setOrder($order_request_a);

    // Simulate Request A building the pane form (which triggers a save via
    // recalculation)
    $form_state_a = new FormState();
    $form_state_a->setUserInput([]);
    $form_state_a->set('recalculate_shipping', TRUE);
    $pane_form_a = [
      '#parents' => ['shipping_information'],
    ];
    $pane_a->buildPaneForm($pane_form_a, $form_state_a, $pane_form_a);

    // At this point, Request A saved the order. The DB is now at Version
    // $initial_version + 1. However, $order_request_b is still holding the
    // $initial_version:
    $this->assertGreaterThan(
      $initial_version,
      $this->reloadEntity($order)->getVersion(),
      'Request A should have bumped the order version when building the pane.'
    );

    // Instantiate the pane for Request B:
    /** @var \Drupal\commerce_shipping\Plugin\Commerce\CheckoutPane\ShippingInformation $pane_b */
    $pane_b = $this->checkoutPaneManager->createInstance('shipping_information', [
      '_entity_id' => 'default',
    ], $checkout_flow->getPlugin());
    $pane_b->setOrder($order_request_b);

    // Simulate Request B building the pane form:
    $form_state_b = new FormState();
    $form_state_b->setUserInput([]);
    $form_state_b->set('recalculate_shipping', TRUE);
    $pane_form_b = [
      '#parents' => ['shipping_information'],
    ];

    // Try to build the pane form:
    try {
      $pane_b->buildPaneForm($pane_form_b, $form_state_b, $pane_form_b);
    }
    catch (OrderVersionMismatchException $e) {
      $this->fail('Request B raised OrderVersionMismatchException despite the stale order being reloaded: ' . $e->getMessage());
    }

    // Assert that Request B successfully updated and saved the order without
    // crashing:
    $order_reloaded = $this->reloadEntity($order);
    $this->assertGreaterThan(
      $initial_version,
      $order_reloaded->getVersion(),
      'Request B should have successfully saved the order despite starting from a stale version.'
    );
  }

}
