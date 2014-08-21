<?php

/**
 * @file
 * Contains \Drupal\simplenews\Form\SubscriberMassUnsubscribeForm.
 */

namespace Drupal\simplenews\Form;

use Drupal\Component\Utility\String;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Do a mass subscription for a list of email addresses.
 */
class SubscriberMassUnsubscribeForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simplenews_subscriber_mass_unsubscribe';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['emails'] = array(
      '#type' => 'textarea',
      '#title' => t('Email addresses'),
      '#cols' => 60,
      '#rows' => 5,
      '#description' => t('Email addresses must be separated by comma, space or newline.'),
    );

    $form['newsletters'] = array(
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => t('Unsubscribe from'),
      '#tree' => TRUE,
    );

    foreach (simplenews_newsletter_get_all() as $newsletter) {
      $form['newsletters'][$newsletter->id()] = array(
        '#type' => 'checkbox',
        '#title' => String::checkPlain($newsletter->label()),
        '#description' => String::checkPlain($newsletter->description),
      );
    }

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Unsubscribe'),
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
    $removed = array();
    $invalid = array();
    $checked_lists = array_keys(array_filter($form_state['values']['newsletters']));

    $emails = preg_split("/[\s,]+/", $form_state['values']['emails']);
    foreach ($emails as $email) {
      $email = trim($email);
      if (valid_email_address($email)) {
        foreach ($checked_lists as $newsletter_id) {
          simplenews_unsubscribe($email, $newsletter_id, FALSE, 'mass unsubscribe');
          $removed[] = $email;
        }
      }
      else {
        $invalid[] = $email;
      }
    }
    if ($removed) {
      $removed = implode(", ", $removed);
      drupal_set_message(t('The following addresses were unsubscribed: %removed.', array('%removed' => $removed)));

      $newsletters = simplenews_newsletter_get_all();
      $list_names = array();
      foreach ($checked_lists as $newsletter_id) {
        $list_names[] = $newsletters[$newsletter_id]->label();
      }
      drupal_set_message(t('The addresses were unsubscribed from the following newsletters: %newsletters.', array('%newsletters' => implode(', ', $list_names))));
    }
    else {
      drupal_set_message(t('No addresses were removed.'));
    }
    if ($invalid) {
      $invalid = implode(", ", $invalid);
      drupal_set_message(t('The following addresses were invalid: %invalid.', array('%invalid' => $invalid)), 'error');
    }

    // Return to the parent page.
    $form_state->setRedirect('simplenews.subscriber_list');
  }
}
