<?php

namespace Drupal\Tests\commerce_shipping\Functional;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\ShipmentItem;
use Drupal\Core\Url;
use Drupal\physical\Weight;
use Drupal\Tests\commerce_order\Functional\OrderBrowserTestBase;

/**
 * Tests the shipment summary UI.
 *
 * @group commerce_shipping
 */
class OrderShipmentSummaryTest extends OrderBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_shipping',
  ];

  /**
   * The default profile's address.
   *
   * @var array
   */
  protected $defaultAddress = [
    'country_code' => 'US',
    'administrative_area' => 'SC',
    'locality' => 'Greenville',
    'postal_code' => '29616',
    'address_line1' => '9 Drupal Ave',
    'given_name' => 'Bryan',
    'family_name' => 'Centarro',
  ];

  /**
   * The tested order.
   */
  protected OrderInterface $order;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $order_type = OrderType::load('default');
    $order_type->setThirdPartySetting('commerce_shipping', 'shipment_type', 'default');
    $order_type->save();

    $order_item = $this->createEntity('commerce_order_item', [
      'type' => 'default',
      'unit_price' => [
        'number' => '999',
        'currency_code' => 'USD',
      ],
    ]);

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $this->createEntity('commerce_order', [
      'type' => 'default',
      'mail' => $this->loggedInUser->getEmail(),
      'order_items' => [$order_item],
      'uid' => $this->loggedInUser,
      'store_id' => $this->store,
      'state' => 'completed',
    ]);
    $this->order = $order;

    $profile = $this->createEntity('profile', [
      'type' => 'customer',
      'uid' => 0,
      'address' => $this->defaultAddress,
    ]);

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->createEntity('commerce_shipment', [
      'type' => 'default',
      'title' => 'Test shipment',
      'order_id' => $this->order->id(),
      'amount' => new Price('10', 'USD'),
      'items' => [
        new ShipmentItem([
          'order_item_id' => $order_item->id(),
          'title' => 'Test shipment item label',
          'quantity' => 1,
          'weight' => new Weight(0, 'g'),
          'declared_value' => new Price('1', 'USD'),
        ]),
      ],
      'shipping_profile' => $profile,
    ]);

    $this->order->set('shipments', [$shipment]);
    $this->order->save();
  }

  /**
   * Tests shipping information on the order view page for customer.
   */
  public function testShippingSummaryOrderViewPage(): void {
    $this->drupalGet(Url::fromRoute('entity.commerce_order.user_view', [
      'user' => $this->order->getCustomerId(),
      'commerce_order' => $this->order->id(),
    ]));

    // Confirm that profile is rendered in the shipping information section.
    $address_text = $this->getSession()
      ->getPage()
      ->find('css', '.customer-information__shipping p.address')
      ->getText();
    $country_repository = $this->container->get('address.country_repository');
    $country_list = $country_repository->getList();
    foreach ($this->defaultAddress as $property => $value) {
      if ($property === 'country_code') {
        $value = $country_list[$value];
      }
      $this->assertStringContainsString($value, $address_text);
    }
  }

}
