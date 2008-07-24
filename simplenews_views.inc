<?php
// $Id$

/**
 * @file simplenews_views.inc
 * Interface between simplenews.module and views.module.
 */

views_include_handlers();

/**
 * Implementation of hook_views_data().
 */
function simplenews_views_data() {
  // Basic table information.

  // Define the base group of this table. Fields that don't
  // have a group defined will go into this field by default.
  $data['simplenews_newsletters']['table']['group']  = t('Simplenews');

  $data['simplenews_newsletters']['table']['base'] = array(
    'field' => 'nid',
    'title' => t('Node'),
    'help' => t('Using nodes as newsletters.'),
  );

  $data['simplenews_newsletters']['table']['join']['node']['handler'] = 'views_join';
  $data['simplenews_newsletters']['table']['join']['node']['arguments'] = array('simplenews_newsletters', 'node', 'nid', 'nid');
  $data['simplenews_newsletters']['table']['group'] = t('Simplenews newsletter');

  // ----------------------------------------------------------------
  // Fields

  // newsletter send status
  $data['simplenews_newsletters']['s_status'] = array(
    'title' => t('Send status'), // The item it appears as on the UI,
    'help' => t('Newsletter send status. Not send, Pending (being send or waiting for cron to run), Send.'), // The help that appears on the UI,
    'field' => array(
      'field' => 's_status', // the real field
      'group' => t('Simplenews'), // The group it appears in on the UI,
      'handler' => 'simplenews_handler_send_status',
    ),
  );
  return $data;
}

/**
 * A handler to provide proper displays for newsletter send status.
 *
 * Allows for display of not send/pending/send.
 */
class simplenews_handler_send_status extends views_handler_field {
  /**
   * Provides status select list
   */
  function options_form(&$form, &$form_state) {
    $form['status'] = array(
      '#type' => 'select',
      '#title' => t('Status'),
      '#default_value' => $this->data->status['status'],
      '#options' => array(
        0 => t('Not send'),
        1 => t('Pending'),
        2 => t('Send'),
      ),
    );
  }
}
