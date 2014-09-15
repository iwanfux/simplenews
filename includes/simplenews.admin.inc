<?php

/**
 * @file
 * Newsletter admin, subscription admin, simplenews settings
 *
 * @ingroup simplenews
 */
use Drupal\Component\Utility\String;
use Drupal\Core\Form\FormStateInterface;

/**
 * Menu callback: Admin form for sent and draft newsletters.
 *
 * @see simplenews_admin_newsletter_submit()
 */
function simplenews_admin_newsletter_issues($form, FormStateInterface $form_state, $action = 'sent') {
  // @todo Fix the delete operation

  $form['filter'] = simplenews_issue_filter_form();
  $form['#submit'][] = 'simplenews_issue_filter_form_submit';
  $form['filter']['#theme'] = 'simplenews_filter_form';
  $form['admin'] = simplenews_admin_issues();

  return $form;
}

/**
 * Generate issue filters
 */
function simplenews_issue_filters() {
  // Newsletter filter
  $filters['newsletter'] = array(
    'title' => t('Subscribed to'),
    'options' => array(
      'all' => t('All newsletters'),
      'newsletter_id-0' => t('Unassigned newsletters'),
    ),
  );
  foreach (simplenews_newsletter_list() as $newsletter_id => $name) {
    $filters['newsletter']['options']['newsletter_id-' . $newsletter_id] = $name;
  }

  return $filters;
}

/**
 * Return form for issue filters.
 *
 * @see simplenews_issue_filter_form_submit()
 */
function simplenews_issue_filter_form() {
  // Current filter selections in $session var; stored at form submission
  // Example: array('newsletter' => 'all')
  $session = isset($_SESSION['simplenews_issue_filter']) ? $_SESSION['simplenews_issue_filter'] : _simplenews_issue_filter_default();
  $filters = simplenews_issue_filters();

  $form['filters'] = array(
    '#type' => 'fieldset',
    '#title' => t('Show only newsletters which'),
  );

  // Filter values are default
  $form['filters']['newsletter'] = array(
    '#type' => 'select',
    '#title' => $filters['newsletter']['title'],
    '#options' => $filters['newsletter']['options'],
    '#default_value' => $session['newsletter'],
  );
  $form['filters']['buttons']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Filter'),
    '#prefix' => '<span class="spacer" />',
  );
  // Add Reset button if filter is in use
  if ($session != _simplenews_issue_filter_default()) {
    $form['filters']['buttons']['reset'] = array(
      '#type' => 'submit',
      '#value' => t('Reset'),
    );
  }

  return $form;
}

/**
 * Helper function: returns issue filter default settings
 */
function _simplenews_issue_filter_default() {
  return array(
    'newsletter' => 'all',
  );
}

/**
 * Form submit callback for the newsletter issue filter.
 */
function simplenews_issue_filter_form_submit($form, FormStateInterface $form_state) {
  switch ($form_state['values']['op']) {
    case t('Filter'):
      $_SESSION['simplenews_issue_filter'] = array(
        'newsletter' => $form_state['values']['newsletter'],
      );
      break;
    case t('Reset'):
      $_SESSION['simplenews_issue_filter'] = _simplenews_issue_filter_default();
      break;
  }
}

/**
 * Form builder: Builds a list of newsletters with operations.
 *
 * @see simplenews_admin_issues_validate()
 * @see simplenews_admin_issues_submit()
 */
