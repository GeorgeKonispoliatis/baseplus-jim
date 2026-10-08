<?php

namespace Drupal\commerce_product_outofstock_extra\EventSubscriber;

use Drupal\commerce_product\Event\ProductEvents;
use Drupal\commerce_product\Event\ProductVariationAjaxChangeEvent;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Ajax\InvokeCommand;

/**
 * commerce_product_outofstock_extra event subscriber.
 */
class CommerceProductOutofstockExtraSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The events to subscribe to and the methods they should execute.
   */
  public static function getSubscribedEvents() {
    return [
      ProductEvents::PRODUCT_VARIATION_AJAX_CHANGE => 'AjaxResponse',
    ];
  }

  /**
   * React to a new product variation being selected on the Add to Cart form.
   *
   * @param \Drupal\commerce_product\Event\ProductVariationAjaxChangeEvent $event
   *   The product variation Ajax change event object.
   */
  public function AjaxResponse(ProductVariationAjaxChangeEvent $event) {
    // Pull the value of out of stock field out of the event.
    $out_of_stock_value = $event->getproductVariation()->get('field_mt_vrt_gnr_out_of_stock')->value;
    if ($out_of_stock_value) {
      // Add our Ajax command to the response array.
      $event->getResponse()->addCommand(new InvokeCommand('.product-content-second', 'addClass', ['mt-out-of-stock']));
    } else {
      $event->getResponse()->addCommand(new InvokeCommand('.product-content-second', 'removeClass', ['mt-out-of-stock']));
    }
  }

}
