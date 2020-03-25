<?php
namespace Drupal\wbx_commerce\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Event\CheckoutCompletionRegisterEvent;
use Drupal\commerce_checkout\Event\CheckoutEvents;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CompletionRegister;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the registration after checkout pane.
 *
 * @CommerceCheckoutPane(
 *   id = "wbx_completion_register",
 *   label = @Translation("WBX Guest registration after checkout"),
 *   display_label = @Translation("Account information"),
 *   default_step = "complete",
 * )
 */
class WBXCompletionRegister extends CompletionRegister {
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    // Validate the entity. This will ensure that the username and email are in
    // the right format and not already taken.
    $values = $form_state->getValue($pane_form['#parents']);
    if (!isset($values['name'])) {
      $values = $form_state->getValues();
    }
    $account = $this->userStorage->create([
      'mail' => $this->order->getEmail(),
      'name' => $values['name'],
      'pass' => $values['pass'],
      'status' => TRUE,
    ]);

    /** @var \Drupal\user\UserInterface $account */
    $form_display = EntityFormDisplay::collectRenderDisplay($account, 'register');
    $form_display->extractFormValues($account, $pane_form, $form_state);
    $form_display->validateFormValues($account, $pane_form, $form_state);

    // Manually flag violations of fields not handled by the form display. This
    // is necessary as entity form displays only flag violations for fields
    // contained in the display.
    // @see \Drupal\user\AccountForm::flagViolations
    $violations = $account->validate();
    foreach ($violations->getByFields(['name', 'pass']) as $violation) {
      list($field_name) = explode('.', $violation->getPropertyPath(), 2);
      $form_state->setError($pane_form[$field_name], $violation->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    if (!isset($values['name'])) {
      $values = $form_state->getValues();
    }
    $account = $this->userStorage->create([
      'pass' => $values['pass'],
      'mail' => $this->order->getEmail(),
      'name' => $values['name'],
      'status' => TRUE,
    ]);
    /** @var \Drupal\user\UserInterface $account */
    $form_display = EntityFormDisplay::collectRenderDisplay($account, 'register');
    $form_display->extractFormValues($account, $pane_form, $form_state);
    $account->save();
    user_login_finalize($account);
    $this->credentialsCheckFlood->clearAccount($this->clientIp, $account->getAccountName());

    $this->orderAssignment->assign($this->order, $account);
    // Notify other modules.
    $event = new CheckoutCompletionRegisterEvent($account, $this->order);
    $this->eventDispatcher->dispatch(CheckoutEvents::COMPLETION_REGISTER, $event);
    // Event subscribers are allowed to set a redirect url, to send the
    // customer to their orders page, for example.
    if ($url = $event->getRedirectUrl()) {
      $form_state->setRedirectUrl($url);
    }
    $this->messenger()->addStatus($this->t('Registration successful. You are now logged in.'));
  }
}
