<?php

/**
 * @file
 * Contains \Drupal\simplenews\Form\SubscriptionsBlockForm.
 */

namespace Drupal\simplenews\Form;

use Drupal\Component\Utility\String;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure simplenews subscriptions of the logged user.
 */
class SubscriptionsBlockForm extends FormBase {

  protected $uniqueId;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    if (empty($this->uniqueId)) {
      throw new \Exception('Unique ID must be set with setUniqueId.');
    }
    return 'simplenews_subscriptions_block_' . $this->uniqueId;
  }

  /**
   * {@inheritdoc}
   */
  public function setUniqueId($id) {
    $this->uniqueId = $id;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $newsletters = array(), $message = '') {
    $user = \Drupal::currentUser();
    $subscriber = $mail = FALSE;
    if ($user->getEmail()) {
      $subscriber = simplenews_subscriber_load_by_mail($user->getEmail());
      $mail = $user->getEmail();
    }

    $form['message'] = array(
      '#type' => 'item',
      '#markup' => $message,
    );

    if (count($newsletters) == 1) {
      $keys = array_keys($newsletters);
      $newsletter_id = array_shift($keys);

      $form['newsletters'] = array(
        '#type' => 'value',
        '#value' => array($newsletter_id => 1),
      );
      if ($mail) {
        $form['mail'] = array('#type' => 'value', '#value' => $mail);

        if ($subscriber && $subscriber->isSubscribed($newsletter_id)) {
          $form['unsubscribe'] = array(
            '#type' => 'submit',
            '#value' => t('Unsubscribe'),
            '#weight' => 30,
            // @todo: add clean submit handler
          );
        } else {
          $form['subscribe'] = array(
            '#type' => 'submit',
            '#value' => t('Subscribe'),
            '#weight' => 20,
            // @todo: add clean submit handler
          );
        }
      } else {
        $form['mail'] = array(
          '#type' => 'textfield',
          '#title' => t('E-mail'),
          '#size' => 20,
          '#maxlength' => 128,
          '#weight' => 10,
          '#required' => TRUE,
        );
        $form['subscribe'] = array(
          '#type' => 'submit',
          '#value' => t('Subscribe'),
          '#weight' => 20,
          // @todo: add clean submit handler
        );
      }
    } else {
      $options = array();
      $default_value = $subscriber ? $subscriber->getSubscribedNewsletterIds() : array();
      $default_value = array_intersect($default_value, array_keys($newsletters));

      foreach ($newsletters as $id => $newsletter) {
        $options[$id] = String::checkPlain($newsletter->name);
      }

      $form['newsletters'] = array(
        '#type' => 'checkboxes',
        '#options' => $options,
        '#default_value' => $default_value
      );

      // If we have a mail address, which is either from a logged in user or a
      // subscriber identified through the hash code, display the mail address
      // instead of a textfield. Anonymous users will still have to confirm any
      // changes.
      if ($mail) {
        $form['newsletters']['#description'] = t('Check the newsletters you want to subscribe to. Uncheck the ones you want to unsubscribe from.');
        $form['mail'] = array('#type' => 'value', '#value' => $mail);
        $form['update'] = array(
          '#type' => 'submit',
          '#value' => t('Update'),
          '#weight' => 20,
          // @todo: add clean submit handler
        );
      }
      else {
        $form['newsletters']['#description'] = t('Select the newsletter(s) to which you want to subscribe or unsubscribe.');
        $form['mail'] = array(
          '#type' => 'textfield',
          '#title' => t('E-mail'),
          '#size' => 20,
          '#maxlength' => 128,
          '#weight' => 10,
          '#required' => TRUE,
        );
        $form['subscribe'] = array(
          '#type' => 'submit',
          '#value' => t('Subscribe'),
          '#weight' => 20,
          // @todo: add clean submit handler
        );
        $form['unsubscribe'] = array(
          '#type' => 'submit',
          '#value' => t('Unsubscribe'),
          '#weight' => 30,
          // @todo: add clean submit handler
        );
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $valid_email = valid_email_address($form_state->getValue('mail'));
    if (!$valid_email) {
      $form_state->setErrorByName('mail', t('The e-mail address you supplied is not valid.'));
    }

    $checked_newsletters = array_filter($form_state->getValue('newsletters'));
    // Unless we're in update mode, at least one checkbox must be checked.
    if (!count($checked_newsletters) && $form_state->getValue('op') != t('Update')) {
      $form_state->setErrorByName('newsletters', t('You must select at least one newsletter.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $mail = $form_state->getValue('mail');
    $account = user_load_by_mail($mail);

    // Group confirmation mails as necessary and configured.
    simplenews_confirmation_combine(TRUE);
    switch ($form_state->getValue('op')) {
      case t('Update'):
        // We first subscribe, then unsubscribe. This prevents deletion of subscriptions
        // when unsubscribed from the newsletter.
        arsort($form_state->getValue('newsletters'), SORT_NUMERIC);
        foreach ($form_state->getValue('newsletters') as $newsletter_id => $checked) {
          if ($checked) {
            simplenews_subscribe($mail, $newsletter_id, FALSE, 'website');
          }
          else {
            simplenews_unsubscribe($mail, $newsletter_id, FALSE, 'website');
          }
        }
        drupal_set_message(t('The newsletter subscriptions for %mail have been updated.', array('%mail' => $form_state->getValue('mail'))));
        break;
      case t('Subscribe'):
        foreach ($form_state->getValue('newsletters') as $newsletter_id => $checked) {
          if ($checked) {
            $confirm = simplenews_require_double_opt_in($newsletter_id, $account);
            simplenews_subscribe($mail, $newsletter_id, $confirm, 'website');
          }
        }
        if (simplenews_confirmation_send_combined()) {
          drupal_set_message(t('You will receive a confirmation e-mail shortly containing further instructions on how to complete your subscription.'));
        }
        else {
          drupal_set_message(t('You have been subscribed.'));
        }
        break;
      case t('Unsubscribe'):
        foreach ($form_state->getValue('newsletters') as $newsletter_id => $checked) {
          if ($checked) {
            $confirm = simplenews_require_double_opt_in($newsletter_id, $account);
            simplenews_unsubscribe($mail, $newsletter_id, $confirm, 'website');
          }
        }
        if (simplenews_confirmation_send_combined()) {
          drupal_set_message(t('You will receive a confirmation e-mail shortly containing further instructions on how to cancel your subscription.'));
        }
        else {
          drupal_set_message(t('You have been unsubscribed.'));
        }
        break;
        parent::submitForm($form, $form_state);
    }
  }
}