<?php

/**
 * @file
 * Contains \Drupal\simplenews\Source\SourceCacheStatic.
 */

namespace Drupal\simplenews\Source;

/**
 * Abstract implementation of the source caching that does static caching.
 *
 * Subclasses need to implement the abstract function isCacheable() to decide
 * what should be cached.
 *
 * @ingroup source
 */
abstract class SourceCacheStatic implements SourceCacheInterface {

  /**
   * The simplenews source for which this cache is used.
   *
   * @var SourceNodeInterface
   */
  protected $source;

  /**
   * The cache identifier for the given source.
   */
  protected $cid;

  /**
   * The static cache.
   */
  protected static $cache = array();

  /**
   * Implements SourceCacheInterface::__construct().
   */
  public function __construct(SourceEntityInterface $source) {
    $this->source = $source;

    self::$cache = &drupal_static(__CLASS__, array());
  }

  /**
   * Returns the cache identifier for the current source.
   */
  protected function getCid() {
    if (empty($this->cid)) {
      $entity_id = $this->source->getEntity()->id();
      $this->cid = $this->source->getEntityType() . ':' . $entity_id . ':' . $this->source->getLanguage();
    }
    return $this->cid;
  }

  /**
   * Implements SourceNodeInterface::get().
   */
  public function get($group, $key) {
    if (!$this->isCacheable($group, $key)) {
      return;
    }

    if (isset(self::$cache[$this->getCid()][$group][$key])) {
      return self::$cache[$this->getCid()][$group][$key];
    }
  }

  /**
   * Implements SourceNodeInterface::set().
   */
  public function set($group, $key, $data) {
    if (!$this->isCacheable($group, $key)) {
      return;
    }

    self::$cache[$this->getCid()][$group][$key] = $data;
  }

  /**
   * Return if the requested element should be cached.
   *
   * @return
   *   TRUE if it should be cached, FALSE otherwise.
   */
  abstract function isCacheable($group, $key);
}