<?php
// $Id $
/**
 * @file simplenews.admin.inc
 */

//TODO: consider split into content-admin, subscription-admin(?) and settings

function simplenews_admin_news(&$form_state, $action = 'sent') {
  //_simplenews_create_default_newsletter_term();
  $vid = variable_get('simplenews_vid', '');
  $form = array();
  $form['simplenews_issue_filter'] = array(
    '#type' => 'fieldset',
    '#title' => t('Show issues from'),
    '#collapsible' => FALSE,
    '#prefix' => '<div class="container-inline">',
    '#suffix' => '</div>',
  );
  $header = array(t('Title'), t('Newsletter'), t('Date created'), t('Published'), t('Sent'), t('Edit'));
  list($names, $queries) = array_values(simplenews_get_filter('simplenews_newsletters_filter'));
  $filter = isset($_SESSION['simplenews_newsletters_filter']) ? $_SESSION['simplenews_newsletters_filter'] : '';
  if ($action == 'notsent') {
    $form['simplenews_issue_filter']['filter'] = array(
      '#type' => 'select',
      '#options' => $names,
      '#default_value' => $filter,
    );
    $query = "SELECT DISTINCT n.*, s.s_status FROM {node} n INNER JOIN {simplenews_newsletters} s ON n.nid = s.nid WHERE s.s_status = %d". $queries[$filter] ." ORDER BY n.created DESC";
    $count_query = "SELECT COUNT(n.nid) FROM {node} n INNER JOIN {simplenews_newsletters} s ON n.nid = s.nid WHERE s.s_status = %d". $queries[$filter];
  }
  else {
    $form['simplenews_issue_filter']['filter'] = array(
      '#type' => 'select',
      '#options' => $names,
      '#default_value' => $filter,
    );
    $query = "SELECT n.*, s.s_status FROM {node} n INNER JOIN {simplenews_newsletters} s ON n.nid = s.nid WHERE s.s_status > %d". $queries[$filter] ." ORDER BY n.created DESC";
    $count_query = "SELECT COUNT(n.nid) FROM {node} n INNER JOIN {simplenews_newsletters} s ON n.nid = s.nid WHERE s.s_status > %d". $queries[$filter];
  }
  $result = pager_query(db_rewrite_sql($query, 'n', 'nid', array(SIMPLENEWS_STATUS_SEND_NOT)), 10, 0, db_rewrite_sql($count_query, 'n', 'nid', array(SIMPLENEWS_STATUS_SEND_NOT)), 0);
  $rows = array();
  while ($node = db_fetch_object($result)) {
    $terms = array_keys(taxonomy_node_get_terms_by_vocabulary($node, $vid, 'name'));
    $rows[] = array(l($node->title, 'node/'. $node->nid),
                    isset($terms[0]) ? $terms[0] : t('n/a'),
                    format_date($node->created, 'small'),
                    theme('simplenews_status', $node->status, 'published'),
                    theme('simplenews_status', $node->s_status, 'sent'),
                    l(t('edit'), 'node/'. $node->nid .'/edit', array(), drupal_get_destination()));
  }
  if ($pager = theme('pager', NULL, 10, 0)) {
    $rows[] = array(array('data' => $pager, 'colspan' => '6'));
  }
  if (!$rows) {
    $rows[] = array(array('data' => t('No newsletters available.'), 'colspan' => '6'));
  }
  $form['simplenews_issue_filter']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Filter'),
  );

  $table = theme('table', $header, $rows);

  $form['table'] = array('#value' => $table);
  return $form;
}

function simplenews_admin_news_submit($form, &$form_state) {
  _simplenews_set_filter('simplenews_newsletters_filter', $form_state['values']);
}

/**
 * Store filter values in session var
 * 
 * @param $type identification string
 * @param $form_values array of values to be stored
 */
function _simplenews_set_filter($type, $values) {
  if (empty($_SESSION[$type])) {
    $_SESSION[$type] = 'all';
  }
  $op = $values['op'];
  if ($op == t('Filter') && isset($values['filter'])) {
    $_SESSION[$type] = $values['filter'];
  }
}

function simplenews_types_overview() {
  $rows = array();
  $header = array(t('Newsletter name'), t('Operations'));

  foreach (taxonomy_get_tree(variable_get('simplenews_vid', '')) as $term) {
    $rows[] = array($term->name, l(t('edit newsletter'), 'admin/content/newsletters/types/edit/'. $term->tid));
  }

  if (count($rows) == 0) {
    $rows[] = array(array('data' => t('There are currently no newsletter series.'), 'colspan' => 2));
  }
  return theme('table', $header, $rows);
}

/**
 * Forms API callback; displays newsletter (term) add/edit form.
 */
