<?php

namespace Drupal\wbx_commerce\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class RouteSubscriber.
 *
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {

    if ($route = $collection->get('commerce_cart.page')) {
      $route->setDefaults([
        '_controller' => '\Drupal\wbx_commerce\Controller\CheckoutController::render',
        '_title' => 'Checkout',
      ]);
      $route->setPath('/checkout');
    }
  }
}
