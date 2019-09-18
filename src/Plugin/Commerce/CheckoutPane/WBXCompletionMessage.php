<?php

namespace Drupal\wbx_commerce\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\Core\Render\Markup;

/**
 * Provides the completion message pane.
 *
 * @CommerceCheckoutPane(
 *   id = "wbx_completion_message",
 *   label = @Translation("WBX Completion message"),
 *   default_step = "complete",
 * )
 */
class WBXCompletionMessage extends CheckoutPaneBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form =  parent::buildConfigurationForm($form, $form_state);
    $form['message'] = [
      '#type' => 'text_format',
      '#title' => t('Message'),
      '#format'=> 'formatted_html',
      '#default_value' => $this->configuration['message']
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $conf = parent::defaultConfiguration();
    $conf['message'] = NULL;
    return $conf;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['message'] = $values['message']['value'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $complete_form = [
      'message' => [
        '#markup' => Markup::create('<div class="completion-message">' . $this->configuration['message'] . '</div>'),
      ],
    ];

    return [];
  }

}