//TODO rework this form to be able to set newsletter options when creating the newsletter.
function simplenews_admin_types_form(&$form_state, $tid = NULL) {
  if (isset($tid)) {
    $term = taxonomy_get_term($tid);

    // If the given term is not a newsletter, don't allow editing.
    if (variable_get('simplenews_vid', '') != $term->vid) {
      drupal_not_found();
      return;
    }

    // Redirect on a delete operation for posterity's sake.
    if (isset($form_state['post']['op']) && $form_state['post']['op'] == t('Delete')) {
      drupal_goto('admin/content/newsletters/types/delete/'. $term->tid);
    }

  }
  else {
    // Add form so choose simplenews vocabulary.
    $term = (object)array('vid' => variable_get('simplenews_vid', ''));
  }

  //TODO: move info text to hook_help. Add help for newsletter settings after submitting the newsletter.
  $form['info'] = array('#value' => t('You can create different newsletters (or subjects) to categorize your news (e.g. Cats news, Dogs news, ...).'));
  $form['name'] = array(
    '#type' => 'textfield',
    '#title' => t('Newsletter name'),
    '#size' => 50,
    '#maxlength' => 128,
    '#description' => t('This name is used to identify the newsletter.'),
    '#required' => TRUE,
    '#default_value' => isset($term->name) ? $term->name : '',
  );
  $form['description'] = array(
    '#type' => 'textarea',
    '#title' => t('Description'),
    '#cols' => 60,
    '#rows' => 5,
    '#description' => t('The description can be used to provide more information.'),
    '#default_value' => isset($term->description) ? $term->description : '',
  );
  $form['weight'] = array(
    '#type' => 'weight',
    '#title' => t('Weight'),
    '#delta' => 10,
    '#description' => t('In listings, the heavier (with a higher weight value) terms will sink and the lighter terms will be positioned nearer the top.'),
    '#default_value' => isset($term->weight) ? $term->weight : 0,
  );

  if (isset($term->tid)) {
    //TODO: enable below form at creation of newsletter
    // Store the term ID to indicate a newsletter specific form.
    $form['simplenews_newsletter'] = array(
      '#type' => 'hidden',
      '#value' => $term->tid,
    );

    $form['simplenews_sender_information'] = array(
      '#type' => 'fieldset',
      '#title' => t('Sender information'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );
    $form['simplenews_sender_information']['simplenews_from_name_'. $term->tid] = array(
      '#type' => 'textfield',
      '#title' => t('From name'),
      '#size' => 60,
      '#maxlength' => 128,
      '#default_value' => variable_get('simplenews_from_name_'. $term->tid, variable_get('site_name', 'Drupal')),
    );
    $form['simplenews_sender_information']['simplenews_from_address_'. $term->tid] = array(
      '#type' => 'textfield',
      '#title' => t('From e-mail address'),
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => TRUE,
      '#default_value' => variable_get('simplenews_from_address_'. $term->tid, variable_get('site_mail', ini_get('sendmail_from'))),
    );
    $form['simplenews_hyperlinks'] = array(
      '#type' => 'fieldset',
      '#title' => t('HTML to text conversion'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#description' => t('When your newsletter is sent as plain text, these options will determine how the conversion to text is performed.'),
    );
/*
    Support for inline links removed. Restoring this function will depend on user requests.
    $form['simplenews_hyperlinks']['simplenews_hyperlinks_'. $term->tid] = array(
      '#type' => 'radios',
      '#title' => t('Hyperlink conversion'),
      '#options' => array(t('Append hyperlinks as a numbered reference list'), t('Display hyperlinks inline with the text')),
      '#default_value' => variable_get('simplenews_hyperlinks_'. $term->tid, 1),
    );
*/
  }
  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save'),
    '#weight' => 25,
  );
  $form['vid'] = array(
    '#type' => 'hidden',
    '#value' => isset($term->vid) ? $term->vid : variable_get('simplenews_vid', ''),
  );

  // If we are editing a newsletter term, show delete option. When the submit
  // passes the $form_state['values']['tid'] to taxonomy_save_term() it will delete the
  // term for some reason.
  if (isset($term->tid)) {
    $form['delete'] = array(
      '#type' => 'submit',
      '#value' => t('Delete'),
      '#weight' => 30,
    );
    $form['tid'] = array(
      '#type' => 'value',
      '#value' => $term->tid,
    );
  }
  
  // Return to Newsletter list page after submit
  $form['#redirect'] = 'admin/content/newsletters/types';

  return $form;
}

function simplenews_admin_types_form_validate($form, &$form_state) {
  if (isset($form_state['values']['simplenews_newsletter'])) {
    $tid = $form_state['values']['simplenews_newsletter'];
    $field_name = 'simplenews_from_address_'. $tid;

    if (!valid_email_address($form_state['values'][$field_name])) {
      form_set_error($field_name, t("The sender's e-mail address you supplied is not valid."));
    }
  }
}

/**
 * Forms API callback; handles newsletter (term) form submit.
 */
function simplenews_admin_types_form_submit($form, &$form_state) {
    // Redirect on a delete operation for posterity's sake.
    if (isset($form_state['post']['op']) && $form_state['post']['op'] == t('Delete')) {
      drupal_goto('admin/content/newsletters/types/delete/'. $term->tid);
    }

  switch (taxonomy_save_term($form_state['values'])) {
    case SAVED_NEW:
      drupal_set_message(t('Created new term %name.', array('%name' => $form_state['values']['name'])));
      break;
    case SAVED_UPDATED:
      drupal_set_message(t('Updated term %name.', array('%name' => $form_state['values']['name'])));
      break;
  }

  // Store newsletter specific settings when editing an existing newsletter
  if (isset($form_state['values']['simplenews_newsletter'])) {
    $tid = $form_state['values']['simplenews_newsletter'];
    variable_set('simplenews_from_name_'. $tid, $form_state['values']['simplenews_from_name_'. $tid]);
    variable_set('simplenews_from_address_'. $tid, $form_state['values']['simplenews_from_address_'. $tid]);
    //variable_set('simplenews_hyperlinks_'. $tid, $form_state['values']['simplenews_hyperlinks_'. $tid]);
  }
}

/**
 * Forms API callback; newsletter (term) delete form.
 */
function simplenews_admin_types_delete(&$form_state, $tid = NULL) {
  if (!isset($tid)) {
    drupal_not_found();
    return;
  }
  $term = taxonomy_get_term($tid);
  $form = array();
  $form['tid'] = array(
    '#type' => 'value',
    '#value' => $tid,
  );
  $form['notice'] = array(
    '#value' => '<p><strong>'. t('Note: All subscriptions associated with this newsletter will be lost.') .'</strong></p>',
  );
  $form['#redirect'] = 'admin/content/newsletters/types';

  return confirm_form($form,
    t('Are you sure you want to delete %title?', array('%title' => $term->name)),
    'admin/content/newsletters/types',
    t('This action cannot be undone.'),
    t('Delete'),
    t('Cancel')
  );
}

/**
 * Forms API callback; handles submit for newsletter (term) delete form.
 */
function simplenews_admin_types_delete_submit($form, &$form_state) {
  if ($form_state['values']['confirm']) {
    $term = taxonomy_get_term($form_state['values']['tid']);
    taxonomy_del_term($form_state['values']['tid']);
    drupal_set_message(t('Newsletter %title has been deleted.', array('%title' => $term->name)));
  }
}

/**
 * Delete multiple subscriptions
 */
function simplenews_subscription_multiple_delete_confirm() {
  // Subscriptions to be deleted are passed via session var.
  $snids = $_SESSION['simplenews_subscriptions_delete'];

  $form['snids'] = array('#prefix' => '<ul>', '#suffix' => '</ul>', '#tree' => TRUE);
  // array_filter returns only elements with TRUE values
  foreach (array_filter($snids) as $snid => $value) {
    $mail = db_result(db_query('SELECT mail FROM {simplenews_subscriptions} WHERE snid = %d', $snid));
    $form['snids'][$snid] = array(
      '#type' => 'hidden', 
      '#value' => $snid, 
      '#prefix' => '<li>', 
      '#suffix' => check_plain($mail) ."</li>\n"
    );
  }

  $form['operation'] = array(
    '#type' => 'hidden',
    '#value' => 'delete',
  );
  $form['#submit'][] = 'simplenews_subscription_multiple_delete_confirm_submit';

  return confirm_form($form,
                      t('Are you sure you want to delete these subscriptions?'),
                      'admin/content/newsletters/users',
                      t('This action cannot be undone.'),
                      t('Delete all'),
                      t('Cancel')
                      );
}

function simplenews_subscription_multiple_delete_confirm_submit($form, &$form_state) {
  if ($form_state['values']['confirm']) {
    foreach ($form_state['values']['snids'] as $snid => $value) {
      simplenews_delete_subscription($snid);
    }
    drupal_set_message(t('The subscriptions have been deleted.'));
  }
  return '';
}

function simplenews_subscription_list_add() {
  $form['emails'] = array(
    '#type' => 'textarea',
    '#title' => t('E-mail addresses'),
    '#cols' => 60,
    '#rows' => 5,
    '#description' => t('Supply a comma separated list of e-mail addresses to be added to the list. Spaces between commas and addresses are allowed.'),
  );
  $newsletters = array();
  foreach (taxonomy_get_tree(variable_get('simplenews_vid', '')) as $newsletter) {
    $newsletters[$newsletter->tid] = $newsletter->name;
  }
  $form['newsletters'] = array(
    '#type' => 'fieldset',
    '#title' => t('Subscribe imported addresses to the following newsletter(s)'),
    '#collapsible' => FALSE,
  );
  $form['newsletters']['newsletters'] = array(
    '#type' => 'checkboxes',
    '#options' => $newsletters,
  );
  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Import'),
  );
  return $form;
}

