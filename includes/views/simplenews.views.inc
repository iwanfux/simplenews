<?php

/**
 * @file
 * Views interface for simplenews.
 */

/**
 * Implements hook_views_data().
 */
function simplenews_views_data() {

  /* ------------ Definitions for Simplenews mailspool ---------------------- */

  $data['simplenews_mail_spool']['table'] = array(
    'base' => array(
      'field' => 'msid',
      'title' => t('Simplenews mailspool'),
      'help' => t('Spool for temporary storage of newsletter emails.'),
      'weight' => 10,
      'database' => 'default',
    ),
    'group' => t('Simplenews spool'),
  );

  $data['simplenews_mail_spool']['msid'] = array(
    'title' => t('Ms ID'),
    'help' => t('The primary identifier for a mail spool record.'),
    'field' => array(
      'handler' => 'views_handler_field_numeric',
      'click sortable' => TRUE,
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_numeric',
      'allow empty' => TRUE,
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_numeric',
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
  );

  $data['simplenews_mail_spool']['mail'] = array(
    'title' => t('Subscriber'),
    'help' => t('The formatted email address of mail message receipient.'),
    'field' => array(
      'handler' => 'views_handler_field',
      'click sortable' => TRUE,
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_string',
      'allow empty' => TRUE,
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_string',
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
  );

  $data['simplenews_mail_spool']['nid'] = array(
    'title' => t('Node ID'),
    'help' => t('The {node}.nid of this newsletter.'),
    'field' => array(
      'handler' => 'views_handler_field_numeric',
      'click sortable' => TRUE,
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_numeric',
      'allow empty' => TRUE,
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_numeric',
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
    'relationship' => array(
      'handler' => 'views_handler_relationship',
      'base' => 'node',
      'base field' => 'nid',
      'label' => t('Node'),
    ),
  );

  $data['simplenews_mail_spool']['newsletter_id'] = array(
    'title' => t('Newsletter ID'),
    'help' => t('The newsletter_id this newsletter issue belongs to.'),
    'field' => array(
      'handler' => 'views_handler_field_numeric',
      'click sortable' => TRUE,
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_numeric',
      'allow empty' => TRUE,
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_numeric',
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
    'relationship' => array(
      'handler' => 'views_handler_relationship',
      'base' => 'simplenews_newsletter',
      'base field' => 'newsletter_id',
      'label' => t('Newsletter'),
    ),
  );

  $data['simplenews_mail_spool']['snid'] = array(
    'title' => t('Subscriber ID'),
    'help' => t('The {simplenews_subscriber}.snid foreign key for this spool'),
    'field' => array(
      'handler' => 'views_handler_field_numeric',
      'click sortable' => TRUE,
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_numeric',
      'allow empty' => TRUE,
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_numeric',
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
    'relationship' => array(
      'handler' => 'views_handler_relationship',
      'base' => 'simplenews_subscriber',
      'base field' => 'snid',
      'label' => t('Subscriber'),
    ),
  );

  $data['simplenews_mail_spool']['status'] = array(
    'title' => t('Sent status'),
    'help' => t('The sent status of the email (0 = hold, 1 = pending, 2 = done).'),
    'field' => array(
      'handler' => 'simplenews_handler_field_newsletter_status',
      'click sortable' => TRUE,
    ),
    'filter' => array(
      'handler' => 'simplenews_handler_filter_newsletter_status',
      'allow empty' => TRUE,
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_numeric',
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
  );

  $data['simplenews_mail_spool']['error'] = array(
    'title' => t('Error'),
    'help' => t('A boolean indicating whether an error occured while sending the email.'),
    'field' => array(
      'handler' => 'views_handler_field_boolean',
      'click sortable' => TRUE,
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_boolean_operator',
      'allow empty' => TRUE,
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_numeric',
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
  );

  $data['simplenews_mail_spool']['timestamp'] = array(
    'title' => t('Timestamp'),
    'help' => t('The time status was set or changed.'),
    'field' => array(
      'handler' => 'views_handler_field_date',
      'click sortable' => TRUE,
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_date',
      'allow empty' => TRUE,
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_date',
    ),
    'sort' => array(
      'handler' => 'views_handler_sort_date',
    ),
  );

  $data['simplenews_mail_spool']['data'] = array(
    'title' => t('Data'),
    'help' => t('A serialized array of name value pairs that are related to the email address.'),
    'field' => array(
      'handler' => 'views_handler_field',
      'click sortable' => TRUE,
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_string',
      'allow empty' => TRUE,
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_string',
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
  );

  /* ------------ Definitions for Simplenews subscription ---------------------- */

  $data['simplenews_subscription']['table'] = array(
    'base' => array(
      'field' => 'snid',
      'title' => t('Simplenews subscription'),
      'help' => t('Contains all Subscriptions of every Simplenews Newsletters.'),
      'weight' => 10,
      'database' => 'default',
    ),
    'group' => t('Simplenews subscription'),
  );

  $data['simplenews_subscription']['table']['join'] = array(
    'simplenews_newsletter' => array(
      'left_field' => 'newsletter_id',
      'field' => 'newsletter_id',
    ),
    'simplenews_subscriber' => array(
      'left_field' => 'snid',
      'field' => 'snid',
    ),
  );

  $data['simplenews_subscription']['snid'] = array(
    'title' => t('Subscriber ID'),
    'help' => t('The {simplenews_subscriptions}.snid who is subscribed.'),
    'field' => array(
      'label' => 'TEST',
      'handler' => 'views_handler_field_numeric',
      'click sortable' => TRUE,
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_numeric',
      'allow empty' => TRUE,
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_numeric',
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
    'relationship' => array(
      'handler' => 'views_handler_relationship',
      'base' => 'simplenews_subscriber',
      'base field' => 'snid',
      'label' => t('Subscriber'),
    ),
  );

  $data['simplenews_subscription']['newsletter_id'] = array(
    'title' => t('Newsletter ID'),
    'help' => t('The newsletter ({simplenews_newsletter}.newsletter_id) the subscriber is subscribed to.'),
    'field' => array(
      'handler' => 'views_handler_field_numeric',
      'click sortable' => TRUE,
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_numeric',
      'allow empty' => TRUE,
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_numeric',
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
    'relationship' => array(
      'handler' => 'views_handler_relationship',
      'base' => 'simplenews_newsletter',
      'base field' => 'newsletter_id',
      'label' => t('Newsletter'),
    ),
  );

  $data['simplenews_subscription']['status'] = array(
    'title' => t('Status'),
    'help' => t('A flag indicating whether the user is subscribed (1) or unsubscribed (0).'),
    'field' => array(
      'handler' => 'views_handler_field_boolean',
      'click sortable' => TRUE,
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_boolean_operator',
      'allow empty' => TRUE,
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_numeric',
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
  );

  $data['simplenews_subscription']['timestamp'] = array(
    'title' => t('Timestamp'),
    'help' => t('UNIX timestamp of when the user is (un)subscribed.'),
    'field' => array(
      'handler' => 'views_handler_field_date',
      'click sortable' => TRUE,
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_date',
      'allow empty' => TRUE,
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_date',
    ),
    'sort' => array(
      'handler' => 'views_handler_sort_date',
    ),
  );

  $data['simplenews_subscription']['source'] = array(
    'title' => t('Source'),
    'help' => t('The source via which the user is (un)subscribed.'),
    'field' => array(
      'handler' => 'views_handler_field',
      'click sortable' => TRUE,
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_string',
      'allow empty' => TRUE,
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_string',
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
  );

  return $data;
}
