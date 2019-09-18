<?php

namespace Drupal\wbx_commerce\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_cart\CartSessionInterface;
use Drupal\commerce_checkout\CheckoutOrderManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\SettingsCommand;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class CheckoutController.
 */
class CheckoutController extends ControllerBase {

  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * The checkout order manager.
   *
   * @var \Drupal\commerce_checkout\CheckoutOrderManagerInterface
   */
  protected $checkoutOrderManager;

  /**
   * The cart session.
   *
   * @var \Drupal\commerce_cart\CartSessionInterface
   */
  protected $cartSession;

  /**
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * CheckoutController constructor.
   *
   * @param \Drupal\commerce_cart\CartProviderInterface $cart_provider
   * @param \Drupal\commerce_checkout\CheckoutOrderManagerInterface $checkout_order_manager
   * @param \Drupal\commerce_cart\CartSessionInterface $cart_session
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   * @param \Drupal\Core\Render\Renderer $renderer
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   */
  public function __construct(
    CartProviderInterface $cart_provider,
    CheckoutOrderManagerInterface $checkout_order_manager,
    CartSessionInterface $cart_session,
    FormBuilderInterface $form_builder,
    Renderer $renderer,
    RequestStack $request_stack
  ) {
    $this->cartProvider = $cart_provider;
    $this->checkoutOrderManager = $checkout_order_manager;
    $this->cartSession = $cart_session;
    $this->formBuilder = $form_builder;
    $this->renderer = $renderer;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_cart.cart_provider'),
      $container->get('commerce_checkout.checkout_order_manager'),
      $container->get('commerce_cart.cart_session'),
      $container->get('form_builder'),
      $container->get('renderer'),
      $container->get('request_stack')
    );
  }

  /**
   * AJAX renderer
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   * @param $name
   */
  public function ajaxRender(AjaxResponse $response, $name) {
    if ($name === 'cart') {
      $elements = $this->cartPage();
      $wrapper = '#commerce_cart_form-cart-ajax-wrapper';
    } else {
      $elements = $this->formPage();
      $wrapper = '#wbx-shipping-wrapper';
    }
    $rendered = $this->renderer->renderRoot($elements);
    if (isset($elements['#attached']['drupalSettings'])) {
      $settings = $elements['#attached']['drupalSettings'];
      $response->addCommand(new SettingsCommand($settings, TRUE));
    }
    $response->addCommand(new ReplaceCommand($wrapper, $rendered));
  }

  /**
   * Render.
   *
   * @return array|AjaxResponse
   *   Return Hello string.
   */
  public function render() {
    $request = $this->requestStack->getCurrentRequest();
    $parameters = $request->request->all();
    if (isset($parameters['form_id']) &&
      strpos($parameters['form_id'], 'views_form_commerce_cart_block') !== FALSE) {
      return [];
    }
    if (isset($_REQUEST['_wrapper_format']) && $_REQUEST['_wrapper_format'] === 'drupal_ajax') {
      $response = new AjaxResponse();
      $names = ['cart', 'checkout'];
      if (isset($_REQUEST['form_id'])
        && strpos($_REQUEST['form_id'], 'commerce_checkout_flow') === 0) {
        $names = array_reverse($names);
      }
      foreach ($names as $name) {
        $this->ajaxRender($response, $name);
      }
      return $response;
    }
    return [
      'cart' => $this->cartPage(),
      'checkout' => $this->formPage(),
    ];
  }

  /**
   * Outputs a cart view for each non-empty cart belonging to the current user.
   *
   * @return array
   *   A render array.
   */
  public function cartPage() {
    $build = [];
    $cacheable_metadata = new CacheableMetadata();
    $cacheable_metadata->addCacheContexts(['user', 'session']);

    $carts = $this->cartProvider->getCarts();
    $carts = array_filter($carts, function ($cart) {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $cart */
      return $cart->hasItems();
    });
    if (!empty($carts)) {
      $cart_views = $this->getCartViews($carts);
      foreach ($carts as $cart_id => $cart) {
        $build[$cart_id] = [
          '#prefix' => '<div class="cart cart-form">',
          '#suffix' => '</div>',
          '#type' => 'view',
          '#name' => $cart_views[$cart_id],
          '#arguments' => [$cart_id],
          '#embed' => TRUE,
        ];
        $cacheable_metadata->addCacheableDependency($cart);
      }
    }
    else {
      $build['empty'] = [
        '#theme' => 'commerce_cart_empty_page',
      ];
    }
    $build['#cache'] = [
      'contexts' => $cacheable_metadata->getCacheContexts(),
      'tags' => $cacheable_metadata->getCacheTags(),
      'max-age' => $cacheable_metadata->getCacheMaxAge(),
    ];

    return $build;
  }

  /**
   * Gets the cart views for each cart.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface[] $carts
   *   The cart orders.
   *
   * @return array
   *   An array of view ids keyed by cart order ID.
   */
  protected function getCartViews(array $carts) {
    $order_type_ids = array_map(function ($cart) {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $cart */
      return $cart->bundle();
    }, $carts);
    $order_type_storage = $this->entityTypeManager()->getStorage('commerce_order_type');
    $order_types = $order_type_storage->loadMultiple(array_unique($order_type_ids));
    $cart_views = [];
    foreach ($order_type_ids as $cart_id => $order_type_id) {
      /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
      $order_type = $order_types[$order_type_id];
      $cart_views[$cart_id] = $order_type->getThirdPartySetting('commerce_cart', 'cart_form_view', 'commerce_cart_form');
    }

    return $cart_views;
  }

  /**
   * Builds and processes the form provided by the order's checkout flow.
   *
   * @return array
   *   The render form.
   */
  public function formPage() {
    $carts = $this->cartProvider->getCarts();
    $carts = array_filter($carts, function ($cart) {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $cart */
      return $cart->hasItems();
    });
    $ids = array_keys($carts);
    if (empty($ids)) {
      return NULL;
    }
    $id = array_pop($ids);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = Order::load($id);
    $checkout_flow = $this->checkoutOrderManager->getCheckoutFlow($order);
    $checkout_flow_plugin = $checkout_flow->getPlugin();

    return $this->formBuilder->getForm($checkout_flow_plugin, 'order_information');
  }

}
