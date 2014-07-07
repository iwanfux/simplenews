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

/**
 * Mask a mail address.
 *
 * For example, name@example.org will be masked as n*****@e*****.org.
 *
 * @param $mail
 *   A valid mail address to mask.
 *
 * @return
 *   The masked mail address.
 */
function simplenews_mask_mail($mail) {
  if (preg_match('/^(.).*@(.).*(\..+)$/', $mail)) {
    return preg_replace('/^(.).*@(.).*(\..+)$/', '$1*****@$2*****$3', $mail);
  }
  else {
    // Missing top-level domain.
    return preg_replace('/^(.).*@(.).*$/', '$1*****@$2*****', $mail);
  }
}

/**
 * FAPI ADMIN subscription form.
 *
 * Menu callback: handle the edit subscription page and a subscription
 * page for anonymous users.
 *
 * @see simplenews_subscriptions_admin_form_validate()
 * @see simplenews_subscriptions_admin_form_submit()
 */
function simplenews_subscriptions_admin_form($form, &$form_state, $snid) {
  $subscriber = simplenews_subscriber_load($snid);

  $form = array();
  $options = array();
  $default_value = array();

  // Get newsletters for subscription form checkboxes.
  // Newsletters with opt-in/out method 'hidden' will not be listed.
  foreach (simplenews_newsletter_get_visible() as $newsletter) {
    $options[$newsletter->newsletter_id] = check_plain($newsletter->name);
    $default_value[$newsletter->newsletter_id] = FALSE;
  }

  $form['subscriptions'] = array(
    '#title' => t('Subscriptions for %mail', array('%mail' => $subscriber->mail)),
    '#type' => 'fieldset',
    '#description' => t('Select the newsletter(s) to add/remove from subscription.'),
  );
  $form['subscriptions']['newsletters'] = array(
    '#type' => 'checkboxes',
    '#options' => $options,
    '#default_value' => array_merge($default_value, $subscriber->newsletter_ids),
  );

  $form['activated'] = array(
    '#title' => t('Activation'),
    '#type' => 'fieldset',
    '#description' => t('Activate or inactivate account.'),
  );
  $form['activated']['activated'] = array(
    '#type' => 'checkbox',
    '#title' => t('Activated'),
    '#default_value' => $subscriber->activated,
  );

  if ((variable_get('language_count', 1) > 1)) {
    $languages = language_list('enabled');
    foreach ($languages[1] as $langcode => $item) {
      $name = t($item->name);
      $language_options[$langcode] = $name . ($item->native != $name ? ' (' . $item->native . ')' : '');
    }
    $form['language'] = array(
      '#type' => 'fieldset',
      '#title' => t('Preferred language'),
      '#description' => t('The e-mails will be localized in language chosen. Real users have their preference in account settings.'),
      '#disabled' => FALSE,
    );
    if ($subscriber->uid) {
      // Fallback if user has not defined a language.
      $language_default = t('Site default language (@name)', array('@name' => $language_options[language_default()->language]));
      $form['language']['language'] = array(
        '#type' => 'item',
        '#title' => t('User language'),
        '#markup' => isset($language_options[$subscriber->language]) ? $language_options[$subscriber->language] : $language_default,
      );
    }
    else {
      $form['language']['language'] = array(
        '#type' => 'select',
        '#default_value' => $subscriber->language,
        '#options' => $language_options,
      );
    }
  }

  $form['subscriptions']['mail'] = array('#type' => 'value', '#value' => $subscriber->mail);
  $form['update'] = array(
    '#type' => 'submit',
    '#value' => t('Update'),
    '#weight' => 20,
  );

  $form['#validate'][] = 'simplenews_subscriptions_admin_form_validate';
  $form['#submit'][] = 'simplenews_subscriptions_admin_form_submit';
  $form['#redirect'] = 'admin/content/simplenews/users';

  return $form;
}

/**
 * FAPI ADMIN subscription form_validate.
 */
function simplenews_subscriptions_admin_form_validate($form, &$form_state) {
  $subscriber = simplenews_subscriber_load_by_mail($form_state['values']['mail']);

  $valid_email = valid_email_address($form_state['values']['mail']);
  if (!$valid_email) {
    form_set_error('mail', t('The e-mail address you supplied is not valid.'));
  }
  $checked_newsletters = array_filter($form_state['values']['newsletters']);
  if (!count($checked_newsletters) && !$subscriber) {
    form_set_error('newsletters', t('You must select at least one newsletter.'));
  }
  $languages = language_list('enabled');
  if (!empty($form_state['values']['language'])
    && !isset($languages[1][$form_state['values']['language']])) {
    form_set_error('language', t('Please choose a language from the list.'));
  }
}

/**
 * FAPI ADMIN subscription form_submit.
 */
function simplenews_subscriptions_admin_form_submit($form, &$form_state) {
  $subscriber = simplenews_subscriber_load_by_mail($form_state['values']['mail']);

  // update subscriptions
  arsort($form_state['values']['newsletters'], SORT_NUMERIC);
  foreach ($form_state['values']['newsletters'] as $newsletter_id => $checked) {
    if ($checked) {
      simplenews_subscribe($form_state['values']['mail'], $newsletter_id, FALSE, 'website');
    }
    else {
      simplenews_unsubscribe($form_state['values']['mail'], $newsletter_id, FALSE, 'website');
    }
  }

  // update subscriber
  $data = array();
  $subscriber->activated = $form_state['values']['activated'];
  if (!$subscriber->uid) {
    if (isset($form_state['values']['language'])) {
      $subscriber->language = $form_state['values']['language'];
    }
  }
  simplenews_subscriber_save($subscriber);

  drupal_set_message(t('The newsletter subscriptions for %mail have been updated.', array('%mail' => $form_state['values']['mail'])));
}

