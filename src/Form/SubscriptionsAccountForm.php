<?php

/**
 * @file
 * Contains \Drupal\simplenews\Form\SubscriptionsAccountForm.
 */

namespace Drupal\simplenews\Form;

use Drupal\Component\Utility\String;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\user\UserInterface;

/**
 * Configure simplenews subscriptions of a user.
 */
class SubscriptionsAccountForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simplenews_subscriptions_account';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, UserInterface $user = NULL) {
    $subscriber = simplenews_subscriber_load_by_mail($user->getEmail());

    $options = array();
    $default_value = $subscriber ? $subscriber->getSubscribedNewsletterIds() : array();

    // Get newsletters for subscription form checkboxes.
    // Newsletters with opt-in/out method 'hidden' will not be listed.
    foreach (simplenews_newsletter_get_visible() as $newsletter) {
      $options[$newsletter->id()] = String::checkPlain($newsletter->name);
    }

    $form_state['user'] = $user;

    $form['subscriptions'] = array(
      '#type' => 'fieldset',
      '#description' => t('Select your newsletter subscriptions.'),
    );
    $form['subscriptions']['newsletters'] = array(
      '#type' => 'checkboxes',
      '#options' => $options,
      '#default_value' => $default_value,
    );

    $form['subscriptions']['#title'] = t('Current newsletter subscriptions');

    $form['save'] = array(
      '#type' => 'submit',
      '#value' => t('Save'),
      '#weight' => 20,
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user = \Drupal::currentUser();

    $account = $form_state['user'];

    // We first subscribe, then unsubscribe. This prevents deletion of subscriptions
    // when unsubscribed from the
    arsort($form_state['values']['newsletters'], SORT_NUMERIC);
    foreach ($form_state['values']['newsletters'] as $newsletter_id => $checked) {
      if ($checked) {
        simplenews_subscribe($account->getEmail(), $newsletter_id, FALSE, 'website');
      }
      else {
        simplenews_unsubscribe($account->getEmail(), $newsletter_id, FALSE, 'website');
      }
    }
    if ($user->id() == $account->id()) {
      drupal_set_message(t('Your newsletter subscriptions have been updated.'));
    }
    else {
      drupal_set_message(t('The newsletter subscriptions for user %account have been updated.', array('%account' => $account->label() )));
    }
  }

  /**
   * Checks access for the simplenews account form.
   *
   * @param \Drupal\user\UserInterface $user
   *   (optional) The owner of the shortcut set.
   *
   * @return mixed
   *   AccessInterface::ALLOW, AccessInterface::DENY, or AccessInterface::KILL.
   */
  public function checkAccess(UserInterface $user = NULL) {
    $account = $this->currentUser();
    $this->user = $user;

    if ($account->hasPermission('administer simplenews subscriptions')) {
      // Administrators can administer anyone's subscriptions.
      return AccessInterface::ALLOW;
    }

    if (!$account->hasPermission('subscribe to newsletters')) {
      // The user has no permission to subscribe to newsletters.
      return AccessInterface::DENY;
    }

    if ($this->user->id() == $account->id()) {
      // Users with the 'subscribe to newsletters' permission can administer their own
      // subscriptions.
      return AccessInterface::ALLOW;
    }
    return AccessInterface::DENY;
  }

}