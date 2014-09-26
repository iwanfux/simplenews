<?php

/**
 * @file
 * Contains \Drupal\simplenews\Entity\SubscriberInterface.
 */

namespace Drupal\simplenews;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\UserInterface;

/**
 * Provides an interface defining a contant message entity
 */
interface SubscriberInterface extends ContentEntityInterface {

  /**
   * Returns if the subscriber is active or not.
   *
   * @return boolean
   *   The subscribers status.
   */
  public function getStatus();

  /**
   * Sets the status of the subscriber.
   *
   * @param boolean $status
   *   The subscribers status.
   */
  public function setStatus($status);

  /**
   * Returns the subscribers email address.
   *
   * @return string
   *   The subscribers email address.
   */
  public function getMail();

  /**
   * Sets the subscribers email address.
   *
   * @param string $mail
   *   The subscribers email address.
   */
  public function setMail($mail);

  /**
   * Returns corresponding user ID.
   *
   * @return int
   *   The corresponding user ID.
   */
  public function getUserId();

  /**
   * Sets the corresponding user ID.
   *
   * @param string $uid
   *   The corresponding user ID.
   */
  public function setUserId($uid);

  /**
   * Returns the lang code.
   *
   * @return string
   *   The subscribers lang code.
   */
  public function getLangcode();

  /**
   * Sets the lang code.
   *
   * @param string $langcode
   *   The subscribers lang code.
   */
  public function setLangcode($langcode);
  
  /**
   * Returns the changes.
   *
   * @return string
   *   The subscriber changes.
   */
  public function getChanges();

  /**
   * Sets the changes.
   *
   * @param string $changes
   *   The subscriber changes.
   */
  public function setChanges($changes);

  /**
   * Check if the subscriber has an active subscription to a certain newsletter.
   *
   * @param string $newsletter_id
   *   The ID of a newsletter.
   *
   * @return bool
   *   Returns TRUE if the subscriber has the subscription, otherwise FALSE.
   */
  public function isSubscribed($newsletter_id);

  /**
   * Check if the subscriber has an inactive subscription to a certain newsletter.
   *
   * @param string $newsletter_id
   *   The ID of a newsletter.
   *
   * @return bool
   *   Returns TRUE if the subscriber has the inactive subscription, otherwise FALSE.
   */
  public function isUnsubscribed($newsletter_id);

  /**
   * Check if the subscriber has a subscription to a certain newsletter and return it.
   *
   * @param string $newsletter_id
   *   The ID of a newsletter.
   *
   * @return \Drupal\simplenews\SubscriptionItem
   *   Returns the subscription item if the subscriber has the subscription, otherwise FALSE.
   */
  public function getSubscription($newsletter_id);

  /**
   * Get the ids of all subscribed newsletters.
   *
   * @return array of newsletter ids
   *   Returns the ids of all newsletters the subscriber is subscribed.
   */
  public function getSubscribedNewsletterIds();

  /**
   * Add a subscription to a certain newsletter to the subscriber.
   *
   * @param string $newsletter_id
   *   The ID of a newsletter.
   * @param int $status
   *   The status of the subscription.
   * @param string $source
   *   The source where the subscription comes from.
   * @param int $timestamp
   *   The timestamp of when the subscription was added.
   */
  public function subscribe($newsletter_id, $status = SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED, $source = 'unknown', $timestamp = REQUEST_TIME);

  /**
   * Delete a subscription to a certain newsletter of the subscriber.
   *
   * @param string $newsletter_id
   *   The ID of a newsletter.
   * @param string $source
   *   The source where the subscription comes from.
   * @param int $timestamp
   *   The timestamp of when the subscription was added.
   */
  public function unsubscribe($newsletter_id, $source = 'unknown', $timestamp = REQUEST_TIME);

  /**
   * Returns whether currently syncing field values to corresponding User.
   *
   * @return bool
   *   TRUE if invoked during syncing, otherwise FALSE.
   */
  public function isSyncing();

  /**
   * Identifies configurable fields shared with a user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to match fields against.
   *
   * @return string[]
   *   An indexed array of the names of each field for which there is also a
   *   field on the given user with the same name and type.
   */
  public function getUserSharedFields(UserInterface $user);

}