function simplenews_admin_issues() {
  // Build an 'Update options' form.
  $form['options'] = array(
    '#type' => 'fieldset',
    '#title' => t('Update options'),
    '#prefix' => '<div class="container-inline">',
    '#suffix' => '</div>',
  );
  $options = array();
  foreach (module_invoke_all('simplenews_issue_operations') as $operation => $array) {
    $options[$operation] = $array['label'];
  }
  $form['options']['operation'] = array(
    '#type' => 'select',
    '#options' => $options,
    '#default_value' => 'activate',
  );
  $form['options']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Update'),
    '#submit' => array('simplenews_admin_issues_submit'),
    '#validate' => array('simplenews_admin_issues_validate'),
  );

  if (variable_get('simplenews_last_cron', '')) {
    $form['last_sent'] = array(
      '#markup' => '<p>' . format_plural(variable_get('simplenews_last_sent', 0), 'Last batch: 1 mail sent at !time.', 'Last batch: !count mails sent at !time.', array('!time' => format_date(variable_get('simplenews_last_cron', ''), 'small'), '!count' => variable_get('simplenews_last_sent', 0))) . "</p>\n",
    );
  }
  // Table header. Used as tablesort default
  $header = array(
    'title' => array(
      'data' => t('Title'),
      'specifier' => 'title',
      'type' => 'property',
     ),
    'newsletter' => array(
      'data' => t('Newsletter'),
      'specified' => array(
        'field' => variable_get('simplenews_newsletter_field', 'simplenews_newsletter'),
        'column' => 'target_id',
      ),
      'type' => 'field',
    ),
    'created' => array(
      'data' => t('Created'),
      'specifier' => 'created',
      'sort' => 'desc',
      'type' => 'property',
    ),
    'published' => array('data' => t('Published')),
    'sent' => array('data' => t('Sent')),
    'subscribers' => array('data' => t('Subscribers')),
    'operations' => array('data' => t('Operations')),
  );

  $query = new EntityFieldQuery();
  simplenews_build_issue_filter_query($query);
  $result = $query
    ->entityCondition('entity_type', 'node')
    ->tableSort($header)
    ->entityCondition('bundle', simplenews_get_content_types())
    ->pager(30)
    ->execute();

  $nids = !empty($result['node']) ? array_keys($result['node']) : array();
  $options = array();

  module_load_include('inc', 'simplenews', 'includes/simplenews.mail');
  $categories = simplenews_newsletter_list();
  foreach (node_load_multiple($nids) as $node) {
    $subscriber_count = simplenews_count_subscriptions(simplenews_issue_newsletter_id($node));
    $pending_count = simplenews_count_spool(array('entity_id' => $node->nid, 'entity_type' => 'node'));
    $send_status = simplenews_issue_status($node) == SIMPLENEWS_STATUS_SEND_PENDING ? $subscriber_count - $pending_count : theme('simplenews_status', array('source' => 'sent', 'status' => simplenews_issue_status($node)));

    $options[$node->nid] = array(
      'title' => l($node->title, 'node/' . $node->nid),
      'newsletter' => simplenews_issue_newsletter_id($node) && isset($categories[simplenews_issue_newsletter_id($node)]) ? $categories[simplenews_issue_newsletter_id($node)] : t('- Unassigned -'),
      'created' => format_date($node->created, 'small'),
      'published' => theme('simplenews_status', array('source' => 'published', 'status' => $node->status)),
      'sent' => $send_status,
      'subscribers' => $subscriber_count,
      'operations' => l(t('edit'), 'node/' . $node->nid . '/edit', array('query' => drupal_get_destination())),
    );
  }

  $form['issues'] = array(
    '#type' => 'tableselect',
    '#header' => $header,
    '#options' => $options,
    '#empty' => t('No newsletters available.'),
  );

  $form['pager'] = array('#theme' => 'pager');

  return $form;
}

/**
 * Implements hook_simplenews_issue_operations().
 */
function simplenews_simplenews_issue_operations() {
// @todo: future ideas: 'pause' => t('Pause sending'), 'resume' => t('Resume sending'), 'cancel' => t('Cancel sending'), 'publish' => t('Publish'), 'unpublish' => t('Unpublish'));
  $operations = array(
    'activate' => array(
      'label' => t('Send'),
      'callback' => 'simplenews_issue_send',
    ),
  );
  return $operations;
}

/**
 * Form vaidate callback for the issue list operations.
 */
function simplenews_admin_issues_validate($form, FormStateInterface $form_state) {
  if (isset($form_state['input']['operation'])) {
    $nids = array_keys(array_filter($form_state['input']['issues']));
    if (empty($nids)) {
      form_set_error('', t('No items selected.'));
    }
  }
}

/**
 * Form submit callback for the issue operations.
 */
