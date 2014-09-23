<?php

/**
 * @file
 * Contains \Drupal\simplenews\Form\SubscriptionsAccountForm.
 */

namespace Drupal\simplenews\Form;

use Drupal\Component\Utility\String;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Configure simplenews subscriptions of a user.
 */
class SubscriptionsAccountForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simplenews_subscriptions_account';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $user = NULL) {
    // Uid parameter has to be named $user but we use that name for the entity.
    $uid = $user;
    if (isset($uid)) {
      $user = User::load($uid);
      $form_state->set('user', $user);

      $subscriber = simplenews_subscriber_load_by_uid($uid);
      if (!$subscriber && $user) {
        $subscriber = simplenews_subscriber_load_by_mail($user->getEmail());
      }
      if ($subscriber) {
        $this->entity = $subscriber;
      }
    }

    $options = array();
    $default_value = $this->entity->getSubscribedNewsletterIds();

    // Get newsletters for subscription form checkboxes.
    // Newsletters with opt-in/out method 'hidden' will not be listed.
    foreach (simplenews_newsletter_get_visible() as $newsletter) {
      $options[$newsletter->id()] = String::checkPlain($newsletter->name);
    }


    $form['subscriptions'] = array(
      '#type' => 'fieldset',
      '#title' => t('Current newsletter subscriptions.'),
      '#description' => t('Select your newsletter subscriptions.'),
    );
    $form['subscriptions']['newsletters'] = array(
      '#type' => 'checkboxes',
      '#options' => $options,
      '#default_value' => $default_value,
    );

    $form = parent::buildForm($form, $form_state);

    if ($uid > 0) {
      $form['mail']['#disabled'] = 'disabled';
    }

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

    parent::submitForm($form, $form_state);
  }

  /**
   * Checks access for the simplenews account form.
   *
   * @param int $user
   *   The ID of the account to use in the form.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   An access result object.
   */
  public function checkAccess($user) {
    $user = User::load($user);
    $account = $this->currentUser();

    return AccessResult::allowedIfHasPermission($account, 'administer simplenews subscriptions')
      ->orIf(AccessResult::allowedIfHasPermission($account, 'subscribe to newsletters')
        ->andIf(AccessResult::allowedIf($user->id() == $account->id())));
  }

}
