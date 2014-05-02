<?php

/**
 * @file
 * Contains \Drupal\simplenews\Entity\SubscriberInterface.
 */

namespace Drupal\simplenews;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface defining a contant message entity
 */
interface SubscriberInterface extends ContentEntityInterface {

  /**
   * Returns if the subscriber is avtice or not.
   *
   * @return int
   *   The subscribers status.
   */
  public function getStatus();

  /**
   * Sets the status of the subscriber.
   *
   * @param int $status
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

}
