<?php

namespace Drupal\wbx_commerce\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\commerce_product\Plugin\Field\FieldWidget\ProductVariationAttributesWidget;

/**
 * Plugin implementation of the 'commerce_product_variation_attributes' widget.
 *
 * @FieldWidget(
 *   id = "no_single_select_variation_attributes",
 *   label = @Translation("Product variation attributes(no single select)"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class NoSingleSelectAttributesWidget extends ProductVariationAttributesWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // Transform single selects to text.
    if (isset($element['attributes'])) {
      foreach ($element['attributes'] as $name => $attribute) {
        if (isset($attribute['#type']) && $attribute['#type'] === 'select' && count($attribute['#options']) === 1) {
          $value_label = reset($attribute['#options']);
          $keys = array_keys($attribute['#options']);
          $value = reset($keys);
          $element['attributes'][$name]['#prefix'] = Markup::create('<strong>' . $attribute['#title'] . ':&nbsp;</strong>' . $value_label);
          $element['attributes'][$name]['#type'] = 'value';
          $element['attributes'][$name]['#value'] = $value;
          unset($element['attributes'][$name]['#ajax']);
        }
      }
    }

    return $element;
  }

}
