<?php

namespace Drupal\wbx_commerce\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_cart\Plugin\views\field\EditQuantity;

/**
 * Defines a form element for removing the order item via ajax.
 *
 * @ViewsField("wbx_commerce_order_item_edit_quantity")
 */
class AjaxEditQuantity extends EditQuantity {

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function viewsForm(array &$form, FormStateInterface $form_state) {
    parent::viewsForm($form, $form_state);
    $wrapper_id = $this->view->storage->id() . '-cart-ajax-wrapper';
    $ajax = [
      'wrapper' => $wrapper_id,
    ];
    if ($this->view->storage->id() === 'commerce_cart_block') {
      $ajax['callback'] = [__CLASS__, 'refreshCartBlock'];
    }
    $form['#attached']['library'][] = 'wbx_commerce/quantity';
    foreach ($this->view->result as $row_index => $row) {
      $form[$this->options['id']][$row_index]['#prefix'] = '<div class="plus-minus-wrapper"><div class="minus icon-minus toggle-minus"></div>';
      $form[$this->options['id']][$row_index]['#suffix'] = '<div class="plus icon-plus toggle-plus"></div></div>';
      $element = $form[$this->options['id']][$row_index];
      $form[$this->options['id']][$row_index] = [
        '#type' => 'container',
        'input' => $element,
        'submit' => [
          '#type' => 'submit',
          '#value' => $this->t('Update'),
          '#ajax' => $ajax,
          '#attributes' => [
            'style' => 'display:none'
          ],
          '#order_id' => $this->view->argument['order_id']->value[0],
        ],
      ];
    }
    $form['actions']['#attributes'] = [
      'style' => 'display:none'
    ];
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return mixed
   */
  public static function refreshCartBlock(array &$form, FormStateInterface $form_state) {
    // This code is necessary to prevent form from duplicate submitting.
    // Form is already submitted, so we can remove all form data from request.
    $request = \Drupal::service('request_stack')->getCurrentRequest();
    if (!empty($request->request->all())) {
      $new_request = $request->duplicate([], []);
      while (\Drupal::service('request_stack')->getCurrentRequest()) {
        \Drupal::service('request_stack')->pop();
      }
      \Drupal::service('request_stack')->push($new_request);
    }

    return \Drupal::service('dc_ajax_add_cart.refresh_page_elements_helper')
      ->updateCart($form)
      ->getResponse();
  }

  /**
   * Submit handler for the views form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function viewsFormSubmit(array &$form, FormStateInterface $form_state) {
    $quantities = $form_state->getValue($this->options['id'], []);
    foreach ($quantities as $row_index => $quantity) {
      /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
      $order_item = $this->getEntity($this->view->result[$row_index]);
      if ($order_item->getQuantity() != $quantity['input']) {
        $order_item->setQuantity($quantity['input']);
        $order = $order_item->getOrder();
        $this->cartManager->updateOrderItem($order, $order_item);
        // Tells commerce_cart_order_item_views_form_submit() to save the order.
        $form_state->set('quantity_updated', TRUE);
      }
    }
  }

}