function simplenews_subscription_list_add_validate($form, &$form_state) {
  $checked_newsletters = array_filter($form_state['values']['newsletters']);
  if (!$checked_newsletters) {
    form_set_error('newsletters', t('You must select at least one newsletter.'));
  }
}

function simplenews_subscription_list_add_submit($form, &$form_state) {
  $tree = taxonomy_get_tree(variable_get('simplenews_vid', ''));
  $added = array();
  $invalid = array();
  $checked_newsletters = array_filter($form_state['values']['newsletters']);

  $emails = explode(",", $form_state['values']['emails']);
  foreach ($emails as $email) {
    $email = trim($email);
    if (valid_email_address($email)) {
      foreach ($checked_newsletters as $tid) {
        $newsletter = taxonomy_get_term($tid);
        simplenews_subscribe_user($email, $newsletter->tid, FALSE);
        $added[] = $email;
      }
    }
    else {
      $invalid[] = $email;
    }
  }
  if ($added) {
    $added = implode(", ", $added);
    drupal_set_message(t('The following addresses were added or updated: %added.', array('%added' => $added)));

    $newsletter_names = array();
    foreach ($checked_newsletters as $tid) {
      $newsletter = taxonomy_get_term($tid);
      $newsletter_names[] = $newsletter->name;
    }
    drupal_set_message(t('The addresses were subscribed to the following newsletters: %newsletters.', array('%newsletters' => implode(', ', $newsletter_names))));
  }
  else {
    drupal_set_message(t('No addresses were added.'));
  }
  if ($invalid) {
    $invalid = implode(", ", $invalid);
    drupal_set_message(t('The following addresses were invalid: %invalid.', array('%invalid' => $invalid)), 'error');
  }
}

