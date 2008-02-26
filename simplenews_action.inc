<?php
// $Id$

/**
 * @file simplenews_action.inc
 * Interface between simplenews.module and action.module.
 */

/*
* Implementation of hook_action_info()
*/
function simplenews_action_info() {
  return array(
//    'simplenews_send_newsletter_action' => array(
//      'description' => t('(Re)send single simplenews newsletter'),
//      'type' => 'simplenews',
//      'configurable' => TRUE,
//      'hooks' => array(
//        'cron' => array('run'),
//        'user' => array('insert'),
//      ),
//    ),
    'simplenews_cron_action' => array(
      'description' => t('Send pending simplenews newsletters'),
      'type' => 'simplenews',
      'configurable' => FALSE,
      'hooks' => array( 
        'cron' => array('run'),
      ),
    ),
    'simplenews_subscribe_user_action' => array(
      'type' => 'simplenews',
      'description' => t('Subscribe the user to a newsletter'),
      'configurable' => TRUE,
      'hooks' => array(
        'user' => array('insert', 'update'),
      ),
    ),
    'simplenews_unsubscribe_user_action' => array(
      'type' => 'simplenews',
      'description' => t('Unsubscribe the user from a newsletter'),
      'configurable' => TRUE,
      'hooks' => array(
        'user' => array('update', 'delete'),
      ),
    ),
  );
}


/**
 * Implementation of a Drupal action.
 * Send a simplenews newsletter.
 *   op = cron: Send newsletter tid to all users
 *      = user: Send newsletter tid to current user
 */
//function simplenews_send_newsletter_action(&$object, $context = array()) {
//  if ($contect['op'] == 'cron' || $contect['op'] == 'user') {
//    $mail = array();
//    if ($contect['op'] == 'user') {
//      global $user;
//      $mail = array($user->mail);
//    }
//    //TODO: needs modification of _simplenews_send()
//    //TODO: include 'resend' option
//    if (isset($object->nid)) {
//      $object->simplenews['send'] = SIMPLENEWS_COMMAND_SEND_NOW;
//      $object->simplenews['s_status'] = SIMPLENEWS_STATUS_SEND_PENDING;
//      //TODO: Save node with status. Use node_save($node) ??
//      //TODO: this sending is non-selective. All pending newsletters are send!!!!!
//      _simplenews_send(FALSE);
//      watchdog('action', 'Newsletter issue %title being send.', array('%title' => check_plain($object->title)));
//    }
//  }
//}

/**
 * Implementation of a configurable Drupal action. Send newsletter
 */
//function simplenews_send_newsletter_action_form($context) {
//  if (!isset($context['newsletter'])) {
//    $context['newsletter'] = array();
//  }
//  if (!isset($context['newsletter_issue'])) {
//    $context['newsletter_issue'] = array();
//  }
//  
//  $tree = taxonomy_get_tree(variable_get('simplenews_vid', ''));
//  $terms = array();
//  foreach ($tree as $newsletter) {
//    $terms[$newsletter->tid] = check_plain($newsletter->name);
//  }
//  $form['newsletter'] = array(
//    '#title' => t('Newsletter'),
//    '#type' => 'select',
//    '#options' => $terms,
//    '#description' => t('The newsletter series'),
//  );
//  //TODO: AHAH function to fead above selection result into below $tid
//  $result = taxonomy_select_nodes(array_keys($terms));
//  $nids = array();
//  while ($node = db_fetch_object($result)) {
//    $nids[$node->nid] = check_plain($node->title);
//  }
//  $form['newsletter_issue'] = array(
//    '#title' => t('Newsletter issue'),
//    '#type' => 'select',
//    '#options' => $nids,
//    '#description' => t('The newsletter issue to (re)send'),
//  );
//  $form['resend'] = array(
//    '#title' => t('Resend newsletter'),
//    '#type' => 'checkbox',
//    '#default_value' => FALSE,
//    '#description' => t('Send the newsletter when already sended. When not checked a previously send newsletter will not be send.'),
//  );
//  
//  return $form;
//}

/**
 * Validate simplenews_send_newsletter_action form submissions.
 */
//function simplenews_send_newsletter_action_validate($form, $form_state) {
//  $form_values = $form_state['values'];
//  // Validate the send newsletter form.
//  if (empty($form_values['newsletter_issue'])) {
//    form_set_error('newsletter_issue', t('Please select newsletter issue (after selecting a newsletter).'));
//  }
//}

/**
 * Process simplenews_send_newsletter_action form submissions.
 */
