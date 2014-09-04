<?php

/**
 * @file
 * Contains \Drupal\simplenews\Source\SimplenewsSourceEntityInterface.
 */

namespace Drupal\simplenews\Source;

/**
 * Source interface based on an entity.
 */
interface SimplenewsSourceEntityInterface extends SimplenewsSourceInterface {

  /**
   * Create a source based on an entity.
   */
  function __construct($entity, $subscriber, $entity_type);

  /**
   * Returns the actually used entity of this source.
   */
  function getEntity();

  /**
   * Returns the entity type of the given entity.
   */
  function getEntityType();

  /**
   * Returns the subscriber object.
   */
  function getSubscriber();
}