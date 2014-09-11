<?php

/**
 * @file
 * Contains \Drupal\simplenews\Source\SourceCacheInterface.
 */

namespace Drupal\simplenews\Source;

/**
 * Interface for a simplenews source cache implementation.
 *
 * This is only compatible with the SourceNodeInterface interface.
 *
 * @ingroup source
 */
interface SourceCacheInterface {

  /**
   * Create a new instance, allows to initialize based on the used
   * source.
   */
  function __construct(SourceEntityInterface $source);

  /**
   * Return a cached element, if existing.
   *
   * Although group and key can be used to identify the requested cache, the
   * implementations are responsible to create a unique cache key themself using
   * the $source. For example based on the node id and the language.
   *
   * @param $group
   *   Group of the cache key, which allows cache implementations to decide what
   *   they want to cache. Currently used groups:
   *     - data: Raw data, e.g. attachments.
   *     - build: Built and themed content, before personalizations like tokens.
   *     - final: The final returned data. Caching this means that newsletter
   *       can not be personalized anymore.
   * @param $key
   *   Identifies the requested element, e.g. body, footer or attachments.
   */
  function get($group, $key);

  /**
   * Write an element to the cache.
   *
   * Although group and key can be used to identify the requested cache, the
   * implementations are responsible to create a unique cache key themself using
   * the $source. For example based on the node id and the language.
   *
   * @param $group
   *   Group of the cache key, which allows cache implementations to decide what
   *   they want to cache. Currently used groups:
   *     - data: Raw data, e.g. attachments.
   *     - build: Built and themed content, before personalizations like tokens.
   *     - final: The final returned data. Caching this means that newsletter
   *       can not be personalized anymore.
   * @param $key
   *   Identifies the requested element, e.g. body, footer or attachments.
   * @param $data
   *   The data to be saved in the cache.
   */
  function set($group, $key, $data);
}