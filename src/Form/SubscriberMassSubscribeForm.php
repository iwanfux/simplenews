<?php

/**
 * @file
 * Contains \Drupal\simplenews\Form\SubscriberMassSubscribeForm.
 */

namespace Drupal\simplenews\Form;

use Drupal\Component\Utility\String;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Do a mass subscription for a list of email addresses.
 */
class SubscriberMassSubscribeForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simplenews_subscriber_mass_subscribe';
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
      '#title' => t('Subscribe to'),
      '#tree' => TRUE,
    );

    foreach (simplenews_newsletter_get_all() as $newsletter) {
      $form['newsletters'][$newsletter->id()] = array(
        '#type' => 'checkbox',
        '#title' => String::checkPlain($newsletter->label()),
        '#description' => String::checkPlain($newsletter->description),
      );
    }

    $form['resubscribe'] = array(
      '#type' => 'checkbox',
      '#title' => t('Force resubscription'),
      '#description' => t('If checked, previously unsubscribed e-mail addresses will be resubscribed. Consider that this might be against the will of your users.'),
    );

    // Include language selection when the site is multilingual.
    // Default value is the empty string which will result in receiving emails
    // in the site's default language.
    if (\Drupal::languageManager()->isMultilingual()) {
      $options[''] = t('Site default language');
      $languages = \Drupal::languageManager()->getLanguages();
      foreach ($languages as $langcode => $language) {
        $options[$langcode] = $language->getName();
      }
      $form['language'] = array(
        '#type' => 'radios',
        '#title' => t('Anonymous user preferred language'),
        '#default_value' => '',
        '#options' => $options,
        '#description' => t('New subscriptions will be subscribed with the selected preferred language. The language of existing subscribers is unchanged.'),
      );
    }
    else {
      $form['language'] = array(
        '#type' => 'value',
        '#value' => '',
      );
    }

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Subscribe'),
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
    $added = array();
    $invalid = array();
    $unsubscribed = array();
    $checked_newsletters = array_keys(array_filter($form_state['values']['newsletters']));
    $langcode = $form_state['values']['language'];

    $emails = preg_split("/[\s,]+/", $form_state['values']['emails']);
    foreach ($emails as $email) {
      $email = trim($email);
      if ($email == '') {
        continue;
      }
      if (valid_email_address($email)) {
        $subscriber = simplenews_subscriber_load_by_mail($email);
        foreach (simplenews_newsletter_load_multiple($checked_newsletters) as $newsletter) {
          // If there is a valid subscriber, check if there is a subscription for
          // the current newsletter and if this subscription has the status
          // unsubscribed.
          $is_unsubscribed = $subscriber ? $subscriber->isUnsubscribed($newsletter->id()) : FALSE;
          if (!$is_unsubscribed || $form_state['values']['resubscribe'] == TRUE) {
            simplenews_subscribe($email, $newsletter->id(), FALSE, 'mass subscribe', $langcode);
            $added[] = $email;
          }
          else {
            $unsubscribed[String::checkPlain($newsletter->label())][] = $email;
          }
        }
      }
      else {
        $invalid[] = $email;
      }
    }
    if ($added) {
      $added = implode(", ", $added);
      drupal_set_message(t('The following addresses were added or updated: %added.', array('%added' => $added)));

      $list_names = array();
      foreach (simplenews_newsletter_load_multiple($checked_newsletters) as $newsletter) {
        $list_names[] = $newsletter->label();
      }
      drupal_set_message(t('The addresses were subscribed to the following newsletters: %newsletters.', array('%newsletters' => implode(', ', $list_names))));
    }
    else {
      drupal_set_message(t('No addresses were added.'));
    }
    if ($invalid) {
      $invalid = implode(", ", $invalid);
      drupal_set_message(t('The following addresses were invalid: %invalid.', array('%invalid' => $invalid)), 'error');
    }

    foreach ($unsubscribed as $name => $subscribers) {
      $subscribers = implode(", ", $subscribers);
      drupal_set_message(t('The following addresses were skipped because they have previously unsubscribed from %name: %unsubscribed.', array('%name' => $name, '%unsubscribed' => $subscribers)), 'warning');
    }

    if (!empty($unsubscribed)) {
      drupal_set_message(t("If you would like to resubscribe them, use the 'Force resubscription' option."), 'warning');
    }

    // Return to the parent page.
    $form_state['redirect'] = 'admin/people/simplenews';
  }
}