<?php

namespace Drupal\wbx_commerce\Plugin\Commerce\CheckoutFlow;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowWithPanesBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\commerce_checkout\CheckoutPaneManager;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_order\Entity\Order;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_order\OrderRefreshInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * @CommerceCheckoutFlow(
 *  id = "one_step_checkout_flow",
 *  label = @Translation("One step checkout flow."),
 * )
 */
class OneStepCheckoutFlow extends CheckoutFlowWithPanesBase {

  /**
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * The order refresh.
   *
   * @var \Drupal\commerce_order\OrderRefreshInterface
   */
  protected $orderRefresh;

  /**
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * OneStepCheckoutFlow constructor.
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   * @param \Drupal\commerce_checkout\CheckoutPaneManager $checkout_pane_manager
   * @param \Drupal\commerce_cart\CartProviderInterface $cart_provider
   * @param \Drupal\commerce_order\OrderRefreshInterface $order_refresh
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    EventDispatcherInterface $event_dispatcher,
    RouteMatchInterface $route_match,
    CheckoutPaneManager $checkout_pane_manager,
    CartProviderInterface $cart_provider,
    OrderRefreshInterface $order_refresh,
    AccountProxyInterface $current_user
    ) {
      parent::__construct(
        $configuration,
        $plugin_id,
        $plugin_definition,
        $entity_type_manager,
        $event_dispatcher,
        $route_match,
        $checkout_pane_manager
      );
      $this->cartProvider = $cart_provider;
      $this->orderRefresh = $order_refresh;
      if (empty($this->order)) {
        $carts = $this->cartProvider->getCarts();
        $carts = array_filter($carts, function ($cart) {
          /** @var \Drupal\commerce_order\Entity\OrderInterface $cart */
          return $cart->hasItems();
        });
        $ids = array_keys($carts);
        if (!empty($ids)) {
          $id = array_pop($ids);
          /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
          $this->order = Order::load($id);
        }
      }
      $this->currentUser = $current_user;
      $this->routeMatch = $route_match;
    }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pane_id, $pane_definition) {
    return new static(
      $configuration,
      $pane_id,
      $pane_definition,
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher'),
      $container->get('current_route_match'),
      $container->get('plugin.manager.commerce_checkout_pane'),
      $container->get('commerce_cart.cart_provider'),
      $container->get('commerce_order.order_refresh'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getSteps() {
    // Note that previous_label and next_label are not the labels
    // shown on the step itself. Instead, they are the labels shown
    // when going back to the step, or proceeding to the step.
    $steps =  [
        'order_information' => [
          'label' => $this->t('Checkout'),
          'has_sidebar' => FALSE,
        ],
      ] + parent::getSteps();
    $steps['payment']['hidden'] = FALSE;
    $steps['complete']['label'] = 'Спасибо за заказ!';
    return $steps;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!isset($form['#step_id'])) {
      $form['#step_id'] = $this->routeMatch->getParameter('step');
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!isset($form['#step_id'])) {
      $form['#step_id'] = $this->routeMatch->getParameter('step');
    }
    foreach ($this->getVisiblePanes($form['#step_id']) as $pane_id => $pane) {
      $pane->submitPaneForm($form[$pane_id], $form_state, $form);
    }
    if ($this->hasSidebar($form['#step_id'])) {
      foreach ($this->getVisiblePanes('_sidebar') as $pane_id => $pane) {
        $pane->submitPaneForm($form['sidebar'][$pane_id], $form_state, $form);
      }
    }
    $trigger = $form_state->getTriggeringElement();
    if (!isset($trigger['#ajax']) && ($next_step_id = $this->getNextStepId($form['#step_id']))) {
      $this->order->set('checkout_step', $next_step_id);
      $form_state->setRedirect('commerce_checkout.form', [
        'commerce_order' => $this->order->id(),
        'step' => $next_step_id,
      ]);
      $form_state->setRebuild(FALSE);

      if ($next_step_id === 'complete') {
        $this->orderRefresh->refresh($this->order);
        // Place the order.
        $transition = $this->order->getState()->getWorkflow()->getTransition('place');
        $this->order->getState()->applyTransition($transition);
      }
    } elseif (isset($trigger['#ajax'])) {
      $this->orderRefresh->refresh($this->order);
    }

    $this->order->save();
  }

  public function removeParents(&$input) {
    if (is_array($input)) {
      if (isset($input['#parents'])) {
        unset($input['#parents']);
        $input['#tree'] = TRUE;
      }
      foreach ($input as &$field) {
        $this->removeParents($field);
      }
    }

  }

}
