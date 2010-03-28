<?php
// $Id$

/**
 * @file
 * Views interface for simplenews.
 */

/**
 * Implements hook_views_data().
 */
function simplenews_views_data() {
  // Basic table information.

  // Define the base group of this table. Fields that don't
  // have a group defined will go into this field by default.
  $data['simplenews_newsletter']['table']['group']  = t('Simplenews');

  $data['simplenews_newsletter']['table']['base'] = array(
    'field' => 'nid',
    'title' => t('Node'),
    'help' => t('Using nodes as newsletter.'),
  );

  // joins
  $data['simplenews_newsletter']['table']['join'] = array(
    //...to the node table
    'node' => array(
      'left_field' => 'nid',
      'field' => 'nid',
    ),
  );

  // ----------------------------------------------------------------
  // Fields

  // newsletter sent status
  $data['simplenews_newsletter']['status'] = array(
    'title' => t('Sent status'),
    // The item it appears as on the UI,
    'help' => t('Newsletter sent status: Not sent, Pending (being sent or waiting for cron to run), Sent.'),
    // The help that appears on the UI,
    'field' => array(
      'handler' => 'simplenews_handler_field_newsletter_status',
      'click sortable' => TRUE,
    ),
    'filter' => array(
      'handler' => 'simplenews_handler_filter_newsletter_status',
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
  );
  return $data;
}

/**
 * Implements hook_views_handlers().
 */
function simplenews_views_handlers() {
  return array(
    // field handlers
    'simplenews_handler_field_newsletter_status',

    // filter handlers
    'simplenews_handler_filter_newsletter_status',
  );
}