function simplenews_admin_issues_submit($form, FormStateInterface $form_state) {
  // Call operation functions as defined in hook_simplenews_issue_operations().
  $operations = module_invoke_all('simplenews_issue_operations');
  $operation = $operations[$form_state['values']['operation']];
  // Filter out unchecked list issues
  $nids = array_filter($form_state['values']['issues']);
  if ($function = $operation['callback']) {
    // Add in callback arguments if present.
    if (isset($operation['callback arguments'])) {
      $args = array_merge(array($nids), $operation['callback arguments']);
    }
    else {
      $args = array($nids);
    }
    call_user_func_array($function, $args);
  }
  else {
    // We need to rebuild the form to go to a second step. For example, to
    // show the confirmation form for the deletion of nodes.
    $form_state['rebuild'] = TRUE;
  }
}

/**
 * Callback to send newsletters.
 */
function simplenews_issue_send($nids) {
  $sent_nodes = array();
  foreach (node_load_multiple($nids) as $node) {
    if (simplenews_issue_status($node) != SIMPLENEWS_STATUS_SEND_NOT) {
      continue;
    }

    if ($node->status == NODE_NOT_PUBLISHED) {
      simplenews_issue_update_sent_status($node, SIMPLENEWS_COMMAND_SEND_PUBLISH);
      drupal_set_message(t('Newsletter %title is unpublished and will be sent on publish.', array('%title' => $node->title)));
      continue;
    }

    simplenews_add_node_to_spool($node);
    $sent_nodes[$node->nid] = $node->title;
  }

  // If there were any newsletters sent, display a message.
  if (!empty($sent_nodes)) {
    $conditions = array('entity_id' => array_keys($sent_nodes), 'entity_type' => 'node');
    // Attempt to send immediatly, if configured to do so.
    if (simplenews_mail_attempt_immediate_send($conditions)) {
      drupal_set_message(t('Sent the following newsletters: %titles.', array('%titles' => implode(', ', $sent_nodes))));
    }
    else {
      drupal_set_message(t('The following newsletter are now pending: %titles.', array('%titles' => implode(', ', $sent_nodes))));
    }
  }
}

/**
 * Apply filters for subscription filters based on session.
 *
 * @param $query
 *   A SelectQuery to which the filters should be applied.
 */
function simplenews_build_issue_filter_query(EntityFieldQuery $query) {
  if (isset($_SESSION['simplenews_issue_filter'])) {
    foreach ($_SESSION['simplenews_issue_filter'] as $key => $value) {
      switch ($key) {
        case 'list':
        case 'newsletter':
          if ($value != 'all') {
            list($key, $value) = explode('-', $value, 2);
            $query->fieldCondition('simplenews_issue', 'target_id', $value);
          }
          break;
      }
    }
  }
}

/**
 * Menu callback: list admin form with list of available list categories.
 *
 * @ingroup forms
 * @see simplenews_admin_newsletter_categories_submit()
 * @see theme_simplenews_admin_newsletter_categories()
 */
function simplenews_admin_categories() {
  $form['#tree'] = TRUE;
  if ($newsletters = entity_load('simplenews_newsletter')) {
    foreach ($newsletters as $newsletter) {
      $form[$newsletter->newsletter_id]['#newsletter'] = $newsletter;
      $form[$newsletter->newsletter_id]['name'] = array('#markup' => check_plain($newsletter->name));
      $form[$newsletter->newsletter_id]['weight'] = array('#type' => 'weight', '#delta' => 10, '#default_value' => $newsletter->weight);
      $form[$newsletter->newsletter_id]['edit'] = array(
        '#type' => 'link',
        '#title' => t('edit newsletter'),
        '#href' => "admin/config/services/simplenews/categories/$newsletter->newsletter_id/edit",
      );
    }
  }

  // Only make this form include a submit button and weight if more than one
  // newsletter exists.
  if (count($newsletters) > 1) {
    $form['submit'] = array('#type' => 'submit', '#value' => t('Save'));
  }
  elseif (!empty($newsletters)) {
    $form[$newsletter->newsletter_id]['weight'] = array('#type' => 'value', '#value' => 0);
  }
  return $form;
}

/**
 * Form submit callback for the simplenews categories.
 */
