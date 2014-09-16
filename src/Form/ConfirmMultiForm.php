<?php

/**
 * @file
 * Contains \Drupal\simplenews\Form\ConfirmMultiForm.
 */

namespace Drupal\simplenews\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\simplenews\SubscriberInterface;

/**
 * Implements a multi confirmation form for simplenews subscriptions.
 */
class ConfirmMultiForm extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Confirm subscription');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return t('You can always change your subscriptions later.');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simplenews_confirm_multi';
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('simplenews.newsletter_subscriptions');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, SubscriberInterface $subscriber = NULL) {
    $form = parent::buildForm($form, $form_state);
    $form['question'] = array(
      '#markup' => '<p>' . t('Are you sure you want to confirm the following subscription changes for %user?', array('%user' => simplenews_mask_mail($subscriber->getMail()))) . "<p>\n",
    );

    $form['changes'] = array(
      '#theme' => 'item_list',
      '#items' => simplenews_confirmation_get_changes_list($subscriber),
    );

    $form['subscriber'] = array(
      '#type' => 'value',
      '#value' => $subscriber,
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
    $subscriber = $form_state->getValue('subscriber');
    foreach ($subscriber->getChanges() as $newsletter_id => $action) {

      if ($action == 'subscribe') {
        if (!$subscriber->isSubscribed($newsletter_id)) {
          // Subscribe the user if not already subscribed.
          $subscriber->subscribe($newsletter_id);
        }
      }
      elseif ($action == 'unsubscribe') {
        if ($subscriber->isSubscribed($newsletter_id)) {
          // Subscribe the user if not already subscribed.
          $subscriber->unsubscribe($newsletter_id);
        }
      }
    }

    // Clear changes.
    $subscriber->setChanges(array());
    $subscriber->save();

    drupal_set_message(t('Subscription changes confirmed for %user.', array('%user' => $subscriber->getMail())));
    $form_state->setRedirect('<front>');
  }

}