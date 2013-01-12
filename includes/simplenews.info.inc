<?php

/*
 * @file
 * Metadata controllers for simplenews entities.
 */

/**
 * Metadata controller for the simplenews_newsletter entity.
 *
 * @ingroup newsletter
 */
class SimplenewsNewsletterMetadataController extends EntityDefaultMetadataController {

  /**
   * Overrides EntityDefaultMetadataController::entityPropertyInfo().
   */
  function entityPropertyInfo() {
    $info = parent::entityPropertyInfo();
    $properties = &$info[$this->type]['properties'];

    $properties['format']['options list'] = 'simplenews_format_options';
    $properties['priority']['options list'] = 'simplenews_get_priority';
    $properties['new_account']['options list'] = 'simplenews_new_account_options';
    $properties['opt_inout']['options list'] = 'simplenew_opt_inout_options';
    $properties['receipt']['type'] = 'boolean';
    $properties['block']['type'] = 'boolean';
    $properties['from_name']['label'] = t('From name');
    $properties['from_address']['label'] = t('From address');
    $properties['new_account']['labe'] = t('New account');
    $properties['email_subject']['label'] = t('E-mail subject');
    $properties['opt_inout']['label'] = t('Opt-in/out method');

    return $info;
  }
}

/**
 * Metadata controller for the simplenews_subscriber entity.
 *
 * @ingroup subscriber
 */
class SimplenewsSubscriberMetadataController extends EntityDefaultMetadataController {

  /**
   * Overrides EntityDefaultMetadataController::entityPropertyInfo().
   */
  function entityPropertyInfo() {
    $info = parent::entityPropertyInfo();
    $properties = &$info[$this->type]['properties'];
    $properties['activated']['type'] = 'boolean';
    $properties['mail']['label'] = t('E-mail');
    $properties['uid']['type'] = 'user';
    $properties['uid']['label'] = t('User');
    $properties['language']['type'] = 'language';
    $properties['created']['type'] = 'date';
    $properties['simplenews_subscriptions'] = array(
      'label' => t('Simplenews Subscriptions'),
      'description' => t('The simplenews newsletters the party is subscribed to.'),
      'type' => 'list<simplenews_newsletter>',
      'getter callback' => 'simplenews_property_simplenews_subscriptions_getter_callback',
    );

    return $info;
  }
}