function simplenews_admin_categories_submit($form, FormStateInterface $form_state) {
  foreach ($form_state['values'] as $newsletter_id => $newsletter) {
    if (is_numeric($newsletter_id) && $form[$newsletter_id]['#newsletter']->weight != $newsletter['weight']) {
      $form[$newsletter_id]['#newsletter']->weight = $newsletter['weight'];
      $form[$newsletter_id]['#newsletter']->save();
    }
  }
  drupal_set_message(t('Your configuration has been saved.'));
}

/**
 * Form builder function, display a list of simplenews categories.
 *
 * @ingroup theming
 */
function theme_simplenews_admin_categories($variables) {
  $form = $variables['form'];

  $rows = array();

  foreach (element_children($form) as $key) {
    if (isset($form[$key]['name'])) {
      $newsletter = &$form[$key];

      $row = array();
      $row[] = drupal_render($newsletter['name']);
      if (isset($newsletter['weight'])) {
        $newsletter['weight']['#attributes']['class'] = array('simplenews-newsletter-weight');
        $row[] = drupal_render($newsletter['weight']);
      }
      $row[] = drupal_render($newsletter['edit']);
      $rows[] = array('data' => $row, 'class' => array('draggable'));
    }
  }

  $header = array(t('Newsletter name'));
  if (isset($form['submit'])) {
    $header[] = t('Weight');
    drupal_add_tabledrag('simplenews-newsletter', 'order', 'self', 'simplenews-newsletter-weight');
  }
  $header[] = array('data' => t('Operations'), 'colspan' => '3');
  return theme('table', array('header' => $header, 'rows' => $rows, 'empty' => t('No newsletter categories. <a href="@link">Add newsletter</a>.', array('@link' => url('admin/config/services/simplenews/add'))), 'attributes' => array('id' => 'simplenews-newsletter'))) . drupal_render_children($form);
}

/**
 * Menu callback: Delete newsletter.
 *
 * @see simplenews_admin_newsletter_delete_submit()
 */
function simplenews_admin_newsletter_delete($form, FormStateInterface $form_state, $newsletter) {
  // Store some newsletter values for submit handling.
  $form = array();
  $form['newsletter_id'] = array('#type' => 'value', '#value' => $newsletter->newsletter_id);
  $form['name'] = array('#type' => 'value', '#value' => $newsletter->name);

  $form['notice'] = array(
    '#markup' => '<p><strong>' . t('Note: All subscriptions associated with this newsletter will be lost.') . '</strong></p>',
  );

  return confirm_form($form, t('Are you sure you want to delete the newsletter %name?', array('%name' => $newsletter->name)), 'admin/config/services/simplenews', t('This action cannot be undone.'), t('Delete'), t('Cancel')
  );
}

/**
 * Menu callback: subscription administration.
 */
function simplenews_admin_subscription() {
  // @todo Fix the delete operation

  $form['filter'] = simplenews_subscription_filter_form();
  $form['#submit'][] = 'simplenews_subscription_filter_form_submit';
  $form['filter']['#theme'] = 'simplenews_filter_form';
  $form['admin'] = simplenews_subscription_list_form();

  return $form;
}

/**
 * Implements hook_simplenews_subscription_operations().
 */
function simplenews_simplenews_subscription_operations() {
  $operations = array(
    'activate' => array(
      'label' => t('Activate'),
      'callback' => 'simplenews_subscription_activate',
      'callback arguments' => array(SIMPLENEWS_SUBSCRIPTION_ACTIVE),
    ),
    'inactivate' => array(
      'label' => t('Inactivate'),
      'callback' => 'simplenews_subscription_activate',
      'callback arguments' => array(SIMPLENEWS_SUBSCRIPTION_INACTIVE),
    ),
    'delete' => array(
      'label' => t('Delete'),
      'callback' => 'simplenews_subscription_delete_multiple',
    ),
  );
  return $operations;
}

/**
 * Callback function to (de-)activate subscriptions.
 *
 * @param $snids
 *   Array of snid's to be activated.
 * @param $status
 *   Status of the subscription (0, 1).
 */
function simplenews_subscription_activate($snids, $status) {
  foreach (simplenews_subscriber_load_multiple($snids) as $subscriber) {
    $subscriber->activated = $status;
    simplenews_subscriber_save($subscriber);
  }
}

