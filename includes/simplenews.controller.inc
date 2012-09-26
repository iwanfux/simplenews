<?php

/**
 * @file
 * Seimplenews entity controllers definitions.
 */

/**
 * Controller for simplenews_newsletter entity.
 *
 * @ingroup newsletter
 */
class SimplenewsNewsletterController extends EntityAPIController {

  /**
   * Overrides EntityAPIController::create().
   */
  public function create(array $values = array()) {
    $values += array(
      'name' => '',
      'description' => '',
      'format' => variable_get('simplenews_format', 'plain'),
      'priority' => variable_get('simplenews_priority', SIMPLENEWS_PRIORITY_NONE),
      'receipt' => variable_get('simplenews_receipt', 0),
      'from_name' => variable_get('simplenews_from_name', variable_get('site_name', 'Drupal')),
      'email_subject' => '[[simplenews-newsletter:name]] [node:title]',
      'from_address' => variable_get('simplenews_from_address', variable_get('site_mail', ini_get('sendmail_from'))),
      'hyperlinks' => 1,
      'new_account' => 'none',
      'opt_inout' => 'double',
      'block' => 1,
      'weight' => '0',
    );
    return parent::create($values);
  }

  /**
   * Overrides EntityAPIController::delete().
   *
   * Make sure that all subscriptions of the newsletter are deleted.
   */
  public function delete($ids, DatabaseTransaction $transaction = NULL) {
    $names = array();
    foreach (simplenews_newsletter_load_multiple($ids) as $newsletter) {
      $names[$newsletter->newsletter_id] = $newsletter->name;
    }

    parent::delete($ids, $transaction);

    foreach ($ids as $id) {
      // Delete subscriptions.
      simplenews_subscription_delete(array('newsletter_id' => $id));
      drupal_set_message(t('All subscriptions to newsletter %newsletter have been deleted.', array('%newsletter' => $names[$id])));

      // Delete subscription block.
      db_delete('block')
        ->condition('module', 'simplenews')
        ->condition('delta', $id)
        ->execute();
    }
  }

}

/**
 * Controller for simplenews_subscriber entity.
 *
 * @ingroup subscriber
 */
class SimplenewsSubscriberController extends EntityAPIController {

  /**
   * Overrides EntityAPIController::create().
   */
  public function create(array $values = array()) {
    $values += array(
      'changes' => array(),
      'created' => REQUEST_TIME,
    );
    return parent::create($values);
  }

  /**
   * Overrides EntityAPIController::save().
   */
  public function save($entity, DatabaseTransaction $transaction = NULL) {
    $entity->timestamp = REQUEST_TIME;
    parent::save($entity, $transaction);
  }

  /**
   * Overrides EntityAPIController::delete().
   *
   * Make sure that all subscriptions of the subscriber are deleted.
   */
  public function delete($ids, DatabaseTransaction $transaction = NULL) {
    parent::delete($ids, $transaction);

    // Delete subscriptions.
    foreach ($ids as $id) {
      simplenews_subscription_delete(array('snid' => $id));
    }
  }

    /**
   * Overrides EntityAPIController::attachLoad().
   */
  public function attachLoad(&$queried_entities, $revision_id = FALSE) {
    parent::attachLoad($queried_entities, $revision_id);

    // Initialize default values and unserialize.
    foreach ($queried_entities as $subscriber) {
      $subscriber->newsletter_ids = array();
    }

    $query = db_select('simplenews_subscription', 'ss')
      ->fields('ss')
      ->condition('snid', array_keys($queried_entities));

    foreach ($query->execute() as $subscription) {
      if ($subscription->status == SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED) {
        $queried_entities[$subscription->snid]->newsletter_ids[$subscription->newsletter_id] = $subscription->newsletter_id;
      }
      $queried_entities[$subscription->snid]->newsletter_subscription[$subscription->newsletter_id] = $subscription;
    }
  }
}

