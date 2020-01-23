<?php

namespace Drupal\wbx_commerce\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_shipping\Plugin\Commerce\CheckoutPane\ShippingInformation;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Render\Element;
use Drupal\profile\Entity\Profile;

/**
 * Provides the shipping information pane.
 *
 * Collects the shipping profile, then the information for each shipment.
 * Assumes that all shipments share the same shipping profile.
 *
 * @CommerceCheckoutPane(
 *   id = "wbx_shipping_information",
 *   label = @Translation("Shipping information"),
 *   wrapper_element = "container",
 * )
 */
class WBXShippingInformation extends ShippingInformation {

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $pane_form = parent::buildPaneForm($pane_form, $form_state, $complete_form);
    $complete_form['#attached']['library'][] = 'wbx_commerce/shipping';
    $complete_form['#prefix'] = '<div id="wbx-shipping-wrapper">';
    $complete_form['#suffix'] = '</div>';
    $pane_form['recalculate_shipping']['#type'] = 'submit';
    $pane_form['recalculate_shipping']['#ajax']['wrapper'] = 'wbx-shipping-wrapper';
    unset($pane_form['recalculate_shipping']['#ajax']['callback']);
    $pane_form['recalculate_shipping']['#attributes'] = [
      'style' => 'display:none',
    ];
    $pane_form['total'] = [
      '#type' => 'container',
      '#weight' => 100,
      'title' => [
        '#type' => 'markup',
        '#markup' => '<strong>Итого с учётом доставки:&nbsp;</strong>',
      ],
      'total' => $this->order
        ->get('total_price')
        ->view([
          'label' => 'hidden',
          'type' => 'commerce_order_total_summary',
        ])
    ];
    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $shipment_indexes = Element::children($pane_form['shipments']);
    $triggering_element = $form_state->getTriggeringElement();
    $recalculate = !empty($triggering_element['#recalculate']);
    $button_type = isset($triggering_element['#button_type']) ? $triggering_element['#button_type'] : '';
    if (!$recalculate && $button_type === 'primary' && empty($shipment_indexes)) {
      // The checkout step was submitted without shipping being calculated.
      // Force the recalculation now and reload the page.
      $recalculate = TRUE;
      drupal_set_message('Please select a shipping method.', 'error');
      $form_state->setRebuild(TRUE);
    }

    if ($recalculate) {
      $form_state->set('recalculate_shipping', TRUE);
      // The profile in form state needs to reflect the submitted values, since
      // it will be passed to the packers when the form is rebuilt.
      if (isset($pane_form['shipping_profile']['#profile'])) {
        $profile = $pane_form['shipping_profile']['#profile'];
      }
      else {
        $profile = Profile::create(['type' => 'customer']);
      }
      $form_state->set('shipping_profile', $profile);
    }

    foreach ($shipment_indexes as $index) {
      $shipment = $pane_form['shipments'][$index]['#shipment'];
      $form_display = EntityFormDisplay::collectRenderDisplay($shipment, 'default');
      $form_display->removeComponent('shipping_profile');
      $form_display->removeComponent('title');
      $form_display->extractFormValues($shipment, $pane_form['shipments'][$index], $form_state);
      $form_display->validateFormValues($shipment, $pane_form['shipments'][$index], $form_state);
    }
    $trigger = $form_state->getTriggeringElement();
    if (isset($trigger['#ajax'])) {
      $form_state->clearErrors();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $form_state->setRebuild();
    parent::submitPaneForm($pane_form, $form_state, $complete_form);
  }

}
