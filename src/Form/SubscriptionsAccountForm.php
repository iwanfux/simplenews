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
use Drupal\simplenews\Entity\Subscriber;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Configure simplenews subscriptions of a user.
 */
class SubscriptionsAccountForm extends SubscriberFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $user = NULL) {
    // Uid parameter has to be named $user but we use that name for the entity.
    $uid = $user;

    // Try to load a subscriber from the uid.
    if (isset($uid)) {
      $user = User::load($uid);
      $form_state->set('user', $user);
      $this->setEntity(simplenews_subscriber_load_by_uid($uid) ?: Subscriber::create(array('mail' => $user->getEmail())));
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions[static::SUBMIT_UPDATE]['#value'] = $this->t('Save');
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  protected function getSubmitMessage(FormStateInterface $form_state, $op, $confirm) {
    $user = $form_state->get('user');
    if (\Drupal::currentUser()->id() == $user->id()) {
      return $this->t('Your newsletter subscriptions have been updated.');
    }
    return $this->t('The newsletter subscriptions for user %account have been updated.', array('%account' => $user->label()));
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

    // Hide the form from menus/tabs if sync is disabled.
    if (!\Drupal::config('simplenews.settings')->get('subscription.sync_account')) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowedIfHasPermission($account, 'administer simplenews subscriptions')
      ->orIf(AccessResult::allowedIfHasPermission($account, 'subscribe to newsletters')
        ->andIf(AccessResult::allowedIf($user->id() == $account->id())));
  }

}
