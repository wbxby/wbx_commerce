<?php

namespace Drupal\wbx_commerce\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_payment\Plugin\Commerce\CheckoutPane\PaymentProcess;

/**
 * Provides the payment process pane.
 *
 * @CommerceCheckoutPane(
 *   id = "wbx_payment_process",
 *   label = @Translation("WBX Payment process"),
 *   default_step = "payment",
 *   wrapper_element = "container",
 * )
 */
class WBXPaymentProcess extends PaymentProcess {
  /**
   * {@inheritdoc}
   */
  public function isVisible() {
    if ($this->order->getTotalPrice()->isZero()) {
      // Hide the pane for free orders, since they don't need a payment.
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Gets the step ID that the customer should be sent to on error.
   *
   * @return string
   *   The error step ID.
   */
  protected function getErrorStepId() {
    // Default to the step that contains the PaymentInformation pane.
    $step_id = $this->checkoutFlow->getPane('wbx_payment_information')->getStepId();
    if ($step_id === '_disabled') {
      // Can't redirect to the _disabled step. This could mean that isVisible()
      // was overridden to allow PaymentProcess to be used without a
      // payment_information pane, but this method was not modified.
      throw new \RuntimeException('Cannot get the step ID for the payment_information pane. The pane is disabled.');
    }

    return $step_id;
  }

}
