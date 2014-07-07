<?php

/**
 * @file
 * Contains \Drupal\simplenews\Form\ConfirmMultiForm.
 */

namespace Drupal\simplenews\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Url;
use Drupal\simplenews\SubscriberInterface;
use Symfony\Component\Validator\Constraints\Null;

/**
 * Provides an generic base class for a confirmation form.
 */
abstract class ConfirmMultiForm extends ConfirmFormBase {

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
  public function getFormName() {
    return 'simplenews_confirm_multi';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state, SubscriberInterface $subscriber = NULL) {
    $form = array();
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
  public function validateForm(array &$form, array &$form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    $subscriber = $form_state['values']['subscriber'];
    foreach ($subscriber->getChanges() as $newsletter_id => $action) {
      if ($action == 'subscribe') {
        simplenews_subscribe($subscriber->getMail(), $newsletter_id, FALSE, 'website');
      }
      elseif ($action == 'unsubscribe') {
        simplenews_unsubscribe($subscriber->getMail(), $newsletter_id, FALSE, 'website');
      }
    }

    // Clear changes.
    $subscriber->setChanges(array());
    $subscriber->save();

    drupal_set_message(t('Subscription changes confirmed for %user.', array('%user' => $subscriber->getMail())));
    $form_state['redirect_route'] = new Url('<front>');
  }

}