function simplenews_subscription_list_export() {
  $tree = taxonomy_get_tree(variable_get('simplenews_vid', ''));
  $form['simplenews_status'] = array(
    '#type' => 'fieldset',
    '#title' => t('Status'),
    '#description' => t('Select at least 1 status'),
    '#collapsible' => FALSE,
  );
  $form['simplenews_status']['active'] = array(
    '#type' => 'checkbox',
    '#title' => t('Active users'),
    '#return_value' => 1,
  );
  $form['simplenews_status']['inactive'] = array(
    '#type' => 'checkbox',
    '#title' => t('Inactive users'),
    '#return_value' => 1,
  );
  if ($tree) {
    $form['simplenews_newsletter'] = array(
      '#type' => 'fieldset',
      '#title' => t('Subscribed to'),
      '#description' => t('Select at least 1 newsletter'),
      '#collapsible' => FALSE,
    );
    foreach ($tree as $newsletter) {
      $form['simplenews_newsletter']['tid_'. $newsletter->tid] = array(
        '#type' => 'checkbox',
        '#title' => $newsletter->name,
        '#return_value' => 1,
      );
    }
  }
  $form['emails'] = array(
    '#type' => 'textarea',
    '#title' => t('E-mail addresses'),
    '#cols' => 60,
    '#rows' => 5,
    '#default_value' => t('No search performed'),
  );
  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Export'),
  );
  $form['#after_build'] = array('simplenews_admin_export_after_build');
  $form['#redirect'] = FALSE;
  return $form;
}

function simplenews_admin_export_after_build($form, $form_element) {
  if (isset($form_element['values']['op']) && $form_element['values']['op'] == t('Export')) {
    $tree = taxonomy_get_tree(variable_get('simplenews_vid', ''));
    if (isset($form_element['values']['active']) && $form_element['values']['active'] == 1) {
      $where[] = 's.activated = 1';
    }
    if ($form_element['values']['inactive'] == 1) {
      $where[] = 's.activated = 0';
    }
    $where = isset($where) ? implode(' OR ', $where) : NULL;
    if ($tree) {
      foreach ($tree as $newsletter) {
        if ($form_element['values']['tid_'. $newsletter->tid]) {
          $where_tid[] = 't.tid = '. $newsletter->tid;
        }
      }
    }
    $where_tid = isset($where_tid) ? implode(' OR ', $where_tid) : NULL;
    if ($where && $where_tid) {
      $query = 'SELECT DISTINCT s.mail FROM {simplenews_subscriptions} s INNER JOIN {simplenews_snid_tid} t ON s.snid = t.snid WHERE ('. $where .') AND ('. $where_tid .')';
      $result = db_query($query);
      while ($mail = db_fetch_object($result)) {
        $mails[] = $mail->mail;
      }
    }
    if (isset($mails)) {
      $exported_mails = implode(", ", $mails);
    }
    else {
      $exported_mails = t('No addresses were found.');
    }
    $form['emails']['#value'] = $exported_mails;
  }
  return $form;
}