/**
 * Count number of subscribers per newsletter list.
 *
 * @param $newsletter_id
 *   The newsletter id.
 *
 * @return
 *   Number of subscribers.
 */
function simplenews_count_subscriptions($newsletter_id) {
  $subscription_count = &drupal_static(__FUNCTION__);

  if (isset($subscription_count[$newsletter_id])) {
    return $subscription_count[$newsletter_id];
  }

  // @todo: entity query + aggregate
  $query = db_select('simplenews_subscriber__subscriptions', 'ss');
  $query->leftJoin('simplenews_subscriber', 'sn', 'sn.id = ss.entity_id');
  $query->condition('subscriptions_target_id', $newsletter_id)
    ->condition('sn.status', 1)
    ->condition('ss.subscriptions_status', SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED);
  $subscription_count[$newsletter_id] = $query->countQuery()->execute()->fetchField();
  return $subscription_count[$newsletter_id];
}

/**
 * Return a status image.
 *
 * @param $variables An associative array containing:
 *   source: Source which status will be displayed ('published', 'activated', 'sent')
 *   status: Status of the source (0 or 1)
 *
 * @return string
 *   HTML string containing an image tag.
 *
 * @ingroup theming
 */
function theme_simplenews_status($variables) {
  $source = $variables['source'];
  $status = $variables['status'];
  switch ($source) {
    case 'published':
      $images = array(
        0 => 'images/sn-saved.png',
        1 => 'images/sn-sent.png',
      );
      $title = array(
        0 => t('Not published'),
        1 => t('Published'),
      );
      break;
    case 'activated':
      $images = array(
        0 => 'images/sn-saved.png',
        1 => 'images/sn-sent.png',
      );
      $title = array(
        0 => t('Inactive: no newsletters will be sent'),
        1 => t('Active: user will receive newsletters'),
      );
      break;
    case 'sent':
      $images = array(
        SIMPLENEWS_STATUS_SEND_PENDING => 'images/sn-cron.png',
        SIMPLENEWS_STATUS_SEND_READY => 'images/sn-sent.png',
      );
      $title = array(
        SIMPLENEWS_STATUS_SEND_NOT => t('Not yet sent'),
        SIMPLENEWS_STATUS_SEND_PENDING => t('Currently sending by cron'),
        SIMPLENEWS_STATUS_SEND_READY => t('Sent'),
        SIMPLENEWS_STATUS_SEND_PUBLISH => t('Send on publish'),
      );
      break;
  }
  // Build the output
  if (isset($images[$status])) {
    $img_vars = array(
      '#theme' => 'image',
      '#uri' => drupal_get_path('module', 'simplenews') . '/' . $images[$status],
      '#alt' => $title[$status],
      '#title' => $title[$status],
      '#getsize' => TRUE,
    );
    $output = drupal_render($img_vars);
  }
  else {
    $output = String::checkPlain($title[$status]);
  }
  return $output;
}

/**
 * Theme simplenews issues and subscriptions filter form.
 *
 * @ingroup theming
 */
function theme_simplenews_filter_form($variables) {
  $form = $variables['form'];

  $output = '<div id="simplenews-admin-filter">';
  $output .= drupal_render($form['filters']);
  $output .= '</div>';
  $output .= drupal_render_children($form);
  return $output;
}

/**
 * Theme simplenews subscriptions administration filter form.
 *
 * @ingroup theming
 */
function theme_simplenews_subscription_filter_form($variables) {
  $form = $variables['form'];

  $output = '<div id="simplenews-subscription-filter">';
  $output .= drupal_render($form['filters']);
  $output .= '</div>';
  $output .= drupal_render_children($form);
  return $output;
}

/**
 * Menu callback; Newsletter tab page.
 */
function simplenews_node_tab_page($node) {
  drupal_set_title(t('<em>Newsletter</em> @title', array('@title' => $node->title)), PASS_THROUGH);
  return drupal_get_form('simplenews_node_tab_send_form', $node);
}

/**
 * @todo
 */