//function simplenews_send_newsletter_action_submit($form, $form_state) {
//  $form_values = $form_state['values'];
//
//  $params = array(
//    'nid' => $form_values['newsletter_issue'],
//    'title' => $form['newsletter_issue']['#options'][$form_values['newsletter_issue']],
//  );
//  return $params;
//}

/**
 * Implementation of a Drupal action.
 * Simplenews cron handling
 */
function simplenews_cron_action(&$object, $context = array()) {
  _simplenews_send(FALSE);
  watchdog('action', 'Simplenews cron executed.');
}

/**
 * Implementation of a Drupal action.
 * Subscribe the user to a newsletter
 */
function simplenews_subscribe_user_action(&$object, $context = array()) {
  if ($context['hook'] == 'user') {
    if (isset($context['tid'])) {
      // This action is only called in the context of user. User data is in $context.
      $account = $context['account'];
      simplenews_subscribe_user($account->mail, $context['tid'], FALSE);
      drupal_set_message(t('You have been subscribed to newsletter %newsletter.', array('%newsletter' => check_plain($context['name']))));
      watchdog('action', 'User %name subscribed to newsletter %newsletter.', array('%name' => check_plain($account->name), '%newsletter' => check_plain($context['name'])));
    }
  }
}

/**
 * Implementation of a configurable Drupal action.
 */
function simplenews_subscribe_user_action_form($context) {
  if (!isset($context['newsletter'])) {
    $context['newsletter'] = array();
  }
  
  $tree = taxonomy_get_tree(variable_get('simplenews_vid', ''));
  $terms = array();
  foreach ($tree as $newsletter) {
    $terms[$newsletter->tid] = check_plain($newsletter->name);
  }
  $form['newsletter'] = array(
    '#title' => t('Newsletter'),
    '#type' => 'select',
    '#options' => $terms,
    '#description' => t('The newsletter series the user will be subscribed to.'),
  );
  return $form;
}

/**
 * Process simplenews_subscribe_user_action form submissions.
 */
function simplenews_subscribe_user_action_submit($form, $form_state) {
  $form_values = $form_state['values'];

  $params = array(
    'tid' => $form_values['newsletter'],
    'name' => $form['newsletter']['#options'][$form_values['newsletter']],
  );
  return $params;
}

/**
 * Implementation of a Drupal action.
 * Unsubscribe the user from a newsletter
 */
function simplenews_unsubscribe_user_action(&$object, $context = array()) {
  if ($context['hook'] == 'user') {
    if (isset($context['tid'])) {
      // This action is only called in the context of user. User data is in $context.
      $account = $context['account'];
      //TODO: Unsubscribing should be done by simplenews_unsubscribe_user but simplenews_get_user_subscription fails because the user is already removed
      if ($result = db_fetch_object(db_query("SELECT snid FROM {simplenews_subscriptions} WHERE mail = '%s'", $account->mail))) {
        db_query('DELETE FROM {simplenews_snid_tid} WHERE snid = %d AND tid = %d', $result->snid, $context['tid']);

        // Clean up simplenews_subscriptions if no more newsletter subscriptions.
        if (!db_result(db_query("SELECT COUNT(*) FROM {simplenews_snid_tid} t WHERE t.snid = %d", $result->snid))) {
          db_query('DELETE FROM {simplenews_subscriptions} WHERE snid = %d', $result->snid);
        }
      }
      drupal_set_message(t('You have been removed from the %newsletter subscription list.', array('%newsletter' => check_plain($context['name']))));
      watchdog('action', 'User %name unsubscribed from newsletter %newsletter.', array('%name' => check_plain($account->name), '%newsletter' => check_plain($context['name'])));
    }
  }
}

/**
 * Implementation of a configurable Drupal action. 
 */
function simplenews_unsubscribe_user_action_form($context) {
  if (!isset($context['newsletter'])) {
    $context['newsletter'] = array();
  }
  
  $tree = taxonomy_get_tree(variable_get('simplenews_vid', ''));
  $terms = array();
  foreach ($tree as $newsletter) {
    $terms[$newsletter->tid] = check_plain($newsletter->name);
  }
  $form['newsletter'] = array(
    '#title' => t('Newsletter'),
    '#type' => 'select',
    '#options' => $terms,
    '#description' => t('The newsletter series the user will be unsubscribed from.'),
  );
  return $form;
}

/**
 * Process simplenews_unsubscribe_user_action form submissions.
 */
function simplenews_unsubscribe_user_action_submit($form, $form_state) {
  $form_values = $form_state['values'];

  $params = array(
    'tid' => $form_values['newsletter'],
    'name' => $form['newsletter']['#options'][$form_values['newsletter']],
  );
  return $params;
}
