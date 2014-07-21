<?php

/**
 * @file
 * (Un)subscription and (un)subscription confirmation
 *
 * FAPI subscription form cases:
 * - BLOCK
 *   self action: anonymous / authenticated user
 *   via hook_block: block
 *
 *
 * - MULTI BLOCK
 *   self action: anonymous / authenticated user
 *   authenticated user
 *   via hook_block: multi_block
 *   using PAGE handlers
 *
 * - ADMIN
 *   admin action: authenticated user
 *   via hook_menu: admin
 *
 * FAPI additional form cases:
 * - CONFIRM ADD
 * - CONFIRM REMOVAL
 *
 * @ingroup simplenews
 */

/**
 * FAPI BLOCK subscription form.
 *
 * @param $newsletter_id
 *   ID of selected newsletter.
 *
 * @see simplenews_block_form_validate()
 * @see simplenews_block_form_submit()
 */
function simplenews_block_form($form, &$form_state, $newsletter_id) {
  global $user;
  $form = array();

  $submit_text = t('Subscribe');
  if ($user->uid) {
    if (simplenews_user_is_subscribed($user->mail, $newsletter_id)) {
      $submit_text = t('Unsubscribe');
      $form['action'] = array('#type' => 'value', '#value' => 'unsubscribe');
      $form['#attributes'] = array('class' => array('simplenews-unsubscribe'));
    }
    else {
      $form['action'] = array('#type' => 'value', '#value' => 'subscribe');
      $form['#attributes'] = array('class' => array('simplenews-subscribe'));
    }
    $form['mail'] = array('#type' => 'value', '#value' => $user->mail);
  }
  else {
    $form['mail'] = array(
      '#type' => 'textfield',
      '#title' => t('E-mail'),
      '#size' => 20,
      '#maxlength' => 128,
      '#required' => TRUE,
    );
    $form['action'] = array('#type' => 'value', '#value' => 'subscribe');
    $form['#attributes'] = array('class' => array('simplenews-subscribe'));
  }

  // All block forms use the same validate and submit function.
  // #newsletter_id carries the newsletter_id for processing of the right
  // newsletter.
  $form['#newsletter_id'] = $newsletter_id;
  $form['#validate'][] = 'simplenews_block_form_validate';
  $form['#submit'][] = 'simplenews_block_form_submit';
  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => $submit_text,
  );
  return $form;
}

/*
 * FAPI BLOCK subscription form_validate.
 */

function simplenews_block_form_validate($form, &$form_state) {
  if (!valid_email_address($form_state['values']['mail'])) {
    form_set_error('mail', t("The e-mail address you supplied is not valid."));
  }
}

/*
 * FAPI BLOCK subscription form_submit.
 */

function simplenews_block_form_submit($form, &$form_state) {
  $newsletter_id = $form['#newsletter_id'];
  $account = simplenews_load_user_by_mail($form_state['values']['mail']);

  $confirm = simplenews_require_double_opt_in($newsletter_id, $account);

  switch ($form_state['values']['action']) {
    case 'subscribe':
      simplenews_subscribe($form_state['values']['mail'], $newsletter_id, $confirm, 'website');
      if ($confirm) {
        drupal_set_message(t('You will receive a confirmation e-mail shortly containing further instructions on how to complete your subscription.'));
      }
      else {
        drupal_set_message(t('You have been subscribed.'));
      }
      break;
    case 'unsubscribe':
      simplenews_unsubscribe($form_state['values']['mail'], $newsletter_id, $confirm, 'website');
      if ($confirm) {
        drupal_set_message(t('You will receive a confirmation e-mail shortly containing further instructions on how to cancel your subscription.'));
      }
      else {
        drupal_set_message(t('You have been unsubscribed.'));
      }
      break;
  }
}

/**
 * FAPI MULTI BLOCK subscription form.
 *
 * Menu callback: Generates the subscription form for users for the multisignup block.
 *
 * @see simplenews_subscriptions_multi_block_form_validate()
 * @see simplenews_subscriptions_multi_block_form_submit()
 */
function simplenews_subscriptions_multi_block_form($form, &$form_state) {
  global $user;

  $subscriber = !empty($user->mail) ? simplenews_subscriber_load_by_mail($user->mail) : FALSE;
  // If someone not authorized to edit their subscription, return empty form.
  if (!user_access('subscribe to newsletters')) {
    return;
  }

  $form = array();
  $options = array();
  $default_value = array();

  // Get newsletters for subscription form checkboxes.
  // Newsletters with opt-in/out method 'hidden' will not be listed.
  foreach (simplenews_newsletter_get_visible() as $newsletter) {
    $options[$newsletter->newsletter_id] = check_plain($newsletter->name);
    $default_value[$newsletter->newsletter_id] = FALSE;
  }

  if ($subscriber) {
    // If there is an existing subscriber object, use the existing settings.
    $default_value = array_merge($default_value, $subscriber->newsletter_ids);
  }

  $form['newsletters'] = array(
    '#type' => 'checkboxes',
    '#options' => $options,
    '#default_value' => $default_value,
  );

  // If current user is logged in, just display email.
  // Anonymous users see an email box and will receive confirmations
  if (user_is_logged_in()) {
    // @todo why not simply Manage your subscriptions?
    $form['mail'] = array('#type' => 'value', '#value' => $user->mail);
    $form['update'] = array(
      '#type' => 'submit',
      '#value' => t('Update'),
      '#weight' => 20,
      // @todo: add clean submit handler
    );
  }
  else {
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

  $form['#validate'][] = 'simplenews_subscriptions_page_form_validate';
  $form['#submit'][] = 'simplenews_subscriptions_page_form_submit';

  return $form;
}