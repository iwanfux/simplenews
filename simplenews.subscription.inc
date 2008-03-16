<?php
//$Id$

/**
 * Generates the subscription form for users.
 */
function simplenews_subscription_manager_form(&$form_state, $snid = NULL) {
//TODO: make this function call suitable for selected range of newsletters $range = array(snid, snid, snid)
//TODO: (nice to have) make this form suitable for single/multi select
  global $user;

  if (isset($snid)) {
    $subscription = simplenews_get_subscription(array('snid' => $snid));
  } 
  else {
    $subscription = simplenews_get_subscription((array)$user);
  }

  // If non-admin is trying to edit someone else's subscription, access denied.
  if ($user->uid && $user->uid != $subscription->uid && !user_access('administer newsletters')) {
    drupal_access_denied();
    return;
  }
  $form = _simplenews_subscription_manager_form($subscription);
  $form['#validate'][] = 'simplenews_subscription_manager_form_validate';
  $form['#submit'][] = 'simplenews_subscription_manager_form_submit';
  $form['#redirect'] = '';  //Return to home page after (un)subscribe

  return $form;
}

/**
 * Forms API callback; validates the settings form.
 */
function simplenews_subscription_manager_form_validate($form, &$form_state) {
  $valid_email = valid_email_address($form_state['values']['mail']);
  if (!$valid_email) {
    form_set_error('mail', t('The e-mail address you supplied is not valid.'));
  }
  $checked_newsletters = array_filter($form_state['values']['newsletters']);
  $account['mail'] = $form_state['values']['mail'];
  if (!count($checked_newsletters) && !simplenews_get_user_subscription($account)) {
    form_set_error('newsletters', t('You must select at least one newsletter.'));
  }
}

/**
 * Forms API callback; submit handler for subscription form.
 */
function simplenews_subscription_manager_form_submit($form, &$form_state) {
  switch ($form_state['values']['op']) {
    case t('Update'):
      foreach ($form_state['values']['newsletters'] as $tid => $checked) {
        if ($checked) {
          simplenews_subscribe_user($form_state['values']['mail'], $tid, FALSE);
        }
        else {
          simplenews_unsubscribe_user($form_state['values']['mail'], $tid, FALSE);
        }
      }
      drupal_set_message(t('The newsletter subscriptions for %mail have been updated.', array('%mail' => $form_state['values']['mail'])));
      break;
    case t('Subscribe'):
      foreach ($form_state['values']['newsletters'] as $tid => $checked) {
        if ($checked) {
          simplenews_subscribe_user($form_state['values']['mail'], $tid);
        }
      }
      drupal_set_message(t('You will receive a confirmation e-mail shortly containing further instructions on how to complete your subscription.'));
      break;
    case t('Unsubscribe'):
      foreach ($form_state['values']['newsletters'] as $tid => $checked) {
        if ($checked) {
          simplenews_unsubscribe_user($form_state['values']['mail'], $tid);
        }
      }
      drupal_set_message(t('You will receive a confirmation e-mail shortly containing further instructions on how to complete the unsubscription process.'));
      break;
  }
}

/**
 * Menu callback; confirm the user's (un)subscription request when they click
 * on the confirm link in the e-mail footer.
 */
function simplenews_confirm_subscription($op1 = NULL, $op2 = NULL) {
  $md5 = drupal_substr($op2, 0, 10);
  list($snid, $tid) = explode('t', drupal_substr($op2, 10));

  $result = db_query('SELECT snid, mail FROM {simplenews_subscriptions} WHERE snid = %d', $snid);
  if (!($subs = db_fetch_object($result))) {
    drupal_not_found();
    return;
  }

  if ($md5 == drupal_substr(md5($subs->mail . simplenews_private_key()), 0, 10)) {
    $newsletter = taxonomy_get_term($tid);
    if ($op1 == 'remove') {
      return drupal_get_form('simplenews_confirm_removal_form', $subs->mail, $newsletter);
    }
    elseif ($op1 == 'add') {
      return drupal_get_form('simplenews_confirm_add_form', $subs->mail, $newsletter);
    }
  }

  // If md5 didn't match, do a not found.
  drupal_not_found();
  return;
}

/**
 * Generate the confirm add form.
 */
