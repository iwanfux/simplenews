<?php

/**
 * @file
 * Hooks provided by the Simplenews module.
 */

/**
 * @todo
 */
function hook_simplenews_issue_operations() {

}

/**
 * @todo
 */
function hook_simplenews_subscription_operations() {

}

/**
 * @todo
 */
function hook_simplenews_category_insert($category) {

}

/**
 * @todo
 */
function hook_simplenews_category_update($category) {

}

/**
 * @todo
 */
function hook_simplenews_category_delete($category) {

}

/**
 * @todo
 */
function hook_simplenews_mailing_list_insert($list) {

}

/**
 * @todo
 */
function hook_simplenews_subscriber_update($subscriber) {

}

/**
 * @todo
 */
function hook_simplenews_subscriber_insert($subscriber) {

}

/**
 * @todo
 */
function hook_simplenews_subscriber_delete($subscriber) {

}

/**
 * Invoked if a user is subscribed to a newsletter.
 *
 * @param $subscriber
 *   The subscriber object including all subscriptions of this user.
 *
 * @param $subscription
 *   The subscription object for this specific subscribe action.
 */
function hook_simplenews_subscribe_user($subscriber, $subscription) {

}

/**
 * Invoked if a user is unsubscribed from a newsletter.
 *
 * @param $subscriber
 *   The subscriber object including all subscriptions of this user.
 *
 * @param $subscription
 *   The subscription object for this specific unsubscribe action.
 */
function hook_simplenews_unsubscribe_user($subscriber, $subscription) {

}

/**
 * Expose SimplenewsSource cache implementations.
 *
 * @return
 *   An array keyed by the name of the class that provides the implementation,
 *   the array value consists of another array with the keys label and
 *   description.
 */
function hook_simplenews_source_cache_info() {
  return array(
    'SimplenewsSourceCacheNone' => array(
      'label' => t('No caching'),
      'description' => t('This allows to theme each newsletter separately.'),
    ),
    'SimplenewsSourceCacheBuild' => array(
      'label' => t('Cached content source'),
      'description' => t('This caches the rendered content to be sent for multiple recipients. It is not possible to use subscriber specific theming but tokens can be used for personalization.'),
    ),
  );
}