<?php

namespace Drupal\wbx_commerce\Plugin\views\field;

use Drupal\commerce_cart\Plugin\views\field\RemoveButton;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form element for removing the order item via ajax.
 *
 * @ViewsField("wbx_commerce_views_item_remove_button")
 */
class AjaxRemoveButton extends RemoveButton {

  /**
   * {@inheritdoc}
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

    // @TODO Remove this once https://www.drupal.org/node/2897120 gets into
    // core.
    $form['#attached']['library'][] = 'core/jquery.form';
    $form['#attached']['library'][] = 'core/drupal.ajax';

    $form['#prefix'] = "<div id='{$wrapper_id}'>";
    $form['#suffix'] = '</div>';

    foreach ($this->view->result as $row_index => $row) {
      $form[$this->options['id']][$row_index] = [
        '#type' => 'submit',
        '#value' => t('Remove'),
        '#name' => 'delete-order-item-' . $row_index,
        '#remove_order_item' => TRUE,
        '#row_index' => $row_index,
        '#attributes' => [
          'class' => [
            'delete-order-item',
            'use-ajax-submit',
          ],
        ],
        '#ajax' => $ajax,
      ];
    }
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

}