function simplenews_node_tab_send_form($form, FormStateInterface $form_state, $node) {

  $status = simplenews_issue_status($node);

  $form = array();
  // We will need the node
  $form['nid'] = array(
    '#type' => 'value',
    '#value' => $node->nid,
  );

  // @todo delete this fieldset?
  $form['simplenews'] = array(
    '#type' => 'fieldset',
    '#title' => t('Send newsletter'),
    '#collapsible' => FALSE,
    '#collapsed' => FALSE,
    '#tree' => TRUE,
  );

  // Show newsletter sending options if newsletter has not been send yet.
  // If send a notification is shown.
  if ($status == SIMPLENEWS_STATUS_SEND_NOT || $status == SIMPLENEWS_STATUS_SEND_PUBLISH) {

    $options = array(
      SIMPLENEWS_COMMAND_SEND_TEST => t('Send one test newsletter to the test address'),
    );

    // Add option to send on publish when the node is unpublished.
    if ($node->status == NODE_NOT_PUBLISHED) {
      $options[SIMPLENEWS_COMMAND_SEND_PUBLISH] = t('Send newsletter when published');
    }
    else {
      $options[SIMPLENEWS_COMMAND_SEND_NOW] = t('Send newsletter');
    }

    if ($status == SIMPLENEWS_STATUS_SEND_PUBLISH) {
      $send_default = SIMPLENEWS_STATUS_SEND_PUBLISH;
    }
    else {
      $send_default = variable_get('simplenews_send', SIMPLENEWS_COMMAND_SEND_TEST);
    }
    $form['simplenews']['send'] = array(
      '#type' => 'radios',
      '#title' => t('Send newsletter'),
      '#default_value' => $send_default,
      '#options' => $options,
      '#attributes' => array(
        'class' => array('simplenews-command-send'),
      ),
    );

    $address_default = variable_get('site_mail', ini_get('sendmail_from'));
    if (variable_get('simplenews_test_address_override', 0)) {
      $form['simplenews']['test_address'] = array(
        '#type' => 'textfield',
        '#title' => t('Test email addresses'),
        '#description' => t('A comma-separated list of email addresses to be used as test addresses.'),
        '#default_value' => variable_get('simplenews_test_address', $address_default),
        '#size' => 60,
        '#maxlength' => 128,
      );
    }
    else {
      $form['simplenews']['test_address'] = array(
        '#type' => 'value',
        '#value' => variable_get('simplenews_test_address', $address_default),
      );
    }

    $default_handler = isset($form_state['values']['simplenews']['recipient_handler']) ? $form_state['values']['simplenews']['recipient_handler'] : simplenews_issue_handler($node);
    $options = simplenews_handler_field_allowed_values();
    $form['simplenews']['recipient_handler'] = array(
      '#type' => 'select',
      '#title' => t('Recipients'),
      '#description' => t('Please select to configure who to send the email to.'),
      '#options' => $options,
      '#default_value' => $default_handler,
      '#access' => count($options) > 1,
      '#ajax' => array(
        'callback' => 'simplenews_node_tab_send_form_handler_update',
        'wrapper' => 'recipient-handler-settings',
        'method' => 'replace',
        'effect' => 'fade',
      ),
    );

    // Get the handler class
    ctools_include('plugins');
    $handler = ctools_get_plugins('simplenews', 'recipient_handlers', $default_handler);
    $class = $handler['class'];
    $settings = simplenews_issue_handler_settings($node);

    if (method_exists($class, 'settingsForm')) {
      $element = array(
        '#parents' => array('simplenews', 'recipient_handler_settings'),
        '#prefix' => '<div id="recipient-handler-settings">',
        '#suffix' => '</div>',
      );

      $form['simplenews']['recipient_handler_settings'] = $class::settingsForm($element, $settings);
    }
    else {
      $form['simplenews']['recipient_handler']['#suffix'] = '<div id="recipient-handler-settings"></div>';
    }
  }
  else {
    $form['simplenews']['none'] = array(
      '#type' => 'checkbox',
      '#return_value' => 0,
      '#attributes' => array(
        'checked' => 'checked',
        'disabled' => 'disabled',
      ),
    );
    $form['simplenews']['none']['#title'] = ($status == SIMPLENEWS_STATUS_SEND_READY) ? t('This newsletter has been sent') : t('This newsletter is pending');
    return $form;
  }
  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Submit'),
  );

  return $form;
}

