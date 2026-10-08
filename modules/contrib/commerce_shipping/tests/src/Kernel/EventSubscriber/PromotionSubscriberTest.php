<?php

namespace Drupal\Tests\commerce_shipping\Kernel\EventSubscriber;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Tests\commerce_shipping\Kernel\ShippingKernelTestBase;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_promotion\Entity\Promotion;
use Drupal\commerce_shipping\Entity\Shipment;
use Drupal\commerce_shipping\Entity\ShippingMethod;
use Drupal\commerce_shipping\Entity\ShippingMethodInterface;
use Drupal\commerce_shipping\Event\ShippingRatesEvent;
use Drupal\commerce_shipping\ShipmentItem;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\commerce_shipping\ShippingService;
use Drupal\physical\Weight;

/**
 * Tests the promotion subscriber.
 *
 * @coversDefaultClass \Drupal\commerce_shipping\EventSubscriber\PromotionSubscriber
 *
 * @group commerce_shipping
 */
class PromotionSubscriberTest extends ShippingKernelTestBase implements ServiceModifierInterface {

  /**
   * A sample user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * A sample order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * A sample shipment.
   *
   * @var \Drupal\commerce_shipping\Entity\ShipmentInterface
   */
  protected $shipment;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_promotion',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_promotion');
    $this->installSchema('commerce_promotion', ['commerce_promotion_usage']);

    $user = $this->createUser();
    $this->user = $this->reloadEntity($user);

    $variation = ProductVariation::create([
      'type' => 'default',
      'sku' => 'test-product-01',
      'title' => 'Hat',
      'price' => new Price('20.00', 'USD'),
      'weight' => new Weight('0', 'g'),
    ]);
    $variation->save();

    $order_item = OrderItem::create([
      'type' => 'default',
      'quantity' => 1,
      'title' => $variation->getOrderItemTitle(),
      'purchased_entity' => $variation,
      'unit_price' => new Price('20.00', 'USD'),
    ]);
    $order_item->save();

    $this->order = Order::create([
      'type' => 'default',
      'state' => 'draft',
      'order_number' => '2026/1',
      'mail' => $this->user->getEmail(),
      'uid' => $this->user->id(),
      'store_id' => $this->store->id(),
      'order_items' => [$order_item],
    ]);
    // Simulate a previously selected (expensive) shipping rate: the order
    // carries an order-level "shipping" adjustment, which inflates the order
    // total to 70.00 USD (20.00 goods + 50.00 shipping).
    $this->order->addAdjustment(new Adjustment([
      'type' => 'shipping',
      'label' => 'Shipping',
      'amount' => new Price('50.00', 'USD'),
      'source_id' => '1',
    ]));
    $this->order->setRefreshState(Order::REFRESH_SKIP);
    $this->order->save();

    $this->shipment = Shipment::create([
      'type' => 'default',
      'title' => 'Shipment',
      'items' => [
        new ShipmentItem([
          'order_item_id' => $order_item->id(),
          'title' => 'Hat',
          'quantity' => 1,
          'weight' => new Weight('10', 'kg'),
          'declared_value' => new Price('20.00', 'USD'),
        ]),
      ],
      'order_id' => $this->order->id(),
      'amount' => new Price('10.00', 'USD'),
    ]);
    $this->shipment->save();
  }

  /**
   * Test that things does not crash when we have no shipping promotions.
   */
  public function testSubscriberNoShippingPromotions() {
    /** @var \Drupal\commerce_shipping\EventSubscriber\PromotionSubscriber $subscriber */
    $subscriber = $this->container->get('commerce_shipping.promotion_subscriber');
    $rates = [
      new ShippingRate([
        'shipping_method_id' => 'test',
        'service' => new ShippingService('test', 'Test'),
        'amount' => Price::fromArray([
          'currency_code' => 'USD',
          'number' => 100,
        ]),
      ]),
    ];
    $method = $this->createMock(ShippingMethodInterface::class);
    $event = new ShippingRatesEvent($rates, $method, $this->shipment);

    // Create a promotion as well.
    $promotion = Promotion::create([
      'name' => 'Promotion 1',
      'order_types' => ['default'],
      'stores' => [$this->store->id()],
      'status' => TRUE,
      'offer' => [
        'target_plugin_id' => 'order_item_percentage_off',
        'target_plugin_configuration' => [
          'percentage' => '0.5',
        ],
      ],
    ]);
    $promotion->save();
    // Now run the subscriber.
    $subscriber->onCalculate($event);
    $this->assertCount(1, $event->getRates());
  }

  /**
   * Tests that the stale shipping cost does not affect the rate discount.
   *
   * Regression test for #3330729: when the order already carried an
   * order-level "shipping" adjustment from a previously selected rate, that
   * stale cost leaked into the cloned order used to preview rates. Promotion
   * conditions evaluating the order total were therefore checked against the
   * wrong total, so rates in the rate widget were discounted (or not)
   * incorrectly.
   *
   * @covers ::onCalculate
   */
  public function testStaleShippingAdjustmentIgnored() {
    // Sanity check on the starting total.
    $this->assertEquals(new Price('70.00', 'USD'), $this->order->getTotalPrice());

    $shipping_method = ShippingMethod::create([
      'stores' => $this->store->id(),
      'name' => 'Standard shipping',
      'plugin' => [
        'target_plugin_id' => 'flat_rate',
        'target_plugin_configuration' => [
          'rate_label' => 'Standard shipping',
          'rate_amount' => [
            'number' => '10.00',
            'currency_code' => 'USD',
          ],
        ],
      ],
      'status' => TRUE,
    ]);
    $shipping_method->save();

    // A display-inclusive shipping promotion that only applies while the
    // order total (goods, without shipping) is at most 30.00 USD. With the
    // stale shipping adjustment included the cloned order total is 70.00 USD
    // and the promotion wrongly fails to apply; with it removed the total is
    // 20.00 USD and the promotion correctly applies.
    $promotion = Promotion::create([
      'name' => 'Free-ish shipping under 30',
      'order_types' => ['default'],
      'stores' => [$this->store->id()],
      'status' => TRUE,
      'offer' => [
        'target_plugin_id' => 'shipment_percentage_off',
        'target_plugin_configuration' => [
          'display_inclusive' => TRUE,
          'percentage' => '0.5',
        ],
      ],
      'conditions' => [
        [
          'target_plugin_id' => 'order_total_price',
          'target_plugin_configuration' => [
            'operator' => '<=',
            'amount' => [
              'number' => '30.00',
              'currency_code' => 'USD',
            ],
            'type' => 'total',
          ],
        ],
      ],
    ]);
    $promotion->save();

    $rate = new ShippingRate([
      'shipping_method_id' => $shipping_method->id(),
      'service' => new ShippingService('default', 'Standard shipping'),
      'amount' => new Price('10.00', 'USD'),
    ]);
    $event = new ShippingRatesEvent([$rate], $shipping_method, $this->shipment);

    /** @var \Drupal\commerce_shipping\EventSubscriber\PromotionSubscriber $subscriber */
    $subscriber = $this->container->get('commerce_shipping.promotion_subscriber');
    $subscriber->onCalculate($event);

    $rates = $event->getRates();
    $this->assertCount(1, $rates);
    $adjusted_rate = reset($rates);
    // The pre-promotion amount is always the undiscounted rate.
    $this->assertEquals(new Price('10.00', 'USD'), $adjusted_rate->getPrePromotionAmount());
    // The displayed amount must be discounted to 5.00 USD. Before the fix the
    // promotion was skipped (stale total 70.00 > 30.00) and the amount stayed
    // at 10.00 USD.
    $this->assertEquals(new Price('5.00', 'USD'), $adjusted_rate->getAmount());
  }

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Only filter out shipment-based promotion offers for the test that
    // exercises the "no shipping promotions" scenario; other tests in this
    // class (e.g. testStaleShippingAdjustmentIgnored) rely on a real
    // shipment-based offer being available.
    if ($this->name() === 'testSubscriberNoShippingPromotions') {
      $container->getDefinition('plugin.manager.commerce_promotion_offer')->setClass(TestNoShippingOfferManager::class);
    }
  }

}
