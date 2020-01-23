<?php

namespace Drupal\wbx_commerce\Plugin\Commerce\Condition;

use Drupal\commerce_order\Plugin\Commerce\Condition\OrderTotalPrice;
use Drupal\Core\Entity\EntityInterface;
use Drupal\commerce_price\Price;

/**
 * Provides the total price condition for orders.
 *
 * @CommerceCondition(
 *   id = "order_subtotal_price",
 *   label = @Translation("Subtotal price"),
 *   display_label = @Translation("Current order total without shipping"),
 *   category = @Translation("Order", context = "Commerce"),
 *   entity_type = "commerce_order",
 * )
 */
class OrderSubTotalPrice extends OrderTotalPrice {

  /**
   * {@inheritdoc}
   */
  public function evaluate(EntityInterface $entity) {
    $this->assertEntity($entity);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $entity;
    $total_price = $order->getTotalPrice();
    if (!$total_price) {
      return FALSE;
    }
    $condition_price = Price::fromArray($this->configuration['amount']);
    if ($total_price->getCurrencyCode() != $condition_price->getCurrencyCode()) {
      return FALSE;
    }

    $adjustments = $order->collectAdjustments();

    // We need to get all adjustments--shipping, coupons, etc--and bring the subtotal back to normal
    $zero_price = new Price(0, $this->configuration['amount']['currency_code']);
    foreach ($adjustments as $adjustment) {
      if ($adjustment->getAmount()->lessThan($zero_price)) {
        // We only want to add back shipping & tax costs
        continue;
      }
      $total_price = $total_price->subtract($adjustment->getAmount());
    }

    switch ($this->configuration['operator']) {
      case '>=':
        return $total_price->greaterThanOrEqual($condition_price);

      case '>':
        return $total_price->greaterThan($condition_price);

      case '<=':
        return $total_price->lessThanOrEqual($condition_price);

      case '<':
        return $total_price->lessThan($condition_price);

      case '==':
        return $total_price->equals($condition_price);

      default:
        throw new \InvalidArgumentException("Invalid operator {$this->configuration['operator']}");
    }
  }

}
