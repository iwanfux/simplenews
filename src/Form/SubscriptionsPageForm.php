<?php

/**
 * @file
 * Contains \Drupal\simplenews\Form\SubscriptionsPageForm.
 */

namespace Drupal\simplenews\Form;

use Drupal\Component\Utility\String;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Configure simplenews subscriptions of the logged user.
 */
class SubscriptionsPageForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simplenews_subscriptions_page';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $snid = NULL, $timestamp = NULL, $hash = NULL) {
    $user = \Drupal::currentUser();

    $subscriber = $mail = FALSE;
    if ($user->getEmail()) {
      $subscriber = simplenews_subscriber_load_by_mail($user->getEmail());
      $mail = $user->getEmail();
    }
    // If a hash is provided, try to load the corresponding subscriber.
    elseif ($snid && $timestamp && $hash) {
      $subscriber = simplenews_subscriber_load($snid);
      if ($subscriber && $hash == simplenews_generate_hash($subscriber->getMail(), 'manage', $timestamp)) {
        $mail = $subscriber->getMail();
      }
      else {
        throw new NotFoundHttpException();
      }
    }

    $options = array();
    $default_value = $subscriber ? $subscriber->getSubscribedNewsletterIds() : array();

    // Get newsletters for subscription form checkboxes.
    // Newsletters with opt-in/out method 'hidden' will not be listed.
    foreach (simplenews_newsletter_get_visible() as $newsletter) {
      $options[$newsletter->id()] = String::checkPlain($newsletter->name);
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
      $form['#title'] = $this->t('Subscriptions for %mail', array('%mail' => $mail));
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
          drupal_set_message(t('The newsletter subscriptions for %mail have been updated.', array('%mail' => $form_state->getValue('mail'))));
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
          drupal_set_message(t('The newsletter subscriptions for %mail have been updated.', array('%mail' => $form_state->getValue('mail'))));
        }
        break;
        parent::submitForm($form, $form_state);
    }
  }
}