/**
 * Menu callback: subscription administration.
 */
function simplenews_subscription_admin($form_state) {
  // Delete subscriptions requires delete confirmation. This is handled with a different form
  if (isset($form_state['post']['operation']) && $form_state['post']['operation'] == 'delete') {
    $destination = drupal_get_destination();
    $_SESSION['simplenews_subscriptions_delete'] = $form_state['post']['snids'];
    // Note: we redirect from admin/content/newsletters/users to admin/content/newsletters/subscriptions/delete to make the tabs disappear.
    drupal_goto("admin/content/newsletters/subscriptions/delete", $destination);  
  }
  
  $form = simplenews_subscription_filter_form();
  $form['admin'] = simplenews_subscription_list_form();

  return $form;
}

function simplenews_subscription_list_form() {
//  global $form_values;

  // Table header. Used as tablesort default
  $header = array(
    array('data' => t('E-mail'), 'field' => 'ss.mail', 'sort' => 'asc'),
    array('data' => t('Username'), 'field' => 'u.name'), 
    array('data' => t('Status'), 'field' => 'ss.activated'),
    t('Operations')
  );

  // Data collection with filter and sorting applied
  $filter = simplenews_build_subscription_filter_query();
  $query = 'SELECT DISTINCT ss.*, u.name FROM {simplenews_subscriptions} ss INNER JOIN {users} u ON ss.uid = u.uid INNER JOIN {simplenews_snid_tid} s ON ss.snid = s.snid'. $filter['where'];

  // $count_query used to count distinct records only
  $count_query = preg_replace('/SELECT.*FROM /', 'SELECT COUNT(DISTINCT ss.mail) FROM ', $query);
  $query .= tablesort_sql($header);
  $result = pager_query($query, 30, 0, $count_query);
  
  // Update options
  $form['options'] = array(
    '#type' => 'fieldset',
    '#title' => t('Update options'),
    '#prefix' => '<div class="container-inline">',
    '#suffix' => '</div>',
  );
  $form['options']['operation'] = array(
    '#type' => 'select', 
    '#options' => array(
      'activate' => 'Activate',
      'inactivate' => 'Inactivate',
      'delete' => 'Delete',
    ),  
    '#default_value' => 'activate');
  $form['options']['submit'] = array(
    '#type' => 'submit', 
    '#value' => t('Update'),
    '#submit' => array('simplenews_subscription_list_form_submit'),
  );
  
  $snids = array();
  // Subscription table and table pager
  while ($subscription = db_fetch_object($result)) {
    $snids[$subscription->snid] = '';
    $form['mail'][$subscription->snid] = array('#value' => $subscription->mail);
    $form['name'][$subscription->snid] =  array('#value' => isset($subscription->uid) ? l($subscription->name, 'user/'. $subscription->uid) : $subscription->name);
    $form['status'][$subscription->snid] = array('#value' => theme('simplenews_status', $subscription->activated, 'activated'));
    $form['operations'][$subscription->snid] = array('#value' => l(t('edit'), 'admin/content/newsletters/users/edit/'. $subscription->snid, array(), drupal_get_destination()));
  }
  $form['snids'] = array('#type' => 'checkboxes', '#options' => $snids);
  $form['pager'] = array('#value' => theme('pager', NULL, 30, 0));
  $form['#theme'] = 'simplenews_subscription_list';
  return $form;
}

function simplenews_subscription_list_form_validate($form, &$form_state) {
  if (isset($form_state['values']['operation'])) {
    $snids = array_filter($form_state['values']['snids']);
    if (empty($snids)) {
      form_set_error('', t('No items selected.'));
    }
  }
}

/**
 * Submit the subscription administration update form.
 */
function simplenews_subscription_list_form_submit($form, &$form_state) {
  if (isset($form_state['values']['operation'])) {
    $snids = array_filter($form_state['values']['snids']);
    $args = array($snids);
    switch ($form_state['values']['operation']) {
      case 'activate':
        call_user_func_array('simplenews_activate_subscription', $args);
        drupal_set_message(t('The update has been performed.'));
        break;
      case 'inactivate':
        call_user_func_array('simplenews_inactivate_subscription', $args);
        drupal_set_message(t('The update has been performed.'));
        break;
    }
  }
}

/**
 * Generate settings form.
 */
