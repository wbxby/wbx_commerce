<?php

namespace Drupal\wbx_commerce\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_payment\Plugin\Commerce\CheckoutPane\PaymentInformation;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsStoredPaymentMethodsInterface;

/**
 * Provides the payment information pane.
 *
 * Collects the payment profile, then the information for each shipment.
 * Assumes that all payments share the same payment profile.
 *
 * @CommerceCheckoutPane(
 *   id = "wbx_payment_information",
 *   label = @Translation("Payment method"),
 *   wrapper_element = "container",
 * )
 */
class WBXPaymentInformation extends PaymentInformation {

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $pane_form = parent::buildPaneForm($pane_form, $form_state, $complete_form);
    $pane_form['billing_information']['#access'] = FALSE;
    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    if ($this->order->isPaid() || $this->order->getTotalPrice()->isZero()) {
      $this->order->setBillingProfile($pane_form['billing_information']['#profile']);
      return;
    }

    $values_key = is_array($pane_form['#parents']) ? $pane_form['#parents'][0] : $pane_form['#parents'];
    $values = $form_state->getValue($values_key);
    if (empty($values)) {
      return;
    }
    /** @var \Drupal\commerce_payment\PaymentOption $selected_option */
    $selected_option = $pane_form['#payment_options'][$values['payment_method']];
    /** @var \Drupal\commerce_payment\PaymentGatewayStorageInterface $payment_gateway_storage */
    $payment_gateway_storage = $this->entityTypeManager->getStorage('commerce_payment_gateway');
    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
    $payment_gateway = $payment_gateway_storage->load($selected_option->getPaymentGatewayId());
    if (!$payment_gateway) {
      return;
    }

    if ($payment_gateway->getPlugin() instanceof SupportsStoredPaymentMethodsInterface) {
      if (!empty($selected_option->getPaymentMethodTypeId())) {
        // The payment method was just created.
        $payment_method = $values['add_payment_method'];
      }
      else {
        /** @var \Drupal\commerce_payment\PaymentMethodStorageInterface $payment_method_storage */
        $payment_method_storage = $this->entityTypeManager->getStorage('commerce_payment_method');
        $payment_method = $payment_method_storage->load($selected_option->getPaymentMethodId());
      }

      /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
      $this->order->set('payment_gateway', $payment_method->getPaymentGateway());
      $this->order->set('payment_method', $payment_method);
      $this->order->setBillingProfile($payment_method->getBillingProfile());
    }
    else {
      $this->order->set('payment_gateway', $payment_gateway);
      $this->order->set('payment_method', NULL);
      $inline_form = $pane_form['billing_information']['#inline_form'];
      /** @var \Drupal\profile\Entity\ProfileInterface $billing_profile */
      $billing_profile = $inline_form->getEntity();
      $this->order->setBillingProfile($billing_profile);
    }
  }

}
