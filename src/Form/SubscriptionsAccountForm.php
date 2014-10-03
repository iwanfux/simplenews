<?php

/**
 * @file
 * Contains \Drupal\simplenews\Form\SubscriptionsAccountForm.
 */

namespace Drupal\simplenews\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;

/**
 * Configure simplenews subscriptions of a user.
 */
class SubscriptionsAccountForm extends SubscriberFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $user = NULL) {
    // Try to load a subscriber from the uid, otherwise just set the mail field
    // on the new subscriber.
    if (isset($user) && $user = User::load($user)) {
      $form_state->set('user', $user);
      if ($subscriber = simplenews_subscriber_load_by_uid($user->id())) {
        $this->setEntity($subscriber);
      }
      else {
        $this->entity->setUserId($user->id());
        $this->entity->setMail($user->getEmail());
      }
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

    return AccessResult::allowedIfHasPermission($account, 'administer simplenews subscriptions')
      ->orIf(AccessResult::allowedIfHasPermission($account, 'subscribe to newsletters')
        ->andIf(AccessResult::allowedIf($user->id() == $account->id())));
  }

}