function simplenews_confirm_add_form(&$form_state, $mail, $newsletter) {
  $form = array();
  $form['mail'] = array('#type' => 'value', '#value' => $mail);
  $form['newsletter'] = array('#type' => 'value', '#value' => $newsletter);
  $form['#redirect'] = '';

  return confirm_form($form,
    t('Are you sure you want to add %user to the %newsletter subscription list?', array('%user' => $mail, '%newsletter' => $newsletter->name)),
    '',
    t('You always have the option of unsubscribing later.'),
    t('Subscribe'),
    t('Cancel')
  );
}

/**
 * Forms API callback; handles form submission for a user confirming subscribe
 * request.
 */
function simplenews_confirm_add_form_submit($form, &$form_state) {
  simplenews_subscribe_user($form_state['values']['mail'], $form_state['values']['newsletter']->tid, FALSE);
  drupal_set_message(t('%user was successfully added to the %newsletter subscription list.', array('%user' => $form_state['values']['mail'], '%newsletter' => $form_state['values']['newsletter']->name)));
}

/**
 * Generate the confirm remove form.
 */
function simplenews_confirm_removal_form(&$form_state, $mail, $newsletter) {
  $form = array();
  $form['mail'] = array('#type' => 'value', '#value' => $mail);
  $form['newsletter'] = array('#type' => 'value', '#value' => $newsletter);
  $form['#redirect'] = '';
  
  return confirm_form($form,
    t('Are you sure you want to remove %user from the %newsletter subscription list?', array('%user' => $mail, '%newsletter' => $newsletter->name)),
    '',
    t('This action will only remove you from the newsletter subscription list. If you are registered at our site, your account information will remain unchanged.'),
    t('Unsubscribe'),
    t('Cancel')
  );
}

/**
 * Forms API callback; handles form submission for a user confirming unsubscribe
 * request.
 */
function simplenews_confirm_removal_form_submit($form, &$form_state) {
  simplenews_unsubscribe_user($form_state['values']['mail'], $form_state['values']['newsletter']->tid, FALSE);
  drupal_set_message(t('%user was successfully removed from the %newsletter subscription list.', array('%user' => $form_state['values']['mail'], '%newsletter' => $form_state['values']['newsletter']->name)));
}

/**
 * Menu callback; handles the edit/delete subscription page and a subscription
 * page for anonymous users.
 */
function simplenews_admin_users_form(&$form_state, $snid = NULL) {
/*
  if (isset($snid) && $_POST['op'] == t('Delete')) {
    // Kill destination redirect.
    unset($_REQUEST['destination']);
    drupal_goto('admin/content/newsletters/users/delete/'. $snid);
  }
*/
  $form = simplenews_subscription_manager_form($snid);
  $form['#redirect'] = 'admin/content/newsletters/users';
  return $form;
}

/**
 * Forms API callback; delete user subscription form. Form to delete user from
 * all newsletters.
 */
function simplenews_admin_users_delete(&$form_state, $snid = NULL) {
  if (!isset($snid)) {
    drupal_not_found();
    return;
  }

  $subscription = simplenews_get_subscription(array('snid' => $snid));
  $form = array();
  $form['snid'] = array('#type' => 'value', '#value' => $snid);

  return confirm_form($form,
    t('Are you sure you want to remove %mail from all newsletter subscription lists?', array('%mail' => $subscription->mail)),
    'admin/content/newsletters/users',
    t('This action will remove %mail from all newsletter subscription lists. To unsubscribe this user from a particular newsletter, press Cancel and edit this user.', array('%mail' => $subscription->mail)),
    t('Delete'),
    t('Cancel')
  );
}

/**
 * Forms API callback; delete user subscription submit.
 */
function simplenews_admin_users_delete_submit($form, &$form_state) {
  if ($form_state['values']['confirm']) {
    $account = db_fetch_object(db_query('SELECT mail FROM {simplenews_subscriptions} WHERE snid = %d', $form_state['values']['snid']));
    simplenews_delete_subscription($form_state['values']['snid']);
    drupal_set_message(t('The user %user was successfully deleted from the subscription list.', array('%user' => $account->mail)));
    // TODO: move redirect to simplenews_admin_users_delete()
    $form_state['redirect'] = 'admin/content/newsletters/users';
  }
}




?>