/**
 * Return the updated recipient handler settings form.
 */
function simplenews_node_tab_send_form_handler_update($form, FormStateInterface $form_state) {
  return empty($form['simplenews']['recipient_handler_settings']) ? array('#markup' => '<div id="recipient-handler-settings"></div>') : $form['simplenews']['recipient_handler_settings'];
}

/**
 * @todo
 */
function simplenews_node_tab_send_form_validate($form, FormStateInterface $form_state) {
  $values = $form_state['values'];
  $node = node_load($values['nid']);

  // Validate recipient handler settings.
  if (!empty($form['simplenews']['recipient_handler_settings'])) {
    ctools_include('plugins');
    $handler = $values['simplenews']['recipient_handler'];
    $handler = ctools_get_plugins('simplenews', 'recipient_handlers', $handler);
    $class = $handler['class'];

    if (method_exists($class, 'settingsFormValidate')) {
      $class::settingsFormValidate($form['simplenews']['recipient_handler_settings'], $form_state);
    }
  }

  $default_address = variable_get('simplenews_test_address', variable_get('site_mail', ini_get('sendmail_from')));
  $mails = array($default_address);
  if (isset($values['simplenews']['send']) && $values['simplenews']['send'] == SIMPLENEWS_COMMAND_SEND_TEST && variable_get('simplenews_test_address_override', 0)) {
    // @todo Can we simplify and use only two kind of messages?
    if (!empty($values['simplenews']['test_address'])) {
      $mails = explode(',', $values['simplenews']['test_address']);
      foreach ($mails as $mail) {
        $mail = trim($mail);
        if ($mail == '') {
          form_set_error('simplenews][test_address', t('Test email address is empty.'));
        }
        elseif (!valid_email_address($mail)) {
          form_set_error('simplenews][test_address', t('Invalid email address "%mail".', array('%mail' => $mail)));
        }
      }
    }
    else {
      form_set_error('simplenews][test_address', t('Missing test email address.'));
    }
  }
  $form_state['test_addresses'] = $mails;
}

/**
 * @todo
 */
function simplenews_node_tab_send_form_submit($form, FormStateInterface $form_state) {
  $values = $form_state['values'];
  $node = node_load($values['nid']);

  // Save the recipient handler and it's settings.
  simplenews_issue_handler($node, $values['simplenews']['recipient_handler']);

  if (!empty($form['simplenews']['recipient_handler_settings'])) {
    ctools_include('plugins');
    $handler = $values['simplenews']['recipient_handler'];
    $handler = ctools_get_plugins('simplenews', 'recipient_handlers', $handler);
    $class = $handler['class'];

    if (method_exists($class, 'settingsFormSubmit')) {
      $settings = $class::settingsFormSubmit($form['simplenews']['recipient_handler_settings'], $form_state);
      simplenews_issue_handler_settings($node, $settings);
    }
  }

  // Send newsletter to all subscribers or send test newsletter
  module_load_include('inc', 'simplenews', 'includes/simplenews.mail');
  if ($values['simplenews']['send'] == SIMPLENEWS_COMMAND_SEND_NOW) {
    simplenews_add_node_to_spool($node);
    // Attempt to send immediatly, if configured to do so.
    if (simplenews_mail_attempt_immediate_send(array('entity_id' => $node->nid, 'entity_type' => 'node'))) {
      drupal_set_message(t('Newsletter %title sent.', array('%title' => $node->title)));
    }
    else {
      drupal_set_message(t('Newsletter %title pending.', array('%title' => $node->title)));
    }
  }
  elseif ($values['simplenews']['send'] == SIMPLENEWS_COMMAND_SEND_TEST) {
    simplenews_send_test($node, $form_state['test_addresses']);
  }

  // If the selected command is send on publish, just set the newsletter status.
  if ($values['simplenews']['send'] == SIMPLENEWS_COMMAND_SEND_PUBLISH) {
    simplenews_issue_status($node, SIMPLENEWS_STATUS_SEND_PUBLISH);
    drupal_set_message(t('The newsletter will be sent when the content is published.'));
  }

  node_save($node);
}
