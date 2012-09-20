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
