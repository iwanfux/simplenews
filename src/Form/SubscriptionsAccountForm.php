<?php

/**
 * @file
 * Contains \Drupal\simplenews\Form\SubscriptionsAccountForm.
 */

namespace Drupal\simplenews\Form;

use Drupal\Component\Utility\String;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
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

    $form_state->set('user', $user);

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

    $account = $form_state->get('user');

    // We first subscribe, then unsubscribe. This prevents deletion of subscriptions
    // when unsubscribed from the
    arsort($form_state->getValue('newsletters'), SORT_NUMERIC);
    foreach ($form_state->getValue('newsletters') as $newsletter_id => $checked) {
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
   *   The account to use in the form.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   An access result object.
   */
  public function checkAccess(UserInterface $user) {
    $account = $this->currentUser();

    return AccessResult::allowedIfHasPermission($account, 'administer simplenews subscriptions')
      ->orIf(AccessResult::allowedIfHasPermission($account, 'subscribe to newsletters')
        ->andIf(AccessResult::allowedIf($user->id() == $account->id())));
  }

}