function simplenews_admin_settings(&$form_state) {
  $address_default = variable_get('site_mail', ini_get('sendmail_from'));
  $form = array();

  $form['simplenews_default_options'] = array(
    '#type' => 'fieldset',
    '#title' => t('Default newsletter options'),
    '#collapsible' => TRUE,
    '#collapsed' => FALSE,
    '#description' => t('These options will be the defaults for new newsletters, but can be overridden in the newsletter editing form.'),
  );
  $description = t('Select the default newsletter sending format.');
  if (!module_exists('mimemail')) {
        $description .= t(' Mime Mail module is required to send newsletters in HTML format.');
  }
  $form['simplenews_default_options']['simplenews_format'] = array(
    '#type' => 'select',
    '#title' => t('Format'),
    '#options' => _simplenews_format_options(),
    '#description' => $description,
    '#default_value' => variable_get('simplenews_format', 'plain'),
  );
  $form['simplenews_default_options']['simplenews_priority'] = array(
    '#type' => 'select',
    '#title' => t('Priority'),
    '#options' => array(0 => t('none'), 1 => t('highest'), 2 => t('high'), 3 => t('normal'), 4 => t('low'), 5 => t('lowest')),
    '#description' => t('Note that e-mail priority is ignored by a lot of e-mail programs.'),
    '#default_value' => variable_get('simplenews_priority', 0),
  );
  $form['simplenews_default_options']['simplenews_receipt'] = array(
    '#type' => 'checkbox',
    '#title' => t('Request receipt'),
    '#return_value' => 1,
    '#default_value' => variable_get('simplenews_receipt', 0),
    '#description' => t('Request a Read Receipt from your newsletters. A lot of e-mail programs ignore these so it is not a definitive indication of how many people have read your newsletter.'),
  );
  $form['simplenews_default_options']['simplenews_send'] = array(
    '#type' => 'radios',
    '#title' => t('Default selection for sending newsletters'),
    '#options' => array(0 => t("Don't send now"), 2 => t('Send one test newsletter to the test address'), 1 => t('Send newsletter')),
    '#default_value' => variable_get('simplenews_send', 0),
  );
  $form['simplenews_test_address'] = array(
    '#type' => 'fieldset',
    '#title' => t('Test addresses options'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#description' => t('Supply a comma-separated list of e-mail addresses to be used as test addresses. The override function allows to override these addresses upon newsletter creation.'),
  );
  $form['simplenews_test_address']['simplenews_test_address'] = array(
    '#type' => 'textfield',
    '#title' => t('Test e-mail address'),
    '#size' => 60,
    '#maxlength' => 128,
    '#default_value' => variable_get('simplenews_test_address', $address_default),
  );
  $form['simplenews_test_address']['simplenews_test_address_override'] = array(
    '#type' => 'checkbox',
    '#title' => t('Allow test address override'),
    '#return_value' => 1,
    '#default_value' => variable_get('simplenews_test_address_override', 0),
  );
  $form['simplenews_sender_info'] = array(
    '#type' => 'fieldset',
    '#title' => t('Sender information'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#description' => t('Default sender address that will only be used for confirmation e-mails. You can specify sender information for each newsletter separately on the newsletter\'s settings page.'),
  );
  $form['simplenews_sender_info']['simplenews_from_name'] = array(
    '#type' => 'textfield',
    '#title' => t('From name'),
    '#size' => 60,
    '#maxlength' => 128,
    '#default_value' => variable_get('simplenews_from_name', variable_get('site_name', 'Drupal')),
  );
  $form['simplenews_sender_info']['simplenews_from_address'] = array(
    '#type' => 'textfield',
    '#title' => t('From e-mail address'),
    '#size' => 60,
    '#maxlength' => 128,
    '#required' => TRUE,
    '#default_value' => variable_get('simplenews_from_address', $address_default),
  );
  $form['simplenews_init_send'] = array(
    '#type' => 'fieldset',
    '#title' => t('Initial send time'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#description' => t('Sets the maximum time in seconds during which newsletters are sent. If not all recipients have been mailed within this time, sending is further handled as a cronjob.') .' <br /> '. t('The highest value in the dropdown list is based on max_execution_time in your php.ini file. Note that if not all previous newsletters have been sent to all recipients yet, these are sent first.'),
  );
  $max_time = array(0, 1, 2, 3, 4);
  for ($i = 5; $i < ini_get('max_execution_time'); $i=$i+5) {
    $max_time[] = $i;
  }
  $form['simplenews_init_send']['simplenews_time'] = array(
    '#type' => 'select',
    '#title' => t('Seconds'),
    '#options' => drupal_map_assoc($max_time),
    '#default_value' => variable_get('simplenews_time', 2),
  );
  $form['simplenews_cron_throttle'] = array(
    '#type' => 'fieldset',
    '#title' => t('Cron throttle'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#description' => t('Sets the numbers of newsletters sent per cron run. Failure to send will also be counted.'),
  );
  $throttle = drupal_map_assoc(array(10, 20, 30, 40, 50, 100, 200, 300, 400, 500, 1000, 2000, 3000, 4000, 5000));
  $throttle[999999] = t('Unlimited');
  $form['simplenews_cron_throttle']['simplenews_throttle'] = array(
    '#type' => 'select',
    '#title' => t('Number of mails'),
    '#options' => $throttle,
    '#default_value' => variable_get('simplenews_throttle', 20),
  );
  $form['simplenews_debug_options'] = array(
    '#type' => 'fieldset',
    '#title' => t('Debugging'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#description' => t('Log outgoing emails in the system log.'),
  );
  $form['simplenews_debug_options']['simplenews_debug'] = array(
    '#type' => 'checkbox',
    '#title' => t('Log outgoing Simplenews emails'),
    '#return_value' => 1,
    '#default_value' => variable_get('simplenews_debug', 0),
    '#description' => t('When checked all outgoing simplenews emails are logged. A logged email does not guarantee that it is send or will be delivered. It only indicates that a message is send to the PHP mail() function. No status information is available of delivery by the PHP mail() function.'),
  );
  return system_settings_form($form);
}

function simplenews_admin_settings_validate($form, &$form_state) {
  if (!valid_email_address($form_state['values']['simplenews_from_address'])) {
    form_set_error($field_name, t("The sender's e-mail address you supplied is not valid."));
  }
}

/**
 * Callback function to activate the specified subscriptions.
 * 
 * @param $snid array of snid's
 */
function simplenews_activate_subscription($snid) {
  db_query('UPDATE {simplenews_subscriptions} SET activated = %d WHERE snid IN(%s)', 1, implode(',', $snid));
}

/**
 * Callback function to inactivate the specified subscriptions.
 * 
 * @param $snid array of snid's
 */
function simplenews_inactivate_subscription($snid) {
  db_query('UPDATE {simplenews_subscriptions} SET activated = %d WHERE snid IN(%s)', 0, implode(',', $snid));
}

/**
 * Built filter selection box options and filter query where clause
 * 
 * @param $type identification string
 * @param $na TRUE for orphaned newsletters
 * 
 * @return array of filter selection box options and related query where clause
 */
function simplenews_get_filter($type, $na = TRUE) {
  //Default data
  $names['all'] = t('all newsletters');
  $queries['all'] = '';
  if ($na) {
    $names['na'] = t('orphaned newsletters');
    $queries['na'] = ' AND s.tid = 0';
  }
  // Data for each newsletter
  foreach (taxonomy_get_tree(variable_get('simplenews_vid', '')) as $newsletter) {
    $names[$newsletter->tid] = $newsletter->name;
    $queries[$newsletter->tid] = ' AND s.tid = '. $newsletter->tid;
  }
  return array($names, $queries);
}

/**
 * Generate subscription filters
 */
function simplenews_subscription_filters() {
  // Newsletter filter
  $filters['newsletter'] = array(
    'title' => t('Subscribed to'),
    'options' => array(
      'all'   => t('All newsletters'),
    ),
  );
  foreach (taxonomy_get_tree(variable_get('simplenews_vid', '')) as $newsletter) {
    $filters['newsletter']['options']['tid-'. $newsletter->tid] = $newsletter->name;
  }
  
  // E-mail filter
  $filters['email'] = array(
    'title' => t('E-mail address'),
  );
  
  return $filters;
}

/**
 * Return form for subscription filters.
 */
function simplenews_subscription_filter_form() {
  // Current filter selections in $session var; stored at form submission
  // Example: array('newsletter' => 'all', 'email' => 'hotmail')
  $session = $_SESSION['simplenews_subscriptions_filter'];
  $session = is_array($session) ? $session : _simplenews_subscription_filter_default();
  $filters = simplenews_subscription_filters();

  $form['filters'] = array(
    '#type' => 'fieldset',
    '#title' => t('Subscription filters'),
    '#collapsible' => FALSE,
    '#prefix' => '<div class="simplenews-subscription-filter">',
    '#suffix' => '</div>',
  );
  
  // Filter values are default
  $form['filters']['newsletter'] = array(
    '#type' => 'select',
    '#title' => $filters['newsletter']['title'],
    '#options' => $filters['newsletter']['options'],
    '#default_value' => $session['newsletter'],
  );
  $form['filters']['email'] = array(
    '#type' => 'textfield',
    '#title' => $filters['email']['title'],
    '#default_value' => $session['email'],
  );
  $form['filters']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Filter'),
    '#prefix' => '<span class="spacer" />',
  );
  // Add Reset button if filter is in use
  if ($session != _simplenews_subscription_filter_default()) {
    $form['filters']['reset'] = array(
      '#type' => 'submit', 
      '#value' => t('Reset')
    );
  }
  $form['#submit'][] = 'simplenews_subscription_filter_form_submit';
  
  return $form;
}

/**
 * Helper function: returns subscription filter default settings
 */
function _simplenews_subscription_filter_default() {
  return array('newsletter' => 'all', 'email' => '');
}

function simplenews_subscription_filter_form_submit($form, &$form_state) {
  switch ($form_state['values']['op']) {
    case t('Filter'):
      $_SESSION['simplenews_subscriptions_filter'] = array(
        'newsletter' => $form_state['values']['newsletter'],
        'email' => $form_state['values']['email'],
      );
      break;
    case t('Reset'):
      $_SESSION['simplenews_subscriptions_filter'] = _simplenews_subscription_filter_default();
      break;
  }
}

/**
 * Build query for subscription filters based on session var content. 
 * 
 * @return array of SQL query parts 
 *   array('where' => $where, 'join' => $join, 'args' => $args)
 */
function simplenews_build_subscription_filter_query() {
  // Variables $args and $join are currently not used but left in for future extensions
  $where = $args = array();
  $join = '';
  
  // Build query
  if (isset($_SESSION['simplenews_subscriptions_filter'])) {
    foreach ($_SESSION['simplenews_subscriptions_filter'] as $key => $value) {
    switch ($key) {
        case 'newsletter':
          if ($value != 'all') {
            list($key, $value) = explode('-', $value, 2);
            $where[] = 's.'. $key .' = '. $value;
          }
          break;
        case 'email':
          if (!empty($value)) {
            $where[] = 'ss.mail LIKE "%%'. db_escape_string($value) .'%%"';
          }
          break;
      }
      $args[] = $value;
    }
  }
  
  // All conditions are combined with AND
  $where = empty($where) ? '' : ' AND '. implode(' AND ', $where);
 
  return array('where' => $where, 'join' => $join, 'args' => $args);
}

/**
 * Theme subscription administration overview.
 */
function theme_simplenews_subscription_list($form) {
  // Subscription table header
  $header = array(
    theme('table_select_header_cell'), 
    array('data' => t('E-mail'), 'field' => 'ss.mail', 'sort' => 'asc'),
    array('data' => t('Username'), 'field' => 'u.name'), 
    array('data' => t('Status'), 'field' => 'ss.activated'),
    t('Operations')
  );
    
  // Subscription table
  $output = drupal_render($form['options']);
  if (isset($form['mail']) && is_array($form['mail'])) {
    foreach (element_children($form['mail']) as $key) {
      $row = array();
      $row[] = drupal_render($form['snids'][$key]);
      $row[] = drupal_render($form['mail'][$key]);
      $row[] = drupal_render($form['name'][$key]);
      $row[] = drupal_render($form['status'][$key]);
      $row[] = drupal_render($form['operations'][$key]);
      $rows[] = $row;
    }
  }
  else  {
    $rows[] = array(array('data' => t('No subscriptions available.'), 'colspan' => '4'));
  }

  // Render table header, pager and form
  $output .= theme('table', $header, $rows);
  if ($form['pager']['#value']) {
    $output .= drupal_render($form['pager']);
  }
  $output .= drupal_render($form);

  return $output;
}

/**
 * Process variables to format the simplenews status indication.
 *
 * $variables contains:
 * - $source
 * - $status
 *
 * @see simplenews-status.tpl.php
 * @see theme_simplenews_status()
 */
function template_preprocess_simplenews_status(&$variables) {
  switch ($variables['source']) {
    case 'published':
      $images = array(0 => 'sn-saved.png', 1 => 'sn-sent.png');
      $title = array(0 => t('Not published'), 1 => t('Published'));
      break;
    case 'activated':
      $images = array(0 => 'sn-saved.png', 1 => 'sn-sent.png');
      $title = array(0 => t('Inactive: no newsletters will be sent'), 1 => t('Active: user will receive newsletters'));
      break;
    case 'sent':
      $images = array(
        SIMPLENEWS_STATUS_SEND_NOT => 'sn-saved.png',
        SIMPLENEWS_STATUS_SEND_PENDING => 'sn-cron.png',
        SIMPLENEWS_STATUS_SEND_READY => 'sn-sent.png'
      );
      $title = array(
        SIMPLENEWS_STATUS_SEND_NOT => t('Not sent'),
        SIMPLENEWS_STATUS_SEND_PENDING => t('Currently sending by cron'),
        SIMPLENEWS_STATUS_SEND_READY => t('Sent')
      );
      break;
  }
  
  // Build the variables
  $variables['image'] = base_path() . drupal_get_path('module', 'simplenews') .'/'. $images[$variables['status']];
  $variables['alt'] = $variables['title'] = $title[$variables['status']];
}
